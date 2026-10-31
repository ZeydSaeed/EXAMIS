import { Head } from '@inertiajs/react';
import { CheckCircle2, Download, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageContainer } from '@/components/examis/page-header';
import { PdfPages } from '@/components/examis/pdf-pages';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';

type Props = {
    studentNumber: number | null;
    formUrl: string;
};

/**
 * The end of the one-time account (ADR-0010): the application has gone to the
 * committee and only the printable form remains. No way on from here.
 */
export default function PrintedApplication({
    studentNumber,
    formUrl,
}: Props) {
    const { t } = useTrans();
    // Phones and tablets, in either orientation: a finger, not a mouse.
    const [isTouch, setIsTouch] = useState(false);

    useEffect(() => {
        setIsTouch(window.matchMedia('(pointer: coarse)').matches);
    }, []);

    return (
        <>
            <Head title={t('applicant.printed_title')} />
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
                    {isTouch ? (
                        <>
                            <Button size="lg" className="w-full" asChild>
                                <a
                                    href={`${formUrl}?download=1`}
                                    download
                                    data-test="printed-form-download"
                                >
                                    <Download className="size-4" aria-hidden />
                                    {t('applicant.download_form')}
                                </a>
                            </Button>
                            <p className="text-sm text-muted-foreground">
                                {t('applicant.download_form_hint')}
                            </p>
                        </>
                    ) : (
                        <Button size="lg" asChild>
                            <a
                                href={formUrl}
                                target="_blank"
                                rel="noopener"
                                data-test="printed-form-link"
                            >
                                <Printer className="size-4" aria-hidden />
                                {t('applicant.print_form')}
                            </a>
                        </Button>
                    )}
                </section>

                <PdfPages url={formUrl} title={t('applicant.print_form')} />
            </PageContainer>
        </>
    );
}
