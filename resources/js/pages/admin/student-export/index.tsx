import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Download, Inbox } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import {
    HeaderDateFilter,
    HeaderFilter,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { compactQuery, useListQuery } from '@/hooks/use-list-query';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Row = { uuid: string; cells: (string | null)[] };

type Filters = {
    from?: string;
    to?: string;
    status?: string;
    per_page?: string | number;
};

type Props = {
    columns: string[];
    students: Paginated<Row>;
    filters: Filters;
    statuses: { value: string; label: string }[];
};

/** Long enough to finish typing a year by hand before the list reloads. */
const DATE_DELAY_MS = 500;

export default function StudentExport({
    columns,
    students,
    filters,
    statuses,
}: Props) {
    const { t } = useTrans();
    const { errors } = usePage().props;
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const { urlFor, visit, tableArea } = useListQuery({
        url: admin.studentExport.index.url,
        filters,
        paginator: students,
    });

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('nav.student_export'),
                href: admin.studentExport.index(),
            },
        ],
    });

    useEffect(() => {
        if (from === (filters.from ?? '') && to === (filters.to ?? '')) {
            return;
        }

        const timer = window.setTimeout(
            () => visit({ from, to, page: 1 }),
            DATE_DELAY_MS,
        );

        return () => window.clearTimeout(timer);
    }, [from, to, filters.from, filters.to, visit]);

    return (
        <>
            <Head title={t('nav.student_export')} />

            <ListHeader>
                <HeaderFilter
                    label={t('student_export.status')}
                    value={filters.status ?? ''}
                    options={statuses}
                    onChange={(status) => visit({ status, page: 1 })}
                />
                <HeaderDateFilter
                    label={t('student_export.from')}
                    value={from}
                    onChange={setFrom}
                />
                <HeaderDateFilter
                    label={t('student_export.to')}
                    value={to}
                    onChange={setTo}
                />
            </ListHeader>

            <div className="flex min-h-0 w-full flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.student_export')}</h1>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {t('student_export.total', { count: students.total })}
                    </p>
                    {/* Exports exactly what the list shows: every filter goes along. */}
                    <Button asChild size="sm">
                        <a
                            href={admin.studentExport.download.url({
                                query: compactQuery({
                                    from: filters.from,
                                    to: filters.to,
                                    status: filters.status,
                                }),
                            })}
                            data-test="export"
                        >
                            <Download aria-hidden />
                            {t('student_export.export')}
                        </a>
                    </Button>
                </div>

                <InputError
                    message={errors.to ?? errors.from ?? errors.status}
                />

                <div ref={tableArea} className="min-h-0 flex-1">
                    {students.data.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="[&_td]:whitespace-nowrap [&_th]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.student_export')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-12 text-center!"
                                        >
                                            {t('admin.serial')}
                                        </th>
                                        {columns.map((column) => (
                                            <th scope="col" key={column}>
                                                {column}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {students.data.map((row, index) => (
                                        <tr key={row.uuid}>
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {(students.from ?? 1) + index}
                                            </td>
                                            {row.cells.map((cell, column) => (
                                                <td key={column}>{cell}</td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <NumberedPagination
                    current={students.current_page}
                    last={students.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}
