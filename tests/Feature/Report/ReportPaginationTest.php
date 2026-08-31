<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Report\App\Models\Report;
use Tests\TestCase;

/**
 * Danh sách báo cáo và ghi nhận đánh giá phân trang ở máy chủ — không tải
 * toàn bộ bản ghi về trình duyệt rồi cắt trang bằng JavaScript.
 */
class ReportPaginationTest extends TestCase
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

    /** @return array{dept: Department, director: User} */
    private function setUpDepartment(): array
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);

        return [
            'dept' => $dept,
            'director' => $this->makeUser(['department_id' => $dept->id], ['department_director']),
        ];
    }

    private function createReports(User $director, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->actingAs($director)->postJson('/api/report/personnel-evaluation', [
                'title' => 'Báo cáo số '.$i,
                'period_type' => 'month',
                'period_from' => '2026-08-01',
                'period_to' => '2026-08-31',
            ])->assertCreated();
        }
    }

    public function test_report_list_returns_pagination_meta(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $this->createReports($director, 7);

        $response = $this->actingAs($director)
            ->getJson('/api/report?per_page=3&page=1')
            ->assertOk();

        $this->assertCount(3, $response->json('reports'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(3, $response->json('meta.last_page'));
        $this->assertSame(3, $response->json('meta.per_page'));
        $this->assertSame(7, $response->json('meta.total'));
    }

    public function test_report_list_last_page_returns_remainder(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $this->createReports($director, 7);

        $response = $this->actingAs($director)
            ->getJson('/api/report?per_page=3&page=3')
            ->assertOk();

        $this->assertCount(1, $response->json('reports'));
        $this->assertSame(3, $response->json('meta.current_page'));
    }

    public function test_report_list_search_narrows_total(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $this->createReports($director, 5);

        $response = $this->actingAs($director)
            ->getJson('/api/report?q='.urlencode('số 3'))
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Báo cáo số 3', $response->json('reports.0.title'));
    }

    public function test_evaluation_event_list_returns_pagination_meta(): void
    {
        ['dept' => $dept, 'director' => $director] = $this->setUpDepartment();

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Chủ động',
            'type' => 'behavior',
            'levels' => [['code' => 'A1', 'label' => 'Chủ động lập kế hoạch', 'score' => 3]],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($director)->postJson('/api/evaluation/events', [
                'user_id' => $director->id,
                'criterion_id' => $criterion->id,
                'level_code' => 'A1',
                'occurred_at' => '2026-08-1'.$i,
                'reason' => 'Ghi nhận '.$i,
            ])->assertCreated();
        }

        $response = $this->actingAs($director)
            ->getJson('/api/evaluation/events?per_page=2&page=1')
            ->assertOk();

        $this->assertCount(2, $response->json('events'));
        $this->assertSame(5, $response->json('meta.total'));
        $this->assertSame(3, $response->json('meta.last_page'));
        // Danh mục tiêu chí và nhân sự vẫn trả kèm để dựng form.
        $this->assertNotEmpty($response->json('criteria'));
        $this->assertNotEmpty($response->json('members'));
    }

    public function test_per_page_is_capped(): void
    {
        ['director' => $director] = $this->setUpDepartment();
        $this->createReports($director, 2);

        $this->actingAs($director)
            ->getJson('/api/report?per_page=99999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    /** Xem trước ở bước cuối wizard không được tạo báo cáo nào. */
    public function test_preview_returns_numbers_without_creating_report(): void
    {
        ['director' => $director] = $this->setUpDepartment();

        // Chốt phiên bản bằng cách tạo rồi xoá một báo cáo — xem trước cần có
        // phiên bản đang áp dụng.
        $reportId = $this->actingAs($director)->postJson('/api/report/personnel-evaluation', [
            'title' => 'Tạm',
            'period_type' => 'month',
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ])->json('report.id');
        $this->actingAs($director)->deleteJson('/api/report/'.$reportId)->assertOk();

        $before = Report::query()->count();

        $response = $this->actingAs($director)->postJson('/api/report/personnel-evaluation/preview', [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ])->assertOk();

        $this->assertSame(1, $response->json('summary.total_people'));
        $this->assertNotNull($response->json('version_no'));
        $this->assertSame($before, Report::query()->count());
    }

    public function test_preview_rejects_member_without_permission(): void
    {
        ['dept' => $dept] = $this->setUpDepartment();
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($member)->postJson('/api/report/personnel-evaluation/preview', [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ])->assertForbidden();
    }
}
