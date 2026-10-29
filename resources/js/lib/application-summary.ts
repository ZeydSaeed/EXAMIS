import type { Application } from '@/types';

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

export type SummaryRow = [label: string, value: string | number | null];

export type SummarySection = {
    /** The form step the rows come from. */
    step: 'personal' | 'contact' | 'education';
    title: string;
    rows: SummaryRow[];
};

/**
 * The student status form grouped by form step, in the order of the
 * official form. Shared by the review step, the applicant's own page and
 * the staff review page so they never drift apart.
 */
export function applicationSummarySections(
    application: Application,
    t: Translate,
): SummarySection[] {
    const form = application.form;
    const address = [
        application.labels.governorate,
        form.district,
        form.mahalla && t('fields.address_mahalla', { value: form.mahalla }),
        form.alley && t('fields.address_alley', { value: form.alley }),
        form.house_number &&
            t('fields.address_house', { value: form.house_number }),
    ]
        .filter(Boolean)
        .join(t('fields.address_separator'));

    return [
        {
            step: 'personal',
            title: t('applicant.steps.personal'),
            rows: [
                [
                    t('fields.full_name_ar'),
                    joinName(
                        form.student_name,
                        form.father_name,
                        form.grandfather_name,
                        form.great_grandfather_name,
                    ),
                ],
                [
                    t('fields.mother_name_ar'),
                    joinName(form.mother_first_name, form.mother_father_name),
                ],
                [t('fields.birth_date'), form.birth_date],
                [t('fields.gender'), application.labels.gender],
                [t('fields.national_id_number'), form.national_id_number],
            ],
        },
        {
            step: 'contact',
            title: t('applicant.steps.contact'),
            rows: [
                [t('fields.address'), address || null],
                [t('fields.phone'), form.phone],
            ],
        },
        {
            step: 'education',
            title: t('applicant.steps.education'),
            rows: [
                [t('fields.school_name'), form.school_name],
                [
                    t('fields.school_governorate'),
                    application.labels.school_governorate,
                ],
                [t('fields.branch'), application.labels.branch],
                [t('fields.category'), application.labels.category],
                [t('fields.other_info'), form.other_info],
            ],
        },
    ];
}

/** The same rows, ungrouped. */
export function applicationSummary(
    application: Application,
    t: Translate,
): SummaryRow[] {
    return applicationSummarySections(application, t).flatMap(
        (section) => section.rows,
    );
}

/** Joins name parts with spaces, or null when none is filled in. */
function joinName(...parts: (string | null)[]): string | null {
    const name = parts
        .map((part) => part?.trim())
        .filter(Boolean)
        .join(' ');

    return name === '' ? null : name;
}
