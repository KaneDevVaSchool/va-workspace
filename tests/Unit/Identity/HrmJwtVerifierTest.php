<?php

namespace Tests\Unit\Identity;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;
use Modules\Identity\App\Hrm\Services\HrmJwtVerifier;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class HrmJwtVerifierTest extends TestCase
{
    private OpenSSLAsymmetricKey $privateKey;

    private string $publicKeyPem;

    /** @var array<string, string> chi tiết RSA (n, e) để dựng JWKS giả */
    private array $rsaDetails;

    private string $kid = 'test-kid-0001';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('hrm.jwks');

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.api_token' => 'test-api-token',
            'services.hrm.sso_issuer' => 'https://hrm.test',
            'services.hrm.jwks_cache_ttl' => 21600,
        ]);

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        // Máy dev thiếu openssl.cnf (biến môi trường OPENSSL_CONF không trỏ tới
        // file cấu hình) sẽ làm openssl_pkey_new() trả false, và cả 5 test này
        // đổ với "Cannot get key from parameter 1" — lỗi môi trường, KHÔNG phải
        // lỗi code. Nói rõ nguyên nhân + cách sửa thay vì để lỗi khó hiểu.
        if ($keyPair === false) {
            $errors = [];
            while ($e = openssl_error_string()) {
                $errors[] = $e;
            }

            $this->markTestSkipped(
                'Không tạo được cặp khoá RSA — OpenSSL thiếu file cấu hình. '
                .'Đặt biến môi trường OPENSSL_CONF trỏ tới openssl.cnf của PHP '
                .'(ví dụ ServBay: C:\ServBay\packages\php\8.2\extras\ssl\openssl.cnf). '
                .'Chi tiết: '.implode(' | ', $errors)
            );
        }

        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKey = openssl_pkey_get_private($privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);
        $this->publicKeyPem = $details['key'];
        $this->rsaDetails = $details['rsa'];
    }

    /**
     * Fake JWKS endpoint trả khoá công khai thật của cặp khoá test.
     *
     * KHÔNG gọi trong setUp(): Http::fake() với mảng chỉ GỘP stub
     * (Factory::stubUrl() append vào cuối) chứ không thay thế, nên stub đăng ký
     * TRƯỚC luôn khớp trước. Nếu setUp() fake JWKS 200 thì test muốn mô phỏng
     * "JWKS sập" không thể ghi đè bằng 404 — JWKS vẫn thành công và nhánh
     * fallback không bao giờ chạy. Vì vậy mỗi test tự gọi hàm này khi cần.
     */
    private function fakeJwksAvailable(): void
    {
        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $this->kid,
                    'n' => rtrim(strtr(base64_encode($this->rsaDetails['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($this->rsaDetails['e']), '+/', '-_'), '='),
                ]],
            ], 200),
        ]);
    }

    private function makeToken(array $overrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://hrm.test',
            'aud' => 'va-workspace',
            'sub' => 'user-uuid-1',
            'jti' => 'jti-1',
            'iat' => $now,
            'exp' => $now + 600,
            'email' => 'someone@vaschools.edu.vn',
            'name' => 'Ai Đó',
            'employee_uuid' => 'employee-uuid-1',
            'roles' => ['member'],
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', $this->kid);
    }

    public function test_verify_accepts_valid_token(): void
    {
        $this->fakeJwksAvailable();
        $token = $this->makeToken();

        $claims = app(HrmJwtVerifier::class)->verify($token);

        $this->assertSame('user-uuid-1', $claims['sub']);
        $this->assertSame('va-workspace', $claims['aud']);
    }

    public function test_verify_rejects_token_signed_with_different_key(): void
    {
        $this->fakeJwksAvailable();
        $otherKeyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $now = time();
        $token = JWT::encode([
            'iss' => 'https://hrm.test',
            'aud' => 'va-workspace',
            'sub' => 'user-uuid-1',
            'iat' => $now,
            'exp' => $now + 600,
            'email' => 'x@vaschools.edu.vn',
        ], $otherKeyPair, 'RS256', $this->kid);

        $this->expectException(HrmTokenInvalid::class);
        app(HrmJwtVerifier::class)->verify($token);
    }

    public function test_verify_rejects_expired_token(): void
    {
        $this->fakeJwksAvailable();
        $token = $this->makeToken(['iat' => time() - 1200, 'exp' => time() - 600]);

        $this->expectException(HrmTokenInvalid::class);
        app(HrmJwtVerifier::class)->verify($token);
    }

    public function test_verify_rejects_issuer_mismatch(): void
    {
        $this->fakeJwksAvailable();
        $token = $this->makeToken(['iss' => 'https://someone-else.test']);

        $this->expectException(HrmTokenInvalid::class);
        app(HrmJwtVerifier::class)->verify($token);
    }

    public function test_verify_falls_back_to_verify_token_when_jwks_unavailable(): void
    {
        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response([], 404),
            'https://hrm.test/api/v1/auth/verify-token' => Http::response([
                'data' => [
                    'iss' => 'https://hrm.test',
                    'aud' => 'va-workspace',
                    'sub' => 'user-uuid-fallback',
                    'email' => 'fallback@vaschools.edu.vn',
                    'name' => 'Fallback User',
                ],
            ], 200),
        ]);

        $token = $this->makeToken(['sub' => 'user-uuid-fallback']);

        $claims = app(HrmJwtVerifier::class)->verify($token);

        $this->assertSame('user-uuid-fallback', $claims['sub']);
        Http::assertSent(fn ($request) => $request->url() === 'https://hrm.test/api/v1/auth/verify-token'
            && $request->method() === 'POST');
    }
}
