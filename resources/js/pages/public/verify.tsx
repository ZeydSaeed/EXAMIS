import { Head } from '@inertiajs/react';
import { BadgeCheck, ShieldAlert } from 'lucide-react';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';

type Props = {
    receipt: {
        reference_number: string;
        exam_period: string;
        status_label: string;
        submitted_at: string | null;
    } | null;
};

export default function VerifyReceipt({ receipt }: Props) {
    const { t, locale } = useTrans();

    return (
        <>
            <Head title={t('verify.title')} />
            <div className="mx-auto w-full max-w-xl px-4 py-12">
                {receipt ? (
                    <div className="rounded-xl border border-success/40 bg-card p-6">
                        <div className="mb-4 flex items-center gap-3 text-success">
                            <BadgeCheck className="size-8" aria-hidden />
                            <h1 className="text-xl font-semibold">
                                {t('verify.genuine')}
                            </h1>
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    {t('track.reference_number')}
                                </dt>
                                <dd className="font-semibold" dir="ltr">
                                    {receipt.reference_number}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    {t('applications.exam_period')}
                                </dt>
                                <dd>{receipt.exam_period}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    {t('verify.current_status')}
                                </dt>
                                <dd className="font-medium">
                                    {receipt.status_label}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    {t('applications.submitted_at')}
                                </dt>
                                <dd>
                                    {formatDateTime(
                                        receipt.submitted_at,
                                        locale,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </div>
                ) : (
                    <div className="rounded-xl border border-destructive/40 bg-card p-6">
                        <div className="mb-2 flex items-center gap-3 text-destructive">
                            <ShieldAlert className="size-8" aria-hidden />
                            <h1 className="text-xl font-semibold">
                                {t('verify.not_found')}
                            </h1>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('verify.not_found_text')}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
