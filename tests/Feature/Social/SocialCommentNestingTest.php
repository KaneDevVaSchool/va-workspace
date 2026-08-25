<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialCommentNestingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_comments_nest_two_levels_then_flatten(): void
    {
        $user = $this->makeUser();

        $postId = $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bài viết'])
            ->assertCreated()
            ->json('post.id');

        $rootId = $this->actingAs($user)
            ->postJson("/api/social/posts/{$postId}/comments", ['content' => 'Gốc'])
            ->assertCreated()
            ->json('comment.id');

        $level1 = $this->actingAs($user)
            ->postJson("/api/social/posts/{$postId}/comments", [
                'content' => 'Cấp 1',
                'parent_comment_id' => $rootId,
            ])
            ->assertCreated()
            ->json('comment');

        $this->assertSame($rootId, $level1['parent_comment_id']);

        $level2 = $this->actingAs($user)
            ->postJson("/api/social/posts/{$postId}/comments", [
                'content' => 'Cấp 2',
                'parent_comment_id' => $level1['id'],
            ])
            ->assertCreated()
            ->json('comment');

        $this->assertSame($level1['id'], $level2['parent_comment_id']);

        $flattened = $this->actingAs($user)
            ->postJson("/api/social/posts/{$postId}/comments", [
                'content' => 'Cấp 3 ghim vào cấp 1',
                'parent_comment_id' => $level2['id'],
            ])
            ->assertCreated()
            ->json('comment');

        $this->assertSame($level1['id'], $flattened['parent_comment_id']);

        $comments = $this->actingAs($user)
            ->getJson("/api/social/posts/{$postId}/comments")
            ->assertOk()
            ->json('comments');

        $this->assertCount(1, $comments);
        $this->assertCount(1, $comments[0]['replies']);
        $this->assertCount(2, $comments[0]['replies'][0]['replies']);
    }
}
