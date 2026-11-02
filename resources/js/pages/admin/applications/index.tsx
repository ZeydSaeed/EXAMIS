import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Eye, Inbox } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { selectClassName, textareaClassName } from '@/components/examis/field';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderFilter,
    HeaderFilterChip,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import { StatusBadge, statusSurface } from '@/components/examis/status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { ApplicationStatus, Paginated } from '@/types';

type Row = {
    uuid: string;
    student_number: number | null;
    full_name_ar: string | null;
    status: ApplicationStatus;
    status_label: string;
    decisions: { value: string; label: string; requires_reason: boolean }[];
};

type Filters = {
    status?: string;
    period?: string | number;
    assigned?: string;
    q?: string;
    per_page?: string | number;
};

type Props = {
    applications: Paginated<Row>;
    filters: Filters;
    periods: { id: number; name: string }[];
    statuses: { value: ApplicationStatus; label: string }[];
};

/**
 * The status of one application, changed in place. The server offers only
 * what this staff member may do now; with nothing to offer it is just a label.
 */
function StatusCell({ row }: { row: Row }) {
    const { t } = useTrans();
    const [chosen, setChosen] = useState<Row['decisions'][number] | null>(null);
    const form = useForm({ target: '', reason: '' });

    if (row.decisions.length === 0) {
        return (
            <StatusBadge
                status={row.status}
                label={row.status_label}
                variant="plain"
            />
        );
    }

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
                        form.setData({ target: decision.value, reason: '' });
                        form.clearErrors();
                        setChosen(decision);
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
                        onSuccess: () => setChosen(null),
                    })
                }
                body={
                    <div className="grid gap-2 text-start">
                        <Label htmlFor={`reason-${row.uuid}`}>
                            {chosen?.requires_reason
                                ? t('admin.reason_required')
                                : t('admin.reason_optional')}
                        </Label>
                        <textarea
                            id={`reason-${row.uuid}`}
                            className={textareaClassName}
                            maxLength={1000}
                            required={chosen?.requires_reason}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
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

export default function AdminApplications({
    applications,
    filters,
    periods,
    statuses,
}: Props) {
    const { t } = useTrans();
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
                                            {t('admin.preview')}
                                        </th>
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
                                                    key={row.status}
                                                    row={row}
                                                />
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
