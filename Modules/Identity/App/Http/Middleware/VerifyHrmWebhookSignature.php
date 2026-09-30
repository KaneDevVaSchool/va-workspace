<?php

namespace Modules\Identity\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực chữ ký HMAC-SHA256 (hex) của webhook VA-HRM. BẮT BUỘC dùng
 * $request->getContent() (raw body) — không re-encode $request->all(),
 * vì thứ tự key/whitespace khác sẽ làm signature lệch so với chuỗi HRM
 * đã ký.
 */
class VerifyHrmWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.hrm.webhook_secret');

        if ($secret === '') {
            abort(500, 'Webhook VA-HRM chưa cấu hình secret.');
        }

        $signature = (string) $request->header('X-VA-HRM-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            abort(401, 'Chữ ký webhook không hợp lệ.');
        }

        return $next($request);
    }
}
