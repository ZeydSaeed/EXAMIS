export type ApplicationStatus =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'requires_correction'
    | 'resubmitted'
    | 'accepted'
    | 'rejected'
    | 'withdrawn';

/** Fields of the official student status form (استمارة واقع حال). */
export type ApplicationForm = {
    student_name: string | null;
    father_name: string | null;
    grandfather_name: string | null;
    great_grandfather_name: string | null;
    mother_first_name: string | null;
    mother_father_name: string | null;
    birth_date: string | null;
    birth_place_code: string | null;
    birth_place_other: string | null;
    gender: 'male' | 'female' | null;
    national_id_number: string | null;
    phone: string | null;
    district: string | null;
    mahalla: string | null;
    alley: string | null;
    house_number: string | null;
    /** The last school attended. */
    school_name: string | null;
    school_governorate_code: string | null;
    branch_code: string | null;
    /** The specialization within the branch. */
    category_code: string | null;
    other_info: string | null;
};

export type ApplicationDocument = {
    uuid: string;
    document_type: string;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    status: 'pending' | 'verified' | 'rejected';
    rejection_reason: string | null;
    uploaded_at: string | null;
    url: string | null;
};

export type ApplicationEvent = {
    id: number;
    type: string;
    type_label: string;
    from_status: ApplicationStatus | null;
    to_status: ApplicationStatus | null;
    to_status_label: string | null;
    reason: string | null;
    actor: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

export type Application = {
    uuid: string;
    reference_number: string | null;
    status: ApplicationStatus;
    status_label: string;
    lock_version: number;
    is_editable: boolean;
    exam_period?: {
        id: number;
        code: string;
        name: string;
        closes_at: string;
        is_accepting: boolean;
    };
    /** Composed from the name parts; read-only. */
    full_name: string | null;
    mother_name: string | null;
    form: ApplicationForm;
    labels: {
        governorate: string | null;
        school_governorate: string | null;
        birth_place: string | null;
        branch: string | null;
        category: string | null;
        gender: string | null;
    };
    submitted_at: string | null;
    last_submitted_at: string | null;
    decided_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    documents?: ApplicationDocument[];
    events?: ApplicationEvent[];
    assignee?: { id: number; name: string } | null;
};

export type DocumentRequirement = {
    code: string;
    name: string;
    description?: string | null;
    required: boolean;
    mime_types?: string[];
    max_size_kb?: number;
    is_photo?: boolean;
};

export type ReferenceOption = {
    code: string;
    parent_code: string | null;
    name_ar: string;
    name_en: string;
};

export type MissingRequirements = {
    fields: string[];
    documents: string[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type CursorPaginated<T> = {
    data: T[];
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};
