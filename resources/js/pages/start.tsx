import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, LogIn, UserPlus } from 'lucide-react';
import { BrandBackdrop } from '@/components/examis/brand-backdrop';
import { ExamisMark } from '@/components/examis/examis-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { dashboard, login } from '@/routes';
import apply from '@/routes/apply';

type Props = {
    canRegister: boolean;
};

/**
 * Standalone entry page: the Ministry of Education seal on the right, the
 * application mark in the middle and the vocational-education seal on the
 * left (fixed sides in both languages), then the service title and the
 * ways in. No portal header or footer.
 */
export default function Start({ canRegister }: Props) {
    const { t, isRtl } = useTrans();
    const { auth, name } = usePage().props;
    const Forward = isRtl ? ArrowLeft : ArrowRight;

    return (
        <>
            <Head title={t('start.title')} />

            <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-[var(--examis-pearl)] text-[var(--examis-navy)] dark:bg-[#0a1230] dark:text-white">
                <BrandBackdrop />

                <header className="flex justify-end px-4 pt-4 sm:px-6">
                    <LanguageSwitcher className="text-current hover:bg-black/5 dark:hover:bg-white/10" />
                </header>

                <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 pb-10 text-center sm:px-6">
                    {/* Sides are fixed: ministry right, vocational left. */}
                    <div
                        dir="rtl"
                        className="flex w-full items-center justify-between gap-3 motion-safe:animate-[start-rise_0.7s_ease-out_both] sm:justify-center sm:gap-12"
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
                            className="size-24 drop-shadow-md sm:size-36"
                        />
                        <img
                            src="/images/vocational-education-logo.png"
                            alt={t('start.vocational_logo')}
                            className="size-20 object-contain drop-shadow-md sm:size-28"
                            decoding="async"
                            draggable={false}
                        />
                    </div>

                    <div className="mt-10 space-y-4 motion-safe:animate-[start-rise_0.7s_0.15s_ease-out_both] sm:mt-14">
                        <p className="inline-flex items-center gap-2 rounded-full border border-[var(--examis-green)]/25 bg-white/60 px-4 py-1.5 text-sm font-medium text-[var(--examis-green)] backdrop-blur dark:border-white/15 dark:bg-white/5 dark:text-emerald-300">
                            <span className="size-2 rounded-full bg-[var(--examis-gold)]" />
                            {t('start.department')}
                        </p>
                        <h1 className="text-4xl leading-tight font-bold tracking-tight text-balance sm:text-6xl">
                            {t('start.title')}
                        </h1>
                        <p className="mx-auto max-w-xl text-base text-pretty text-[var(--examis-navy)]/70 sm:text-lg dark:text-white/70">
                            {t('start.tagline')}
                        </p>
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
                            <>
                                {canRegister && (
                                    <Button
                                        size="lg"
                                        className="h-12 px-8 text-base shadow-[var(--examis-navy)]/20 shadow-lg"
                                        asChild
                                    >
                                        <Link
                                            href={apply.terms()}
                                            data-test="start-register"
                                        >
                                            <UserPlus
                                                className="size-5"
                                                aria-hidden
                                            />
                                            {t('start.apply_now')}
                                        </Link>
                                    </Button>
                                )}
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="h-12 bg-white/70 px-8 text-base backdrop-blur dark:bg-white/5"
                                    asChild
                                >
                                    <Link
                                        href={login()}
                                        data-test="start-login"
                                    >
                                        <LogIn className="size-5" aria-hidden />
                                        {t('auth.login')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </main>

                <footer className="px-4 pb-6 text-center text-xs text-[var(--examis-navy)]/60 dark:text-white/50">
                    {t('start.footer')} © {new Date().getFullYear()}
                </footer>
            </div>
        </>
    );
}
