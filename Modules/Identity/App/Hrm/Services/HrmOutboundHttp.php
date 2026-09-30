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

    public static function client(bool $withApiToken = false, int $timeoutSeconds = 5): PendingRequest
    {
        $request = Http::baseUrl(self::baseUrl())
            ->timeout($timeoutSeconds)
            ->retry(2, 200, throw: false)
            ->acceptJson();

        if ($withApiToken) {
            $request = $request->withToken((string) config('services.hrm.api_token'));
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
