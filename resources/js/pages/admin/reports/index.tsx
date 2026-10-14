import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { selectClassName } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

type Row = { code: string | null; count: number; label: string | null };

type Props = {
    periodId: number | null;
    periods: { id: number; name: string }[];
    byStatus: Row[];
    byGovernorate: Row[];
    byBranch: Row[];
    drafts: number;
};

function Breakdown({ title, rows }: { title: string; rows: Row[] }) {
    const { t } = useTrans();
    const total = rows.reduce((sum, row) => sum + row.count, 0);

    return (
        <section className="rounded-xl border bg-card p-4" aria-label={title}>
            <h2 className="mb-3 font-semibold">{title}</h2>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('admin.no_results')}
                </p>
            ) : (
                <table className="w-full text-sm">
                    <tbody className="divide-y">
                        {rows.map((row) => (
                            <tr key={row.code ?? 'none'}>
                                <th
                                    scope="row"
                                    className="py-2 text-start font-normal"
                                >
                                    {row.label ?? t('reports.unspecified')}
                                </th>
                                <td className="w-1/2 py-2">
                                    <div className="flex items-center gap-2">
                                        <div
                                            className="h-2 flex-1 rounded bg-muted"
                                            aria-hidden
                                        >
                                            <div
                                                className="h-2 rounded bg-primary"
                                                style={{
                                                    width: `${total ? (row.count / total) * 100 : 0}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="w-12 text-end font-medium tabular-nums">
                                            {row.count}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}

export default function Reports({
    periodId,
    periods,
    byStatus,
    byGovernorate,
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
            <PageContainer>
                <PageHeader
                    title={t('nav.reports')}
                    description={t('reports.description', { drafts })}
                    actions={
                        canExport &&
                        periodId && (
                            <Button variant="outline" asChild>
                                <a href={admin.reports.export.url(periodId)}>
                                    <Download className="size-4" aria-hidden />
                                    {t('reports.export_csv')}
                                </a>
                            </Button>
                        )
                    }
                />

                <div className="grid max-w-sm gap-1.5">
                    <Label htmlFor="report-period">
                        {t('applications.exam_period')}
                    </Label>
                    <select
                        id="report-period"
                        className={selectClassName}
                        value={periodId ?? ''}
                        onChange={(event) =>
                            router.get(
                                admin.reports.index.url(),
                                { period: event.target.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    >
                        {periods.map((period) => (
                            <option key={period.id} value={period.id}>
                                {period.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Breakdown title={t('admin.by_status')} rows={byStatus} />
                    <Breakdown
                        title={t('reports.by_governorate')}
                        rows={byGovernorate}
                    />
                    <Breakdown title={t('reports.by_branch')} rows={byBranch} />
                </div>
            </PageContainer>
        </>
    );
}
