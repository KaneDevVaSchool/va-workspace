<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * '*' vì server production chạy sau reverse proxy/CDN (Nginx...) và
     * không nhận request trực tiếp từ internet — an toàn để tin mọi proxy.
     * Thiếu dòng này khiến Laravel không đọc header X-Forwarded-Proto/Host,
     * nên tự nhận nhầm scheme/host (vd http thay vì https) → URL callback
     * Google OAuth và session cookie lệch nhau giữa 2 request, gây lỗi giả
     * "Phiên đăng nhập đã hết hạn" (InvalidStateException) ngẫu nhiên dù
     * người dùng đăng nhập đúng cách.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
