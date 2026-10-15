import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Highlight, matchesQuery } from '@/components/examis/highlight';
import { HeaderFilter, ListHeader } from '@/components/examis/list-header';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';

type Props = {
    pages: {
        slug: string;
        title: string;
        is_published: boolean;
        updated_at: string | null;
    }[];
};

/**
 * The portal has a handful of fixed pages, so search and the filter run in
 * the browser instead of round-tripping to the server.
 */
export default function ContentIndex({ pages }: Props) {
    const { t, locale } = useTrans();
    const [search, setSearch] = useState('');
    const [visibility, setVisibility] = useState('');

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.content'), href: admin.content.index() }],
    });

    const rows = pages.filter(
        (page) =>
            (visibility === '' ||
                page.is_published === (visibility === 'published')) &&
            (matchesQuery(page.title, search) ||
                matchesQuery(page.slug, search)),
    );

    return (
        <>
            <Head title={t('nav.content')} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                placeholder={t('content.search_placeholder')}
            >
                <HeaderFilter
                    label={t('applications.status')}
                    value={visibility}
                    options={[
                        { value: 'published', label: t('content.published') },
                        { value: 'hidden', label: t('content.hidden') },
                    ]}
                    onChange={setVisibility}
                />
            </ListHeader>

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.content')}</h1>

                <div className="min-h-0 flex-1">
                    {rows.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[40rem] [&_td]:whitespace-nowrap">
                                <caption className="sr-only">
                                    {t('nav.content')}
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
                                            {t('content.page_title')}
                                        </th>
                                        <th scope="col">
                                            {t('content.address')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="text-center!"
                                        >
                                            {t('applications.status')}
                                        </th>
                                        <th scope="col">
                                            {t('content.updated_at')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((page, index) => (
                                        <tr key={page.slug}>
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </td>
                                            <td>
                                                <Link
                                                    href={admin.content.edit(
                                                        page.slug,
                                                    )}
                                                    className="hover:underline"
                                                >
                                                    <Highlight
                                                        text={page.title}
                                                        query={search}
                                                    />
                                                </Link>
                                            </td>
                                            <td
                                                className="text-muted-foreground"
                                                dir="ltr"
                                            >
                                                /pages/
                                                <Highlight
                                                    text={page.slug}
                                                    query={search}
                                                />
                                            </td>
                                            <td className="text-center!">
                                                {page.is_published
                                                    ? t('content.published')
                                                    : t('content.hidden')}
                                            </td>
                                            <td className="text-muted-foreground">
                                                {formatDateTime(
                                                    page.updated_at,
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
            </div>
        </>
    );
}
