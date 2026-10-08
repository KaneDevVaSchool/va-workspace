<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /** @var list<string> */
    private const LINK_PREVIEW_USER_AGENT_MARKERS = [
        'facebookexternalhit',
        'facebot',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'whatsapp',
        'telegrambot',
        'discordbot',
        'zalo',
    ];

    protected function inExceptArray($request): bool
    {
        if ($request->isMethod('GET') && $request->is('/') && $this->isLinkPreviewBot($request)) {
            return true;
        }

        return parent::inExceptArray($request);
    }

    private function isLinkPreviewBot($request): bool
    {
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return false;
        }

        foreach (self::LINK_PREVIEW_USER_AGENT_MARKERS as $marker) {
            if (str_contains($ua, $marker)) {
                return true;
            }
        }

        return false;
    }
}
