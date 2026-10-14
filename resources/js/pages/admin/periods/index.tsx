import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarRange, Plus } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Pagination } from '@/components/examis/pagination';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    code: string;
    name: string;
    academic_year: string;
    status: string;
    opens_at: string;
    closes_at: string;
    applications_count: number;
};

export default function PeriodsIndex({ periods }: { periods: Paginated<Row> }) {
    const { t, locale } = useTrans();

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.periods'), href: admin.periods.index() }],
    });

    return (
        <>
            <Head title={t('nav.periods')} />
            <PageContainer>
                <PageHeader
                    title={t('nav.periods')}
                    description={t('periods.description')}
                    actions={
                        <Button asChild>
                            <Link href={admin.periods.create()}>
                                <Plus className="size-4" aria-hidden />
                                {t('periods.create')}
                            </Link>
                        </Button>
                    }
                />

                {periods.data.length === 0 ? (
                    <EmptyState
                        icon={CalendarRange}
                        title={t('periods.empty')}
                    />
                ) : (
                    <DataTableFrame>
                        <table className="min-w-[40rem]">
                            <thead>
                                <tr>
                                    <th scope="col">{t('periods.code')}</th>
                                    <th scope="col">{t('periods.name')}</th>
                                    <th scope="col">
                                        {t('applications.status')}
                                    </th>
                                    <th scope="col">{t('periods.window')}</th>
                                    <th scope="col">{t('nav.applications')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.data.map((period) => (
                                    <tr key={period.id}>
                                        <td className="font-mono" dir="ltr">
                                            <Link
                                                href={admin.periods.edit(
                                                    period.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {period.code}
                                            </Link>
                                        </td>
                                        <td>
                                            {period.name}{' '}
                                            <span className="text-muted-foreground">
                                                ({period.academic_year})
                                            </span>
                                        </td>
                                        <td>
                                            {t(
                                                `periods.status.${period.status}`,
                                            )}
                                        </td>
                                        <td className="text-muted-foreground">
                                            {formatDateTime(
                                                period.opens_at,
                                                locale,
                                            )}{' '}
                                            —{' '}
                                            {formatDateTime(
                                                period.closes_at,
                                                locale,
                                            )}
                                        </td>
                                        <td>{period.applications_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </DataTableFrame>
                )}

                <Pagination
                    prev={periods.prev_page_url}
                    next={periods.next_page_url}
                />
            </PageContainer>
        </>
    );
}
