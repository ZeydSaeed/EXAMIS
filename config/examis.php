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
        // Start low on a new host (300, then a day) and raise to a year.
        'hsts_max_age' => (int) env('EXAMIS_HSTS_MAX_AGE', 31536000),
        // Staff sign-in. Raise only on an isolated load-test environment (k6 runs from one IP).
        'login_attempts_per_ip_per_hour' => (int) env('EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR', 100),
        // Students, counted per IP apart from staff. Mobile carriers in Iraq
        // put many subscribers behind one address (CGNAT), and a school lab
        // signs a whole class in from one, so these allow a busy shared
        // address; guessing is stopped per account (access code lock).
        'students_per_ip' => [
            'logins_per_minute' => (int) env('EXAMIS_STUDENT_LOGINS_PER_IP_PER_MINUTE', 60),
            'attempts_per_hour' => (int) env('EXAMIS_STUDENT_ATTEMPTS_PER_IP_PER_HOUR', 600),
            'registrations_per_minute' => (int) env('EXAMIS_REGISTRATIONS_PER_IP_PER_MINUTE', 10),
            'registrations_per_day' => (int) env('EXAMIS_REGISTRATIONS_PER_IP_PER_DAY', 300),
            'lookups_per_minute' => (int) env('EXAMIS_LOOKUPS_PER_IP_PER_MINUTE', 30),
            'lookups_per_hour' => (int) env('EXAMIS_LOOKUPS_PER_IP_PER_HOUR', 300),
        ],
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
        // Per device (IP) and account, so a guesser locks out only themselves;
        // the total across all devices still stops spread-out guessing.
        'access_code_attempts_per_hour' => (int) env('EXAMIS_ACCESS_CODE_ATTEMPTS_PER_HOUR', 10),
        'access_code_attempts_per_hour_total' => (int) env('EXAMIS_ACCESS_CODE_ATTEMPTS_PER_HOUR_TOTAL', 100),
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
    | content checks only and is NOT a substitute for an antivirus engine;
    | "clamav" runs the same checks and then the ClamAV daemon (production).
    | `scan_inline` runs the scan during the upload, so the student never
    | waits on a queue worker. Turn it off for a slow scanner such as an
    | antivirus, which then runs on the `queue` below.
    |
    */

    /*
    | Shared with the server's backup job (deploy/backup): it writes
    | status.json here, and a "request" file here starts a manual backup.
    */
    'backups' => [
        'status_dir' => env('EXAMIS_BACKUP_STATUS_DIR', storage_path('app/backup-status')),
    ],

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'documents'),
        'scanner' => env('DOCUMENT_SCANNER', 'structural'),
        'scan_inline' => (bool) env('DOCUMENT_SCAN_INLINE', true),
        // Used when `scanner` is "clamav": the clamd socket (unix:// or tcp://).
        'clamav' => [
            'socket' => env('CLAMAV_SOCKET', 'unix:///run/clamav/clamd.ctl'),
            'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        ],
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
