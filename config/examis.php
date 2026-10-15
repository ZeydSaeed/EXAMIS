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
        'name_ar' => env('EXAMIS_ORG_NAME_AR', 'الجهة المنظمة للامتحانات الخارجية'),
        'name_en' => env('EXAMIS_ORG_NAME_EN', 'External Examinations Authority'),
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
        'require_staff_two_factor' => (bool) env('EXAMIS_REQUIRE_STAFF_2FA', env('APP_ENV') === 'production'),
        'content_security_policy' => (bool) env('EXAMIS_CSP_ENABLED', true),
        'hsts' => (bool) env('EXAMIS_HSTS_ENABLED', env('APP_ENV') === 'production'),
        // Raise only on an isolated load-test environment (k6 runs from one IP).
        'login_attempts_per_ip_per_hour' => (int) env('EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Applicant documents
    |--------------------------------------------------------------------------
    |
    | `disk` must be private (never the `public` disk). `scanner` selects the
    | malware/structure scanner: "structural" performs signature and active
    | content checks only and is NOT a substitute for an antivirus engine.
    |
    */

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'documents'),
        'scanner' => env('DOCUMENT_SCANNER', 'structural'),
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
