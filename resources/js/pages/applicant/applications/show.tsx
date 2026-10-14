import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    FileDown,
    FileText,
    Pencil,
    Printer,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { textareaClassName } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { StatusBadge } from '@/components/examis/status-badge';
import { Timeline } from '@/components/examis/timeline';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime, formatFileSize } from '@/lib/format';
import { dashboard } from '@/routes';
import applications from '@/routes/applications';
import type {
    Application,
    DocumentRequirement,
    MissingRequirements,
} from '@/types';

type Props = {
    application: Application;
    documentTypes: DocumentRequirement[];
    missing: MissingRequirements | null;
    can: { edit: boolean; withdraw: boolean; receipt: boolean };
};

export default function ShowApplication({
    application,
    documentTypes,
    can,
}: Props) {
    const { t, locale } = useTrans();
    const { flash } = usePage();
    const [withdrawOpen, setWithdrawOpen] = useState(false);
    const withdrawal = useForm({ reason: '', confirm: true });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.my_applications'), href: dashboard() },
            {
                title:
                    application.reference_number ?? t('applicant.application'),
                href: applications.show(application.uuid),
            },
        ],
    });

    const submitted = (
        flash as { submitted?: { reference_number: string | null } }
    ).submitted;
    const latestReason = [...(application.events ?? [])]
        .reverse()
        .find((event) => event.reason)?.reason;
    const typeName = (code: string) =>
        documentTypes.find((type) => type.code === code)?.name ?? code;

    return (
        <>
            <Head
                title={
                    application.reference_number ?? t('applicant.application')
                }
            />
            <PageContainer className="max-w-4xl">
                {submitted && (
                    <Alert
                        className="border-success/40 bg-success/5"
                        data-test="submission-confirmed"
                    >
                        <CheckCircle2 className="size-5 text-success" />
                        <AlertTitle className="text-base">
                            {t('applicant.submitted_title')}
                        </AlertTitle>
                        <AlertDescription>
                            <p>{t('applicant.submitted_text')}</p>
                            <p
                                className="mt-2 text-2xl font-bold tracking-wide text-foreground"
                                dir="ltr"
                                data-test="reference-number"
                            >
                                {submitted.reference_number}
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <PageHeader
                    title={
                        application.exam_period?.name ??
                        t('applicant.application')
                    }
                    description={
                        application.reference_number ? (
                            <>
                                {t('track.reference_number')}:{' '}
                                <bdi
                                    dir="ltr"
                                    className="font-semibold text-foreground"
                                >
                                    {application.reference_number}
                                </bdi>
                            </>
                        ) : (
                            t('applicant.not_submitted_yet')
                        )
                    }
                    actions={
                        <>
                            <StatusBadge
                                status={application.status}
                                label={application.status_label}
                                className="text-sm"
                            />
                            {can.edit && (
                                <Button asChild>
                                    <Link
                                        href={applications.edit(
                                            application.uuid,
                                        )}
                                    >
                                        <Pencil
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {application.status === 'draft'
                                            ? t(
                                                  'applicant.continue_application',
                                              )
                                            : t(
                                                  'applicant.respond_to_correction',
                                              )}
                                    </Link>
                                </Button>
                            )}
                            {can.receipt && (
                                <Button variant="outline" asChild>
                                    <a
                                        href={applications.receipt.url(
                                            application.uuid,
                                        )}
                                        target="_blank"
                                        rel="noopener"
                                        data-test="download-receipt"
                                    >
                                        <Printer
                                            className="size-4"
                                            aria-hidden
                                        />
                                        {t('applicant.receipt')}
                                    </a>
                                </Button>
                            )}
                        </>
                    }
                />

                {application.status === 'requires_correction' &&
                    latestReason && (
                        <Alert className="border-warning/50 bg-warning/10">
                            <FileText className="size-4" />
                            <AlertTitle>
                                {t('applicant.reviewer_notes')}
                            </AlertTitle>
                            <AlertDescription className="whitespace-pre-line text-foreground">
                                {latestReason}
                            </AlertDescription>
                        </Alert>
                    )}

                {application.status === 'rejected' && latestReason && (
                    <Alert variant="destructive">
                        <XCircle className="size-4" />
                        <AlertTitle>
                            {t('applicant.rejection_reason')}
                        </AlertTitle>
                        <AlertDescription className="whitespace-pre-line">
                            {latestReason}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="space-y-6">
                        <section
                            className="rounded-xl border bg-card p-4 sm:p-6"
                            aria-labelledby="summary"
                        >
                            <h2 id="summary" className="mb-4 font-semibold">
                                {t('applicant.summary')}
                            </h2>
                            <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'fields.full_name_ar',
                                            application.form.full_name_ar,
                                        ],
                                        [
                                            'fields.mother_name_ar',
                                            application.form.mother_name_ar,
                                        ],
                                        [
                                            'fields.birth_date',
                                            application.form.birth_date,
                                        ],
                                        [
                                            'fields.governorate',
                                            application.labels.governorate,
                                        ],
                                        [
                                            'fields.school_name',
                                            application.form.school_name,
                                        ],
                                        [
                                            'fields.branch',
                                            application.labels.branch,
                                        ],
                                        [
                                            'fields.category',
                                            application.labels.category,
                                        ],
                                        [
                                            'applications.submitted_at',
                                            formatDateTime(
                                                application.submitted_at,
                                                locale,
                                            ),
                                        ],
                                    ] as const
                                ).map(([label, value]) => (
                                    <div key={label}>
                                        <dt className="text-muted-foreground">
                                            {t(label)}
                                        </dt>
                                        <dd className="font-medium" dir="auto">
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section
                            className="rounded-xl border bg-card p-4 sm:p-6"
                            aria-labelledby="documents"
                        >
                            <h2 id="documents" className="mb-4 font-semibold">
                                {t('applicant.steps.documents')}
                            </h2>
                            {(application.documents ?? []).length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('documents.none')}
                                </p>
                            ) : (
                                <ul className="divide-y text-sm">
                                    {(application.documents ?? []).map(
                                        (document) => (
                                            <li
                                                key={document.uuid}
                                                className="flex flex-wrap items-center justify-between gap-2 py-2"
                                            >
                                                <span className="font-medium">
                                                    {typeName(
                                                        document.document_type,
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t(
                                                        `documents.status.${document.status}`,
                                                    )}{' '}
                                                    ·{' '}
                                                    {formatFileSize(
                                                        document.size_bytes,
                                                        locale,
                                                    )}
                                                </span>
                                                {document.url && (
                                                    <a
                                                        href={document.url}
                                                        className="flex items-center gap-1 text-primary hover:underline"
                                                    >
                                                        <FileDown
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        {t(
                                                            'documents.download',
                                                        )}
                                                    </a>
                                                )}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section
                            className="rounded-xl border bg-card p-4"
                            aria-labelledby="history"
                        >
                            <h2 id="history" className="mb-4 font-semibold">
                                {t('applicant.history')}
                            </h2>
                            <Timeline events={application.events ?? []} />
                        </section>

                        {can.withdraw && (
                            <section className="no-print rounded-xl border border-destructive/30 p-4">
                                <h2 className="mb-2 font-semibold">
                                    {t('applicant.withdraw_title')}
                                </h2>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    {t('applicant.withdraw_text')}
                                </p>
                                <ConfirmDialog
                                    title={t(
                                        'applicant.withdraw_confirm_title',
                                    )}
                                    description={t(
                                        'applicant.withdraw_confirm_text',
                                    )}
                                    confirmLabel={t('applicant.withdraw')}
                                    destructive
                                    open={withdrawOpen}
                                    onOpenChange={setWithdrawOpen}
                                    processing={withdrawal.processing}
                                    onConfirm={() =>
                                        withdrawal.post(
                                            applications.withdraw.url(
                                                application.uuid,
                                            ),
                                            {
                                                onSuccess: () =>
                                                    setWithdrawOpen(false),
                                            },
                                        )
                                    }
                                    body={
                                        <div className="grid gap-2">
                                            <Label htmlFor="withdraw-reason">
                                                {t('applicant.withdraw_reason')}
                                            </Label>
                                            <textarea
                                                id="withdraw-reason"
                                                className={textareaClassName}
                                                maxLength={500}
                                                value={withdrawal.data.reason}
                                                onChange={(event) =>
                                                    withdrawal.setData(
                                                        'reason',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    }
                                >
                                    <Button
                                        variant="outline"
                                        className="w-full border-destructive/40 text-destructive"
                                    >
                                        {t('applicant.withdraw')}
                                    </Button>
                                </ConfirmDialog>
                            </section>
                        )}
                    </aside>
                </div>
            </PageContainer>
        </>
    );
}
