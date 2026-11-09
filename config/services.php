<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Cloudflare Access in front of the staff portal (App\Services\Staff\
    | CloudflareAccess). The token needs only Account > Access: Apps and
    | Policies > Edit. ALWAYS_ALLOW (comma separated) keeps the owner in even
    | if their staff account changes. Leave the token empty to turn it off.
    */
    'cloudflare_access' => [
        'account_id' => env('CLOUDFLARE_ACCESS_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_ACCESS_TOKEN'),
        'domain' => env('CLOUDFLARE_ACCESS_DOMAIN', env('EXAMIS_STAFF_HOST')),
        'policy' => env('CLOUDFLARE_ACCESS_POLICY', 'EXAMIS staff e-mails'),
        'always_allow' => array_values(array_filter(array_map('trim', explode(',', (string) env('CLOUDFLARE_ACCESS_ALWAYS_ALLOW', ''))))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
