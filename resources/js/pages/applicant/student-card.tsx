import { Head, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { KeyRound, Printer } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import { createPortal } from 'react-dom';
import { AccessCodeNotice } from '@/components/examis/access-code-notice';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import student from '@/routes/student';

const noSubscription = () => () => {};

type Props = {
    student: {
        username: string;
        student_number: number | null;
        phone: string | null;
        has_access_code: boolean;
        access_code_issued_at: string | null;
        trusted_devices: number;
    };
    qr: { url: string; svg: string };
    accessCode: string | null;
};

/**
 * The student's permanent sign-in card. The QR code carries only a random
 * id; the access code is shown once, right after it is issued (ADR-0009).
 */
export default function StudentCard({
    student: account,
    qr,
    accessCode,
}: Props) {
    const { t, locale, isRtl } = useTrans();
    const { organization } = usePage().props;
    const [open, setOpen] = useState(false);
    const regenerate = useForm({});
    // The print sheet lives directly under <body> so print CSS can hide the
    // whole app around it; there is no <body> during server rendering.
    const printTarget = useSyncExternalStore(
        noSubscription,
        () => document.body,
        () => null,
    );

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.student_card'), href: student.card() }],
    });

    return (
        <>
            <Head title={t('nav.student_card')} />
            <PageContainer>
                <PageHeader
                    title={t('student.card_title')}
                    description={t('student.card_description')}
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            className="no-print"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" aria-hidden />
                            {t('student.print_card')}
                        </Button>
                    }
                />

                {accessCode && <AccessCodeNotice code={accessCode} />}

                <div className="grid gap-6 md:grid-cols-[auto_1fr]">
                    <section
                        className="flex flex-col items-center gap-3 rounded-xl border bg-card p-4 sm:p-6"
                        aria-labelledby="qr-title"
                    >
                        <h2 id="qr-title" className="font-semibold">
                            {t('student.qr_code')}
                        </h2>
                        <div
                            className="rounded-lg bg-white p-2"
                            role="img"
                            aria-label={t('student.qr_code')}
                            // Server-generated SVG from mpdf/qrcode; contains no user input.
                            dangerouslySetInnerHTML={{ __html: qr.svg }}
                        />
                        <p className="max-w-56 text-center text-xs text-muted-foreground">
                            {t('student.qr_hint')}
                        </p>
                    </section>

                    <section
                        className="rounded-xl border bg-card p-4 sm:p-6"
                        aria-labelledby="identity-title"
                    >
                        <h2 id="identity-title" className="mb-4 font-semibold">
                            {t('student.account')}
                        </h2>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
                            <dt className="text-muted-foreground">
                                {t('student.student_number')}
                            </dt>
                            <dd>
                                <bdi
                                    dir="ltr"
                                    className="text-lg font-bold tracking-widest"
                                    data-test="student-number"
                                >
                                    {account.student_number ?? '—'}
                                </bdi>
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('auth.username')}
                            </dt>
                            <dd>
                                <bdi dir="ltr">{account.username}</bdi>
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('auth.mobile')}
                            </dt>
                            <dd>
                                <bdi dir="ltr">{account.phone ?? '—'}</bdi>
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('student.trusted_devices')}
                            </dt>
                            <dd>{account.trusted_devices}</dd>
                            {account.access_code_issued_at && (
                                <>
                                    <dt className="text-muted-foreground">
                                        {t('student.code_issued_at')}
                                    </dt>
                                    <dd>
                                        {formatDateTime(
                                            account.access_code_issued_at,
                                            locale,
                                        )}
                                    </dd>
                                </>
                            )}
                        </dl>

                        <div className="no-print mt-6 space-y-3 border-t pt-4 text-sm leading-relaxed text-muted-foreground">
                            <p>{t('student.security_note')}</p>
                            <ConfirmDialog
                                title={t('student.regenerate_code')}
                                description={t('student.regenerate_confirm')}
                                confirmLabel={t('student.regenerate_code')}
                                open={open}
                                onOpenChange={setOpen}
                                processing={regenerate.processing}
                                onConfirm={() =>
                                    regenerate.post(student.accessCode.url(), {
                                        preserveScroll: true,
                                        onSuccess: () => setOpen(false),
                                    })
                                }
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    data-test="regenerate-access-code"
                                >
                                    <KeyRound className="size-4" aria-hidden />
                                    {account.has_access_code
                                        ? t('student.regenerate_code')
                                        : t('student.issue_code')}
                                </Button>
                            </ConfirmDialog>
                        </div>
                    </section>
                </div>
            </PageContainer>

            {/* Printed instead of the page; never includes the access code. */}
            {printTarget &&
                createPortal(
                    <section
                        className="print-sheet hidden bg-white text-black"
                        dir={isRtl ? 'rtl' : 'ltr'}
                        aria-hidden
                    >
                        <div className="w-full max-w-xl rounded-xl border-2 border-black p-6">
                            <div className="mb-6 border-b border-black pb-3 text-center">
                                <p className="text-sm">{organization}</p>
                                <h1 className="mt-1 text-xl font-bold">
                                    {t('student.card_title')}
                                </h1>
                            </div>
                            <div className="flex items-center gap-6">
                                <div
                                    className="shrink-0"
                                    // Server-generated SVG from mpdf/qrcode; contains no user input.
                                    dangerouslySetInnerHTML={{ __html: qr.svg }}
                                />
                                <dl className="grid flex-1 grid-cols-[auto_1fr] gap-x-4 gap-y-3">
                                    <dt>{t('student.student_number')}</dt>
                                    <dd>
                                        <bdi
                                            dir="ltr"
                                            className="text-2xl font-bold tracking-widest"
                                        >
                                            {account.student_number ?? '—'}
                                        </bdi>
                                    </dd>
                                    <dt>{t('auth.username')}</dt>
                                    <dd>
                                        <bdi dir="ltr">{account.username}</bdi>
                                    </dd>
                                    <dt>{t('auth.mobile')}</dt>
                                    <dd>
                                        <bdi dir="ltr">
                                            {account.phone ?? '—'}
                                        </bdi>
                                    </dd>
                                </dl>
                            </div>
                            <p className="mt-6 border-t border-black pt-3 text-xs">
                                {t('student.qr_hint')}
                            </p>
                        </div>
                    </section>,
                    printTarget,
                )}
        </>
    );
}
