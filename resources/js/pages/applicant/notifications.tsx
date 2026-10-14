import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { EmptyState } from '@/components/examis/empty-state';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Pagination } from '@/components/examis/pagination';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import applications from '@/routes/applications';
import notificationRoutes from '@/routes/notifications';
import type { Paginated } from '@/types';

type NotificationItem = {
    id: string;
    data: {
        application?: string;
        reference?: string;
        status?: string;
        reason?: string | null;
    };
    status_label: string | null;
    read_at: string | null;
    created_at: string | null;
};

export default function Notifications({
    notifications,
}: {
    notifications: Paginated<NotificationItem>;
}) {
    const { t, locale } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.notifications'), href: notificationRoutes.index() },
        ],
    });

    const hasUnread = notifications.data.some(
        (notification) => !notification.read_at,
    );

    return (
        <>
            <Head title={t('nav.notifications')} />
            <PageContainer className="max-w-3xl">
                <PageHeader
                    title={t('nav.notifications')}
                    actions={
                        hasUnread && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        notificationRoutes.read.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck className="size-4" aria-hidden />
                                {t('notifications.mark_all_read')}
                            </Button>
                        )
                    }
                />

                {notifications.data.length === 0 ? (
                    <EmptyState icon={Bell} title={t('notifications.empty')} />
                ) : (
                    <ul className="divide-y rounded-xl border bg-card">
                        {notifications.data.map((notification) => (
                            <li
                                key={notification.id}
                                className={`p-4 ${notification.read_at ? '' : 'bg-primary/5'}`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="space-y-1">
                                        <p className="text-sm">
                                            {!notification.read_at && (
                                                <span className="sr-only">
                                                    {t(
                                                        'notifications.unread',
                                                    )}{' '}
                                                </span>
                                            )}
                                            {t('notifications.status_changed', {
                                                reference:
                                                    notification.data
                                                        .reference ?? '',
                                                status:
                                                    notification.status_label ??
                                                    '',
                                            })}
                                        </p>
                                        {notification.data.reason && (
                                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                                {notification.data.reason}
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateTime(
                                                notification.created_at,
                                                locale,
                                            )}
                                        </p>
                                    </div>
                                    {notification.data.application && (
                                        <Link
                                            href={applications.show(
                                                notification.data.application,
                                            )}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {t('applicant.view_application')}
                                        </Link>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination
                    prev={notifications.prev_page_url}
                    next={notifications.next_page_url}
                />
            </PageContainer>
        </>
    );
}
