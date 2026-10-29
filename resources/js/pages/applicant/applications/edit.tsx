import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePoll,
} from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Plus,
    RefreshCw,
    Save,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DocumentUploader } from '@/components/examis/document-uploader';
import {
    describedBy,
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { StatusBadge } from '@/components/examis/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { applicationSummary } from '@/lib/application-summary';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import applications from '@/routes/applications';
import type {
    Application,
    ApplicationForm,
    DocumentRequirement,
    IssuedDocument,
    MissingRequirements,
    ReferenceOption,
} from '@/types';

type Props = {
    application: Application;
    documentTypes: DocumentRequirement[];
    missing: MissingRequirements;
    options: {
        branches: ReferenceOption[];
        /** Specializations; `parent_code` is the branch. */
        categories: ReferenceOption[];
    };
    step: number;
};

type FormData = ApplicationForm & { lock_version: number };

type TextField = Exclude<keyof ApplicationForm, 'issued_documents'>;

/** Rows of "documents issued for study purposes" on the official form. */
const MAX_ISSUED_DOCUMENTS = 4;

const STEPS = [
    'personal',
    'contact',
    'education',
    'documents',
    'review',
] as const;

const STEP_FIELDS: Record<(typeof STEPS)[number], (keyof ApplicationForm)[]> = {
    personal: [
        'student_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'mother_first_name',
        'mother_father_name',
        'birth_date',
        'gender',
        'national_id_number',
    ],
    contact: ['district', 'mahalla', 'alley', 'house_number', 'phone'],
    education: [
        'school_name',
        'intermediate_school_name',
        'last_grade',
        'failed_years',
        'left_years',
        'branch_code',
        'category_code',
        'other_info',
        'issued_documents',
    ],
    documents: [],
    review: [],
};

export default function EditApplication({
    application,
    documentTypes,
    missing,
    options,
    step: initialStep,
}: Props) {
    const { t, locale } = useTrans();
    const [step, setStep] = useState(
        Math.min(Math.max(initialStep, 1), STEPS.length) - 1,
    );
    const [submissionUnconfirmed, setSubmissionUnconfirmed] = useState(false);

    const form = useForm<FormData>({
        ...application.form,
        lock_version: application.lock_version,
    });
    const submission = useForm({ declaration: false });

    const hasPendingDocuments = (application.documents ?? []).some(
        (document) => document.status === 'pending',
    );
    usePoll(
        4000,
        { only: ['application', 'missing'] },
        { autoStart: hasPendingDocuments },
    );

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.my_applications'), href: dashboard() },
            {
                title:
                    application.exam_period?.name ?? t('applicant.application'),
                href: applications.edit(application.uuid),
            },
        ],
    });

    const optionName = (option: ReferenceOption) =>
        locale === 'en' ? option.name_en : option.name_ar;
    const currentStep = STEPS[step];
    const errors = form.errors as Record<string, string | undefined>;
    const stepHasErrors = (index: number) =>
        STEP_FIELDS[STEPS[index]].some((field) =>
            Object.keys(errors).some(
                (key) =>
                    errors[key] &&
                    (key === field || key.startsWith(`${field}.`)),
            ),
        );
    const isCorrection = application.status === 'requires_correction';

    const save = (afterSave?: () => void) => {
        form.put(applications.update.url(application.uuid), {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = (page.props as unknown as Props).application;
                form.setDefaults({
                    ...updated.form,
                    lock_version: updated.lock_version,
                });
                form.setData('lock_version', updated.lock_version);
                afterSave?.();
            },
        });
    };

    const goTo = (index: number) => {
        setStep(index);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const handleNext = (event: FormEvent) => {
        event.preventDefault();

        if (STEP_FIELDS[currentStep].length === 0) {
            goTo(step + 1);

            return;
        }

        save(() => goTo(step + 1));
    };

    const submit = () => {
        setSubmissionUnconfirmed(false);
        submission.post(applications.submit.url(application.uuid), {
            onNetworkError: () => {
                setSubmissionUnconfirmed(true);

                return false;
            },
        });
    };

    const input = (
        field: TextField,
        props: Partial<React.ComponentProps<typeof Input>> = {},
    ) => (
        <Input
            id={field}
            name={field}
            value={form.data[field] ?? ''}
            onChange={(event) =>
                form.setData(
                    field,
                    event.target.value === ''
                        ? null
                        : (event.target.value as never),
                )
            }
            aria-invalid={form.errors[field] ? true : undefined}
            aria-describedby={describedBy(field, undefined, form.errors[field])}
            {...props}
        />
    );

    const select = (
        field: TextField,
        choices: { value: string; label: string }[],
        placeholder = t('common.choose'),
    ) => (
        <select
            id={field}
            name={field}
            className={selectClassName}
            value={form.data[field] ?? ''}
            onChange={(event) =>
                form.setData(
                    field,
                    event.target.value === ''
                        ? null
                        : (event.target.value as never),
                )
            }
            aria-invalid={form.errors[field] ? true : undefined}
            aria-describedby={describedBy(field, undefined, form.errors[field])}
        >
            <option value="">{placeholder}</option>
            {choices.map((choice) => (
                <option key={choice.value} value={choice.value}>
                    {choice.label}
                </option>
            ))}
        </select>
    );

    const specializations = options.categories.filter(
        (item) => item.parent_code === form.data.branch_code,
    );
    const chooseBranch = (branch: string | null) =>
        form.setData((data) => ({
            ...data,
            branch_code: branch,
            category_code: options.categories.some(
                (item) =>
                    item.code === data.category_code &&
                    item.parent_code === branch,
            )
                ? data.category_code
                : null,
        }));
    const folderColorKey = `fields.folder_colors.${form.data.branch_code}`;
    const folderColorHint =
        form.data.branch_code && t(folderColorKey) !== folderColorKey
            ? t('fields.folder_color', { color: t(folderColorKey) })
            : undefined;
    const issuedDocuments = form.data.issued_documents ?? [];
    const setIssuedDocuments = (rows: IssuedDocument[]) =>
        form.setData('issued_documents', rows);
    const documentsByType = Object.fromEntries(
        (application.documents ?? []).map((document) => [
            document.document_type,
            document,
        ]),
    );
    const isReady =
        missing.fields.length === 0 && missing.documents.length === 0;
    const serverErrors = submission.errors as Record<
        string,
        string | undefined
    >;
    const submissionError =
        serverErrors.application ??
        serverErrors.exam_period ??
        serverErrors.branch_code ??
        serverErrors.category_code ??
        serverErrors.status;

    return (
        <>
            <Head title={t('applicant.application_form')} />
            <PageContainer className="max-w-4xl">
                <PageHeader
                    title={
                        application.exam_period?.name ??
                        t('applicant.application_form')
                    }
                    description={
                        application.exam_period
                            ? t('home.closes_on', {
                                  date: formatDate(
                                      application.exam_period.closes_at,
                                      locale,
                                  ),
                              })
                            : undefined
                    }
                    actions={
                        <StatusBadge
                            status={application.status}
                            label={application.status_label}
                        />
                    }
                />

                {isCorrection && (
                    <Alert>
                        <AlertTriangle className="size-4" />
                        <AlertTitle>
                            {t('applicant.correction_requested')}
                        </AlertTitle>
                        <AlertDescription>
                            {t('applicant.correction_requested_text')}{' '}
                            <Link
                                href={applications.show(application.uuid)}
                                className="underline"
                            >
                                {t('applicant.view_reviewer_notes')}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                {form.errors.lock_version && (
                    <Alert variant="destructive" data-test="stale-version">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{t('applicant.stale_title')}</AlertTitle>
                        <AlertDescription className="flex flex-col items-start gap-2">
                            {form.errors.lock_version}
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.reload()}
                            >
                                <RefreshCw className="size-4" aria-hidden />
                                {t('common.reload')}
                            </Button>
                        </AlertDescription>
                    </Alert>
                )}

                <nav
                    aria-label={t('applicant.form_steps')}
                    className="no-print"
                >
                    <ol className="grid grid-cols-5 gap-1 sm:gap-2">
                        {STEPS.map((name, index) => (
                            <li key={name}>
                                <button
                                    type="button"
                                    onClick={() => goTo(index)}
                                    aria-current={
                                        index === step ? 'step' : undefined
                                    }
                                    className={`flex w-full flex-col items-center gap-1 rounded-lg border px-1 py-2 text-xs transition-colors sm:flex-row sm:justify-center sm:gap-2 sm:text-sm ${
                                        index === step
                                            ? 'border-primary bg-primary/10 font-semibold text-primary'
                                            : 'hover:bg-muted'
                                    } ${stepHasErrors(index) ? 'border-destructive' : ''}`}
                                >
                                    <span className="flex size-6 items-center justify-center rounded-full bg-muted text-xs">
                                        {index + 1}
                                    </span>
                                    <span className="hidden sm:inline">
                                        {t(`applicant.steps.${name}`)}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ol>
                    <p className="mt-2 text-center text-sm font-medium sm:hidden">
                        {t(`applicant.steps.${currentStep}`)}
                    </p>
                </nav>

                {['personal', 'contact', 'education'].includes(currentStep) && (
                    <form
                        onSubmit={handleNext}
                        className="space-y-6 rounded-xl border bg-card p-4 sm:p-6"
                        noValidate
                    >
                        <h2 className="text-lg font-semibold">
                            {t(`applicant.steps.${currentStep}`)}
                        </h2>

                        {currentStep === 'personal' && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    {t('fields.full_name_ar_hint')}
                                </p>
                                <Field
                                    id="student_name"
                                    label={t('fields.student_name')}
                                    error={form.errors.student_name}
                                    required
                                >
                                    {input('student_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                        autoComplete: 'given-name',
                                    })}
                                </Field>
                                <Field
                                    id="father_name"
                                    label={t('fields.father_name')}
                                    error={form.errors.father_name}
                                    required
                                >
                                    {input('father_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                    })}
                                </Field>
                                <Field
                                    id="grandfather_name"
                                    label={t('fields.grandfather_name')}
                                    error={form.errors.grandfather_name}
                                    required
                                >
                                    {input('grandfather_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                    })}
                                </Field>
                                <Field
                                    id="great_grandfather_name"
                                    label={t('fields.great_grandfather_name')}
                                    error={form.errors.great_grandfather_name}
                                    required
                                >
                                    {input('great_grandfather_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                    })}
                                </Field>
                                <Field
                                    id="mother_first_name"
                                    label={t('fields.mother_first_name')}
                                    error={form.errors.mother_first_name}
                                    required
                                >
                                    {input('mother_first_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                    })}
                                </Field>
                                <Field
                                    id="mother_father_name"
                                    label={t('fields.mother_father_name')}
                                    error={form.errors.mother_father_name}
                                    required
                                >
                                    {input('mother_father_name', {
                                        lang: 'ar',
                                        dir: 'rtl',
                                    })}
                                </Field>
                                <Field
                                    id="birth_date"
                                    label={t('fields.birth_date')}
                                    error={form.errors.birth_date}
                                    required
                                >
                                    {input('birth_date', { type: 'date' })}
                                </Field>
                                <Field
                                    id="gender"
                                    label={t('fields.gender')}
                                    error={form.errors.gender}
                                    required
                                >
                                    {select('gender', [
                                        {
                                            value: 'male',
                                            label: t('fields.male'),
                                        },
                                        {
                                            value: 'female',
                                            label: t('fields.female'),
                                        },
                                    ])}
                                </Field>
                                <Field
                                    id="national_id_number"
                                    label={t('fields.national_id_number')}
                                    hint={t('fields.national_id_hint')}
                                    error={form.errors.national_id_number}
                                    required
                                    className="sm:col-span-2"
                                >
                                    {input('national_id_number', {
                                        dir: 'ltr',
                                        inputMode: 'numeric',
                                        autoComplete: 'off',
                                    })}
                                </Field>
                            </div>
                        )}

                        {currentStep === 'contact' && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    id="governorate"
                                    label={t('fields.governorate')}
                                    hint={t('fields.residence_hint')}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="governorate"
                                        value={t('fields.karbala')}
                                        readOnly
                                        aria-describedby="governorate-hint"
                                        className="bg-muted"
                                    />
                                </Field>
                                <Field
                                    id="district"
                                    label={t('fields.district')}
                                    error={form.errors.district}
                                    required
                                >
                                    {input('district')}
                                </Field>
                                <Field
                                    id="mahalla"
                                    label={t('fields.mahalla')}
                                    error={form.errors.mahalla}
                                    required
                                >
                                    {input('mahalla', {
                                        dir: 'ltr',
                                        inputMode: 'numeric',
                                    })}
                                </Field>
                                <Field
                                    id="alley"
                                    label={t('fields.alley')}
                                    error={form.errors.alley}
                                    required
                                >
                                    {input('alley', {
                                        dir: 'ltr',
                                        inputMode: 'numeric',
                                    })}
                                </Field>
                                <Field
                                    id="house_number"
                                    label={t('fields.house_number')}
                                    error={form.errors.house_number}
                                    required
                                >
                                    {input('house_number', {
                                        dir: 'ltr',
                                        inputMode: 'numeric',
                                    })}
                                </Field>
                                <Field
                                    id="phone"
                                    label={t('fields.phone')}
                                    hint={t('fields.phone_account_hint')}
                                    error={form.errors.phone}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="phone"
                                        type="tel"
                                        dir="ltr"
                                        value={form.data.phone ?? ''}
                                        readOnly
                                        aria-readonly
                                        aria-describedby="phone-hint"
                                        className="bg-muted text-start"
                                        data-test="phone-readonly"
                                    />
                                </Field>
                            </div>
                        )}

                        {currentStep === 'education' && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    id="school_name"
                                    label={t('fields.school_name')}
                                    error={form.errors.school_name}
                                    required
                                >
                                    {input('school_name')}
                                </Field>
                                <Field
                                    id="intermediate_school_name"
                                    label={t('fields.intermediate_school_name')}
                                    error={form.errors.intermediate_school_name}
                                    required
                                >
                                    {input('intermediate_school_name')}
                                </Field>
                                <Field
                                    id="last_grade"
                                    label={t('fields.last_grade')}
                                    hint={t('fields.last_grade_hint')}
                                    error={form.errors.last_grade}
                                    required
                                    className="sm:col-span-2"
                                >
                                    {input('last_grade')}
                                </Field>
                                <Field
                                    id="failed_years"
                                    label={t('fields.failed_years')}
                                    hint={t('fields.years_hint')}
                                    error={form.errors.failed_years}
                                    required
                                >
                                    {input('failed_years', {
                                        type: 'number',
                                        inputMode: 'numeric',
                                        dir: 'ltr',
                                        min: 0,
                                        max: 20,
                                    })}
                                </Field>
                                <Field
                                    id="left_years"
                                    label={t('fields.left_years')}
                                    hint={t('fields.years_hint')}
                                    error={form.errors.left_years}
                                    required
                                >
                                    {input('left_years', {
                                        type: 'number',
                                        inputMode: 'numeric',
                                        dir: 'ltr',
                                        min: 0,
                                        max: 20,
                                    })}
                                </Field>
                                <Field
                                    id="branch_code"
                                    label={t('fields.branch')}
                                    hint={folderColorHint}
                                    error={form.errors.branch_code}
                                    required
                                >
                                    <select
                                        id="branch_code"
                                        name="branch_code"
                                        className={selectClassName}
                                        value={form.data.branch_code ?? ''}
                                        onChange={(event) =>
                                            chooseBranch(
                                                event.target.value || null,
                                            )
                                        }
                                        aria-invalid={
                                            form.errors.branch_code
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={describedBy(
                                            'branch_code',
                                            folderColorHint,
                                            form.errors.branch_code,
                                        )}
                                    >
                                        <option value="">
                                            {t('common.choose')}
                                        </option>
                                        {options.branches.map((item) => (
                                            <option
                                                key={item.code}
                                                value={item.code}
                                            >
                                                {optionName(item)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    id="category_code"
                                    label={t('fields.category')}
                                    error={form.errors.category_code}
                                    required
                                >
                                    {select(
                                        'category_code',
                                        specializations.map((item) => ({
                                            value: item.code,
                                            label: optionName(item),
                                        })),
                                        form.data.branch_code
                                            ? t('common.choose')
                                            : t('fields.choose_branch_first'),
                                    )}
                                </Field>
                                <Field
                                    id="other_info"
                                    label={t('fields.other_info')}
                                    error={form.errors.other_info}
                                    className="sm:col-span-2"
                                >
                                    <textarea
                                        id="other_info"
                                        name="other_info"
                                        rows={3}
                                        maxLength={1000}
                                        className={textareaClassName}
                                        value={form.data.other_info ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'other_info',
                                                event.target.value === ''
                                                    ? null
                                                    : event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.errors.other_info
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={describedBy(
                                            'other_info',
                                            undefined,
                                            form.errors.other_info,
                                        )}
                                    />
                                </Field>
                                <fieldset className="grid gap-3 sm:col-span-2">
                                    <legend className="text-sm font-medium">
                                        {t('fields.issued_documents')}
                                    </legend>
                                    <p className="-mt-1 text-xs text-muted-foreground">
                                        {t('fields.issued_documents_hint', {
                                            max: MAX_ISSUED_DOCUMENTS,
                                        })}
                                    </p>
                                    {issuedDocuments.map((row, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_1fr_2fr_auto] sm:items-end"
                                            data-test={`issued-document-${index}`}
                                        >
                                            {(
                                                [
                                                    [
                                                        'number',
                                                        'fields.document_number',
                                                        {
                                                            dir: 'ltr',
                                                        },
                                                    ],
                                                    [
                                                        'date',
                                                        'fields.document_date',
                                                        { type: 'date' },
                                                    ],
                                                    [
                                                        'recipient',
                                                        'fields.document_recipient',
                                                        {},
                                                    ],
                                                ] as const
                                            ).map(([key, label, props]) => {
                                                const id = `issued_documents_${index}_${key}`;
                                                const error =
                                                    errors[
                                                        `issued_documents.${index}.${key}`
                                                    ];

                                                return (
                                                    <Field
                                                        key={key}
                                                        id={id}
                                                        label={t(label)}
                                                        error={error}
                                                    >
                                                        <Input
                                                            id={id}
                                                            value={
                                                                row[key] ?? ''
                                                            }
                                                            onChange={(event) =>
                                                                setIssuedDocuments(
                                                                    issuedDocuments.map(
                                                                        (
                                                                            item,
                                                                            position,
                                                                        ) =>
                                                                            position ===
                                                                            index
                                                                                ? {
                                                                                      ...item,
                                                                                      [key]:
                                                                                          event
                                                                                              .target
                                                                                              .value ||
                                                                                          null,
                                                                                  }
                                                                                : item,
                                                                    ),
                                                                )
                                                            }
                                                            aria-invalid={
                                                                error
                                                                    ? true
                                                                    : undefined
                                                            }
                                                            aria-describedby={describedBy(
                                                                id,
                                                                undefined,
                                                                error,
                                                            )}
                                                            {...props}
                                                        />
                                                    </Field>
                                                );
                                            })}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setIssuedDocuments(
                                                        issuedDocuments.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    'fields.remove_document',
                                                )}
                                                title={t(
                                                    'fields.remove_document',
                                                )}
                                            >
                                                <Trash2
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </Button>
                                        </div>
                                    ))}
                                    {issuedDocuments.length <
                                        MAX_ISSUED_DOCUMENTS && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="justify-self-start"
                                            onClick={() =>
                                                setIssuedDocuments([
                                                    ...issuedDocuments,
                                                    {
                                                        number: null,
                                                        date: null,
                                                        recipient: null,
                                                    },
                                                ])
                                            }
                                            data-test="add-issued-document"
                                        >
                                            <Plus
                                                className="size-4"
                                                aria-hidden
                                            />
                                            {t('fields.add_document')}
                                        </Button>
                                    )}
                                </fieldset>
                            </div>
                        )}

                        {/* Phones: the main action first, all full width. */}
                        <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-between">
                            <Button
                                type="button"
                                variant="outline"
                                className="order-2 sm:order-none"
                                disabled={form.processing || !form.isDirty}
                                onClick={() => save()}
                                data-test="save-draft"
                            >
                                <Save className="size-4" aria-hidden />
                                {t('applicant.save_draft')}
                            </Button>
                            <div className="contents sm:flex sm:gap-2">
                                {step > 0 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="order-3 sm:order-none"
                                        onClick={() => goTo(step - 1)}
                                    >
                                        {t('common.previous')}
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    className="order-1 sm:order-none"
                                    disabled={form.processing}
                                    data-test="next-step"
                                >
                                    {form.processing && <Spinner />}
                                    {t('applicant.save_and_continue')}
                                </Button>
                            </div>
                        </div>
                    </form>
                )}

                {currentStep === 'documents' && (
                    <section
                        className="space-y-4"
                        aria-labelledby="documents-heading"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <h2
                                id="documents-heading"
                                className="text-lg font-semibold"
                            >
                                {t('applicant.steps.documents')}
                            </h2>
                            {hasPendingDocuments && (
                                <span
                                    className="flex items-center gap-2 text-sm text-muted-foreground"
                                    role="status"
                                >
                                    <Spinner /> {t('documents.checking')}
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('documents.intro')}
                        </p>
                        {documentTypes.map((requirement) => (
                            <DocumentUploader
                                key={requirement.code}
                                applicationUuid={application.uuid}
                                requirement={requirement}
                                current={documentsByType[requirement.code]}
                            />
                        ))}
                        <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-between">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => goTo(step - 1)}
                            >
                                {t('common.previous')}
                            </Button>
                            <Button
                                type="button"
                                onClick={() => goTo(step + 1)}
                                data-test="next-step"
                            >
                                {t('common.next')}
                            </Button>
                        </div>
                    </section>
                )}

                {currentStep === 'review' && (
                    <section
                        className="space-y-5"
                        aria-labelledby="review-heading"
                    >
                        <h2
                            id="review-heading"
                            className="text-lg font-semibold"
                        >
                            {t('applicant.steps.review')}
                        </h2>

                        <dl className="grid gap-x-6 gap-y-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2 sm:p-6">
                            {applicationSummary(application, t).map(
                                ([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex flex-col gap-0.5 border-b pb-2 last:border-0"
                                    >
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd
                                            className="font-medium whitespace-pre-line"
                                            dir="auto"
                                        >
                                            {value ?? '—'}
                                        </dd>
                                    </div>
                                ),
                            )}
                        </dl>

                        {!isReady && (
                            <Alert
                                variant="destructive"
                                data-test="missing-requirements"
                            >
                                <AlertTriangle className="size-4" />
                                <AlertTitle>
                                    {t('applicant.incomplete_title')}
                                </AlertTitle>
                                <AlertDescription>
                                    <ul className="list-inside list-disc">
                                        {missing.fields.map((field) => (
                                            <li key={field}>
                                                {t(
                                                    `fields.${field.replace(/_code$/, '')}`,
                                                )}
                                            </li>
                                        ))}
                                        {missing.documents.map((code) => (
                                            <li key={code}>
                                                {documentTypes.find(
                                                    (type) =>
                                                        type.code === code,
                                                )?.name ?? code}
                                            </li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        {submissionUnconfirmed && (
                            <Alert
                                variant="destructive"
                                data-test="submission-unconfirmed"
                            >
                                <AlertTriangle className="size-4" />
                                <AlertTitle>
                                    {t('applicant.unconfirmed_title')}
                                </AlertTitle>
                                <AlertDescription className="flex flex-col items-start gap-2">
                                    {t('applicant.unconfirmed_text')}
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={applications.show(
                                                application.uuid,
                                            )}
                                        >
                                            {t('applicant.check_status')}
                                        </Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        )}

                        {submissionError && isReady && (
                            <Alert variant="destructive">
                                <AlertTriangle className="size-4" />
                                <AlertDescription>
                                    {submissionError}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="space-y-4 rounded-xl border bg-card p-4 sm:p-6">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="declaration"
                                    checked={submission.data.declaration}
                                    onCheckedChange={(checked) =>
                                        submission.setData(
                                            'declaration',
                                            checked === true,
                                        )
                                    }
                                    disabled={!isReady}
                                    aria-describedby={
                                        submission.errors.declaration
                                            ? 'declaration-error'
                                            : undefined
                                    }
                                />
                                <Label
                                    htmlFor="declaration"
                                    className="leading-relaxed font-normal"
                                >
                                    {t('applicant.declaration')}
                                </Label>
                            </div>
                            {submission.errors.declaration && (
                                <p
                                    id="declaration-error"
                                    className="text-sm text-destructive"
                                >
                                    {submission.errors.declaration}
                                </p>
                            )}
                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => goTo(step - 1)}
                                >
                                    {t('common.previous')}
                                </Button>
                                <Button
                                    type="button"
                                    size="lg"
                                    onClick={submit}
                                    disabled={
                                        !isReady ||
                                        !submission.data.declaration ||
                                        submission.processing
                                    }
                                    data-test="submit-application"
                                >
                                    {submission.processing ? (
                                        <Spinner />
                                    ) : isReady ? (
                                        <Send className="size-4" aria-hidden />
                                    ) : (
                                        <CheckCircle2
                                            className="size-4"
                                            aria-hidden
                                        />
                                    )}
                                    {isCorrection
                                        ? t('applicant.resubmit')
                                        : t('applicant.submit')}
                                </Button>
                            </div>
                        </div>
                    </section>
                )}
            </PageContainer>
        </>
    );
}
