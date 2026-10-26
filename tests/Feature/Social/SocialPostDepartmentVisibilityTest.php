<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;
use Tests\TestCase;

class SocialPostDepartmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['status' => 'active'], $attributes));
    }

    private function makeDept(string $code, string $name): Department
    {
        return Department::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
    }

    public function test_department_picker_lists_only_active_hrm_catalog(): void
    {
        $company = Company::upsertFromHrm('co-vid', 'VID', 'VA Schools');
        $hrm = Department::query()->create([
            'code' => 'VID_PCN',
            'name' => 'Phòng Công nghệ',
            'external_code' => 'VM_PCN',
            'hrm_org_unit_uuid' => 'catalog-pcn',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        Department::query()->create([
            'code' => 'LOCAL',
            'name' => 'Phòng nội bộ',
            'is_active' => true,
        ]);
        Department::query()->create([
            'code' => 'OLD',
            'name' => 'Phòng cũ',
            'external_code' => 'OLD',
            'hrm_org_unit_uuid' => 'catalog-old',
            'is_active' => false,
        ]);

        $this->actingAs($this->makeUser())
            ->getJson('/api/social/departments')
            ->assertOk()
            ->assertJsonCount(1, 'departments')
            ->assertJsonPath('departments.0.id', $hrm->id)
            ->assertJsonPath('departments.0.name', 'Phòng Công nghệ')
            ->assertJsonPath('departments.0.hrm_code', 'VM_PCN')
            ->assertJsonPath('departments.0.company_code', 'VID')
            ->assertJsonPath('departments.0.company_name', 'VA Schools');
    }

    public function test_default_mode_is_all_and_visible_to_everyone(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $author = $this->makeUser(['department_id' => $deptA->id]);
        $viewer = $this->makeUser(['department_id' => null]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', ['content' => 'Bài chung'])
            ->assertCreated()
            ->assertJsonPath('post.department_visibility_mode', 'all')
            ->assertJsonPath('post.department_visibility', null);

        $posts = $this->actingAs($viewer)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');

        $this->assertCount(1, $posts);
    }

    public function test_include_mode_only_visible_to_selected_departments(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $deptB = $this->makeDept('B', 'Phòng B');
        $author = $this->makeUser(['department_id' => $deptA->id]);
        $memberA = $this->makeUser(['department_id' => $deptA->id]);
        $memberB = $this->makeUser(['department_id' => $deptB->id]);
        $noDept = $this->makeUser(['department_id' => null]);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Chỉ phòng A thấy',
                'department_visibility_mode' => 'include',
                'department_visibility_ids' => [$deptA->id],
            ])
            ->assertCreated()
            ->assertJsonPath('post.department_visibility_mode', 'include')
            ->assertJsonPath('post.department_visibility.0.id', $deptA->id)
            ->json('post.id');

        $postsForA = $this->actingAs($memberA)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(1, $postsForA);
        $this->assertSame($postId, $postsForA[0]['id']);

        $postsForB = $this->actingAs($memberB)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(0, $postsForB);

        $postsForNoDept = $this->actingAs($noDept)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(0, $postsForNoDept);
    }

    public function test_exclude_mode_hides_from_selected_departments_only(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $deptB = $this->makeDept('B', 'Phòng B');
        $author = $this->makeUser(['department_id' => $deptA->id]);
        $memberA = $this->makeUser(['department_id' => $deptA->id]);
        $memberB = $this->makeUser(['department_id' => $deptB->id]);
        $noDept = $this->makeUser(['department_id' => null]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Trừ phòng A',
                'department_visibility_mode' => 'exclude',
                'department_visibility_ids' => [$deptA->id],
            ])
            ->assertCreated()
            ->assertJsonPath('post.department_visibility_mode', 'exclude');

        $postsForA = $this->actingAs($memberA)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(0, $postsForA);

        $postsForB = $this->actingAs($memberB)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(1, $postsForB);

        $postsForNoDept = $this->actingAs($noDept)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(1, $postsForNoDept);
    }

    public function test_include_or_exclude_requires_at_least_one_department(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $author = $this->makeUser(['department_id' => $deptA->id]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Thiếu danh sách phòng ban',
                'department_visibility_mode' => 'include',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_visibility_ids']);
    }

    public function test_visibility_mode_ignored_outside_company_wall(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $author = $this->makeUser(['department_id' => $deptA->id]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Bài phòng ban',
                'post_scope' => 'department',
                'department_visibility_mode' => 'include',
                'department_visibility_ids' => [$deptA->id],
            ])
            ->assertCreated()
            ->assertJsonPath('post.post_scope', 'department')
            ->assertJsonPath('post.department_visibility_mode', 'all')
            ->assertJsonPath('post.department_visibility', null);
    }

    public function test_author_still_sees_own_post_excluded_from_their_department_on_direct_fetch(): void
    {
        $deptA = $this->makeDept('A', 'Phòng A');
        $author = $this->makeUser(['department_id' => $deptA->id]);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Tự loại phòng mình',
                'department_visibility_mode' => 'exclude',
                'department_visibility_ids' => [$deptA->id],
            ])
            ->assertCreated()
            ->json('post.id');

        // Xem trực tiếp qua GET /posts/{id} không bị lọc theo department_visibility
        // (lọc chỉ áp dụng cho danh sách bảng tin), nên tác giả vẫn mở được bài của mình.
        $this->actingAs($author)
            ->getJson("/api/social/posts/{$postId}")
            ->assertOk()
            ->assertJsonPath('post.id', $postId);

        // Nhưng trên feed bảng tin chung, bài không xuất hiện kể cả với chính tác giả
        // vì phòng ban của tác giả nằm trong danh sách loại trừ.
        $posts = $this->actingAs($author)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->json('posts');
        $this->assertCount(0, $posts);
    }
}
