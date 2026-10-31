import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, LogIn } from 'lucide-react';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import { ExamisMark } from '@/components/examis/examis-mark';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { dashboard } from '@/routes';
import apply from '@/routes/apply';

/**
 * Standalone entry page: the Ministry of Education seal on the right, the
 * application mark in the middle and the vocational-education seal on the
 * left (fixed sides in both languages), then the service title and the
 * ways in. No portal header or footer.
 */
export default function Start() {
    const { t, isRtl } = useTrans();
    const { auth, name } = usePage().props;
    const Forward = isRtl ? ArrowLeft : ArrowRight;

    return (
        <>
            <Head title={t('start.title')} />

            <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
                <BrandBackdrop />

                {/* Sides are fixed: ministry right, vocational left. */}
                <header
                    dir="rtl"
                    className="flex w-full items-center justify-between gap-3 px-4 pt-6 motion-safe:animate-[start-rise_0.7s_ease-out_both] sm:px-10 sm:pt-8"
                >
                    <img
                        src="/images/moe-iraq-logo.png"
                        alt={t('start.moe_logo')}
                        className="size-20 object-contain drop-shadow-md sm:size-28"
                        decoding="async"
                        draggable={false}
                    />
                    <ExamisMark
                        surface="light"
                        title={name}
                        className="size-28 drop-shadow-md sm:size-44"
                    />
                    <img
                        src="/images/vocational-education-logo.png"
                        alt={t('start.vocational_logo')}
                        className="size-20 object-contain drop-shadow-md sm:size-28"
                        decoding="async"
                        draggable={false}
                    />
                </header>

                <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 py-10 text-center sm:px-6">
                    <div className="space-y-4 motion-safe:animate-[start-rise_0.7s_0.15s_ease-out_both]">
                        <div className="space-y-2">
                            <h1 className="text-4xl leading-tight font-bold tracking-tight text-balance sm:text-6xl">
                                {t('start.heading')}
                            </h1>
                            <p className="text-4xl leading-tight font-bold tracking-tight text-balance sm:text-6xl">
                                {t('start.city')}
                            </p>
                        </div>
                        <p className="mx-auto max-w-xl text-base text-pretty text-[var(--examis-navy)]/70 sm:text-lg dark:text-white/70">
                            {t('start.tagline')}
                        </p>
                        <div className="mx-auto max-w-xl text-sm leading-7 font-bold text-pretty">
                            <p>{t('start.credits_patron')}</p>
                            <p>{t('start.credits_development')}</p>
                            <p>{t('start.credits_support')}</p>
                        </div>
                    </div>

                    <div className="mt-10 flex w-full max-w-md flex-col gap-3 motion-safe:animate-[start-rise_0.7s_0.3s_ease-out_both] sm:max-w-none sm:flex-row sm:justify-center">
                        {auth.user ? (
                            <Button
                                size="lg"
                                className="h-12 px-8 text-base shadow-lg"
                                asChild
                            >
                                <Link href={dashboard()}>
                                    {t('home.go_to_account')}
                                    <Forward className="size-4" aria-hidden />
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                size="lg"
                                className="h-12 px-8 text-base shadow-[var(--examis-navy)]/20 shadow-lg"
                                asChild
                            >
                                <Link
                                    href={apply.terms()}
                                    data-test="start-login"
                                >
                                    <LogIn className="size-5" aria-hidden />
                                    {t('start.enter_form')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </main>

                <footer className="px-4 pb-6 text-center text-xs text-[var(--examis-navy)]/60 dark:text-white/50">
                    {t('start.footer')}
                </footer>
            </div>
        </>
    );
}
