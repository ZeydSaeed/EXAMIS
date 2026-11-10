import { Form, Head } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { useState } from 'react';
import { ApplyHeader } from '@/components/examis/apply-header';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';
import apply from '@/routes/apply';

type Props = {
    title: string | null;
    terms: string[];
    status?: string;
};

/**
 * Before registering, the student reads the application terms (the
 * published "eligibility" content page) and accepts them.
 */
export default function ApplicationTerms({ title, terms, status }: Props) {
    const { t, locale, isRtl } = useTrans();
    const [accepted, setAccepted] = useState(false);
    const Forward = isRtl ? ArrowLeft : ArrowRight;

    return (
        <>
            <Head title={title ?? t('apply.terms_title')} />

            <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
                <BrandBackdrop />

                <ApplyHeader />

                <main className="mx-auto w-full max-w-3xl flex-1 px-4 pt-8 pb-40 sm:px-6 sm:pt-12">
                    {status && (
                        <div
                            role="status"
                            className="mb-6 rounded-md border border-success/40 bg-success/10 p-3 text-center text-sm font-medium text-success"
                        >
                            {status}
                        </div>
                    )}

                    <div className="mb-6 motion-safe:animate-[start-rise_0.6s_ease-out_both]">
                        <div className="space-y-1">
                            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                {title ?? t('apply.terms_title')}
                            </h1>
                            <p className="text-sm text-[var(--examis-navy)]/70 dark:text-white/65">
                                {t('apply.terms_intro')}
                            </p>
                        </div>
                    </div>

                    <section
                        aria-label={title ?? t('apply.terms_title')}
                        className="rounded-3xl border border-white/60 bg-white/75 p-2 shadow-[var(--examis-navy)]/5 shadow-xl backdrop-blur-md motion-safe:animate-[start-rise_0.6s_0.2s_ease-out_both] sm:p-3 dark:border-white/10 dark:bg-white/5"
                    >
                        {terms.length === 0 ? (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                {t('apply.no_terms')}
                            </p>
                        ) : (
                            <ol className="divide-y divide-[var(--examis-navy)]/8 dark:divide-white/10">
                                {terms.map((term, index) => (
                                    <li
                                        key={index}
                                        className="flex gap-4 rounded-2xl p-4 transition-colors hover:bg-[var(--examis-pearl)]/60 sm:p-5 dark:hover:bg-white/5"
                                    >
                                        <span
                                            aria-hidden
                                            className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-[var(--examis-gold)]/20 text-sm font-bold text-[var(--examis-navy)] dark:bg-[var(--examis-gold)]/15 dark:text-[var(--examis-gold)]"
                                        >
                                            {(index + 1).toLocaleString(
                                                locale === 'ar'
                                                    ? 'ar-IQ'
                                                    : 'en',
                                            )}
                                        </span>
                                        <p className="min-w-0 flex-1 pt-1 text-justify text-[0.95rem] leading-8">
                                            {term}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </main>

                {/* Acceptance bar, always in reach at the bottom. */}
                <Form
                    {...apply.terms.accept.form()}
                    className="fixed inset-x-0 bottom-0 z-10 border-t border-[var(--examis-navy)]/10 bg-white/85 backdrop-blur-lg dark:border-white/10 dark:bg-[#0a1230]/85"
                >
                    {({ processing, errors }) => (
                        <div className="mx-auto flex w-full max-w-3xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="accept"
                                        name="accept"
                                        value="1"
                                        checked={accepted}
                                        onCheckedChange={(value) =>
                                            setAccepted(value === true)
                                        }
                                        className="mt-0.5 border-[var(--examis-navy)]/40 dark:border-white/60"
                                        data-test="accept-terms"
                                    />
                                    <Label
                                        htmlFor="accept"
                                        className="leading-relaxed font-medium"
                                    >
                                        {t('apply.accept')}
                                    </Label>
                                </div>
                                <InputError
                                    className="mt-1"
                                    message={errors.accept}
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={!accepted || processing}
                                    className={cn(
                                        'flex-1 px-8 transition-all sm:flex-none',
                                        accepted && 'shadow-lg',
                                    )}
                                    data-test="terms-next"
                                >
                                    {processing ? (
                                        <Spinner />
                                    ) : (
                                        accepted && (
                                            <Check
                                                className="size-4"
                                                aria-hidden
                                            />
                                        )
                                    )}
                                    {t('apply.next')}
                                    <Forward className="size-4" aria-hidden />
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
