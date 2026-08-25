<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Social\App\Models\SocialPost;
use Tests\TestCase;

class SocialPostContentSanitizeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_script_tag_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<script>alert(1)</script>Xin chào'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('<script', $post->content);
        $this->assertStringContainsString('Xin chào', $post->content);
    }

    public function test_img_onerror_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<img src=x onerror="alert(1)">Nội dung'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('<img', $post->content);
        $this->assertStringNotContainsString('onerror', $post->content);
    }

    public function test_javascript_scheme_link_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<a href="javascript:alert(1)">click</a>'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('javascript:', $post->content);
    }

    public function test_data_scheme_link_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => '<a href="data:text/html,<script>alert(1)</script>">x</a>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('data:', $post->content);
        $this->assertStringNotContainsString('<script', $post->content);
    }

    public function test_style_attribute_only_keeps_color(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => '<span style="color:red;background:url(javascript:alert(1))">x</span>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('background', $post->content);
        $this->assertStringNotContainsString('javascript:', $post->content);
    }

    public function test_onclick_attribute_is_stripped_but_tag_kept(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<p onclick="alert(1)">x</p>'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('onclick', $post->content);
        $this->assertStringContainsString('<p>', $post->content);
    }

    public function test_whitelisted_tags_are_kept(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<b>bold</b><strong>bold2</strong>'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringContainsString('bold', $post->content);
        $this->assertStringContainsString('bold2', $post->content);
    }

    public function test_script_only_content_is_rejected_as_empty(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => '<script>alert(1)</script>'])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertSame('', trim(strip_tags($post->content)));
    }

    public function test_font_size_style_is_kept_when_valid(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => '<span style="font-size: 24px; color: #9a0036">Chữ to</span>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringContainsString('font-size', $post->content);
        $this->assertStringContainsString('24px', $post->content);
        $this->assertStringContainsString('Chữ to', $post->content);
    }

    public function test_css_expression_in_font_size_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => '<span style="font-size: expression(alert(1))">x</span>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('expression', $post->content);
    }

    public function test_arbitrary_css_property_other_than_color_and_font_size_is_stripped(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson('/api/social/posts', [
                'content' => '<span style="color:#9a0036;position:fixed;top:0;left:0;width:100vw">x</span>',
            ])
            ->assertCreated();

        $post = SocialPost::query()->latest('id')->first();

        $this->assertStringNotContainsString('position', $post->content);
        $this->assertStringNotContainsString('fixed', $post->content);
    }
}
