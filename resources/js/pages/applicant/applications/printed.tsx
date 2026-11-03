import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Download, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { PageContainer } from '@/components/examis/page-header';
import { PdfPages } from '@/components/examis/pdf-pages';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';

type Props = {
    studentNumber: number | null;
    formUrl: string;
    fileName: string;
    finishUrl: string;
};

/**
 * The end of the one-time account (ADR-0010): the application has gone to the
 * committee and only the form remains. No way on from here but printing or
 * downloading it. A browser cannot tell a finished print from a cancelled one,
 * so the student confirms, and only that closes the account.
 */
export default function PrintedApplication({
    studentNumber,
    formUrl,
    fileName,
    finishUrl,
}: Props) {
    const { t } = useTrans();
    // Phones and tablets, in either orientation: a finger, not a mouse.
    const [isTouch, setIsTouch] = useState(false);
    const [formReady, setFormReady] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [downloadFailed, setDownloadFailed] = useState(false);
    const [confirming, setConfirming] = useState<'print' | 'download' | null>(
        null,
    );
    const [finishing, setFinishing] = useState(false);

    useEffect(() => {
        setIsTouch(window.matchMedia('(pointer: coarse)').matches);
    }, []);

    useEffect(() => {
        if (!formReady) {
            return;
        }

        const askAboutPrint = () => setConfirming('print');

        window.addEventListener('afterprint', askAboutPrint);

        return () => window.removeEventListener('afterprint', askAboutPrint);
    }, [formReady]);

    const downloadForm = async () => {
        setDownloading(true);
        setDownloadFailed(false);

        try {
            const response = await fetch(`${formUrl}?download=1`);

            if (
                !response.ok ||
                !response.headers.get('Content-Type')?.includes('pdf')
            ) {
                throw new Error('The form did not arrive.');
            }

            const file = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');

            link.href = file;
            link.download = fileName;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(file), 60_000);
            setConfirming('download');
        } catch {
            setDownloadFailed(true);
        } finally {
            setDownloading(false);
        }
    };

    const finish = () =>
        router.post(
            finishUrl,
            {},
            {
                onStart: () => setFinishing(true),
                onFinish: () => setFinishing(false),
            },
        );

    return (
        <>
            <Head title={t('applicant.printed_tab')} />
            <PageContainer>
                <section
                    className="space-y-3 rounded-xl border bg-card p-4 sm:p-6"
                    data-test="printed-confirmation"
                >
                    <h1 className="flex items-center gap-2 text-lg font-bold">
                        <CheckCircle2
                            className="size-5 text-success"
                            aria-hidden
                        />
                        {t('applicant.printed_title')}
                    </h1>
                    <p className="text-sm leading-relaxed">
                        {t(
                            isTouch
                                ? 'applicant.printed_text_download'
                                : 'applicant.printed_text',
                        )}
                    </p>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt className="text-muted-foreground">
                            {t('student.student_number')}
                        </dt>
                        <dd>
                            <bdi dir="ltr" className="font-semibold">
                                {studentNumber ?? '—'}
                            </bdi>
                        </dd>
                    </dl>
                    <div className="flex flex-wrap gap-2">
                        {!isTouch && (
                            <Button
                                size="lg"
                                disabled={!formReady}
                                onClick={() => window.print()}
                                data-test="printed-form-print"
                            >
                                <Printer className="size-4" aria-hidden />
                                {t('applicant.print_form')}
                            </Button>
                        )}
                        <Button
                            size="lg"
                            variant={isTouch ? 'default' : 'outline'}
                            className={isTouch ? 'w-full' : undefined}
                            disabled={downloading}
                            onClick={() => void downloadForm()}
                            data-test="printed-form-download"
                        >
                            {downloading ? (
                                <Spinner />
                            ) : (
                                <Download className="size-4" aria-hidden />
                            )}
                            {t('applicant.download_form')}
                        </Button>
                    </div>
                    {isTouch && (
                        <p className="text-sm text-muted-foreground">
                            {t('applicant.download_form_hint')}
                        </p>
                    )}
                    {downloadFailed && (
                        <p className="text-sm text-destructive" role="alert">
                            {t('applicant.download_failed')}
                        </p>
                    )}
                </section>

                <PdfPages
                    url={formUrl}
                    title={t('applicant.print_form')}
                    onReady={() => setFormReady(true)}
                />

                <ConfirmDialog
                    title={t(
                        confirming === 'download'
                            ? 'applicant.finish_download_title'
                            : 'applicant.finish_print_title',
                    )}
                    description={t('applicant.finish_text')}
                    confirmLabel={t(
                        confirming === 'download'
                            ? 'applicant.finish_download_confirm'
                            : 'applicant.finish_print_confirm',
                    )}
                    cancelLabel={t('applicant.finish_retry')}
                    open={confirming !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirming(null);
                        }
                    }}
                    processing={finishing}
                    onConfirm={finish}
                />
            </PageContainer>
        </>
    );
}
