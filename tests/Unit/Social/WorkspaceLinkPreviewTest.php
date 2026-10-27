<?php

namespace Tests\Unit\Social;

use Modules\Social\App\Models\SocialLinkPreview;
use Modules\Social\App\Services\SocialLinkPreviewService;
use Tests\TestCase;

class WorkspaceLinkPreviewTest extends TestCase
{
    public function test_internal_login_url_returns_workspace_preview_card_data(): void
    {
        config(['app.url' => 'https://workspace.test']);

        $preview = app(SocialLinkPreviewService::class)->previewForUrl('https://workspace.test/login');

        $this->assertNotNull($preview);
        $this->assertSame(SocialLinkPreview::PROVIDER_WORKSPACE, $preview['provider']);
        $this->assertStringContainsString('Đăng nhập', (string) $preview['title']);
        $this->assertNotEmpty($preview['description']);
        $this->assertSame('workspace.test', $preview['site_name']);
    }
}
