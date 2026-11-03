import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Field, textareaClassName } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';

type Props = {
    page: {
        slug: string;
        title_ar: string;
        title_en: string;
        body_ar: string;
        body_en: string;
        is_published: boolean;
    };
    /** Set for the printed form's texts, which are never published. */
    receipt: {
        kind: 'list' | 'text' | 'letter';
        placeholders: string[];
    } | null;
};

export default function ContentEdit({ page, receipt }: Props) {
    const { t } = useTrans();
    const form = useForm({
        title_ar: page.title_ar,
        title_en: page.title_en,
        body_ar: page.body_ar,
        body_en: page.body_en,
        is_published: page.is_published,
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.content'), href: admin.content.index() },
            { title: page.slug, href: admin.content.edit(page.slug) },
        ],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(admin.content.update.url(page.slug));
    };

    return (
        <>
            <Head title={page.title_ar} />
            <PageContainer className="max-w-5xl">
                <PageHeader
                    title={t('content.edit')}
                    description={
                        receipt
                            ? t(`content.receipt_${receipt.kind}_hint`)
                            : t('content.plain_text_hint')
                    }
                />
                {receipt && receipt.placeholders.length > 0 && (
                    <p
                        className="flex flex-wrap gap-2 text-sm"
                        data-test="receipt-placeholders"
                    >
                        {receipt.placeholders.map((placeholder) => (
                            <code
                                key={placeholder}
                                className="rounded bg-muted px-1.5 py-0.5"
                            >
                                {`{${placeholder}}`}
                            </code>
                        ))}
                    </p>
                )}
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <section
                            className="space-y-4 rounded-xl border bg-card p-4"
                            lang="ar"
                            dir="rtl"
                        >
                            <Field
                                id="title_ar"
                                label={t('content.title_ar')}
                                error={form.errors.title_ar}
                                required
                            >
                                <Input
                                    id="title_ar"
                                    value={form.data.title_ar}
                                    onChange={(event) =>
                                        form.setData(
                                            'title_ar',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="body_ar"
                                label={t('content.body_ar')}
                                error={form.errors.body_ar}
                                required
                            >
                                <textarea
                                    id="body_ar"
                                    rows={16}
                                    className={textareaClassName}
                                    value={form.data.body_ar}
                                    onChange={(event) =>
                                        form.setData(
                                            'body_ar',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </section>
                        <section
                            className="space-y-4 rounded-xl border bg-card p-4"
                            lang="en"
                            dir="ltr"
                        >
                            <Field
                                id="title_en"
                                label={t('content.title_en')}
                                error={form.errors.title_en}
                                required
                            >
                                <Input
                                    id="title_en"
                                    value={form.data.title_en}
                                    onChange={(event) =>
                                        form.setData(
                                            'title_en',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="body_en"
                                label={t('content.body_en')}
                                error={form.errors.body_en}
                                required
                            >
                                <textarea
                                    id="body_en"
                                    rows={16}
                                    className={textareaClassName}
                                    value={form.data.body_en}
                                    onChange={(event) =>
                                        form.setData(
                                            'body_en',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </section>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        {/* The printed form's texts are never published. */}
                        {receipt ? (
                            <span />
                        ) : (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_published"
                                    checked={form.data.is_published}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_published',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_published">
                                    {t('content.published')}
                                </Label>
                            </div>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </PageContainer>
        </>
    );
}
