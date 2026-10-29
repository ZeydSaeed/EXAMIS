import type { Application } from '@/types';

type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * The student status form as label/value rows, in the order of the official
 * form. Shared by the review step, the applicant's own page and the staff
 * review page so they never drift apart.
 */
export function applicationSummary(
    application: Application,
    t: Translate,
): [label: string, value: string | number | null][] {
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
    const documents = (form.issued_documents ?? [])
        .map((row) =>
            [row.number, row.date, row.recipient].filter(Boolean).join(' — '),
        )
        .filter(Boolean)
        .join('\n');

    return [
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
        [t('fields.address'), address || null],
        [t('fields.phone'), form.phone],
        [t('fields.school_name'), form.school_name],
        [t('fields.intermediate_school_name'), form.intermediate_school_name],
        [t('fields.last_grade'), form.last_grade],
        [t('fields.failed_years'), form.failed_years],
        [t('fields.left_years'), form.left_years],
        [t('fields.branch'), application.labels.branch],
        [t('fields.category'), application.labels.category],
        [t('fields.other_info'), form.other_info],
        [t('fields.issued_documents'), documents || null],
    ];
}

/** Joins name parts with spaces, or null when none is filled in. */
function joinName(...parts: (string | null)[]): string | null {
    const name = parts
        .map((part) => part?.trim())
        .filter(Boolean)
        .join(' ');

    return name === '' ? null : name;
}
