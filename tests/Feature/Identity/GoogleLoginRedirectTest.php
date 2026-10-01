<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;

/**
 * Redirect sang Google cho khách (chưa đăng nhập) — không chạm DB nên không
 * dùng RefreshDatabase, chạy được cả khi MySQL test chưa bật.
 */
class GoogleLoginRedirectTest extends TestCase
{
    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://workspace.test/auth/google/callback',
            'services.google.allowed_domains' => ['vaschools.edu.vn', 'hcm.vaschools.edu.vn'],
        ]);
    }

    public function test_redirect_sends_guest_to_google_with_select_account_prompt(): void
    {
        $this->configureGoogle();

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $target);
        $this->assertStringContainsString('prompt=select_account', $target);
    }

    /** `hd` khoá vào 1 domain, sẽ ẩn tài khoản thuộc domain còn lại. */
    public function test_redirect_does_not_restrict_to_a_single_hosted_domain(): void
    {
        $this->configureGoogle();

        $target = (string) $this->get('/auth/google/redirect')->headers->get('Location');

        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertArrayNotHasKey('hd', $query);
    }

    public function test_redirect_fails_cleanly_when_google_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirectContains('/login');
        $response->assertRedirectContains('error=');
    }

    public function test_hrm_sso_route_still_available(): void
    {
        config([
            'services.hrm.api_base_url' => 'https://hrm.test',
            'services.hrm.sso_client_id' => 'va-workspace',
            'services.hrm.sso_callback_url' => 'https://workspace.test/auth/hrm/callback',
        ]);

        $this->get('/auth/hrm/redirect')
            ->assertRedirectContains('https://hrm.test/sso/authorize');
    }
}
