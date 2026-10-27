<?php

namespace Tests\Unit\Social;

use Illuminate\Support\Facades\Http;
use Modules\Social\App\Models\SocialLinkPreview;
use Modules\Social\App\Services\SocialLinkPreviewService;
use Tests\TestCase;

class ExternalWebsiteLinkPreviewTest extends TestCase
{
    public function test_external_https_url_returns_website_preview_from_open_graph(): void
    {
        config(['app.url' => 'https://workspace.test']);

        Http::fake([
            'https://example.com/careers*' => Http::response(<<<'HTML'
<!DOCTYPE html>
<html><head>
<meta property="og:title" content="Careers – Example Co">
<meta property="og:description" content="Join our team today.">
<meta property="og:image" content="https://example.com/img/cover.jpg">
</head><body></body></html>
HTML, 200),
        ]);

        $preview = app(SocialLinkPreviewService::class)->previewForUrl('https://example.com/careers/');

        $this->assertNotNull($preview);
        $this->assertSame(SocialLinkPreview::PROVIDER_WEBSITE, $preview['provider']);
        $this->assertStringContainsString('Careers', $preview['title']);
        $this->assertStringContainsString('Join our team', $preview['description']);
        $this->assertSame('https://example.com/img/cover.jpg', $preview['thumbnail_url']);
    }
}
