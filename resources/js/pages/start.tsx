import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, LogIn } from 'lucide-react';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import { ExamisMark } from '@/components/examis/examis-mark';
import { StartContacts } from '@/components/examis/start-contacts';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { dashboard } from '@/routes';
import apply from '@/routes/apply';

/**
 * Standalone entry page: the Ministry of Education seal on the right, the
 * application mark in the middle and the vocational-education seal on the
 * left (fixed sides in both languages), then the service title and the
 * ways in. No portal header or footer. Sizes follow the screen height so the
 * whole page fits without scrolling; it only scrolls when a screen is too
 * short to keep the text readable.
 */
export default function Start() {
    const { t, isRtl } = useTrans();
    const { auth, name } = usePage().props;
    const Forward = isRtl ? ArrowLeft : ArrowRight;

    return (
        <>
            <Head title={t('start.title')} />

            <div className="relative isolate h-svh overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
                <BrandBackdrop />

                <div className="flex h-full flex-col overflow-x-hidden overflow-y-auto">
                    {/* Sides are fixed: ministry right, vocational left. */}
                    <header
                        dir="rtl"
                        className="flex w-full items-center justify-between gap-3 px-4 pt-[2svh] motion-safe:animate-[start-rise_0.7s_ease-out_both] sm:px-10"
                    >
                        <img
                            src="/images/moe-iraq-logo.webp"
                            alt={t('start.moe_logo')}
                            className="size-[clamp(3rem,min(12svh,19vw),7rem)] shrink-0 object-contain drop-shadow-md"
                            decoding="async"
                            draggable={false}
                        />
                        <div className="flex flex-col items-center gap-1">
                            <ExamisMark
                                surface="light"
                                title={name}
                                className="size-[clamp(3.5rem,19svh,11rem)] drop-shadow-md"
                            />
                            <p
                                dir="ltr"
                                lang="en"
                                className="max-w-40 text-center text-[clamp(0.6875rem,min(2svh,3.4vw),1rem)] leading-tight font-normal text-black sm:max-w-none dark:text-white"
                            >
                                {t('brand.full_name')}
                            </p>
                        </div>
                        <img
                            src="/images/vocational-education-logo.webp"
                            alt={t('start.vocational_logo')}
                            className="size-[clamp(3rem,min(12svh,19vw),7rem)] shrink-0 object-contain drop-shadow-md"
                            decoding="async"
                            draggable={false}
                        />
                    </header>

                    <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 py-[1.5svh] text-center sm:px-6">
                        <div className="space-y-[1.5svh] motion-safe:animate-[start-rise_0.7s_0.15s_ease-out_both]">
                            <div className="space-y-[1svh]">
                                <h1 className="text-[clamp(1.25rem,min(6.5svh,8vw),3.75rem)] leading-tight font-bold tracking-tight text-balance">
                                    {t('start.heading')}
                                </h1>
                                <p className="inline-block rounded-[1.75rem] border-2 border-[var(--examis-gold)] bg-white/60 px-8 py-[0.5svh] text-[clamp(1rem,min(4svh,6vw),2.25rem)] leading-tight font-bold tracking-tight shadow-[var(--examis-navy)]/10 shadow-md backdrop-blur-sm dark:bg-white/10">
                                    {t('start.city')}
                                </p>
                            </div>
                            <p className="mx-auto max-w-xl text-[clamp(0.75rem,2.2svh,1.125rem)] text-pretty text-[var(--examis-navy)]/70 dark:text-white/70">
                                {t('start.tagline')}
                            </p>
                            <div className="mx-auto max-w-xl text-[clamp(0.6875rem,1.8svh,0.875rem)] leading-normal font-bold text-pretty">
                                <p>{t('start.credits_patron')}</p>
                                <p>{t('start.credits_development')}</p>
                                <p>{t('start.credits_support')}</p>
                            </div>
                        </div>

                        <div className="mt-[2.5svh] flex w-full max-w-md flex-col gap-3 motion-safe:animate-[start-rise_0.7s_0.3s_ease-out_both] sm:max-w-none sm:flex-row sm:justify-center">
                            {auth.user ? (
                                <Button
                                    size="lg"
                                    className="h-[clamp(2.25rem,6svh,3rem)] px-8 text-base shadow-lg"
                                    asChild
                                >
                                    <Link href={dashboard()}>
                                        {t('home.go_to_account')}
                                        <Forward
                                            className="size-4"
                                            aria-hidden
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    size="lg"
                                    className="h-[clamp(2.25rem,6svh,3rem)] px-8 text-base shadow-[var(--examis-navy)]/20 shadow-lg"
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

                    <footer className="px-4 pb-[1.5svh] text-center text-[clamp(0.625rem,1.6svh,0.75rem)] text-[var(--examis-navy)]/60 dark:text-white/50">
                        <StartContacts />
                        {t('start.footer')}
                    </footer>
                </div>
            </div>
        </>
    );
}
