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

    /*
    | Web Push (VAPID) — chuông header + thông báo đẩy trình duyệt.
    | Tạo khóa: php artisan identity:vapid-keys --write
    */
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'workspace@vaschools.edu.vn')),
    ],

    /*
    | GIPHY (tìm & tải GIF/sticker động cho bảng tin) — Modules/Social.
    | Đăng ký key miễn phí tại developers.giphy.com.
    */
    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],

    /*
    | VA Pictures (service Go riêng, upload ảnh lên Amazon S3) — dùng để lưu
    | ảnh/tệp đính kèm bài viết Social thay vì lưu local disk, tránh giới hạn
    | client_max_body_size/upload_max_filesize của web server chính.
    | Xem C:\Users\ASUS\Desktop\vaschools-app\va-pictures (docs/API.md, mục
    | POST /api/v1/upload).
    */
    'va_pictures' => [
        'base_url' => env('VA_PICTURES_BASE_URL', 'https://pictures.vaschools.edu.vn'),
        'api_key' => env('VA_PICTURES_API_KEY'),
    ],

];
