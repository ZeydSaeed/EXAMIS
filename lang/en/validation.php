<?php

return [
    'custom' => [
        'username' => [
            'regex' => 'The username may contain only lowercase English letters, digits and . _ -',
        ],
        'full_name_ar' => [
            'regex' => 'The full name must be written in Arabic letters.',
        ],
        'mother_name_ar' => [
            'regex' => "The mother's name must be written in Arabic letters.",
        ],
        'full_name_en' => [
            'regex' => 'The English name may contain English letters only.',
        ],
        'phone' => [
            'regex' => 'The phone number must contain 10 to 15 digits.',
        ],
        'national_id_number' => [
            'regex' => 'The ID number must be 6 to 20 letters or digits.',
        ],
        'birth_date' => [
            'before' => 'The date of birth is not plausible for an exam applicant.',
        ],
    ],

    'attributes' => [
        'full_name_ar' => 'full name (Arabic)',
        'full_name_en' => 'full name (English)',
        'mother_name_ar' => "mother's name",
        'birth_date' => 'date of birth',
        'national_id_number' => 'ID number',
        'contact_email' => 'contact email',
        'governorate_code' => 'governorate',
        'directorate_code' => 'directorate',
        'school_name' => 'school name',
        'branch_code' => 'branch',
        'category_code' => 'applicant category',
        'previous_exam_year' => 'previous exam year',
        'document_type' => 'document type',
        'declaration' => 'declaration',
        'terms' => 'terms of use and privacy policy',
    ],
];
