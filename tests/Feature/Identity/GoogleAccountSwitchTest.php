<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

/**
 * Đổi tài khoản Google khi đang có session Workspace — lý do nút Google tồn
 * tại song song với VA-HRM SSO (HRM /sso/authorize giữ session nên phát JWT
 * ngay cho user cũ, không hiện màn hình chọn tài khoản).
 *
 * Redirect cho khách: xem GoogleLoginRedirectTest (không cần DB).
 */
class GoogleAccountSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://workspace.test/auth/google/callback',
            'services.google.allowed_domains' => ['vaschools.edu.vn', 'hcm.vaschools.edu.vn'],
        ]);
    }

    public function test_authenticated_user_without_switch_flag_goes_home(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureGoogle();

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get('/auth/google/redirect')
            ->assertRedirect(rtrim((string) config('app.url'), '/').'/');

        $this->assertAuthenticated();
    }

    public function test_switch_flag_logs_current_user_out_and_goes_to_google(): void
    {
        $this->seed(RoleSeeder::class);
        $this->configureGoogle();

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->get('/auth/google/redirect?switch=1');

        $target = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $target);
        $this->assertStringContainsString('prompt=select_account', $target);

        // Session cũ phải bị huỷ, nếu không Google trả về đúng user cũ.
        $this->assertGuest();
    }
}
