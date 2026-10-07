<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Hrm\Services\HrmDepartmentSyncService;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Repositories\DepartmentRepository;
use Tests\TestCase;

class HrmDepartmentSyncPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_deactivates_workspace_department_when_org_unit_no_longer_in_hrm(): void
    {
        Department::query()->create([
            'code' => 'CN',
            'name' => 'Phòng Công nghệ (cũ)',
            'external_code' => 'CN',
            'hrm_org_unit_uuid' => 'ou-old',
            'is_active' => true,
        ]);

        app(HrmDepartmentSyncService::class)->syncDepartmentsFromRecords([
            [
                'uuid' => 'ou-new',
                'type' => 'department',
                'code' => 'CN',
                'name' => 'Phòng Công nghệ',
                'status' => 'active',
            ],
        ]);

        $this->assertFalse(
            (bool) Department::query()->where('hrm_org_unit_uuid', 'ou-old')->value('is_active'),
        );
        $this->assertTrue(
            (bool) Department::query()->where('hrm_org_unit_uuid', 'ou-new')->value('is_active'),
        );
    }

    public function test_picker_dedupes_active_hrm_departments_by_external_code(): void
    {
        Department::query()->create([
            'code' => 'CN',
            'name' => 'Phòng Công nghệ',
            'external_code' => 'CN',
            'hrm_org_unit_uuid' => 'ou-stale',
            'is_active' => true,
        ]);
        Department::query()->create([
            'code' => 'CN-2',
            'name' => 'Phòng Công nghệ',
            'external_code' => 'CN',
            'hrm_org_unit_uuid' => 'ou-current',
            'is_active' => true,
        ]);

        $picker = app(DepartmentRepository::class)->allActiveSyncedFromHrmForPicker();

        $this->assertCount(1, $picker);
        $this->assertSame('ou-current', $picker->first()->hrm_org_unit_uuid);
    }
}
