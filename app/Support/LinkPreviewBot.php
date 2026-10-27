<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Nhận diện crawler unfurl link (Zalo, Facebook, Telegram…). */
final class LinkPreviewBot
{
    /** @var list<string> */
    private const USER_AGENT_MARKERS = [
        'facebookexternalhit',
        'facebot',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'whatsapp',
        'telegrambot',
        'discordbot',
        'zalo',
        'zalobot',
        'bingpreview',
        'googlebot',
    ];

    public static function matches(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '') {
            return false;
        }

        foreach (self::USER_AGENT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function matchesRequest(Request $request): bool
    {
        return self::matches($request->userAgent());
    }
}
