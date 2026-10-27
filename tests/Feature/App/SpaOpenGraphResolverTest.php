<?php

namespace Tests\Feature\App;

use App\Models\User;
use App\Services\SpaOpenGraphResolver;
use Illuminate\Http\Request;
use Modules\Social\App\Models\SocialPost;
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

    public function test_social_post_query_builds_author_and_excerpt(): void
    {
        $author = User::factory()->create(['name' => 'Lan Hương']);
        $post = SocialPost::query()->create([
            'user_id' => $author->id,
            'content' => '<p>Nội dung bài viết thử nghiệm preview link.</p>',
            'review_status' => SocialPost::REVIEW_APPROVED,
            'department_visibility_mode' => SocialPost::DEPARTMENT_VISIBILITY_ALL,
        ]);

        $meta = app(SpaOpenGraphResolver::class)->resolve(
            Request::create('/social?post='.$post->id, 'GET'),
        );

        $this->assertStringContainsString('Lan Hương', $meta['title']);
        $this->assertStringContainsString('preview link', $meta['description']);
    }

    public function test_wall_query_uses_member_name(): void
    {
        $user = User::factory()->create(['name' => 'Minh Đức']);

        $meta = app(SpaOpenGraphResolver::class)->resolve(
            Request::create('/social?wall='.$user->id, 'GET'),
        );

        $this->assertStringContainsString('Minh Đức', $meta['title']);
        $this->assertStringContainsString('Tường', $meta['title']);
    }
}
