<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script>
        (function () {
            var s =
                window.matchMedia('(display-mode: standalone)').matches ||
                window.matchMedia('(display-mode: fullscreen)').matches ||
                window.navigator.standalone === true;
            if (s) document.documentElement.classList.add('pwa-standalone');
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    <meta name="theme-color" content="#9a0036">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="VA Workspace">
    <title>{{ config('app.name', 'VA Workspace') }}</title>
    @php
        // URL tuyệt đối cùng domain công khai — Zalo/FB không fetch được localhost hay URL tương đối.
        $ogPageUrl = url()->current();
        $ogImage = asset('images/og-cover.jpg');
        $ogTitle = 'VA Workspace — Hệ thống quản lý nội bộ VA Schools';
        $ogDescription = 'Hệ thống quản lý nội bộ VA Schools: quản lý công việc, nhân sự, tài sản và quy trình trong một nền tảng duy nhất.';
    @endphp
    {{-- Open Graph / Twitter Card — preview khi share link lên Zalo, Facebook, Messenger, Slack --}}
    <meta property="og:type" content="website">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:site_name" content="VA Workspace">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $ogPageUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    @if (str_starts_with($ogImage, 'https://'))
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    @endif
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="VA Workspace — Vietnam America Schools">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @php($pwaIconV = config('app.pwa_icon_version'))
    <link rel="icon" href="/images/favicon.png?v={{ $pwaIconV }}" type="image/png" sizes="32x32">
    {{-- iOS “Thêm vào Màn hình chính” — URL ?v= bắt buộc khi đổi logo (Safari cache cứng) --}}
    <link rel="apple-touch-icon" href="/images/pwa/apple-touch-icon.png?v={{ $pwaIconV }}" sizes="180x180">
    <link rel="apple-touch-icon" href="/images/pwa/icon-192.png?v={{ $pwaIconV }}" sizes="192x192">
    <link rel="manifest" href="/manifest.json?v={{ $pwaIconV }}">
    {{-- Boot splash: hiện ngay trước Vite — tránh màn trắng khi mở PWA / reload --}}
    <style>
        body.app-boot-active {
            margin: 0;
            background: #9a0036;
        }

        #app-boot {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: max(1.25rem, env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right))
                max(1.25rem, env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left));
            background: #9a0036;
        }

        html.pwa-standalone,
        html.pwa-standalone body {
            min-height: 100dvh;
            min-height: -webkit-fill-available;
            background-color: #9a0036;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-boot-active">
    <div id="app-boot" class="app-boot" role="status" aria-live="polite" aria-busy="true">
        <img
            src="/images/background/background-logo.png"
            alt=""
            class="app-boot__watermark"
            aria-hidden="true"
        />
        <div class="app-boot__scrim" aria-hidden="true"></div>
        <div class="app-boot__inner">
            <img
                src="/images/logo-2.png"
                alt="Vietnam America Schools"
                class="app-boot__logo"
            />
            <p class="app-boot__brand">VA Workspace</p>
            <div class="app-boot__spinner" aria-hidden="true"></div>
            <p class="app-boot__hint">Đang tải…</p>
        </div>
    </div>
    <div id="app"></div>
</body>
</html>
