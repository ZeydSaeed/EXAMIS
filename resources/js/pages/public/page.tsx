import { Head } from '@inertiajs/react';
import { useTrans } from '@/hooks/use-trans';
import { formatDate } from '@/lib/format';

type Props = {
    page: {
        slug: string;
        title: string;
        body: string;
        updated_at: string | null;
    };
};

/**
 * Managed content is plain text; React escapes it, and blank lines become
 * paragraphs. No HTML from the database is ever rendered.
 */
export default function ContentPage({ page }: Props) {
    const { t, locale } = useTrans();
    const paragraphs = page.body
        .split(/\n{2,}/)
        .filter((paragraph) => paragraph.trim() !== '');

    return (
        <>
            <Head title={page.title} />
            <article className="mx-auto w-full max-w-3xl px-4 py-10">
                <h1 className="mb-6 text-2xl font-bold sm:text-3xl">
                    {page.title}
                </h1>
                <div className="space-y-4 leading-relaxed">
                    {paragraphs.map((paragraph, index) => (
                        <p key={index} className="whitespace-pre-line">
                            {paragraph}
                        </p>
                    ))}
                </div>
                {page.updated_at && (
                    <p className="mt-10 text-xs text-muted-foreground">
                        {t('public.last_updated', {
                            date: formatDate(page.updated_at, locale),
                        })}
                    </p>
                )}
            </article>
        </>
    );
}
