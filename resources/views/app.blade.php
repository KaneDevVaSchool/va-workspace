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
    <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
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

            var mobile = window.matchMedia('(max-width: 768px)').matches || s;
            if (!mobile) return;
            var viewport = document.querySelector('meta[name="viewport"]');
            if (viewport) {
                viewport.setAttribute(
                    'content',
                    'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, interactive-widget=resizes-content'
                );
            }
            var blockPinch = function (event) {
                event.preventDefault();
            };
            document.addEventListener('gesturestart', blockPinch, { passive: false });
            document.addEventListener('gesturechange', blockPinch, { passive: false });
            document.addEventListener('touchmove', function (event) {
                if (event.touches && event.touches.length > 1) blockPinch(event);
            }, { passive: false });
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

                // Bundle chưa chạy được (mất mạng / tải lỗi): đừng gỡ splash để lại trang trắng,
                // hiện thông báo + nút tải lại. Nếu app mount muộn, dismissAppBoot sẽ gỡ cả khối này.
                var mount = document.getElementById('app');
                if (mount && mount.childElementCount === 0) {
                    if (el.querySelector('[data-boot-failure]')) return;
                    var box = document.createElement('div');
                    box.setAttribute('data-boot-failure', '');
                    box.setAttribute('role', 'alert');
                    box.className = 'app-boot-failure';
                    var msg = document.createElement('p');
                    msg.className = 'app-boot-failure__text';
                    msg.textContent = navigator.onLine === false
                        ? 'Không có kết nối mạng. Kiểm tra lại mạng rồi tải lại ứng dụng.'
                        : 'Ứng dụng tải chậm hoặc không tải được. Bạn thử tải lại nhé.';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'app-boot-failure__btn';
                    btn.textContent = 'Tải lại';
                    btn.addEventListener('click', function () { window.location.reload(); });
                    box.appendChild(msg);
                    box.appendChild(btn);
                    el.appendChild(box);
                    window.addEventListener('online', function () { window.location.reload(); }, { once: true });
                    return;
                }

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

        /*
         * Khối báo lỗi khi bundle không tải được (hiện sau 12s, xem script phía trên). Ưu tiên token theme;
         * giá trị sau dấu phẩy chỉ là dự phòng vì chính lúc này theme.css có thể chưa tải được.
         */
        .app-boot-failure {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 99999;
            box-sizing: border-box;
            padding: 1.25rem;
            border-radius: 1rem 1rem 0 0;
            background: var(--color-surface, #ffffff);
            color: var(--color-text, #1f2430);
            font-family: var(--font-family-base, 'Be Vietnam Pro', sans-serif);
            text-align: center;
            box-shadow: var(--shadow-lg, 0 -4px 24px rgba(0, 0, 0, 0.12));
        }

        .app-boot-failure__text {
            margin: 0 0 1rem;
            font-size: 0.9375rem;
            line-height: 1.5;
        }

        .app-boot-failure__btn {
            min-height: 2.75rem;
            padding: 0.75rem 1.5rem;
            border: 0;
            border-radius: 0.75rem;
            background: var(--color-primary-900, #9a0036);
            color: var(--color-on-primary, #ffffff);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
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
