<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class PersonnelEvaluationReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $user->roles()->sync(Role::query()->whereIn('code', $roles)->pluck('id'));
        }

        return $user;
    }

    /** @return array{dept: Department, director: User, member: User} */
    private function setUpDepartment(): array
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);

        return [
            'dept' => $dept,
            'director' => $this->makeUser(['department_id' => $dept->id], ['department_director']),
            'member' => $this->makeUser(['department_id' => $dept->id], ['member']),
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Đánh giá nhân sự tháng 08/2026',
            'period_type' => 'month',
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $overrides);
    }

    public function test_director_creates_report_and_version_is_published_automatically(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $this->assertSame(0, EvaluationConfigVersion::query()->count());

        $response = $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->assertJsonPath('report.title', 'Đánh giá nhân sự tháng 08/2026')
            ->assertJsonPath('report.status', 'draft')
            ->assertJsonPath('report.version_no', 1);

        $this->assertSame(1, EvaluationConfigVersion::query()->count());
        $this->assertNotNull($response->json('report.evaluation_config_version_id'));
    }

    public function test_member_cannot_create_report(): void
    {
        ['member' => $member] = $this->setUpDepartment();

        $this->actingAs($member)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertForbidden();
    }

    public function test_report_list_only_shows_permitted_reports(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();
        $otherDept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $outsider = $this->makeUser(['department_id' => $otherDept->id], ['member']);

        $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload(['viewer_user_ids' => [$member->id]]))
            ->assertCreated();

        $this->actingAs($director)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
        $this->actingAs($member)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
        $this->actingAs($outsider)->getJson('/api/report')->assertOk()->assertJsonCount(0, 'reports');
    }

    /**
     * Giám đốc điều hành giám sát toàn hệ thống nên thấy báo cáo của mọi phòng
     * ban, kể cả phòng mình không thuộc về và không được chia sẻ đích danh.
     */
    public function test_director_officer_sees_reports_of_every_department(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $otherDept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $officer = $this->makeUser(['department_id' => $otherDept->id], ['director_officer']);

        $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated();

        $this->actingAs($officer)->getJson('/api/report')->assertOk()->assertJsonCount(1, 'reports');
    }
}
