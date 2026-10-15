import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Inbox } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderFilter,
    HeaderFilterChip,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import { StatusBadge, statusSurface } from '@/components/examis/status-badge';
import { useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { ApplicationStatus, Paginated } from '@/types';

type Row = {
    uuid: string;
    reference_number: string | null;
    full_name_ar: string | null;
    status: ApplicationStatus;
    status_label: string;
    exam_period: string;
    assignee: string | null;
    submitted_at: string | null;
};

type Filters = {
    status?: string;
    period?: string | number;
    assigned?: string;
    q?: string;
    per_page?: string | number;
};

type Props = {
    applications: Paginated<Row>;
    filters: Filters;
    periods: { id: number; name: string }[];
    statuses: { value: ApplicationStatus; label: string }[];
};

export default function AdminApplications({
    applications,
    filters,
    periods,
    statuses,
}: Props) {
    const { t, locale } = useTrans();
    const { search, setSearch, urlFor, visit, tableArea } = useListQuery({
        url: admin.applications.index.url,
        filters,
        paginator: applications,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.applications'), href: admin.applications.index() },
        ],
    });

    const assignedLabel =
        filters.assigned === 'me'
            ? t('admin.assigned_to_me')
            : filters.assigned === 'unassigned'
              ? t('admin.unassigned')
              : null;
    const periodLabel = filters.period
        ? periods.find((period) => String(period.id) === String(filters.period))
              ?.name
        : null;

    return (
        <>
            <Head title={t('nav.applications')} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim(), page: 1 })}
                placeholder={t('admin.search_placeholder')}
            >
                <HeaderFilter
                    label={t('applications.status')}
                    value={filters.status ?? ''}
                    options={statuses}
                    onChange={(status) => visit({ status, page: 1 })}
                />
                {assignedLabel && (
                    <HeaderFilterChip
                        label={assignedLabel}
                        onRemove={() => visit({ assigned: '', page: 1 })}
                    />
                )}
                {periodLabel && (
                    <HeaderFilterChip
                        label={periodLabel}
                        onRemove={() => visit({ period: '', page: 1 })}
                    />
                )}
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.applications')}</h1>

                <div ref={tableArea} className="min-h-0 flex-1">
                    {applications.data.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[44rem] [&_td]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.applications')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-12 text-center!"
                                        >
                                            #
                                        </th>
                                        <th scope="col">
                                            {t('track.reference_number')}
                                        </th>
                                        <th scope="col">
                                            {t('fields.full_name_ar')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('applications.status')}
                                        </th>
                                        <th scope="col">
                                            {t('admin.assignee')}
                                        </th>
                                        <th scope="col">
                                            {t('applications.submitted_at')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {applications.data.map((row, index) => (
                                        <tr key={row.uuid}>
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {(applications.from ?? 1) +
                                                    index}
                                            </td>
                                            <td>
                                                <Link
                                                    href={admin.applications.show(
                                                        row.uuid,
                                                    )}
                                                    className="hover:underline"
                                                    dir="ltr"
                                                >
                                                    <Highlight
                                                        text={
                                                            row.reference_number
                                                        }
                                                        query={filters.q}
                                                    />
                                                </Link>
                                            </td>
                                            <td>
                                                <Highlight
                                                    text={row.full_name_ar}
                                                    query={filters.q}
                                                />
                                            </td>
                                            <td
                                                className={cn(
                                                    'text-center!',
                                                    statusSurface(row.status),
                                                )}
                                            >
                                                <Link
                                                    href={admin.applications.show(
                                                        row.uuid,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    <StatusBadge
                                                        status={row.status}
                                                        label={row.status_label}
                                                        variant="plain"
                                                    />
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground">
                                                {row.assignee ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground">
                                                {formatDateTime(
                                                    row.submitted_at,
                                                    locale,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <NumberedPagination
                    current={applications.current_page}
                    last={applications.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}
