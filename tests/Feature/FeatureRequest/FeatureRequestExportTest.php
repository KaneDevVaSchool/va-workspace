<?php

namespace Tests\Feature\FeatureRequest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FeatureRequest\App\Models\FeatureRequest;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FeatureRequestExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');
        }

        return $user;
    }

    public function test_super_admin_downloads_an_xlsx_file(): void
    {
        $admin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);
        $author = $this->makeUser(['name' => 'Chị Giàu'], ['member']);

        FeatureRequest::query()->create([
            'created_by' => $author->id,
            'description' => 'Cần thêm bộ lọc theo phòng ban.',
            'status' => FeatureRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/api/superadmin/feature-requests/export');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString('Ghi_nhan_yeu_cau_tinh_nang', $response->headers->get('content-disposition'));
    }

    public function test_file_contains_the_rows_and_summary_sheets(): void
    {
        $admin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);
        $department = Department::query()->create(['code' => 'CN', 'name' => 'Phòng Công Nghệ']);
        $author = $this->makeUser(['name' => 'Chị Giàu', 'department_id' => $department->id], ['member']);

        FeatureRequest::query()->create([
            'created_by' => $author->id,
            'department_id' => $department->id,
            'description' => 'Cần thêm bộ lọc theo phòng ban.',
            'status' => FeatureRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/api/superadmin/feature-requests/export');
        $response->assertOk();

        $path = $response->getFile()->getPathname();

        $spreadsheet = IOFactory::load($path);

        $this->assertSame(
            ['Ghi nhận', 'Theo phòng ban', 'Thông tin xuất'],
            $spreadsheet->getSheetNames(),
        );

        $data = $spreadsheet->getSheetByName('Ghi nhận');
        $this->assertSame('STT', $data->getCell('A4')->getValue());
        $this->assertSame('Chị Giàu', $data->getCell('D5')->getValue());
        $this->assertSame('Phòng Công Nghệ', $data->getCell('F5')->getValue());
        $this->assertSame('Cần thêm bộ lọc theo phòng ban.', $data->getCell('G5')->getValue());
        $this->assertSame('Chờ ghi nhận', $data->getCell('J5')->getValue());

        $byDepartment = $spreadsheet->getSheetByName('Theo phòng ban');
        $this->assertSame('Phòng Công Nghệ', $byDepartment->getCell('A4')->getValue());
        $this->assertSame(1, $byDepartment->getCell('B4')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_status_filter_limits_the_exported_rows(): void
    {
        $admin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);
        $author = $this->makeUser(['name' => 'Chị Giàu'], ['member']);

        FeatureRequest::query()->create([
            'created_by' => $author->id,
            'description' => 'Ghi nhận đang chờ.',
            'status' => FeatureRequest::STATUS_PENDING,
        ]);
        FeatureRequest::query()->create([
            'created_by' => $author->id,
            'description' => 'Ghi nhận đã xong.',
            'status' => FeatureRequest::STATUS_DONE,
        ]);

        $response = $this->actingAs($admin)->get(
            '/api/superadmin/feature-requests/export?export_kind=status&status=done',
        );
        $response->assertOk();

        $path = $response->getFile()->getPathname();

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Ghi nhận');

        // Chỉ một dòng dữ liệu (dòng 5), dòng 6 phải trống.
        $this->assertSame('Ghi nhận đã xong.', $sheet->getCell('G5')->getValue());
        $this->assertNull($sheet->getCell('G6')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    public function test_a_member_without_review_permission_is_refused(): void
    {
        $member = $this->makeUser(['name' => 'Chị Giàu'], ['member']);

        $this->actingAs($member)
            ->getJson('/api/superadmin/feature-requests/export')
            ->assertForbidden();
    }

    public function test_export_by_date_requires_both_dates(): void
    {
        $admin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);

        $this->actingAs($admin)
            ->getJson('/api/superadmin/feature-requests/export?export_kind=date&date_from=2026-09-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_export_is_written_to_the_activity_log(): void
    {
        $admin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);

        $this->actingAs($admin)->get('/api/superadmin/feature-requests/export')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'feature_request.export_excel',
            'actor_id' => $admin->id,
        ]);
    }
}
