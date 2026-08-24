<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * SPA: trang đăng nhập là Vue `/login`, không phải named route Laravel.
     * API (`Accept: json`, XHR, hoặc `/api/*`) trả 401 JSON — không redirect HTML.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        return '/login';
    }
}
