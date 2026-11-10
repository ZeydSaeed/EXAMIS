import { Head, setLayoutProps } from '@inertiajs/react';
import { DashboardBackdrop } from '@/components/examis/dashboard-backdrop';
import { ExamisMark } from '@/components/examis/examis-mark';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

/**
 * Staff home: the first page after sign-in. The EXAMIS mark and the system
 * name (English, then Arabic) sit centred at the top; the illustration fills
 * the rest of the content area. The status cards live on the dashboard.
 */
export default function AdminHome() {
    const { t } = useTrans();

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.staff_home'), href: admin.home() }],
    });

    return (
        <>
            <Head title={t('nav.staff_home')} />

            <div className="flex flex-1 flex-col overflow-hidden rounded-b-xl">
                <header className="flex shrink-0 flex-col items-center gap-2 px-4 pt-6 text-center text-primary">
                    <h1 className="sr-only">{t('nav.staff_home')}</h1>
                    <ExamisMark
                        surface="light"
                        className="h-24 w-auto sm:h-28"
                    />
                    <p
                        lang="en"
                        dir="ltr"
                        className="text-base font-bold tracking-wide sm:text-lg"
                    >
                        {t('brand.name_en')}
                    </p>
                    <p
                        lang="ar"
                        dir="rtl"
                        className="text-sm font-medium text-muted-foreground sm:text-base"
                    >
                        {t('brand.name_ar')}
                    </p>
                </header>

                <div className="relative isolate min-h-0 flex-1">
                    <DashboardBackdrop />
                </div>
            </div>
        </>
    );
}
