<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Organisation identity
    |--------------------------------------------------------------------------
    |
    | Printed on receipts and shown in the portal header. Replace with the
    | examination authority's approved names before going live.
    |
    */

    'organization' => [
        'name_ar' => env('EXAMIS_ORG_NAME_AR', 'الامتحانات التمهيدية للطلبة الخارجيين للدراسة المهنية العامة'),
        'name_en' => env('EXAMIS_ORG_NAME_EN', 'Preliminary Examinations for External Students of General Vocational Study'),
    ],

    'supported_locales' => ['ar', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Student form PDF
    |--------------------------------------------------------------------------
    |
    | The form prints in Times New Roman (times.ttf, timesbd.ttf), which is
    | licensed with Windows and not shipped with the application. Point
    | EXAMIS_RECEIPT_FONT_DIR at a folder holding those files; without them
    | the form falls back to DejaVu Sans.
    |
    */

    'receipt' => [
        'font_dir' => env('EXAMIS_RECEIPT_FONT_DIR', 'C:/Windows/Fonts'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'security' => [
        'content_security_policy' => (bool) env('EXAMIS_CSP_ENABLED', true),
        'hsts' => (bool) env('EXAMIS_HSTS_ENABLED', env('APP_ENV') === 'production'),
        // Raise only on an isolated load-test environment (k6 runs from one IP).
        'login_attempts_per_ip_per_hour' => (int) env('EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Portals (ADR-0011)
    |--------------------------------------------------------------------------
    |
    | The host each portal answers on. Every other host gets a 404.
    |
    */

    'portals' => [
        'students' => env('EXAMIS_STUDENTS_HOST', 'students.examis.test'),
        'staff' => env('EXAMIS_STAFF_HOST', 'staff.examis.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff accounts (ADR-0011)
    |--------------------------------------------------------------------------
    |
    | How long a one-time link stays valid and how long an idle staff session
    | lasts. Administrators hand links to staff themselves, so no mail service
    | is needed.
    |
    */

    'staff' => [
        'invitation_hours' => (int) env('EXAMIS_INVITATION_HOURS', 48),
        'idle_minutes' => (int) env('EXAMIS_STAFF_IDLE_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Student accounts (ADR-0009)
    |--------------------------------------------------------------------------
    |
    | Students sign in with one identifier. A remembered device lets them in
    | directly; any other device must present the private access code, which
    | is locked after `access_code_attempts_per_hour` failures per account.
    |
    */

    'students' => [
        'trusted_device_days' => (int) env('EXAMIS_TRUSTED_DEVICE_DAYS', 180),
        'access_code_attempts_per_hour' => (int) env('EXAMIS_ACCESS_CODE_ATTEMPTS_PER_HOUR', 10),
        'student_number_min' => 100000,
        'student_number_max' => 999999,
    ],

    /*
    |--------------------------------------------------------------------------
    | Applicant documents
    |--------------------------------------------------------------------------
    |
    | `disk` must be private (never the `public` disk). `scanner` selects the
    | malware/structure scanner: "structural" performs signature and active
    | content checks only and is NOT a substitute for an antivirus engine.
    | `scan_inline` runs the scan during the upload, so the student never
    | waits on a queue worker. Turn it off for a slow scanner such as an
    | antivirus, which then runs on the `queue` below.
    |
    */

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'documents'),
        'scanner' => env('DOCUMENT_SCANNER', 'structural'),
        'scan_inline' => (bool) env('DOCUMENT_SCAN_INLINE', true),
        // Keep each generated form on the private disk until its data changes.
        'form_cache' => (bool) env('EXAMIS_FORM_CACHE', true),
        'queue' => env('DOCUMENTS_QUEUE', 'documents'),
        'max_image_pixels' => 40_000_000,
        'max_per_application' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Load testing
    |--------------------------------------------------------------------------
    |
    | Number of synthetic accounts created by LoadTestSeeder (never in production).
    |
    */

    'load_test_users' => (int) env('LOAD_TEST_USERS', 1000),

];
