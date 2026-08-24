<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Google Workspace SSO (đăng nhập Google) — Modules/Identity.
    | redirect: cấu hình tĩnh (không đa host/tunnel như va-hrm) vì
    | va-workspace chạy 1 origin duy nhất ở giai đoạn này.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // Chỉ chấp nhận email thuộc các domain này (Google Workspace SSO).
        'allowed_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', ''))
        ))),
    ],

    /*
    | Email super_admin mặc định (Modules/Identity/Database/Seeders/SuperAdminSeeder.php)
    | — gán đủ 7 role hệ thống cho user này khi seed. Fallback hard-code
    | trong seeder nếu env trống, để seeder luôn chạy được.
    */
    'superadmin_email' => env('SUPERADMIN_EMAIL'),

];
