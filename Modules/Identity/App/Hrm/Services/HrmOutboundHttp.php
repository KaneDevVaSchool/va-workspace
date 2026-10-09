<?php

namespace Modules\Identity\App\Hrm\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** HTTP outbound tới VA-HRM — base URL, TLS verify, retry chung. */
final class HrmOutboundHttp
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('services.hrm.api_base_url'), '/');
    }

    /** Base URL catalog nghỉ phép — mặc định trùng HRM_API_BASE_URL (sau giai đoạn portal). */
    public static function leaveCatalogBaseUrl(): string
    {
        $override = config('services.hrm.leave_api_base_url');

        return rtrim((string) (filled($override) ? $override : config('services.hrm.api_base_url')), '/');
    }

    public static function client(bool $withApiToken = false, int $timeoutSeconds = 5): PendingRequest
    {
        return self::clientForBase(self::baseUrl(), $withApiToken, $timeoutSeconds, (string) config('services.hrm.api_token'));
    }

    /** HTTP tới GET /api/v1/leave/types (portal HRM trước, chuyển về hrm.vaschools.edu.vn sau). */
    public static function leaveCatalogClient(int $timeoutSeconds = 5): PendingRequest
    {
        $token = config('services.hrm.leave_api_token');
        $token = filled($token) ? (string) $token : (string) config('services.hrm.api_token');

        return self::clientForBase(self::leaveCatalogBaseUrl(), true, $timeoutSeconds, $token);
    }

    private static function clientForBase(string $baseUrl, bool $withApiToken, int $timeoutSeconds, string $apiToken): PendingRequest
    {
        $request = Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout($timeoutSeconds)
            ->retry(2, 200, throw: false)
            ->acceptJson();

        if ($withApiToken && $apiToken !== '') {
            $request = $request->withToken($apiToken);
        }

        return self::applyTlsOptions($request);
    }

    public static function applyTlsOptions(PendingRequest $request): PendingRequest
    {
        $verify = config('services.hrm.http_verify', true);

        if ($verify === false || $verify === 'false' || $verify === '0') {
            return $request->withOptions(['verify' => false]);
        }

        if (is_string($verify) && $verify !== '' && ! in_array($verify, ['true', '1'], true)) {
            return $request->withOptions(['verify' => $verify]);
        }

        return $request;
    }
}
