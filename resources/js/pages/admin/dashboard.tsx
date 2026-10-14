import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarRange, ClipboardList, UserCheck } from 'lucide-react';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { StatusBadge } from '@/components/examis/status-badge';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { ApplicationStatus } from '@/types';

type Props = {
    statusCounts: { status: ApplicationStatus; label: string; count: number }[];
    myQueue: number;
    periods: { id: number; name: string; status: string; closes_at: string }[];
};

export default function AdminDashboard({
    statusCounts,
    myQueue,
    periods,
}: Props) {
    const { t, locale } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.admin_dashboard'), href: admin.dashboard() },
        ],
    });

    const awaiting = statusCounts
        .filter(
            (item) =>
                item.status === 'submitted' || item.status === 'resubmitted',
        )
        .reduce((sum, item) => sum + item.count, 0);

    return (
        <>
            <Head title={t('nav.admin_dashboard')} />
            <PageContainer>
                <PageHeader
                    title={t('admin.dashboard_title')}
                    description={t('admin.dashboard_description')}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Link
                        href={admin.applications.index({
                            query: { status: 'submitted' },
                        })}
                        className="flex items-center gap-4 rounded-xl border bg-card p-5 transition-colors hover:border-primary"
                    >
                        <ClipboardList
                            className="size-8 text-primary"
                            aria-hidden
                        />
                        <div>
                            <p className="text-3xl font-bold">{awaiting}</p>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.awaiting_review')}
                            </p>
                        </div>
                    </Link>
                    <Link
                        href={admin.applications.index({
                            query: { assigned: 'me', status: 'under_review' },
                        })}
                        className="flex items-center gap-4 rounded-xl border bg-card p-5 transition-colors hover:border-primary"
                    >
                        <UserCheck
                            className="size-8 text-primary"
                            aria-hidden
                        />
                        <div>
                            <p className="text-3xl font-bold">{myQueue}</p>
                            <p className="text-sm text-muted-foreground">
                                {t('admin.my_queue')}
                            </p>
                        </div>
                    </Link>
                </div>

                <section aria-labelledby="by-status" className="space-y-3">
                    <h2 id="by-status" className="font-semibold">
                        {t('admin.by_status')}
                    </h2>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {statusCounts.map((item) => (
                            <Link
                                key={item.status}
                                href={admin.applications.index({
                                    query: { status: item.status },
                                })}
                                className="flex flex-col gap-2 rounded-lg border bg-card p-4 hover:bg-muted/50"
                            >
                                <StatusBadge
                                    status={item.status}
                                    label={item.label}
                                />
                                <span className="text-2xl font-semibold">
                                    {item.count}
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>

                {periods.length > 0 && (
                    <section aria-labelledby="periods" className="space-y-3">
                        <h2 id="periods" className="font-semibold">
                            {t('nav.periods')}
                        </h2>
                        <ul className="divide-y rounded-xl border bg-card">
                            {periods.map((period) => (
                                <li
                                    key={period.id}
                                    className="flex items-center justify-between gap-2 p-4 text-sm"
                                >
                                    <span className="flex items-center gap-2 font-medium">
                                        <CalendarRange
                                            className="size-4 text-muted-foreground"
                                            aria-hidden
                                        />
                                        {period.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {t(`periods.status.${period.status}`)} ·{' '}
                                        {t('home.closes_on', {
                                            date: formatDate(
                                                period.closes_at,
                                                locale,
                                            ),
                                        })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </PageContainer>
        </>
    );
}
