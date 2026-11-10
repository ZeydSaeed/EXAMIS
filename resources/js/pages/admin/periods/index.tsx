import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarRange, Plus } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderFilter,
    headerButtonClassName,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import { useListQuery } from '@/hooks/use-list-query';
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

type Props = {
    periods: Paginated<Row>;
    filters: { status?: string; q?: string; per_page?: string | number };
    statuses: string[];
};

export default function PeriodsIndex({ periods, filters, statuses }: Props) {
    const { t, locale } = useTrans();
    const { search, setSearch, urlFor, visit, tableArea } = useListQuery({
        url: admin.periods.index.url,
        filters,
        paginator: periods,
    });

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.periods'), href: admin.periods.index() }],
    });

    return (
        <>
            <Head title={t('nav.periods')} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim(), page: 1 })}
                placeholder={t('periods.search_placeholder')}
                actions={
                    <Link
                        href={admin.periods.create()}
                        className={headerButtonClassName}
                        aria-label={t('periods.create')}
                        title={t('periods.create')}
                    >
                        <Plus aria-hidden />
                    </Link>
                }
            >
                <HeaderFilter
                    label={t('applications.status')}
                    value={filters.status ?? ''}
                    options={statuses.map((status) => ({
                        value: status,
                        label: t(`periods.status.${status}`),
                    }))}
                    onChange={(status) => visit({ status, page: 1 })}
                />
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.periods')}</h1>

                <div ref={tableArea} className="min-h-0 flex-1">
                    {periods.data.length === 0 ? (
                        <EmptyState
                            icon={CalendarRange}
                            title={
                                filters.q || filters.status
                                    ? t('admin.no_results')
                                    : t('periods.empty')
                            }
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[44rem] [&_td]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.periods')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-12 text-center!"
                                        >
                                            #
                                        </th>
                                        <th scope="col">{t('periods.code')}</th>
                                        <th scope="col">{t('periods.name')}</th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('applications.status')}
                                        </th>
                                        <th scope="col">
                                            {t('periods.window')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('nav.applications')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {periods.data.map((period, index) => (
                                        <tr key={period.id}>
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {(periods.from ?? 1) + index}
                                            </td>
                                            <td>
                                                <Link
                                                    href={admin.periods.edit(
                                                        period.id,
                                                    )}
                                                    className="font-mono hover:underline"
                                                    dir="ltr"
                                                >
                                                    <Highlight
                                                        text={period.code}
                                                        query={filters.q}
                                                    />
                                                </Link>
                                            </td>
                                            <td>
                                                <Highlight
                                                    text={period.name}
                                                    query={filters.q}
                                                />{' '}
                                                <span className="text-muted-foreground">
                                                    (
                                                    <Highlight
                                                        text={
                                                            period.academic_year
                                                        }
                                                        query={filters.q}
                                                    />
                                                    )
                                                </span>
                                            </td>
                                            <td className="text-center!">
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
                                            <td className="text-center! tabular-nums">
                                                {period.applications_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <NumberedPagination
                    current={periods.current_page}
                    last={periods.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}
