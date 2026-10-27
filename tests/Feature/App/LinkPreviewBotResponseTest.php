<?php

namespace Tests\Feature\App;

use Tests\TestCase;

class LinkPreviewBotResponseTest extends TestCase
{
    public function test_zalo_bot_gets_minimal_link_preview_html_for_share_path(): void
    {
        $response = $this->get('/share', [
            'User-Agent' => 'ZaloBot/1.0',
        ]);

        $response->assertOk();
        $response->assertSee('og:title', false);
        $response->assertSee('Giới thiệu', false);
        $response->assertDontSee('app-boot', false);
        $response->assertDontSee('·', false);
    }
}
