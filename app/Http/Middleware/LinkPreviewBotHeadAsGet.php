<?php

namespace App\Http\Middleware;

use App\Support\LinkPreviewBot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zalo hay gửi HEAD trước; Laravel trả body rỗng nên parser không thấy og:* trong HTML.
 */
final class LinkPreviewBotHeadAsGet
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('HEAD') && LinkPreviewBot::matchesRequest($request)) {
            $request->setMethod('GET');
        }

        return $next($request);
    }
}
