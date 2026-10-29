<?php

return [
    'custom' => [
        'student_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'father_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'grandfather_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'great_grandfather_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'mother_first_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'mother_father_name' => [
            'regex' => 'The :attribute must be written in Arabic letters.',
        ],
        'username' => [
            'regex' => 'The username may contain only lowercase English letters, digits and . _ -',
            'not_regex' => 'The username cannot consist of digits only.',
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
            'unique' => 'This mobile number is already registered to another account. Sign in with it or contact support.',
        ],
        'national_id_number' => [
            'regex' => 'The ID number must be 6 to 20 letters or digits.',
        ],
        'birth_date' => [
            'before' => 'The date of birth is not plausible for an exam applicant.',
        ],
    ],

    'attributes' => [
        'student_name' => 'Student\'s name',
        'father_name' => 'Father\'s name',
        'grandfather_name' => 'Grandfather\'s name',
        'great_grandfather_name' => 'Great-grandfather\'s name',
        'mother_first_name' => 'Mother\'s name',
        'mother_father_name' => 'Mother\'s father\'s name',
        'full_name_ar' => 'full name (Arabic)',
        'mother_name_ar' => "mother's name",
        'birth_date' => 'date of birth',
        'national_id_number' => 'national ID number',
        'school_name' => 'last school',
        'branch_code' => 'branch',
        'category_code' => 'specialization',
        'document_type' => 'document type',
        'declaration' => 'declaration',
        'terms' => 'terms of use and privacy policy',
        'district' => 'district',
        'mahalla' => 'mahalla number',
        'alley' => 'alley number',
        'house_number' => 'house number',
        'intermediate_school_name' => 'intermediate school',
        'last_grade' => 'last grade attended',
        'failed_years' => 'years failed in the last grade',
        'left_years' => 'years out of school after the last grade',
        'other_info' => 'other information',
        'issued_documents' => 'documents issued to the student',
        'issued_documents.*.number' => 'document number',
        'issued_documents.*.date' => 'document date',
        'issued_documents.*.recipient' => 'recipient',
    ],
];
