import { Form, Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Field } from '@/components/examis/field';
import { StatusBadge } from '@/components/examis/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import { store } from '@/routes/track';
import type { ApplicationStatus } from '@/types';

type Props = {
    result: {
        reference_number: string;
        status: ApplicationStatus;
        status_label: string;
        exam_period: string;
        submitted_at: string | null;
    } | null;
};

export default function TrackApplication({ result }: Props) {
    const { t, locale } = useTrans();

    return (
        <>
            <Head title={t('public.track_application')} />
            <div className="mx-auto w-full max-w-xl px-4 py-10">
                <h1 className="mb-2 text-2xl font-bold">
                    {t('public.track_application')}
                </h1>
                <p className="mb-6 text-sm text-muted-foreground">
                    {t('track.description')}
                </p>

                <Form
                    {...store.form()}
                    className="space-y-5 rounded-xl border bg-card p-5"
                    resetOnSuccess={['birth_date']}
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                id="reference_number"
                                label={t('track.reference_number')}
                                error={errors.reference_number}
                                required
                            >
                                <Input
                                    id="reference_number"
                                    name="reference_number"
                                    dir="ltr"
                                    required
                                    autoCapitalize="characters"
                                    placeholder="EX2027-0000019"
                                />
                            </Field>
                            <Field
                                id="birth_date"
                                label={t('track.birth_date')}
                                error={errors.birth_date}
                                required
                            >
                                <Input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    required
                                />
                            </Field>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                                data-test="track-button"
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Search className="size-4" aria-hidden />
                                )}
                                {t('track.search')}
                            </Button>
                        </>
                    )}
                </Form>

                {result && (
                    <section
                        className="mt-6 rounded-xl border bg-card p-5"
                        aria-live="polite"
                        data-test="track-result"
                    >
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('track.reference_number')}
                                </dt>
                                <dd className="font-semibold" dir="ltr">
                                    {result.reference_number}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('applications.status')}
                                </dt>
                                <dd>
                                    <StatusBadge
                                        status={result.status}
                                        label={result.status_label}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('applications.exam_period')}
                                </dt>
                                <dd>{result.exam_period}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('applications.submitted_at')}
                                </dt>
                                <dd>
                                    {formatDateTime(
                                        result.submitted_at,
                                        locale,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </section>
                )}
            </div>
        </>
    );
}
