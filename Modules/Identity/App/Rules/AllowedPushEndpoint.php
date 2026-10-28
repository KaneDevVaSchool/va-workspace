<?php

namespace Modules\Identity\App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Endpoint Web Push phải là https và thuộc dịch vụ push của trình duyệt
 * (config services.webpush.allowed_hosts). Server sẽ POST tới URL này khi gửi
 * thông báo, nên không nhận host tuỳ ý — tránh bị lợi dụng gọi vào mạng nội bộ.
 */
class AllowedPushEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isAllowed($value)) {
            $fail('Địa chỉ nhận thông báo đẩy không hợp lệ.');
        }
    }

    public static function isAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        // Chặn userinfo (https://fcm.googleapis.com@evil.test/...) và cổng lạ.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        foreach ((array) config('services.webpush.allowed_hosts', []) as $allowed) {
            $allowed = strtolower((string) $allowed);
            if ($allowed === '') {
                continue;
            }
            // ".push.apple.com" khớp mọi subdomain (không khớp chính "push.apple.com").
            if (str_starts_with($allowed, '.')) {
                if (str_ends_with($host, $allowed)) {
                    return true;
                }
            } elseif ($host === $allowed) {
                return true;
            }
        }

        return false;
    }
}
