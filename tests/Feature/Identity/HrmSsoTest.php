<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Identity\Database\Seeders\RoleSeeder;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class HrmSsoTest extends TestCase
{
    use RefreshDatabase;

    private OpenSSLAsymmetricKey $privateKey;

    private string $kid = 'test-kid-sso';

    /** @var array<string, mixed> */
    private array $jwksPayload;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('hrm.jwks');

        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.sso_client_id' => 'va-workspace',
            'services.hrm.sso_issuer' => 'https://hrm.test',
            'services.hrm.sso_callback_url' => 'http://localhost/auth/hrm/callback',
            'services.hrm.jwks_cache_ttl' => 21600,
        ]);

        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKey = openssl_pkey_get_private($privateKeyPem);
        $details = openssl_pkey_get_details($keyPair);
        $rsa = $details['rsa'];

        $this->jwksPayload = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $this->kid,
                'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
            ]],
        ];

        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response($this->jwksPayload, 200),
        ]);
    }

    private function makeToken(array $overrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://hrm.test',
            'aud' => 'va-workspace',
            'sub' => 'hrm-user-1',
            'jti' => 'jti-1',
            'iat' => $now,
            'exp' => $now + 600,
            'email' => 'nhanvien@vaschools.edu.vn',
            'name' => 'Nguyễn Văn A',
            'employee_uuid' => null,
            'roles' => ['member'],
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', $this->kid);
    }

    public function test_redirect_stores_state_and_redirects_to_hrm(): void
    {
        $response = $this->get('/auth/hrm/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://hrm.test/sso/authorize?', $location);
        $this->assertStringContainsString('client_id=va-workspace', $location);
        $this->assertNotNull(session('hrm_sso.state'));
    }

    public function test_callback_with_valid_jwt_logs_in_and_creates_user_without_employee_profile(): void
    {
        $this->get('/auth/hrm/redirect');
        $state = session('hrm_sso.state');

        $token = $this->makeToken(['sub' => 'hrm-user-2', 'email' => 'novo@vaschools.edu.vn', 'employee_uuid' => null]);

        $response = $this->get("/auth/hrm/callback?token={$token}&state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/callback?status=ok', $response->headers->get('Location'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'novo@vaschools.edu.vn')->firstOrFail();
        $this->assertSame('hrm-user-2', $user->hrm_user_uuid);
        $this->assertNull($user->hrm_employee_uuid);
    }

    public function test_callback_creates_new_user_and_fetches_employee_profile(): void
    {
        Http::fake([
            'https://hrm.test/.well-known/jwks.json' => Http::response($this->jwksPayload, 200),
            'https://hrm.test/api/v1/employees/emp-uuid-3' => Http::response([
                'data' => [
                    'uuid' => 'emp-uuid-3',
                    'code' => 'NV003',
                    'full_name' => 'Người Có Hồ Sơ',
                    'status' => 'active',
                    'company_email' => 'comperfil@vaschools.edu.vn',
                    'manager_uuid' => null,
                    'manager_code' => null,
                    'manager_email' => null,
                    'primary_assignment' => [
                        'is_primary' => true,
                        'is_current' => true,
                        'effective_from' => '2024-01-01',
                        'effective_to' => null,
                        'company' => ['uuid' => 'co-1', 'code' => 'VAS', 'name' => 'VA Schools'],
                        'org_unit' => ['uuid' => 'ou-1', 'name' => 'Phòng CNTT', 'path' => '/1'],
                        'position' => ['uuid' => 'pos-1', 'code' => 'DEV', 'title' => 'Lập trình viên', 'level' => 3],
                    ],
                    'concurrent_assignments' => [],
                ],
            ], 200),
            'https://hrm.test/api/v1/employees/emp-uuid-3/manager' => Http::response(['data' => null], 200),
        ]);

        $this->get('/auth/hrm/redirect');
        $state = session('hrm_sso.state');
        $token = $this->makeToken(['sub' => 'hrm-user-3', 'email' => 'comperfil@vaschools.edu.vn', 'employee_uuid' => 'emp-uuid-3']);

        $response = $this->get("/auth/hrm/callback?token={$token}&state={$state}");

        $response->assertRedirect();
        $user = User::query()->where('email', 'comperfil@vaschools.edu.vn')->firstOrFail();
        $this->assertSame('NV003', $user->employee_code);
        $this->assertSame('Lập trình viên', $user->job_title_name);
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        $this->get('/auth/hrm/redirect');
        $token = $this->makeToken();

        $response = $this->get("/auth/hrm/callback?token={$token}&state=wrong-state");

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_callback_rejects_invalid_signature(): void
    {
        $this->get('/auth/hrm/redirect');
        $state = session('hrm_sso.state');

        $otherKeyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $now = time();
        $badToken = JWT::encode([
            'iss' => 'https://hrm.test', 'aud' => 'va-workspace', 'sub' => 'hrm-user-x',
            'iat' => $now, 'exp' => $now + 600, 'email' => 'x@vaschools.edu.vn',
        ], $otherKeyPair, 'RS256', $this->kid);

        $response = $this->get("/auth/hrm/callback?token={$badToken}&state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_terminated_user_cannot_login(): void
    {
        $this->seed(RoleSeeder::class);

        User::factory()->create([
            'email' => 'demitido@vaschools.edu.vn',
            'hrm_user_uuid' => 'hrm-user-terminado',
            'status' => 'inactive',
        ]);

        $this->get('/auth/hrm/redirect');
        $state = session('hrm_sso.state');
        $token = $this->makeToken(['sub' => 'hrm-user-terminado', 'email' => 'demitido@vaschools.edu.vn']);

        $response = $this->get("/auth/hrm/callback?token={$token}&state={$state}");

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
        $this->assertGuest();
    }
}
