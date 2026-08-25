<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Tests\TestCase;

class SocialDepartmentWallTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['status' => 'active'], $attributes));
    }

    public function test_department_post_does_not_appear_in_company_feed(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Kế toán', 'is_active' => true]);
        $user = $this->makeUser(['department_id' => $dept->id]);

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bài viết phòng ban', 'post_scope' => 'department'])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'department')
            ->assertJsonPath('post.department', 'Phòng Kế toán');

        $companyPosts = $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');

        $this->assertCount(0, $companyPosts);

        $departmentPosts = $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertOk()
            ->json('posts');

        $this->assertCount(1, $departmentPosts);
        $this->assertSame('department', $departmentPosts[0]['post_scope']);
    }

    public function test_company_post_does_not_appear_in_department_feed(): void
    {
        $dept = Department::query()->create(['code' => 'D1', 'name' => 'Phòng Kế toán', 'is_active' => true]);
        $user = $this->makeUser(['department_id' => $dept->id]);

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Bài viết chung'])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'company');

        $departmentPosts = $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertOk()
            ->json('posts');

        $this->assertCount(0, $departmentPosts);
    }

    public function test_user_from_other_department_does_not_see_department_post(): void
    {
        $deptA = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B', 'name' => 'Phòng B', 'is_active' => true]);

        $authorA = $this->makeUser(['department_id' => $deptA->id]);
        $memberB = $this->makeUser(['department_id' => $deptB->id]);

        $this->actingAs($authorA)
            ->postJson('/api/social/posts', ['content' => 'Chỉ phòng A xem', 'post_scope' => 'department'])
            ->assertCreated();

        $postsForB = $this->actingAs($memberB)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertOk()
            ->json('posts');

        $this->assertCount(0, $postsForB);
    }

    public function test_user_without_department_cannot_post_or_view_department_wall(): void
    {
        $user = $this->makeUser(['department_id' => null]);

        $this->actingAs($user)
            ->postJson('/api/social/posts', ['content' => 'Không có phòng ban', 'post_scope' => 'department'])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->getJson('/api/social/posts?post_scope=department')
            ->assertStatus(422);
    }
}
