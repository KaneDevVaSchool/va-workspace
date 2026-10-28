<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Department;
use Tests\TestCase;

class SocialPostCompanyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['status' => 'active'], $attributes));
    }

    public function test_company_picker_lists_active_hrm_companies(): void
    {
        $active = Company::upsertFromHrm('co-vm', 'VM', 'VA Schools VM');
        Company::query()->create([
            'hrm_uuid' => 'co-old',
            'code' => 'OLD',
            'name' => 'Cũ',
            'is_active' => false,
        ]);

        $this->actingAs($this->makeUser())
            ->getJson('/api/social/companies')
            ->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('companies.0.id', $active->id)
            ->assertJsonPath('companies.0.code', 'VM')
            ->assertJsonPath('companies.0.name', 'VA Schools VM');
    }

    public function test_include_mode_only_visible_to_selected_companies(): void
    {
        $coA = Company::upsertFromHrm('co-a', 'A', 'Pháp nhân A');
        $coB = Company::upsertFromHrm('co-b', 'B', 'Pháp nhân B');
        $deptA = Department::query()->create(['code' => 'A_PCN', 'name' => 'PCN A', 'company_id' => $coA->id, 'is_active' => true]);
        $deptB = Department::query()->create(['code' => 'B_PCN', 'name' => 'PCN B', 'company_id' => $coB->id, 'is_active' => true]);

        $author = $this->makeUser(['company_id' => $coA->id, 'department_id' => $deptA->id]);
        $memberA = $this->makeUser(['company_id' => $coA->id, 'department_id' => $deptA->id]);
        $memberB = $this->makeUser(['company_id' => $coB->id, 'department_id' => $deptB->id]);
        $memberBViaDept = $this->makeUser(['company_id' => null, 'department_id' => $deptB->id]);

        $postId = $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Chỉ pháp nhân A',
                'company_visibility_mode' => 'include',
                'company_visibility_ids' => [$coA->id],
            ])
            ->assertCreated()
            ->assertJsonPath('post.company_visibility_mode', 'include')
            ->assertJsonPath('post.company_visibility.0.id', $coA->id)
            ->json('post.id');

        $this->actingAs($memberA)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->assertJsonCount(1, 'posts');

        $this->actingAs($memberB)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->actingAs($memberBViaDept)
            ->getJson('/api/social/posts?post_scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'posts');

        $this->assertSame($postId, $this->actingAs($memberA)->getJson('/api/social/posts?post_scope=company')->json('posts.0.id'));
    }

    public function test_company_include_takes_precedence_over_department_fields(): void
    {
        $co = Company::upsertFromHrm('co-x', 'X', 'Pháp nhân X');
        $dept = Department::query()->create(['code' => 'X1', 'name' => 'PB X', 'company_id' => $co->id, 'is_active' => true]);
        $otherDept = Department::query()->create(['code' => 'Y1', 'name' => 'PB Y', 'is_active' => true]);
        $author = $this->makeUser(['company_id' => $co->id, 'department_id' => $dept->id]);

        $this->actingAs($author)
            ->postJson('/api/social/posts', [
                'content' => 'Ưu tiên pháp nhân',
                'company_visibility_mode' => 'include',
                'company_visibility_ids' => [$co->id],
                'department_visibility_mode' => 'include',
                'department_visibility_ids' => [$otherDept->id],
            ])
            ->assertCreated()
            ->assertJsonPath('post.company_visibility_mode', 'include')
            ->assertJsonPath('post.department_visibility_mode', 'all')
            ->assertJsonPath('post.department_visibility', null);
    }
}
