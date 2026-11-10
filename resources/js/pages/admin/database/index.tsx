import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

type TableRow = {
    name: string;
    label: string;
    rows: number;
    writable: boolean;
};

export default function DatabaseIndex({ tables }: { tables: TableRow[] }) {
    const { t } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.database'), href: admin.database.index() },
        ],
    });

    return (
        <>
            <Head title={t('nav.database')} />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={t('nav.database')}
                    description={t('db.tables_hint')}
                />

                <DataTableFrame className="examis-data-table--plain">
                    <table className="min-w-[32rem]">
                        <caption className="sr-only">
                            {t('nav.database')}
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">{t('db.table')}</th>
                                <th scope="col" className="text-center!">
                                    {t('db.rows')}
                                </th>
                                <th scope="col" className="text-center!">
                                    {t('db.editable')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {tables.map((table) => (
                                <tr key={table.name}>
                                    <td>
                                        <Link
                                            href={admin.database.table(
                                                table.name,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {table.label}
                                        </Link>
                                        <span className="block font-mono text-xs text-muted-foreground">
                                            {table.name}
                                        </span>
                                    </td>
                                    <td className="text-center! tabular-nums">
                                        {table.rows}
                                    </td>
                                    <td className="text-center! text-muted-foreground">
                                        {table.writable ? (
                                            t('db.editable')
                                        ) : (
                                            <span className="inline-flex items-center gap-1">
                                                <Lock
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                {t('db.read_only')}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableFrame>
            </PageContainer>
        </>
    );
}
