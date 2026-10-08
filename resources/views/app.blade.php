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
    <link rel="icon" href="/images/favicon.png" type="image/png" sizes="32x32">
    {{-- iOS “Thêm vào Màn hình chính” — không dùng manifest icon --}}
    <link rel="apple-touch-icon" href="/images/pwa/apple-touch-icon.png" sizes="180x180">
    <link rel="apple-touch-icon" href="/images/pwa/icon-192.png" sizes="192x192">
    <link rel="manifest" href="/manifest.json">
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
