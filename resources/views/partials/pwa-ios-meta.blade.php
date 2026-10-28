{{--
  Meta + link PWA cho iOS (Safari “Thêm vào Màn hình chính”).
  Cấu hình: config/pwa_ios.php — splash PNG: scripts/generate-pwa-ios-splash.py
--}}
@php
    $pwaIconV = config('app.pwa_icon_version');
    $splashV = config('app.pwa_splash_version', '1');
    $iosPwa = config('pwa_ios');
    $iosTitle = $iosPwa['apple_web_app_title'] ?? 'VA Workspace';
    $iosStatusBar = $iosPwa['status_bar_style'] ?? 'default';
    $employeeMeShell = (bool) preg_match('#^/dashboard/me(/|$)#', request()->getPathInfo());
    $pwaThemeColor = $employeeMeShell
        ? ($iosPwa['employee_theme_color'] ?? '#1750b5')
        : ($iosPwa['theme_color'] ?? '#9a0036');
@endphp
<meta name="color-scheme" content="light">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="application-name" content="{{ $iosTitle }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="{{ $iosStatusBar }}">
<meta name="apple-mobile-web-app-title" content="{{ $iosTitle }}">
<meta name="theme-color" content="{{ $pwaThemeColor }}">
<link rel="icon" href="/images/favicon.png?v={{ $pwaIconV }}" type="image/png" sizes="32x32">
@foreach ($iosPwa['touch_icons'] ?? [] as $icon)
<link rel="apple-touch-icon" href="/{{ $icon['href'] }}?v={{ $pwaIconV }}" sizes="{{ $icon['sizes'] }}">
@endforeach
@foreach ($iosPwa['startup_images'] ?? [] as $splash)
<link
    rel="apple-touch-startup-image"
    href="/images/pwa/ios-splash/{{ $splash['file'] }}?v={{ $splashV }}"
    media="{{ $splash['media'] }}"
>
@endforeach
<link rel="manifest" href="/manifest.json?v={{ $pwaIconV }}">
