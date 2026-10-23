<?php

/*
| iOS “Thêm vào Màn hình chính” — apple-touch-startup-image theo media query.
| Sinh PNG: php artisan pwa:ios-splash hoặc python scripts/generate-pwa-ios-splash.py
| Bump app.pwa_splash_version khi đổi splash.
*/

$ratio3 = '(-webkit-device-pixel-ratio: 3)';
$ratio2 = '(-webkit-device-pixel-ratio: 2)';

return [

    'apple_web_app_title' => 'VA Workspace',
    'status_bar_style' => 'black-translucent',
    'orientation' => 'portrait',

    'touch_icons' => [
        ['href' => 'images/pwa/apple-touch-icon.png', 'sizes' => '180x180'],
        ['href' => 'images/pwa/icon-192.png', 'sizes' => '192x192'],
    ],

    /*
     * Portrait — device-width/height = CSS px (Apple HIG / ios-metafilename).
     * href trỏ file WxH thật (pixel) trong public/images/pwa/ios-splash/
     */
    'startup_images' => [
        // iPhone SE (2nd/3rd), 8
        [
            'file' => '750x1334.png',
            'media' => "(device-width: 375px) and (device-height: 667px) and {$ratio2} and (orientation: portrait)",
        ],
        // iPhone 12/13 mini, X, XS, 11 Pro
        [
            'file' => '1125x2436.png',
            'media' => "(device-width: 375px) and (device-height: 812px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 12/13/14, 15, 16 (6.1")
        [
            'file' => '1170x2532.png',
            'media' => "(device-width: 390px) and (device-height: 844px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 15/16 Pro (6.3")
        [
            'file' => '1206x2622.png',
            'media' => "(device-width: 402px) and (device-height: 874px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 14/15/16 Pro (393×852)
        [
            'file' => '1179x2556.png',
            'media' => "(device-width: 393px) and (device-height: 852px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 14 Plus, 13 Pro Max (428×926)
        [
            'file' => '1284x2778.png',
            'media' => "(device-width: 428px) and (device-height: 926px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 15 Plus, 15/16 Pro Max, 16 Plus (430×932)
        [
            'file' => '1290x2796.png',
            'media' => "(device-width: 430px) and (device-height: 932px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPhone 16 Pro Max (440×956)
        [
            'file' => '1320x2868.png',
            'media' => "(device-width: 440px) and (device-height: 956px) and {$ratio3} and (orientation: portrait)",
        ],
        // iPad mini, Air 10.5"
        [
            'file' => '1536x2048.png',
            'media' => "(device-width: 768px) and (device-height: 1024px) and {$ratio2} and (orientation: portrait)",
        ],
        // iPad Pro 11"
        [
            'file' => '1668x2388.png',
            'media' => "(device-width: 834px) and (device-height: 1194px) and {$ratio2} and (orientation: portrait)",
        ],
        // iPad Pro 12.9"
        [
            'file' => '2048x2732.png',
            'media' => "(device-width: 1024px) and (device-height: 1366px) and {$ratio2} and (orientation: portrait)",
        ],
    ],

];
