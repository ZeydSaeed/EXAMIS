import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Database, Plus } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight } from '@/components/examis/highlight';
import {
    headerButtonClassName,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import { useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';
import type { DatabaseColumn, DatabaseTable } from './types';

type Row = {
    key: string;
    values: Record<string, string | null>;
};

type Props = {
    table: DatabaseTable;
    columns: DatabaseColumn[];
    rows: Paginated<Row>;
    filters: { q?: string; per_page?: string | number };
};

export default function DatabaseTablePage({
    table,
    columns,
    rows,
    filters,
}: Props) {
    const { t } = useTrans();
    const { auth, status } = usePage<{ status?: string | null }>().props;
    const canManage = auth.user.permissions.includes('database.manage');
    const { search, setSearch, urlFor, visit, tableArea } = useListQuery({
        url: (options) => admin.database.table.url(table.name, options),
        filters,
        paginator: rows,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.database'), href: admin.database.index() },
            { title: table.label, href: admin.database.table(table.name) },
        ],
    });

    return (
        <>
            <Head title={table.label} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim(), page: 1 })}
                placeholder={t('db.search_placeholder')}
                actions={
                    canManage && table.insertable ? (
                        <Link
                            href={admin.database.create(table.name)}
                            className={headerButtonClassName}
                            aria-label={t('db.add_row')}
                            title={t('db.add_row')}
                        >
                            <Plus aria-hidden />
                        </Link>
                    ) : undefined
                }
            />

            <div className="mx-auto flex min-h-0 w-full flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="text-lg font-semibold">
                    {table.label}{' '}
                    <span
                        dir="ltr"
                        className="font-mono text-sm font-normal text-muted-foreground"
                    >
                        {table.name} · {rows.total}
                    </span>
                </h1>

                {status && (
                    <p
                        role="status"
                        className="rounded-md border border-emerald-600/30 bg-emerald-600/10 px-3 py-2 text-sm"
                    >
                        {status}
                    </p>
                )}

                <div ref={tableArea} className="min-h-0 flex-1">
                    {rows.data.length === 0 ? (
                        <EmptyState
                            icon={Database}
                            title={
                                filters.q
                                    ? t('admin.no_results')
                                    : t('db.empty')
                            }
                        />
                    ) : (
                        <div dir="ltr" className="h-full">
                            <DataTableFrame className="examis-data-table--plain max-h-full">
                                <table className="[&_td]:max-w-72 [&_td]:truncate [&_td]:whitespace-nowrap">
                                    <caption className="sr-only">
                                        {table.label}
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" className="w-16">
                                                <span className="sr-only">
                                                    {t('db.open')}
                                                </span>
                                            </th>
                                            {columns.map((column) => (
                                                <th
                                                    key={column.name}
                                                    scope="col"
                                                    className="font-mono text-xs"
                                                >
                                                    {column.name}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.data.map((row) => (
                                            <tr key={row.key}>
                                                <td>
                                                    <Link
                                                        href={admin.database.edit(
                                                            table.name,
                                                            {
                                                                query: {
                                                                    key: row.key,
                                                                },
                                                            },
                                                        )}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {t('db.open')}
                                                    </Link>
                                                </td>
                                                {columns.map((column) => {
                                                    const value =
                                                        row.values[column.name];

                                                    return (
                                                        <td
                                                            key={column.name}
                                                            title={value ?? ''}
                                                        >
                                                            {value === null ? (
                                                                <span className="text-muted-foreground/60 italic">
                                                                    NULL
                                                                </span>
                                                            ) : (
                                                                <Highlight
                                                                    text={value}
                                                                    query={
                                                                        filters.q
                                                                    }
                                                                />
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </DataTableFrame>
                        </div>
                    )}
                </div>

                <NumberedPagination
                    current={rows.current_page}
                    last={rows.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}
