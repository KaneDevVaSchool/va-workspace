<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

/**
 * Báo cáo đã lưu phải mở lại ra đúng danh sách nhân sự của lúc lưu.
 *
 * Trước khi chụp danh sách, phạm vi nhân sự lấy động theo trạng thái hiện
 * tại: người nghỉ việc sau kỳ thì biến mất khỏi báo cáo cũ, còn người mới
 * chuyển đến lại hiện ra trong kỳ họ chưa làm ở đó.
 */
class ReportPeopleSnapshotTest extends TestCase
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

    /** @return array{dept: Department, other: Department, director: User, member: User} */
    private function setUpDepartment(): array
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $other = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);

        return [
            'dept' => $dept,
            'other' => $other,
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

    private function createAndSave(User $director): int
    {
        $reportId = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->assertCreated()
            ->json('report.id');

        $this->actingAs($director)
            ->patchJson('/api/report/'.$reportId.'/save')
            ->assertOk()
            ->assertJsonPath('report.status', 'saved');

        return $reportId;
    }

    public function test_saved_report_keeps_person_who_left_afterwards(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();

        $reportId = $this->createAndSave($director);

        $this->assertSame(2, $this->actingAs($director)
            ->getJson('/api/report/'.$reportId)
            ->json('summary.total_people'));

        // Nhân viên nghỉ việc sau khi báo cáo đã lưu.
        $member->forceFill(['status' => 'inactive'])->save();

        $response = $this->actingAs($director)->getJson('/api/report/'.$reportId)->assertOk();

        $this->assertSame(2, $response->json('summary.total_people'));
        $this->assertContains(
            $member->id,
            collect($response->json('rows'))->pluck('user_id')->all(),
        );
    }

    public function test_saved_report_ignores_person_who_joined_afterwards(): void
    {
        ['dept' => $dept, 'other' => $other, 'director' => $director] = $this->setUpDepartment();

        $reportId = $this->createAndSave($director);
        $before = $this->actingAs($director)->getJson('/api/report/'.$reportId)->json('summary.total_people');

        // Người phòng khác chuyển đến sau khi báo cáo đã lưu.
        $newcomer = $this->makeUser(['department_id' => $other->id], ['member']);
        $newcomer->forceFill(['department_id' => $dept->id])->save();

        $response = $this->actingAs($director)->getJson('/api/report/'.$reportId)->assertOk();

        $this->assertSame($before, $response->json('summary.total_people'));
        $this->assertNotContains(
            $newcomer->id,
            collect($response->json('rows'))->pluck('user_id')->all(),
        );
    }

    public function test_draft_report_still_follows_current_department(): void
    {
        ['dept' => $dept, 'other' => $other, 'director' => $director] = $this->setUpDepartment();

        $reportId = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload())
            ->json('report.id');

        $before = $this->actingAs($director)->getJson('/api/report/'.$reportId)->json('summary.total_people');

        $newcomer = $this->makeUser(['department_id' => $other->id], ['member']);
        $newcomer->forceFill(['department_id' => $dept->id])->save();

        // Còn nháp thì vẫn cập nhật theo phòng ban hiện tại.
        $this->assertSame(
            $before + 1,
            $this->actingAs($director)->getJson('/api/report/'.$reportId)->json('summary.total_people'),
        );
    }

    public function test_snapshot_respects_selected_people_filter(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();

        $reportId = (int) $this->actingAs($director)
            ->postJson('/api/report/personnel-evaluation', $this->payload([
                'filter_user_ids' => [$member->id],
            ]))
            ->json('report.id');

        $this->actingAs($director)->patchJson('/api/report/'.$reportId.'/save')->assertOk();

        $response = $this->actingAs($director)->getJson('/api/report/'.$reportId)->assertOk();

        $this->assertSame(1, $response->json('summary.total_people'));
        $this->assertSame($member->id, $response->json('rows.0.user_id'));
    }

    public function test_employee_detail_works_for_person_who_left(): void
    {
        ['director' => $director, 'member' => $member] = $this->setUpDepartment();

        $reportId = $this->createAndSave($director);

        $member->forceFill(['status' => 'inactive'])->save();

        $this->actingAs($director)
            ->getJson('/api/report/'.$reportId.'/employees/'.$member->id)
            ->assertOk()
            ->assertJsonPath('detail.user_id', $member->id);
    }
}
