<?php

namespace Modules\Evaluation\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationPosition;
use Modules\Evaluation\App\Models\EvaluationTemplate;
use Modules\Evaluation\App\Models\EvaluationTemplateCriterion;

/**
 * Mẫu đánh giá demo — cần EvaluationCriteriaSeeder + EvaluationPositionSeeder
 * đã chạy trước (dùng tiêu chí + vị trí có sẵn). Idempotent: updateOrCreate
 * theo code cố định (không gọi EvaluationTemplateService::generateCode() để
 * tránh side-effect/khoá bảng không cần thiết trong seeder).
 */
class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()->orderBy('id')->value('id');
        if ($createdBy === null) {
            return;
        }

        $this->seedDepartmentTemplate($createdBy);
        $this->seedGlobalTemplate($createdBy);
    }

    private function seedDepartmentTemplate(int $createdBy): void
    {
        $criterion = EvaluationCriteria::query()->where('is_active', true)->orderBy('department_id')->first();
        if ($criterion === null) {
            return;
        }

        $departmentId = (int) $criterion->department_id;

        $template = EvaluationTemplate::query()->updateOrCreate(
            ['code' => 'EVT-0001'],
            [
                'department_id' => $departmentId,
                'name'          => 'Đánh giá năng lực nhân viên (mẫu demo)',
                'description'   => 'Mẫu demo dùng tiêu chí sẵn có của phòng ban để minh hoạ.',
                'is_global'     => false,
                'is_active'     => true,
                'created_by'    => $createdBy,
                'updated_by'    => $createdBy,
            ],
        );

        $criteria = EvaluationCriteria::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        $this->syncCriteria($template, $criteria);

        $positionIds = EvaluationPosition::query()
            ->where('kind', EvaluationPosition::KIND_POSITION)
            ->orderBy('name')
            ->limit(2)
            ->pluck('id')
            ->all();
        $template->positions()->sync($positionIds);
    }

    private function seedGlobalTemplate(int $createdBy): void
    {
        $anyDepartmentId = EvaluationCriteria::query()->where('is_active', true)->value('department_id');
        if ($anyDepartmentId === null) {
            return;
        }

        $template = EvaluationTemplate::query()->updateOrCreate(
            ['code' => 'EVT-0002'],
            [
                'department_id' => (int) $anyDepartmentId,
                'name'          => 'Đánh giá năng lực toàn hệ thống (mẫu demo)',
                'description'   => 'Mẫu demo dùng chung toàn hệ thống, trộn tiêu chí từ nhiều phòng ban.',
                'is_global'     => true,
                'is_active'     => true,
                'created_by'    => $createdBy,
                'updated_by'    => $createdBy,
            ],
        );

        $criteria = EvaluationCriteria::query()
            ->where('is_active', true)
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        $this->syncCriteria($template, $criteria);

        $positionIds = EvaluationPosition::query()
            ->where('kind', EvaluationPosition::KIND_DEPARTMENT)
            ->orderBy('name')
            ->limit(1)
            ->pluck('id')
            ->all();
        $template->positions()->sync($positionIds);
    }

    /** @param  \Illuminate\Support\Collection<int, EvaluationCriteria>  $criteria */
    private function syncCriteria(EvaluationTemplate $template, $criteria): void
    {
        EvaluationTemplateCriterion::query()
            ->where('evaluation_template_id', $template->id)
            ->delete();

        foreach ($criteria->values() as $index => $criterion) {
            EvaluationTemplateCriterion::query()->create([
                'evaluation_template_id' => $template->id,
                'evaluation_criteria_id' => $criterion->id,
                'weight_label'           => 'quan_trong',
                'weight_value'           => EvaluationTemplateCriterion::WEIGHT_MAP['quan_trong'],
                'required_score'         => null,
                'count_in_total'         => true,
                'sort_order'             => $index,
            ]);
        }
    }
}
