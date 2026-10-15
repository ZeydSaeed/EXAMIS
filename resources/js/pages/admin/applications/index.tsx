import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { Inbox, Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight } from '@/components/examis/highlight';
import { NumberedPagination } from '@/components/examis/pagination';
import { StatusBadge, statusSurface } from '@/components/examis/status-badge';
import { HeaderSlot } from '@/components/header-slots';
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

const MIN_ROWS = 5;
const MAX_ROWS = 100;
const SEARCH_DELAY_MS = 350;

/** Drops empty values so the URL only carries active filters. */
function compact(query: Record<string, unknown>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(query)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)]),
    );
}

export default function AdminApplications({
    applications,
    filters,
    periods,
    statuses,
}: Props) {
    const { t, locale } = useTrans();
    const [search, setSearch] = useState(filters.q ?? '');
    const tableArea = useRef<HTMLDivElement>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.applications'), href: admin.applications.index() },
        ],
    });

    const urlFor = useCallback(
        (changes: Filters & { page?: number }) =>
            admin.applications.index.url({
                query: compact({
                    ...filters,
                    per_page: applications.per_page,
                    page: applications.current_page,
                    ...changes,
                }),
            }),
        [filters, applications.per_page, applications.current_page],
    );

    const visit = useCallback(
        (changes: Filters & { page?: number }) =>
            router.get(
                urlFor(changes),
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            ),
        [urlFor],
    );

    // Live search: wait for a pause in typing, then go back to page one.
    useEffect(() => {
        if (search.trim() === (filters.q ?? '').trim()) {
            return;
        }

        const timer = window.setTimeout(
            () => visit({ q: search.trim(), page: 1 }),
            SEARCH_DELAY_MS,
        );

        return () => window.clearTimeout(timer);
    }, [search, filters.q, visit]);

    // Fit as many rows as the table area can show without scrolling.
    useEffect(() => {
        const area = tableArea.current;

        if (!area) {
            return;
        }

        let timer: number | undefined;

        const fit = () => {
            const rem = parseFloat(
                getComputedStyle(document.documentElement).fontSize,
            );
            const frame = area.querySelector<HTMLElement>('.examis-data-table');
            const head = frame?.querySelector('thead')?.offsetHeight;
            const row = frame
                ?.querySelector('tbody tr')
                ?.getBoundingClientRect().height;
            const scrollbar =
                frame && frame.scrollWidth > frame.clientWidth
                    ? frame.offsetHeight - frame.clientHeight
                    : 0;
            const rowHeight = row || rem * 2.4;
            const rows = Math.floor(
                (area.clientHeight - (head || rowHeight) - scrollbar - 2) /
                    rowHeight,
            );
            const perPage = Math.min(MAX_ROWS, Math.max(MIN_ROWS, rows));

            if (perPage !== applications.per_page) {
                const first = applications.from ?? 1;
                visit({
                    per_page: perPage,
                    page: Math.floor((first - 1) / perPage) + 1,
                });
            }
        };

        const observer = new ResizeObserver(() => {
            window.clearTimeout(timer);
            timer = window.setTimeout(fit, 150);
        });

        observer.observe(area);

        return () => {
            observer.disconnect();
            window.clearTimeout(timer);
        };
    }, [applications.per_page, applications.from, visit]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: search.trim(), page: 1 });
    };

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

            <HeaderSlot name="start">
                <select
                    aria-label={t('applications.status')}
                    className="ms-4 h-8 max-w-44 cursor-pointer truncate rounded-md border border-white/25 bg-white/10 ps-2.5 pe-7 text-sm text-white transition-colors outline-none hover:bg-white/15 focus-visible:border-white/60 focus-visible:ring-2 focus-visible:ring-white/30 [&_option]:bg-background [&_option]:text-foreground"
                    value={filters.status ?? ''}
                    onChange={(event) =>
                        visit({ status: event.target.value, page: 1 })
                    }
                >
                    <option value="">
                        {t('applications.status')}: {t('common.all')}
                    </option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
                {[
                    { key: 'assigned' as const, label: assignedLabel },
                    { key: 'period' as const, label: periodLabel },
                ].map(
                    ({ key, label }) =>
                        label && (
                            <button
                                key={key}
                                type="button"
                                onClick={() => visit({ [key]: '', page: 1 })}
                                className="hidden h-7 max-w-40 items-center gap-1 rounded-full bg-white/15 ps-2.5 pe-1.5 text-xs text-white hover:bg-white/25 lg:inline-flex"
                                aria-label={`${t('admin.remove_filter')}: ${label}`}
                            >
                                <span className="truncate">{label}</span>
                                <X className="size-3.5 shrink-0" aria-hidden />
                            </button>
                        ),
                )}
            </HeaderSlot>

            <HeaderSlot name="center">
                <form
                    onSubmit={submitSearch}
                    role="search"
                    className="relative w-[clamp(9rem,34vw,30rem)]"
                >
                    <Search
                        className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-white/70"
                        aria-hidden
                    />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('admin.search_placeholder')}
                        aria-label={t('admin.search')}
                        className="h-9 w-full rounded-full border border-white/25 bg-white/10 ps-9 pe-9 text-sm text-white shadow-inner transition-colors outline-none placeholder:text-white/65 hover:bg-white/15 focus:border-white/60 focus:bg-white/20 focus:ring-2 focus:ring-white/25 [&::-webkit-search-cancel-button]:hidden"
                    />
                    {search !== '' && (
                        <button
                            type="button"
                            onClick={() => setSearch('')}
                            className="absolute inset-y-0 end-2 my-auto grid size-6 place-items-center rounded-full text-white/75 hover:bg-white/15 hover:text-white"
                            aria-label={t('admin.clear_search')}
                        >
                            <X className="size-3.5" aria-hidden />
                        </button>
                    )}
                </form>
            </HeaderSlot>

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
                                                <StatusBadge
                                                    status={row.status}
                                                    label={row.status_label}
                                                    variant="plain"
                                                />
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
