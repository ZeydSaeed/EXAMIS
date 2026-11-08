import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Eye, Inbox, Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import {
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderFilter,
    HeaderFilterChip,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import { SendMessageDialog } from '@/components/examis/send-message-dialog';
import { StatusBadge, statusSurface } from '@/components/examis/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import { composeMessage, gapCount } from '@/lib/decision-message';
import type { MessageContact } from '@/lib/decision-message';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { ApplicationStatus, Paginated } from '@/types';

type Row = {
    uuid: string;
    student_number: number | null;
    full_name_ar: string | null;
    status: ApplicationStatus;
    status_label: string;
    phone: string | null;
    decisions: { value: string; label: string; requires_reason: boolean }[];
    contact: MessageContact | null;
    /** Only for staff who may edit the database. */
    manage: {
        application_key: string;
        student_key: string;
        confirm: string;
    } | null;
};

type Template = { id: number; title: string; status: string; body: string };

type SentMessage = { text: string; phone: string | null };

type Filters = {
    status?: string;
    period?: string | number;
    assigned?: string;
    q?: string;
    per_page?: string | number;
};

type Props = {
    applications: Paginated<Row>;
    templates: Template[];
    filters: Filters;
    periods: { id: number; name: string }[];
    statuses: { value: ApplicationStatus; label: string }[];
};

/**
 * The status of one application, changed in place. The server offers only
 * what this staff member may do now; with nothing to offer it is just a label.
 * Choosing a status opens its ready-made messages; the gaps are filled here
 * and the finished text is what the student receives.
 */
function StatusCell({
    row,
    templates,
    onDecided,
}: {
    row: Row;
    templates: Template[];
    onDecided: (message: SentMessage) => void;
}) {
    const { t } = useTrans();
    const [chosen, setChosen] = useState<Row['decisions'][number] | null>(null);
    const [templateId, setTemplateId] = useState('');
    const [gaps, setGaps] = useState<string[]>([]);
    const form = useForm({ target: '', reason: '' });

    if (row.decisions.length === 0 || row.contact === null) {
        return (
            <StatusBadge
                status={row.status}
                label={row.status_label}
                variant="plain"
            />
        );
    }

    const contact = row.contact;
    const available = templates.filter(
        (template) => template.status === chosen?.value,
    );
    const template = available.find((item) => String(item.id) === templateId);

    const fill = (next: Template | undefined, values: string[]) =>
        form.setData(
            'reason',
            next ? composeMessage(next.body, contact, values) : '',
        );

    const choose = (decision: Row['decisions'][number]) => {
        const first = templates.find((item) => item.status === decision.value);
        const empty = Array<string>(first ? gapCount(first.body) : 0).fill('');

        form.clearErrors();
        form.setData({
            target: decision.value,
            reason: first ? composeMessage(first.body, contact, empty) : '',
        });
        setTemplateId(first ? String(first.id) : '');
        setGaps(empty);
        setChosen(decision);
    };

    return (
        <>
            <select
                aria-label={`${t('applications.status')}: ${row.full_name_ar ?? ''}`}
                className={cn(selectClassName, 'h-8 w-48 text-center text-sm')}
                value={row.status}
                data-test={`status-${row.student_number}`}
                onChange={(event) => {
                    const decision = row.decisions.find(
                        (item) => item.value === event.target.value,
                    );

                    if (decision) {
                        choose(decision);
                    }
                }}
            >
                <option value={row.status} disabled>
                    {row.status_label}
                </option>
                {row.decisions.map((decision) => (
                    <option key={decision.value} value={decision.value}>
                        {decision.label}
                    </option>
                ))}
            </select>

            <ConfirmDialog
                title={chosen?.label ?? ''}
                description={t('admin.decision_confirm', {
                    name: row.full_name_ar ?? '',
                })}
                confirmLabel={chosen?.label ?? ''}
                destructive={chosen?.value === 'rejected'}
                open={chosen !== null}
                onOpenChange={(open) => !open && setChosen(null)}
                processing={form.processing}
                onConfirm={() =>
                    form.post(admin.applications.decision.url(row.uuid), {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            setChosen(null);

                            if (form.data.reason.trim() !== '') {
                                onDecided({
                                    text: form.data.reason,
                                    phone: contact.phone,
                                });
                            }
                        },
                    })
                }
                body={
                    <div className="grid max-h-[55svh] gap-3 overflow-y-auto px-1 pb-1 text-start">
                        {available.length > 0 && (
                            <Field
                                id={`template-${row.uuid}`}
                                label={t('admin.message_template')}
                            >
                                <select
                                    id={`template-${row.uuid}`}
                                    className={selectClassName}
                                    value={templateId}
                                    data-test="message-template"
                                    onChange={(event) => {
                                        const next = available.find(
                                            (item) =>
                                                String(item.id) ===
                                                event.target.value,
                                        );
                                        const empty = Array<string>(
                                            next ? gapCount(next.body) : 0,
                                        ).fill('');

                                        setTemplateId(event.target.value);
                                        setGaps(empty);
                                        fill(next, empty);
                                    }}
                                >
                                    <option value="">
                                        {t('admin.no_template')}
                                    </option>
                                    {available.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.title}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        {gaps.map((gap, index) => (
                            <Field
                                key={index}
                                id={`gap-${row.uuid}-${index}`}
                                label={t('admin.gap_label', {
                                    number: index + 1,
                                })}
                            >
                                <Input
                                    id={`gap-${row.uuid}-${index}`}
                                    value={gap}
                                    data-test={`gap-${index}`}
                                    onChange={(event) => {
                                        const values = gaps.map(
                                            (current, position) =>
                                                position === index
                                                    ? event.target.value
                                                    : current,
                                        );

                                        setGaps(values);
                                        fill(template, values);
                                    }}
                                />
                            </Field>
                        ))}

                        {template &&
                            gaps.length > 0 &&
                            chosen?.value === 'requires_correction' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                    data-test="add-gap"
                                    onClick={() => {
                                        setGaps([...gaps, '']);
                                    }}
                                >
                                    <Plus aria-hidden />
                                    {t('admin.add_gap')}
                                </Button>
                            )}

                        <Field
                            id={`reason-${row.uuid}`}
                            label={t('admin.decision_message')}
                        >
                            <textarea
                                id={`reason-${row.uuid}`}
                                className={textareaClassName}
                                rows={8}
                                maxLength={2000}
                                required={chosen?.requires_reason}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </Field>
                        <InputError
                            message={
                                form.errors.reason ??
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).status ??
                                form.errors.target
                            }
                        />
                    </div>
                }
            />
        </>
    );
}

/**
 * Deletes the student for good (account, applications, files, history)
 * after their student number is typed, through the database screen.
 */
function DeleteStudentDialog({
    row,
    onClose,
}: {
    row: Row | null;
    onClose: () => void;
}) {
    const { t } = useTrans();
    const [typed, setTyped] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setTyped('');
        setError(undefined);
        onClose();
    };

    return (
        <ConfirmDialog
            open={row !== null}
            onOpenChange={(open) => !open && close()}
            title={t('db.delete_student')}
            description={t('db.delete_student_text', {
                number: row?.manage?.confirm ?? '',
            })}
            confirmLabel={t('db.delete_student')}
            cancelLabel={t('db.cancel')}
            destructive
            processing={processing}
            onConfirm={() =>
                router.delete(admin.database.destroy.url('users'), {
                    data: {
                        key: row?.manage?.student_key,
                        confirm: typed,
                        stay: true,
                    },
                    preserveScroll: true,
                    onStart: () => setProcessing(true),
                    onFinish: () => setProcessing(false),
                    onError: (errors) =>
                        setError(errors.confirm ?? errors.database),
                    onSuccess: () => {
                        toast.success(t('admin.student_deleted'));
                        close();
                    },
                })
            }
            body={
                <Field
                    id="confirm-student-delete"
                    label={t('db.confirm_label')}
                    error={error}
                >
                    <Input
                        id="confirm-student-delete"
                        dir="ltr"
                        inputMode="numeric"
                        autoComplete="off"
                        value={typed}
                        onChange={(event) => setTyped(event.target.value)}
                    />
                </Field>
            }
        />
    );
}

export default function AdminApplications({
    applications,
    templates,
    filters,
    periods,
    statuses,
}: Props) {
    const { t } = useTrans();
    // Held here: a decision can take the row out of a filtered list.
    const [sent, setSent] = useState<SentMessage | null>(null);
    const [deleting, setDeleting] = useState<Row | null>(null);
    const showActions = applications.data.some((row) => row.manage !== null);
    const { search, setSearch, urlFor, visit, tableArea } = useListQuery({
        url: admin.applications.index.url,
        filters,
        paginator: applications,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.applications'), href: admin.applications.index() },
        ],
    });

    const assignedLabel =
        filters.assigned === 'me'
            ? t('admin.assigned_to_me')
            : filters.assigned === 'unassigned'
              ? t('admin.unassigned')
              : null;
    const periodLabel = filters.period
        ? periods.find((period) => String(period.id) === String(filters.period))
              ?.name
        : null;

    return (
        <>
            <Head title={t('nav.applications')} />

            <SendMessageDialog
                message={sent?.text ?? ''}
                phone={sent?.phone ?? null}
                open={sent !== null}
                onOpenChange={(open) => !open && setSent(null)}
            />

            <DeleteStudentDialog
                row={deleting}
                onClose={() => setDeleting(null)}
            />

            <ListHeader
                search={search}
                searchAutoFocus
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim(), page: 1 })}
                placeholder={t('admin.search_placeholder')}
            >
                <HeaderFilter
                    label={t('applications.status')}
                    value={filters.status ?? ''}
                    options={statuses}
                    onChange={(status) => visit({ status, page: 1 })}
                />
                {assignedLabel && (
                    <HeaderFilterChip
                        label={assignedLabel}
                        onRemove={() => visit({ assigned: '', page: 1 })}
                    />
                )}
                {periodLabel && (
                    <HeaderFilterChip
                        label={periodLabel}
                        onRemove={() => visit({ period: '', page: 1 })}
                    />
                )}
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.applications')}</h1>

                <div ref={tableArea} className="min-h-0 flex-1">
                    {applications.data.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[44rem] [&_td]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.applications')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-12 text-center!"
                                        >
                                            {t('admin.serial')}
                                        </th>
                                        <th scope="col">
                                            {t('student.student_number')}
                                        </th>
                                        <th scope="col">
                                            {t('admin.student_full_name')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('applications.status')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('auth.mobile')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('admin.preview')}
                                        </th>
                                        {showActions && (
                                            <th
                                                scope="col"
                                                className="text-center!"
                                            >
                                                {t('admin.actions')}
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {applications.data.map((row, index) => (
                                        <tr key={row.uuid}>
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {(applications.from ?? 1) +
                                                    index}
                                            </td>
                                            <td>
                                                <Link
                                                    href={admin.applications.show(
                                                        row.uuid,
                                                    )}
                                                    className="tabular-nums hover:underline"
                                                    dir="ltr"
                                                >
                                                    <Highlight
                                                        text={
                                                            row.student_number ===
                                                            null
                                                                ? '—'
                                                                : String(
                                                                      row.student_number,
                                                                  )
                                                        }
                                                        query={filters.q}
                                                    />
                                                </Link>
                                            </td>
                                            <td>
                                                <Highlight
                                                    text={row.full_name_ar}
                                                    query={filters.q}
                                                />
                                            </td>
                                            <td
                                                className={cn(
                                                    'text-center!',
                                                    statusSurface(row.status),
                                                )}
                                            >
                                                <StatusCell
                                                    row={row}
                                                    templates={templates}
                                                    onDecided={setSent}
                                                />
                                            </td>
                                            <td
                                                className="text-center! tabular-nums"
                                                dir="ltr"
                                            >
                                                {row.phone ?? '—'}
                                            </td>
                                            <td className="text-center!">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={admin.applications.receipt.url(
                                                            row.uuid,
                                                        )}
                                                        target="_blank"
                                                        rel="noopener"
                                                        data-test={`preview-${row.student_number}`}
                                                    >
                                                        <Eye aria-hidden />
                                                        {t('admin.preview')}
                                                    </a>
                                                </Button>
                                            </td>
                                            {showActions && (
                                                <td className="text-center!">
                                                    {row.manage && (
                                                        <div className="flex items-center justify-center gap-1">
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={admin.database.edit(
                                                                        'applications',
                                                                        {
                                                                            query: {
                                                                                key: row
                                                                                    .manage
                                                                                    .application_key,
                                                                            },
                                                                        },
                                                                    )}
                                                                >
                                                                    <Pencil
                                                                        aria-hidden
                                                                    />
                                                                    {t(
                                                                        'admin.edit_student',
                                                                    )}
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                                className="text-destructive hover:text-destructive"
                                                                onClick={() =>
                                                                    setDeleting(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2
                                                                    aria-hidden
                                                                />
                                                                {t(
                                                                    'admin.delete_student',
                                                                )}
                                                            </Button>
                                                        </div>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <NumberedPagination
                    current={applications.current_page}
                    last={applications.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}
