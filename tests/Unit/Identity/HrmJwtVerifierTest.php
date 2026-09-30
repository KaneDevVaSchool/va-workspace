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

    private string $kid = 'test-kid-0001';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('hrm.jwks');

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.sso_issuer' => 'https://hrm.test',
            'services.hrm.jwks_cache_ttl' => 21600,
        ]);

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKey = openssl_pkey_get_private($privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);
        $this->publicKeyPem = $details['key'];
        $rsa = $details['rsa'];

        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $this->kid,
                    'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
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
        $token = $this->makeToken();

        $claims = (new HrmJwtVerifier())->verify($token);

        $this->assertSame('user-uuid-1', $claims['sub']);
        $this->assertSame('va-workspace', $claims['aud']);
    }

    public function test_verify_rejects_token_signed_with_different_key(): void
    {
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
        (new HrmJwtVerifier())->verify($token);
    }

    public function test_verify_rejects_expired_token(): void
    {
        $token = $this->makeToken(['iat' => time() - 1200, 'exp' => time() - 600]);

        $this->expectException(HrmTokenInvalid::class);
        (new HrmJwtVerifier())->verify($token);
    }

    public function test_verify_rejects_issuer_mismatch(): void
    {
        $token = $this->makeToken(['iss' => 'https://someone-else.test']);

        $this->expectException(HrmTokenInvalid::class);
        (new HrmJwtVerifier())->verify($token);
    }
}
