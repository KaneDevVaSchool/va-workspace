<!DOCTYPE html>
<html lang="vi" data-theme="light" data-pwa-app-root>
<head>
    <meta charset="utf-8">
    @php
        // UTF-8 có dấu; không dùng em dash (—) — parser preview Zalo hay chỉ lấy og:image, bỏ title/mô tả.
        $ogTitle = 'VA Workspace - Hệ thống quản lý nội bộ VA Schools';
        $ogDescription = 'Hệ thống quản lý nội bộ VA Schools. Quản lý công việc, nhân sự, tài sản và quy trình trên một nền tảng.';
        $ogCoverV = (string) config('app.og_cover_version', '1');
        $ogImage = asset('images/og-cover.jpg').'?v='.rawurlencode($ogCoverV);
        $path = request()->getPathInfo();
        $ogPageUrl = rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path);
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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
                var probe = document.createElement('div');
                probe.setAttribute('aria-hidden', 'true');
                probe.style.cssText =
                    'position:fixed;top:0;left:0;width:0;height:0;visibility:hidden;pointer-events:none;padding:0;' +
                    'padding-top:constant(safe-area-inset-top);padding-top:env(safe-area-inset-top);' +
                    'padding-right:constant(safe-area-inset-right);padding-right:env(safe-area-inset-right);' +
                    'padding-bottom:constant(safe-area-inset-bottom);padding-bottom:env(safe-area-inset-bottom);' +
                    'padding-left:constant(safe-area-inset-left);padding-left:env(safe-area-inset-left)';
                document.documentElement.appendChild(probe);
                var st = getComputedStyle(probe);
                var map = [
                    ['--employee-safe-top', 'paddingTop'],
                    ['--employee-safe-right', 'paddingRight'],
                    ['--employee-safe-bottom', 'paddingBottom'],
                    ['--employee-safe-left', 'paddingLeft'],
                ];
                var root = document.documentElement;
                for (var i = 0; i < map.length; i++) {
                    var px = parseFloat(st[map[i][1]]);
                    if (px > 0) root.style.setProperty(map[i][0], px + 'px');
                }
                if (isIos && !(parseFloat(st.paddingBottom) > 0) && window.screen && window.screen.height >= 812) {
                    root.style.setProperty('--employee-safe-bottom', '34px');
                }
                probe.remove();
                if (isIos) {
                    var applyIosShellH = function () {
                        var h = Math.max(
                            window.innerHeight || 0,
                            document.documentElement.clientHeight || 0
                        );
                        if (h > 0) {
                            root.style.setProperty('--employee-shell-height', h + 'px');
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
            height: 100%;
            min-height: 0;
            overflow: hidden;
            background-color: #9a0036;
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
            body.app-boot-active .app-boot { display: none !important; }
            body.app-boot-active { background: #fff; }
        </style>
        <main class="app-noscript">
            <h1>VA Workspace</h1>
            <p>Hệ thống quản lý nội bộ VA Schools: công việc, chấm công, nghỉ phép và quy trình nội bộ.</p>
            <p>Ứng dụng cần bật JavaScript. Bạn vẫn có thể <a href="/login">đăng nhập</a> sau khi bật JavaScript trong trình duyệt.</p>
        </main>
    </noscript>
    <main id="app-boot" class="app-boot" role="status" aria-live="polite" aria-busy="true">
        <img
            src="/images/background/background-logo.png"
            alt=""
            class="app-boot__watermark"
            aria-hidden="true"
            decoding="async"
            fetchpriority="low"
        />
        <div class="app-boot__scrim" aria-hidden="true"></div>
        <div class="app-boot__inner">
            <img
                src="/images/logo-2.png"
                alt="Vietnam America Schools"
                class="app-boot__logo"
                width="220"
                height="80"
                decoding="async"
                fetchpriority="high"
            />
            <p class="app-boot__brand">VA Workspace</p>
            <div class="app-boot__spinner" aria-hidden="true"></div>
            <p class="app-boot__hint">Đang tải…</p>
        </div>
    </main>
    <div id="app" data-pwa-app-mount></div>
</body>
</html>
