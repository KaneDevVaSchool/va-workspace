<?php

namespace Tests\Feature\App;

use App\Services\SpaOpenGraphResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class SpaOpenGraphResolverTest extends TestCase
{
    public function test_login_path_has_specific_preview(): void
    {
        $meta = app(SpaOpenGraphResolver::class)->resolve(Request::create('/login', 'GET'));

        $this->assertStringContainsString('Đăng nhập', $meta['title']);
        $this->assertNotEmpty($meta['description']);
        $this->assertNotEmpty($meta['image']);
    }

    public function test_employee_chat_path_preview(): void
    {
        $meta = app(SpaOpenGraphResolver::class)->resolve(
            Request::create('/dashboard/me/chat', 'GET'),
        );

        $this->assertStringContainsString('Tin nhắn', $meta['title']);
        $this->assertStringContainsString('VA Workspace', $meta['title']);
    }

    public function test_social_feed_path_preview(): void
    {
        $meta = app(SpaOpenGraphResolver::class)->resolve(
            Request::create('/dashboard/me/feed', 'GET'),
        );

        $this->assertStringContainsString('Bảng tin', $meta['title']);
    }
}
