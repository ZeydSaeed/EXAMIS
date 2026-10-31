import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, RefreshCw, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';
import { ApplicationSummaryTable } from '@/components/examis/application-summary-table';
import { DocumentUploader } from '@/components/examis/document-uploader';
import {
    describedBy,
    Field,
    selectClassName,
    textareaClassName,
} from '@/components/examis/field';
import { PageContainer } from '@/components/examis/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-trans';
import { applicationSummarySections } from '@/lib/application-summary';
import applications from '@/routes/applications';
import apply from '@/routes/apply';
import type {
    Application,
    ApplicationForm,
    DocumentRequirement,
    MissingRequirements,
    ReferenceOption,
} from '@/types';

type Props = {
    application: Application;
    documentTypes: DocumentRequirement[];
    missing: MissingRequirements;
    options: {
        /** For the governorate of the last school. */
        governorates: ReferenceOption[];
    };
    step: number;
};

type FormData = ApplicationForm & { lock_version: number; step?: number };

type TextField = keyof ApplicationForm;

/** `birth_place_code` for a place outside the governorate list. */
const BIRTH_PLACE_OTHER = 'OTHER';

const STEPS = [
    'personal',
    'contact',
    'education',
    'documents',
    'review',
] as const;

/**
 * Required fields in filling order. A field opens once those before it are
 * filled, so nothing can be skipped.
 */
const FIELD_ORDER: Record<(typeof STEPS)[number], TextField[]> = {
    personal: [
        'student_name',
        'father_name',
        'grandfather_name',
        'great_grandfather_name',
        'mother_first_name',
        'mother_father_name',
        'birth_date',
        'birth_place_code',
        'gender',
    ],
    contact: ['district'],
    education: ['school_name', 'school_governorate_code'],
    documents: [],
    review: [],
};

/** These must not start with a space. */
const NAME_FIELDS: TextField[] = [
    'student_name',
    'father_name',
    'grandfather_name',
    'great_grandfather_name',
];

export default function EditApplication({
    application,
    documentTypes,
    missing,
    options,
    step: initialStep,
}: Props) {
    const { t, locale } = useTrans();
    const step = Math.min(Math.max(initialStep, 1), STEPS.length) - 1;
    const [submissionUnconfirmed, setSubmissionUnconfirmed] = useState(false);
    const [notice, setNotice] = useState<{
        title: string;
        items: string[];
    } | null>(null);

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

    const optionName = (option: ReferenceOption) =>
        locale === 'en' ? option.name_en : option.name_ar;
    const currentStep = STEPS[step];
    const sequence =
        form.data.birth_place_code === BIRTH_PLACE_OTHER
            ? FIELD_ORDER[currentStep].flatMap((field) =>
                  field === 'birth_place_code'
                      ? [field, 'birth_place_other' as const]
                      : [field],
              )
            : FIELD_ORDER[currentStep];
    const isEmpty = (field: TextField) => !form.data[field];
    const isLocked = (field: TextField) => {
        const index = sequence.indexOf(field);

        return (index < 0 ? sequence : sequence.slice(0, index)).some(isEmpty);
    };
    const fieldLabel = (field: string) =>
        t(`fields.${field.replace(/_code$/, '')}`);

    // Start on the next field to fill.
    useEffect(() => {
        const next = sequence.find(isEmpty);

        if (next) {
            document.getElementById(next)?.focus();
        }
        // Once per step: each step is a fresh page.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    const isCorrection = application.status === 'requires_correction';
    const awaitingReview =
        application.status === 'submitted' ||
        application.status === 'resubmitted';

    // Each step replaces the last in the history: the form has no way back.
    const handleNext = (event: FormEvent) => {
        event.preventDefault();

        const unfilled = sequence.filter(isEmpty);

        if (unfilled.length > 0) {
            setNotice({
                title: t('applicant.missing_title'),
                items: unfilled.map(fieldLabel),
            });

            return;
        }

        form.transform((data) => ({ ...data, step: step + 1 }));
        form.put(applications.update.url(application.uuid), {
            preserveScroll: 'errors',
            preserveState: 'errors',
            replace: true,
        });
    };

    // Enter moves field by field and ends on the save button. A field that
    // must be filled keeps the focus until it is.
    const moveOnEnter = (event: KeyboardEvent<HTMLFormElement>) => {
        const target = event.target as HTMLInputElement;

        if (
            event.key !== 'Enter' ||
            event.shiftKey ||
            target instanceof HTMLButtonElement
        ) {
            return;
        }

        event.preventDefault();

        if (sequence.includes(target.id as TextField) && !target.value.trim()) {
            return;
        }

        const stops = Array.from(
            event.currentTarget.querySelectorAll<HTMLElement>(
                'input:not([readonly]):not(:disabled), select:not(:disabled), textarea:not(:disabled), button[type="submit"]',
            ),
        );

        stops[stops.indexOf(target) + 1]?.focus();
    };

    const goToReview = () => {
        if (missing.documents.length > 0) {
            setNotice({
                title: t('applicant.missing_documents_title'),
                items: missing.documents.map(
                    (code) =>
                        documentTypes.find((type) => type.code === code)
                            ?.name ?? code,
                ),
            });

            return;
        }

        router.get(
            applications.edit.url(application.uuid, {
                query: { step: STEPS.indexOf('review') + 1 },
            }),
            {},
            { replace: true },
        );
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
            disabled={isLocked(field)}
            onChange={(event) => {
                const value = event.target.value;

                if (NAME_FIELDS.includes(field) && /^\s/.test(value)) {
                    setNotice({
                        title: t('applicant.leading_space_title'),
                        items: [t('applicant.leading_space_text')],
                    });
                    form.setData(field, (value.trimStart() || null) as never);

                    return;
                }

                form.setData(field, value === '' ? null : (value as never));
            }}
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
            disabled={isLocked(field)}
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

    const folderColorKey = `fields.folder_colors.${form.data.branch_code}`;
    const folderColorHint =
        form.data.branch_code && t(folderColorKey) !== folderColorKey
            ? t('fields.folder_color', { color: t(folderColorKey) })
            : undefined;
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
            <PageContainer>
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

                <h2
                    id="step-heading"
                    className="flex w-fit items-center gap-2 rounded-lg border border-primary bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                >
                    <span className="flex size-6 items-center justify-center rounded-full bg-primary-foreground text-xs text-primary">
                        {step + 1}
                    </span>
                    {t(`applicant.steps.${currentStep}`)}
                </h2>

                {['personal', 'contact', 'education'].includes(currentStep) && (
                    <form
                        onSubmit={handleNext}
                        onKeyDown={moveOnEnter}
                        className="space-y-6 rounded-xl border bg-card p-4 sm:p-6"
                        noValidate
                    >
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
                                    id="birth_place_code"
                                    label={t('fields.birth_place')}
                                    error={form.errors.birth_place_code}
                                    required
                                >
                                    <select
                                        id="birth_place_code"
                                        name="birth_place_code"
                                        className={selectClassName}
                                        value={form.data.birth_place_code ?? ''}
                                        disabled={isLocked('birth_place_code')}
                                        onChange={(event) =>
                                            form.setData((data) => ({
                                                ...data,
                                                birth_place_code:
                                                    event.target.value || null,
                                                // The written place only goes with "other".
                                                birth_place_other:
                                                    event.target.value ===
                                                    BIRTH_PLACE_OTHER
                                                        ? data.birth_place_other
                                                        : null,
                                            }))
                                        }
                                        aria-invalid={
                                            form.errors.birth_place_code
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={describedBy(
                                            'birth_place_code',
                                            undefined,
                                            form.errors.birth_place_code,
                                        )}
                                        data-test="birth-place"
                                    >
                                        <option value="">
                                            {t('common.choose')}
                                        </option>
                                        {options.governorates.map((item) => (
                                            <option
                                                key={item.code}
                                                value={item.code}
                                            >
                                                {optionName(item)}
                                            </option>
                                        ))}
                                        <option value={BIRTH_PLACE_OTHER}>
                                            {t(
                                                'fields.birth_place_other_option',
                                            )}
                                        </option>
                                    </select>
                                </Field>
                                {form.data.birth_place_code ===
                                    BIRTH_PLACE_OTHER && (
                                    <Field
                                        id="birth_place_other"
                                        label={t('fields.birth_place_other')}
                                        error={form.errors.birth_place_other}
                                        required
                                        className="sm:col-span-2"
                                    >
                                        {input('birth_place_other', {
                                            autoFocus: true,
                                        })}
                                    </Field>
                                )}
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
                                        className="bg-muted text-right"
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
                                    id="school_governorate_code"
                                    label={t('fields.school_governorate')}
                                    error={form.errors.school_governorate_code}
                                    required
                                >
                                    {select(
                                        'school_governorate_code',
                                        options.governorates.map((item) => ({
                                            value: item.code,
                                            label: optionName(item),
                                        })),
                                    )}
                                </Field>
                                <Field
                                    id="last_grade"
                                    label={t('fields.last_grade')}
                                    error={form.errors.last_grade}
                                    className="sm:col-span-2"
                                >
                                    {input('last_grade', { maxLength: 60 })}
                                </Field>
                                {/* Chosen on the branch page after registration; read-only here. */}
                                <Field
                                    id="branch_readonly"
                                    label={t('fields.branch')}
                                    hint={folderColorHint}
                                >
                                    <Input
                                        id="branch_readonly"
                                        value={application.labels.branch ?? '—'}
                                        readOnly
                                        aria-readonly
                                        aria-describedby={
                                            folderColorHint
                                                ? 'branch_readonly-hint'
                                                : undefined
                                        }
                                        className="bg-muted"
                                        data-test="branch-readonly"
                                    />
                                </Field>
                                <Field
                                    id="category_readonly"
                                    label={t('fields.category')}
                                >
                                    <Input
                                        id="category_readonly"
                                        value={
                                            application.labels.category ?? '—'
                                        }
                                        readOnly
                                        aria-readonly
                                        className="bg-muted"
                                        data-test="category-readonly"
                                    />
                                </Field>
                                {!form.data.branch_code && (
                                    <p className="text-sm sm:col-span-2">
                                        {t('fields.branch_not_chosen')}{' '}
                                        <Link
                                            href={apply.specialization()}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            {t('fields.choose_branch_link')}
                                        </Link>
                                    </p>
                                )}
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
                                        disabled={isLocked('other_info')}
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
                            </div>
                        )}

                        <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="next-step"
                            >
                                {form.processing && <Spinner />}
                                {t('applicant.save_and_continue')}
                            </Button>
                        </div>
                    </form>
                )}

                {currentStep === 'documents' && (
                    <section
                        className="space-y-4"
                        aria-labelledby="step-heading"
                    >
                        {hasPendingDocuments && (
                            <p
                                className="flex items-center gap-2 text-sm text-muted-foreground"
                                role="status"
                            >
                                <Spinner /> {t('documents.checking')}
                            </p>
                        )}
                        {documentTypes.map((requirement) => (
                            <DocumentUploader
                                key={requirement.code}
                                applicationUuid={application.uuid}
                                requirement={requirement}
                                current={documentsByType[requirement.code]}
                            />
                        ))}
                        <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                onClick={goToReview}
                                data-test="next-step"
                            >
                                {t('applicant.save_and_continue')}
                            </Button>
                        </div>
                    </section>
                )}

                {currentStep === 'review' && (
                    <section
                        className="space-y-5"
                        aria-labelledby="step-heading"
                    >
                        <ApplicationSummaryTable
                            caption={t('applicant.steps.review')}
                            sections={[
                                ...applicationSummarySections(
                                    application,
                                    t,
                                ).map((section) => ({
                                    key: section.step,
                                    title: section.title,
                                    rows: section.rows,
                                })),
                                {
                                    key: 'documents',
                                    title: t('applicant.steps.documents'),
                                    rows: documentTypes.map((requirement) => {
                                        const document =
                                            documentsByType[requirement.code];

                                        return [
                                            requirement.name,
                                            document ? (
                                                t(
                                                    `documents.status.${document.status}`,
                                                )
                                            ) : (
                                                <span className="font-medium text-destructive">
                                                    {t('documents.missing')}
                                                </span>
                                            ),
                                        ];
                                    }),
                                },
                            ]}
                        />

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

                        {awaitingReview ? (
                            // Already submitted: edits are saved as made; nothing to send again.
                            <div className="space-y-4 rounded-xl border bg-card p-4 sm:p-6">
                                <p
                                    className="flex items-start gap-2 text-sm leading-relaxed"
                                    data-test="submitted-edit-note"
                                >
                                    <CheckCircle2
                                        className="mt-0.5 size-4 shrink-0 text-success"
                                        aria-hidden
                                    />
                                    {t('applicant.submitted_edit_note')}
                                </p>
                                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                    <Button size="lg" asChild>
                                        <Link
                                            href={applications.show(
                                                application.uuid,
                                            )}
                                            data-test="back-to-application"
                                        >
                                            {t('applicant.back_to_application')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ) : (
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
                                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
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
                                            <Send
                                                className="size-4"
                                                aria-hidden
                                            />
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
                        )}
                    </section>
                )}

                <Dialog
                    open={notice !== null}
                    onOpenChange={(open) => !open && setNotice(null)}
                >
                    <DialogContent
                        data-test="form-notice"
                        onCloseAutoFocus={(event) => {
                            const next = sequence.find(isEmpty);

                            if (next) {
                                event.preventDefault();
                                document.getElementById(next)?.focus();
                            }
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>{notice?.title}</DialogTitle>
                        </DialogHeader>
                        <ul className="list-inside list-disc space-y-1 text-sm">
                            {notice?.items.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button data-test="form-notice-close">
                                    {t('applicant.understood')}
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </PageContainer>
        </>
    );
}
