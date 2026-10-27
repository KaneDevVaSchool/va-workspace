<!DOCTYPE html>
<html lang="vi" data-theme="light" data-pwa-app-root>
<head>
    <meta charset="utf-8">
    @php
        // UTF-8 có dấu; không dùng em dash (—) — parser preview Zalo hay chỉ lấy og:image, bỏ title/mô tả.
        $ogMeta = app(\App\Services\SpaOpenGraphResolver::class)->resolve(request());
        $ogTitle = $ogMeta['title'];
        $ogDescription = $ogMeta['description'];
        $ogImage = $ogMeta['image'] ?? asset('images/og-cover.jpg');
        $ogPageUrl = $ogMeta['url'];
    @endphp
    {{-- Open Graph đặt sớm trong <head> — bot Zalo/FB đọc meta trước khi cần JS --}}
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
    <meta property="og:image:alt" content="VA Workspace - Vietnam America Schools">
    <meta name="description" content="{{ $ogDescription }}">
    <meta name="title" content="{{ $ogTitle }}">
    <meta itemprop="name" content="{{ $ogTitle }}">
    <meta itemprop="description" content="{{ $ogDescription }}">
    <meta itemprop="image" content="{{ $ogImage }}">
    <link rel="canonical" href="{{ $ogPageUrl }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function () {
            var ua = window.navigator.userAgent || '';
            var isIos = /iPad|iPhone|iPod/.test(ua) ||
                (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
            var isAndroid = /Android/.test(ua);
            var osRoot = document.documentElement;
            if (isIos) {
                osRoot.classList.add('employee-os-ios');
            } else if (isAndroid) {
                osRoot.classList.add('employee-os-android');
            }

            var s =
                window.matchMedia('(display-mode: standalone)').matches ||
                window.matchMedia('(display-mode: fullscreen)').matches ||
                window.navigator.standalone === true;
            if (s) {
                document.documentElement.classList.add('pwa-standalone');
                var path = window.location.pathname || '';
                if (/^\/dashboard\/me(\/|$)/.test(path)) {
                    osRoot.classList.add('employee-mobile-pwa');
                }
                if (isIos) {
                    var applyIosShellH = function () {
                        var h = Math.max(
                            window.innerHeight || 0,
                            document.documentElement.clientHeight || 0
                        );
                        if (h > 0) {
                            osRoot.style.setProperty('--employee-shell-height', h + 'px');
                        }
                    };
                    applyIosShellH();
                    window.addEventListener('resize', applyIosShellH, { passive: true });
                    window.visualViewport && window.visualViewport.addEventListener('resize', applyIosShellH, { passive: true });
                }
                if (/^\/dashboard\/me(\/|$)/.test(path)) {
                    osRoot.classList.add('pwa-surface-employee');
                }
            }
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    <title>{{ $ogTitle }}</title>
    @include('partials.pwa-ios-meta')
    {{-- Đăng ký SW sớm (trước bundle Vite) để precache shell + offline pass Lighthouse --}}
    <script>
        (function () {
            if (!('serviceWorker' in navigator)) return;
            window.__vaSwRegisterPromise = navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            });
        })();
    </script>
    {{-- Boot splash: welcome mascot chỉ mobile (≤768px); desktop splash gọn --}}
    <link rel="preload" as="image" href="/images/onboarding/cheerful-red-mascot.png" fetchpriority="high" media="(max-width: 768px)">
    <link rel="preload" as="image" href="/images/onboarding/va-logo.png" media="(max-width: 768px)">
    <script>
        (function () {
            var root = document.documentElement;
            var mobile = window.matchMedia('(max-width: 768px)').matches;
            root.classList.add(mobile ? 'va-boot-mobile' : 'va-boot-desktop');
            window.setTimeout(function () {
                var el = document.getElementById('app-boot');
                if (!el || el.classList.contains('welcome--hide')) return;
                document.body.classList.remove('app-boot-active');
                el.classList.add('welcome--hide');
                window.setTimeout(function () { el.remove(); }, 480);
            }, 12000);
        })();
    </script>
    <style>
        body.app-boot-active {
            margin: 0;
            background: #9a0036;
        }

        html.va-boot-mobile body.app-boot-active {
            background: #fff8f0;
        }

        html.pwa-standalone,
        html.pwa-standalone body {
            height: 100%;
            min-height: 0;
            overflow: hidden;
            background-color: #9a0036;
        }

        html.va-boot-mobile.pwa-standalone,
        html.va-boot-mobile.pwa-standalone body {
            background-color: #fff8f0;
        }

        html.pwa-standalone.pwa-surface-employee,
        html.pwa-standalone.pwa-surface-employee body {
            background-color: #eef4f6;
        }

        html.pwa-standalone.employee-os-ios.employee-mobile-pwa,
        html.pwa-standalone.employee-os-ios.employee-mobile-pwa body {
            height: var(--employee-shell-height, 100%);
            max-height: var(--employee-shell-height, 100%);
            background-color: #eef4f6;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/css/pwa-standalone.css', 'resources/js/app.js'])
</head>
<body class="app-boot-active">
    <noscript>
        <style>
            body.app-boot-active #app-boot { display: none !important; }
            body.app-boot-active { background: #fff; }
        </style>
        <main class="app-noscript">
            <h1>VA Workspace</h1>
            <p>Hệ thống quản lý nội bộ VA Schools: công việc, chấm công, nghỉ phép và quy trình nội bộ.</p>
            <p>Ứng dụng cần bật JavaScript. Bạn vẫn có thể <a href="/login">đăng nhập</a> sau khi bật JavaScript trong trình duyệt.</p>
        </main>
    </noscript>
    <main id="app-boot" class="welcome welcome--boot" role="status" aria-live="polite" aria-busy="true" aria-labelledby="app-boot-title">
        <div class="welcome__scene" aria-hidden="true">
            <img
                class="welcome__background"
                src="/images/onboarding/cheerful-red-mascot.png"
                alt=""
                decoding="async"
                fetchpriority="high"
            />
        </div>
        <div class="welcome__wash" aria-hidden="true"></div>

        <header class="welcome__header">
            <img
                class="welcome__logo"
                src="/images/onboarding/va-logo.png"
                alt="Vietnam America Schools"
                width="100"
                height="116"
                decoding="async"
                fetchpriority="high"
            />
            <div class="welcome__heading">
                <p class="welcome__eyebrow">Chào mừng bạn đến với</p>
                <h1 id="app-boot-title" class="welcome__title">VA Workspace</h1>
                <div class="welcome__rule" aria-hidden="true"></div>
                <p class="welcome__intro">Một không gian. Mọi kết nối.</p>
            </div>
        </header>

        <div class="welcome__boot-compact">
            <img
                src="/images/logo-2.png"
                alt="Vietnam America Schools"
                class="welcome__boot-compact-logo"
                width="220"
                height="80"
                decoding="async"
            />
            <p class="welcome__boot-compact-brand">VA Workspace</p>
            <div class="welcome__boot-spinner" aria-hidden="true"></div>
            <p class="welcome__boot-hint">Đang tải…</p>
        </div>

        <footer class="welcome__footer welcome__footer--boot">
            <div class="welcome__boot-status">
                <div class="welcome__boot-spinner" aria-hidden="true"></div>
                <p class="welcome__boot-hint">Đang tải…</p>
            </div>
            <span class="welcome__school">Vietnam America Schools</span>
        </footer>
    </main>
    <div id="app" data-pwa-app-mount></div>
</body>
</html>
