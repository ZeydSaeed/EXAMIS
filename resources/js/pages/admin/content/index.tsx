import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
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

export default function ContentIndex({ pages }: Props) {
    const { t, locale } = useTrans();

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.content'), href: admin.content.index() }],
    });

    return (
        <>
            <Head title={t('nav.content')} />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={t('nav.content')}
                    description={t('content.description')}
                />
                <ul className="divide-y rounded-xl border bg-card">
                    {pages.map((page) => (
                        <li
                            key={page.slug}
                            className="flex flex-wrap items-center justify-between gap-2 p-4"
                        >
                            <div>
                                <Link
                                    href={admin.content.edit(page.slug)}
                                    className="font-medium text-primary hover:underline"
                                >
                                    {page.title}
                                </Link>
                                <p
                                    className="text-xs text-muted-foreground"
                                    dir="ltr"
                                >
                                    /pages/{page.slug}
                                </p>
                            </div>
                            <span className="text-sm text-muted-foreground">
                                {page.is_published
                                    ? t('content.published')
                                    : t('content.hidden')}{' '}
                                · {formatDateTime(page.updated_at, locale)}
                            </span>
                        </li>
                    ))}
                </ul>
            </PageContainer>
        </>
    );
}
