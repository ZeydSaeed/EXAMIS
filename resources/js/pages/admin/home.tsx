import { Head, setLayoutProps } from '@inertiajs/react';
import { DashboardBackdrop } from '@/components/examis/dashboard-backdrop';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

/**
 * Staff home: the first page after sign-in, showing the EXAMIS illustration
 * across the whole content area. The status cards live on the dashboard.
 */
export default function AdminHome() {
    const { t } = useTrans();

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.staff_home'), href: admin.home() }],
    });

    return (
        <>
            <Head title={t('nav.staff_home')} />

            <div className="relative isolate flex-1 overflow-hidden rounded-b-xl">
                <DashboardBackdrop />
                <h1 className="sr-only">{t('nav.staff_home')}</h1>
            </div>
        </>
    );
}
