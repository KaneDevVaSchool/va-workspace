<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'VA Workspace') }}</title>
    <link rel="icon" href="/images/favicon.png" type="image/png">

    {{-- Layout gốc để mount Vue SPA. Không dùng cho khu vực manager/superadmin
         nếu những khu vực đó render bằng Blade + component nhỏ thay vì full SPA;
         tạo layout riêng trong Modules/{Ten}/Resources/views khi cần. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
