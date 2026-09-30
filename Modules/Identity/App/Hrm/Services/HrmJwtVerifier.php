<?php

namespace Modules\Identity\App\Hrm\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;
use UnexpectedValueException;

/**
 * Verify JWT SSO của VA-HRM offline bằng JWKS (RS256) — không gọi
 * POST /api/v1/auth/verify-token mỗi lần login, để giảm phụ thuộc mạng
 * vào HRM tại thời điểm callback (xem plan §4.1: JWKS offline được chọn
 * thay vì verify-token API).
 */
class HrmJwtVerifier
{
    private const CACHE_KEY = 'hrm.jwks';

    /**
     * @return array<string, mixed> claims đã xác thực
     * @throws HrmTokenInvalid
     */
    public function verify(string $jwt): array
    {
        $kid = $this->peekKid($jwt);

        try {
            $keys = JWK::parseKeySet($this->jwks());
            $key = $keys[$kid] ?? null;

            if ($key === null) {
                // Key có thể vừa xoay bên HRM — refetch 1 lần trước khi kết luận invalid.
                $keys = JWK::parseKeySet($this->jwks(forceRefresh: true));
                $key = $keys[$kid] ?? null;
            }

            if ($key === null) {
                throw new HrmTokenInvalid("không tìm thấy khoá xác thực (kid={$kid})");
            }

            $claims = (array) JWT::decode($jwt, $key);
        } catch (ExpiredException) {
            throw new HrmTokenInvalid('token đã hết hạn');
        } catch (SignatureInvalidException) {
            throw new HrmTokenInvalid('chữ ký không hợp lệ');
        } catch (UnexpectedValueException|\DomainException $e) {
            throw new HrmTokenInvalid('token không đọc được: '.$e->getMessage());
        } catch (HrmApiUnavailable $e) {
            throw new HrmTokenInvalid('không lấy được khoá xác thực JWKS: '.$e->getMessage());
        }

        $issuer = (string) config('services.hrm.sso_issuer');
        if ($issuer !== '' && ($claims['iss'] ?? null) !== $issuer) {
            throw new HrmTokenInvalid('issuer không khớp');
        }

        return $claims;
    }

    /** @throws HrmTokenInvalid */
    private function peekKid(string $jwt): string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new HrmTokenInvalid('định dạng JWT không hợp lệ');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || empty($header['kid'])) {
            throw new HrmTokenInvalid('header JWT không hợp lệ (yêu cầu RS256 + kid)');
        }

        return (string) $header['kid'];
    }

    /** Làm nóng cache JWKS chủ động (dùng bởi identity:hrm-warm-jwks). */
    public function warmCache(): void
    {
        $this->jwks(forceRefresh: true);
    }

    /**
     * @return array<string, mixed>
     * @throws HrmApiUnavailable
     */
    private function jwks(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $ttl = (int) config('services.hrm.jwks_cache_ttl', 21600);

        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            try {
                $response = Http::baseUrl((string) config('services.hrm.api_base_url'))
                    ->timeout(3)
                    ->retry(2, 200, throw: false)
                    ->get('/.well-known/jwks.json');
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new HrmApiUnavailable('không kết nối được JWKS endpoint', $e);
            }

            if (! $response->successful()) {
                throw new HrmApiUnavailable("JWKS endpoint trả HTTP {$response->status()}");
            }

            return $response->json();
        });
    }
}
