<?php

namespace Tests\Feature\Evaluation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Services\EvaluationDepartmentCatalogRemapper;
use Modules\Identity\App\Models\Department;
use Tests\TestCase;

class EvaluationDepartmentRemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaps_criteria_from_stale_department_to_hrm_catalog_id(): void
    {
        $canonical = Department::query()->create([
            'code' => 'PB01',
            'name' => 'Phòng HRM',
            'external_code' => 'PB01',
            'company_id' => null,
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-canonical',
        ]);

        $stale = Department::query()->create([
            'code' => 'PB01-old',
            'name' => 'Phòng cũ org-unit',
            'external_code' => 'PB01',
            'company_id' => null,
            'is_active' => false,
            'hrm_org_unit_uuid' => 'ou-stale',
        ]);

        $criterion = EvaluationCriteria::query()->create([
            'department_id' => $stale->id,
            'name' => 'Chất lượng công việc',
            'type' => 'scale',
            'levels' => [['code' => 'M1', 'label' => 'Đạt', 'score' => 1]],
            'is_active' => true,
            'use_in_evaluation' => true,
        ]);

        $stats = app(EvaluationDepartmentCatalogRemapper::class)->remapAll();

        $this->assertSame(1, $stats['criteria_moved']);
        $this->assertSame((int) $canonical->id, (int) $criterion->fresh()->department_id);
        $this->assertSame(0, DB::table('evaluation_criteria')->where('department_id', $stale->id)->count());
    }
}
