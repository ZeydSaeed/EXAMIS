import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { ExternalLink, FileWarning, Printer } from 'lucide-react';
import { useState } from 'react';
import { ApplicationSummaryTable } from '@/components/examis/application-summary-table';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { selectClassName, textareaClassName } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { StatusBadge } from '@/components/examis/status-badge';
import { Timeline } from '@/components/examis/timeline';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import { applicationSummary } from '@/lib/application-summary';
import { formatDateTime, formatFileSize } from '@/lib/format';
import admin from '@/routes/admin';
import applications from '@/routes/applications';
import type { Application, ApplicationStatus } from '@/types';

type Props = {
    application: Application;
    documentTypes: { code: string; name: string; required: boolean }[];
    transitions: {
        target: ApplicationStatus;
        label: string;
        requires_reason: boolean;
    }[];
    canViewDocuments: boolean;
    canAssign: boolean;
    reviewers: { id: number; name: string }[];
};

function TransitionButton({
    application,
    transition,
}: {
    application: Application;
    transition: Props['transitions'][number];
}) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const form = useForm({ target: transition.target, reason: '' });
    const destructive = transition.target === 'rejected';

    return (
        <ConfirmDialog
            title={transition.label}
            description={t('admin.transition_confirm', {
                reference: application.reference_number ?? '',
            })}
            confirmLabel={transition.label}
            destructive={destructive}
            open={open}
            onOpenChange={setOpen}
            processing={form.processing}
            onConfirm={() =>
                form.post(admin.applications.transition.url(application.uuid), {
                    preserveScroll: true,
                    onSuccess: () => setOpen(false),
                })
            }
            body={
                <div className="grid gap-2">
                    <Label htmlFor={`reason-${transition.target}`}>
                        {transition.requires_reason
                            ? t('admin.reason_required')
                            : t('admin.reason_optional')}
                    </Label>
                    <textarea
                        id={`reason-${transition.target}`}
                        className={textareaClassName}
                        maxLength={1000}
                        required={transition.requires_reason}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                    <InputError
                        message={
                            form.errors.reason ??
                            (form.errors as Record<string, string | undefined>)
                                .status ??
                            form.errors.target
                        }
                    />
                </div>
            }
        >
            <Button
                variant={
                    destructive
                        ? 'destructive'
                        : transition.target === 'accepted'
                          ? 'default'
                          : 'outline'
                }
                data-test={`transition-${transition.target}`}
            >
                {transition.label}
            </Button>
        </ConfirmDialog>
    );
}

export default function AdminApplicationShow({
    application,
    documentTypes,
    transitions,
    canViewDocuments,
    canAssign,
    reviewers,
}: Props) {
    const { t, locale } = useTrans();
    const assignment = useForm<{ assignee_id: number | null }>({
        assignee_id: application.assignee?.id ?? null,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.applications'), href: admin.applications.index() },
            {
                title: application.reference_number ?? '',
                href: admin.applications.show(application.uuid),
            },
        ],
    });

    const documentsByType = Object.fromEntries(
        (application.documents ?? []).map((document) => [
            document.document_type,
            document,
        ]),
    );
    return (
        <>
            <Head
                title={
                    application.reference_number ?? t('applicant.application')
                }
            />
            <PageContainer>
                <PageHeader
                    title={
                        [
                            application.form.full_name_ar,
                            application.form.surname,
                        ]
                            .filter(Boolean)
                            .join(' ') || '—'
                    }
                    description={
                        <>
                            <bdi
                                dir="ltr"
                                className="font-semibold text-foreground"
                            >
                                {application.reference_number}
                            </bdi>{' '}
                            · {application.exam_period?.name} ·{' '}
                            {t('applications.submitted_at')}:{' '}
                            {formatDateTime(application.submitted_at, locale)}
                        </>
                    }
                    actions={
                        <>
                            <StatusBadge
                                status={application.status}
                                label={application.status_label}
                                className="text-sm"
                            />
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={applications.receipt.url(
                                        application.uuid,
                                    )}
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Printer className="size-4" aria-hidden />
                                    {t('applicant.receipt')}
                                </a>
                            </Button>
                        </>
                    }
                />

                {transitions.length > 0 && (
                    <section
                        className="no-print flex flex-wrap items-center gap-2 rounded-xl border bg-card p-4"
                        aria-label={t('admin.actions')}
                    >
                        <span className="me-2 text-sm font-medium">
                            {t('admin.actions')}:
                        </span>
                        {transitions.map((transition) => (
                            <TransitionButton
                                key={transition.target}
                                application={application}
                                transition={transition}
                            />
                        ))}
                    </section>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-6">
                        <section
                            className="rounded-xl border bg-card p-4 sm:p-6"
                            aria-labelledby="data"
                        >
                            <h2 id="data" className="mb-4 font-semibold">
                                {t('admin.applicant_data')}
                            </h2>
                            <ApplicationSummaryTable
                                caption={t('admin.applicant_data')}
                                rows={applicationSummary(application, t)}
                            />
                        </section>

                        <section
                            className="rounded-xl border bg-card p-4 sm:p-6"
                            aria-labelledby="docs"
                        >
                            <h2 id="docs" className="mb-4 font-semibold">
                                {t('applicant.steps.documents')}
                            </h2>
                            {!canViewDocuments ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('admin.no_document_permission')}
                                </p>
                            ) : (
                                <ul className="grid gap-4">
                                    {documentTypes.map((type) => {
                                        const document =
                                            documentsByType[type.code];

                                        return (
                                            <li
                                                key={type.code}
                                                className="rounded-lg border p-3"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span className="font-medium">
                                                        {type.name}
                                                        {type.required && (
                                                            <span className="text-destructive">
                                                                {' '}
                                                                *
                                                            </span>
                                                        )}
                                                    </span>
                                                    {document ? (
                                                        <span className="text-sm text-muted-foreground">
                                                            {t(
                                                                `documents.status.${document.status}`,
                                                            )}{' '}
                                                            ·{' '}
                                                            {formatFileSize(
                                                                document.size_bytes,
                                                                locale,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="flex items-center gap-1 text-sm text-destructive">
                                                            <FileWarning
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                            {t(
                                                                'documents.missing',
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                                {document?.url &&
                                                    document.mime_type.startsWith(
                                                        'image/',
                                                    ) && (
                                                        <img
                                                            src={`${document.url}?inline=1`}
                                                            alt={type.name}
                                                            className="mt-3 max-h-80 rounded-md border object-contain"
                                                            loading="lazy"
                                                        />
                                                    )}
                                                {document?.url && (
                                                    <a
                                                        href={`${document.url}?inline=1`}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="mt-2 inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                                    >
                                                        <ExternalLink
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        {t('documents.open')}
                                                    </a>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section
                            className="rounded-xl border bg-card p-4"
                            aria-labelledby="assignment"
                        >
                            <h2 id="assignment" className="mb-3 font-semibold">
                                {t('admin.assignee')}
                            </h2>
                            {canAssign ? (
                                <form
                                    className="grid gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        assignment.post(
                                            admin.applications.assign.url(
                                                application.uuid,
                                            ),
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <Label
                                        htmlFor="assignee"
                                        className="sr-only"
                                    >
                                        {t('admin.assignee')}
                                    </Label>
                                    <select
                                        id="assignee"
                                        className={selectClassName}
                                        value={
                                            assignment.data.assignee_id ?? ''
                                        }
                                        onChange={(event) =>
                                            assignment.setData(
                                                'assignee_id',
                                                event.target.value === ''
                                                    ? null
                                                    : Number(
                                                          event.target.value,
                                                      ),
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('admin.unassigned')}
                                        </option>
                                        {reviewers.map((reviewer) => (
                                            <option
                                                key={reviewer.id}
                                                value={reviewer.id}
                                            >
                                                {reviewer.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            (
                                                assignment.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            ).assignee ??
                                            (
                                                assignment.errors as Record<
                                                    string,
                                                    string | undefined
                                                >
                                            ).status
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            assignment.processing ||
                                            !assignment.isDirty
                                        }
                                    >
                                        {t('admin.save_assignment')}
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm">
                                    {application.assignee?.name ??
                                        t('admin.unassigned')}
                                </p>
                            )}
                        </section>

                        <section
                            className="rounded-xl border bg-card p-4"
                            aria-labelledby="timeline"
                        >
                            <h2 id="timeline" className="mb-4 font-semibold">
                                {t('applicant.history')}
                            </h2>
                            <Timeline events={application.events ?? []} />
                        </section>
                    </aside>
                </div>
            </PageContainer>
        </>
    );
}
