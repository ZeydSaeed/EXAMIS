import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import {
    HeaderFilter,
    headerButtonClassName,
    ListHeader,
} from '@/components/examis/list-header';
import { useTrans } from '@/hooks/use-trans';
import { formatPercent } from '@/lib/format';
import admin from '@/routes/admin';

type Row = { code: string | null; count: number; label: string | null };

type Props = {
    periodId: number | null;
    periods: { id: number; name: string }[];
    byStatus: Row[];
    bySpecialization: Row[];
    byBranch: Row[];
    drafts: number;
};

function Breakdown({ title, rows }: { title: string; rows: Row[] }) {
    const { t, locale } = useTrans();
    const total = rows.reduce((sum, row) => sum + row.count, 0);

    return (
        <DataTableFrame className="examis-data-table--plain max-h-full">
            <table className="w-full [&_td]:whitespace-nowrap">
                <caption className="sr-only">{title}</caption>
                <thead>
                    <tr>
                        <th scope="col" className="w-12 text-center!">
                            #
                        </th>
                        <th scope="col">{title}</th>
                        <th scope="col" className="text-center!">
                            {t('reports.count')}
                        </th>
                        <th scope="col" className="w-2/5">
                            {t('reports.share')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td
                                colSpan={4}
                                className="text-center! text-muted-foreground"
                            >
                                {t('admin.no_results')}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row, index) => (
                            <tr key={row.code ?? 'none'}>
                                <td className="text-center! text-muted-foreground tabular-nums">
                                    {index + 1}
                                </td>
                                <td>{row.label ?? t('reports.unspecified')}</td>
                                <td className="text-center! font-medium tabular-nums">
                                    {row.count}
                                </td>
                                <td>
                                    <div className="flex items-center gap-2">
                                        <div
                                            className="h-2 flex-1 rounded-full bg-muted"
                                            aria-hidden
                                        >
                                            <div
                                                className="h-2 rounded-full bg-primary dark:bg-(--examis-pearl)"
                                                style={{
                                                    width: `${total ? (row.count / total) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="w-12 text-end text-xs text-muted-foreground tabular-nums">
                                            {formatPercent(
                                                total ? row.count / total : 0,
                                                locale,
                                            )}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </DataTableFrame>
    );
}

export default function Reports({
    periodId,
    periods,
    byStatus,
    bySpecialization,
    byBranch,
    drafts,
}: Props) {
    const { t } = useTrans();
    const { auth } = usePage().props;
    const canExport = auth.user.permissions.includes('applications.view_all');

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.reports'), href: admin.reports.index() }],
    });

    return (
        <>
            <Head title={t('nav.reports')} />

            <ListHeader
                actions={
                    canExport &&
                    periodId && (
                        <a
                            href={admin.reports.export.url(periodId)}
                            className={headerButtonClassName}
                            aria-label={t('reports.export_csv')}
                            title={t('reports.export_csv')}
                        >
                            <Download aria-hidden />
                        </a>
                    )
                }
            >
                <HeaderFilter
                    label={t('applications.exam_period')}
                    value={String(periodId ?? '')}
                    options={periods.map((period) => ({
                        value: String(period.id),
                        label: period.name,
                    }))}
                    onChange={(period) =>
                        router.get(
                            admin.reports.index.url(),
                            { period },
                            { preserveState: true, replace: true },
                        )
                    }
                    required
                    className="max-w-md"
                />
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.reports')}</h1>

                <div className="grid content-start items-start gap-4 lg:grid-cols-3">
                    <Breakdown title={t('admin.by_status')} rows={byStatus} />
                    <Breakdown
                        title={t('reports.by_specialization')}
                        rows={bySpecialization}
                    />
                    <Breakdown title={t('reports.by_branch')} rows={byBranch} />
                </div>

                <p className="text-sm text-muted-foreground">
                    {t('reports.drafts', { drafts })}
                </p>
            </div>
        </>
    );
}
