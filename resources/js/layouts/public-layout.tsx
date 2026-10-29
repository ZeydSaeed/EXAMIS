import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ExamisMark } from '@/components/examis/examis-mark';
import { LanguageSwitcher } from '@/components/language-switcher';
import { NetworkStatus } from '@/components/network-status';
import { useDocumentLocale } from '@/hooks/use-document-locale';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { dashboard, home, login, register } from '@/routes';
import pages from '@/routes/pages';
import track from '@/routes/track';
import { ScrollButtons } from '@/components/scroll-buttons';

/**
 * Shell for the public portal: header with sign-in, footer with the managed
 * content pages, skip link for keyboard users.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    useDocumentLocale();

    const { auth, name, organization } = usePage().props;
    const { t } = useTrans();

    const footerLinks = [
        ['about', t('public.links.about')],
        ['instructions', t('public.links.instructions')],
        ['documents', t('public.links.documents')],
        ['faq', t('public.links.faq')],
        ['privacy', t('public.links.privacy')],
        ['terms', t('public.links.terms')],
        ['help', t('public.links.help')],
    ] as const;

    return (
        <div className="flex h-svh flex-col overflow-hidden bg-background">
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground"
            >
                {t('common.skip_to_content')}
            </a>

            <header className="no-print shrink-0 border-b bg-primary text-primary-foreground">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-3">
                    <Link
                        href={home()}
                        className="flex min-w-0 items-center gap-2 text-primary-foreground"
                    >
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-[var(--sis-button-radius)] bg-[var(--sis-oxford)] text-primary-foreground">
                            <AppLogoIcon className="size-6" />
                        </span>
                        <span className="grid min-w-0 leading-tight">
                            <span className="truncate font-semibold">
                                {name}
                            </span>
                            <span className="hidden truncate text-xs text-primary-foreground/75 sm:block">
                                {organization}
                            </span>
                        </span>
                    </Link>

                    <nav
                        className="flex items-center gap-1 sm:gap-2"
                        aria-label={t('public.main_navigation')}
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            asChild
                            className="hidden text-primary-foreground hover:bg-white/10 hover:text-primary-foreground sm:inline-flex"
                        >
                            <Link href={track.create()}>
                                {t('public.track_application')}
                            </Link>
                        </Button>
                        <LanguageSwitcher className="text-primary-foreground hover:bg-white/10 hover:text-primary-foreground" />
                        {auth.user ? (
                            <Button size="sm" variant="secondary" asChild>
                                <Link href={dashboard()}>
                                    {t('public.my_account')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className="border-primary-foreground/40 bg-transparent text-primary-foreground hover:bg-white/10 hover:text-primary-foreground"
                                >
                                    <Link href={login()}>
                                        {t('auth.login')}
                                    </Link>
                                </Button>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    asChild
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href={register()}>
                                        {t('auth.register')}
                                    </Link>
                                </Button>
                            </>
                        )}
                        <ExamisMark className="ms-1 h-9 w-auto shrink-0" />
                    </nav>
                </div>
            </header>

            {/* Header pinned; only long content scrolls, never the window. */}
            <div
                data-scroll-root
                className="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain"
            >
                <main id="main-content" className="flex-[1_0_auto]">
                    {children}
                </main>

                <footer className="no-print border-t bg-muted/40">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1">
                            <p className="font-medium">{organization}</p>
                            <p className="text-muted-foreground">
                                {t('public.footer_note')}
                            </p>
                        </div>
                        <nav aria-label={t('public.footer_navigation')}>
                            <ul className="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                                {footerLinks.map(([slug, label]) => (
                                    <li key={slug}>
                                        <Link
                                            href={pages.show(slug)}
                                            className="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                        >
                                            {label}
                                        </Link>
                                    </li>
                                ))}
                                <li>
                                    <Link
                                        href={track.create()}
                                        className="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        {t('public.track_application')}
                                    </Link>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </footer>
            </div>

            <NetworkStatus />
            <ScrollButtons />
        </div>
    );
}
