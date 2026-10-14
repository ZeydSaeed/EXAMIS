import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { ReferenceOption } from '@/types';

type PeriodData = {
    id?: number;
    code: string;
    name_ar: string;
    name_en: string;
    academic_year: string;
    status: string;
    opens_at: string;
    closes_at: string;
    eligible_branch_codes: string[];
    eligible_category_codes: string[];
    instructions_ar: string | null;
    instructions_en: string | null;
    documents: { document_type_id: number; is_required: boolean }[];
};

type Props = {
    period: PeriodData | null;
    statuses: string[];
    branches: ReferenceOption[];
    categories: ReferenceOption[];
    documentTypes: { id: number; code: string; name: string }[];
};

export default function PeriodForm({
    period,
    statuses,
    branches,
    categories,
    documentTypes,
}: Props) {
    const { t, locale } = useTrans();
    const isEditing = period !== null;

    const form = useForm<Omit<PeriodData, 'id'>>({
        code: period?.code ?? '',
        name_ar: period?.name_ar ?? '',
        name_en: period?.name_en ?? '',
        academic_year: period?.academic_year ?? '',
        status: period?.status ?? 'draft',
        opens_at: period?.opens_at ?? '',
        closes_at: period?.closes_at ?? '',
        eligible_branch_codes: period?.eligible_branch_codes ?? [],
        eligible_category_codes: period?.eligible_category_codes ?? [],
        instructions_ar: period?.instructions_ar ?? '',
        instructions_en: period?.instructions_en ?? '',
        documents: period?.documents ?? [],
    });

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.periods'), href: admin.periods.index() },
            {
                title: isEditing ? period.code : t('periods.create'),
                href: isEditing
                    ? admin.periods.edit(period.id ?? 0)
                    : admin.periods.create(),
            },
        ],
    });

    const toggleCode = (
        key: 'eligible_branch_codes' | 'eligible_category_codes',
        code: string,
        checked: boolean,
    ) => {
        const current = form.data[key];
        form.setData(
            key,
            checked
                ? [...current, code]
                : current.filter((value) => value !== code),
        );
    };

    const requirementFor = (id: number) =>
        form.data.documents.find((row) => row.document_type_id === id);

    const setRequirement = (
        id: number,
        state: 'none' | 'optional' | 'required',
    ) => {
        const others = form.data.documents.filter(
            (row) => row.document_type_id !== id,
        );
        form.setData(
            'documents',
            state === 'none'
                ? others
                : [
                      ...others,
                      {
                          document_type_id: id,
                          is_required: state === 'required',
                      },
                  ],
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isEditing) {
            form.put(admin.periods.update.url(period.id ?? 0));
        } else {
            form.post(admin.periods.store.url());
        }
    };

    const optionName = (option: ReferenceOption) =>
        locale === 'en' ? option.name_en : option.name_ar;

    return (
        <>
            <Head title={isEditing ? period.code : t('periods.create')} />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={isEditing ? t('periods.edit') : t('periods.create')}
                />

                <form onSubmit={submit} className="space-y-6">
                    <section className="grid gap-5 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                        <Field
                            id="code"
                            label={t('periods.code')}
                            hint={t('periods.code_hint')}
                            error={form.errors.code}
                            required
                        >
                            <Input
                                id="code"
                                dir="ltr"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="academic_year"
                            label={t('periods.academic_year')}
                            error={form.errors.academic_year}
                            required
                        >
                            <Input
                                id="academic_year"
                                dir="ltr"
                                placeholder="2026-2027"
                                value={form.data.academic_year}
                                onChange={(event) =>
                                    form.setData(
                                        'academic_year',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="name_ar"
                            label={t('periods.name_ar')}
                            error={form.errors.name_ar}
                            required
                        >
                            <Input
                                id="name_ar"
                                dir="rtl"
                                value={form.data.name_ar}
                                onChange={(event) =>
                                    form.setData('name_ar', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="name_en"
                            label={t('periods.name_en')}
                            error={form.errors.name_en}
                            required
                        >
                            <Input
                                id="name_en"
                                dir="ltr"
                                value={form.data.name_en}
                                onChange={(event) =>
                                    form.setData('name_en', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="opens_at"
                            label={t('periods.opens_at')}
                            error={form.errors.opens_at}
                            required
                        >
                            <Input
                                id="opens_at"
                                type="datetime-local"
                                value={form.data.opens_at}
                                onChange={(event) =>
                                    form.setData('opens_at', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="closes_at"
                            label={t('periods.closes_at')}
                            error={form.errors.closes_at}
                            required
                        >
                            <Input
                                id="closes_at"
                                type="datetime-local"
                                value={form.data.closes_at}
                                onChange={(event) =>
                                    form.setData(
                                        'closes_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="period-status"
                            label={t('applications.status')}
                            error={form.errors.status}
                            required
                        >
                            <select
                                id="period-status"
                                className={selectClassName}
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {t(`periods.status.${status}`)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </section>

                    <section className="grid gap-5 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t('periods.eligible_branches')}
                            </legend>
                            <p className="text-xs text-muted-foreground">
                                {t('periods.eligible_hint')}
                            </p>
                            {branches.map((branch) => (
                                <div
                                    key={branch.code}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`branch-${branch.code}`}
                                        checked={form.data.eligible_branch_codes.includes(
                                            branch.code,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleCode(
                                                'eligible_branch_codes',
                                                branch.code,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`branch-${branch.code}`}
                                        className="font-normal"
                                    >
                                        {optionName(branch)}
                                    </Label>
                                </div>
                            ))}
                        </fieldset>
                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t('periods.eligible_categories')}
                            </legend>
                            <p className="text-xs text-muted-foreground">
                                {t('periods.eligible_hint')}
                            </p>
                            {categories.map((category) => (
                                <div
                                    key={category.code}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`category-${category.code}`}
                                        checked={form.data.eligible_category_codes.includes(
                                            category.code,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleCode(
                                                'eligible_category_codes',
                                                category.code,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`category-${category.code}`}
                                        className="font-normal"
                                    >
                                        {optionName(category)}
                                    </Label>
                                </div>
                            ))}
                        </fieldset>
                    </section>

                    <section className="space-y-3 rounded-xl border bg-card p-4 sm:p-6">
                        <h2 className="font-semibold">
                            {t('periods.documents')}
                        </h2>
                        <div className="divide-y">
                            {documentTypes.map((type) => {
                                const requirement = requirementFor(type.id);
                                const value =
                                    requirement === undefined
                                        ? 'none'
                                        : requirement.is_required
                                          ? 'required'
                                          : 'optional';

                                return (
                                    <div
                                        key={type.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <Label
                                            htmlFor={`doc-${type.id}`}
                                            className="font-normal"
                                        >
                                            {type.name}
                                        </Label>
                                        <select
                                            id={`doc-${type.id}`}
                                            className={`${selectClassName} w-40`}
                                            value={value}
                                            onChange={(event) =>
                                                setRequirement(
                                                    type.id,
                                                    event.target.value as
                                                        | 'none'
                                                        | 'optional'
                                                        | 'required',
                                                )
                                            }
                                        >
                                            <option value="none">
                                                {t('periods.doc_not_requested')}
                                            </option>
                                            <option value="optional">
                                                {t('common.optional')}
                                            </option>
                                            <option value="required">
                                                {t('common.required')}
                                            </option>
                                        </select>
                                    </div>
                                );
                            })}
                        </div>
                        <InputError message={form.errors.documents} />
                    </section>

                    <section className="grid gap-5 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6">
                        <Field
                            id="instructions_ar"
                            label={t('periods.instructions_ar')}
                            error={form.errors.instructions_ar}
                        >
                            <textarea
                                id="instructions_ar"
                                dir="rtl"
                                className={textareaClassName}
                                rows={6}
                                value={form.data.instructions_ar ?? ''}
                                onChange={(event) =>
                                    form.setData(
                                        'instructions_ar',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="instructions_en"
                            label={t('periods.instructions_en')}
                            error={form.errors.instructions_en}
                        >
                            <textarea
                                id="instructions_en"
                                dir="ltr"
                                className={textareaClassName}
                                rows={6}
                                value={form.data.instructions_en ?? ''}
                                onChange={(event) =>
                                    form.setData(
                                        'instructions_en',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </section>

                    <div className="flex justify-end">
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
