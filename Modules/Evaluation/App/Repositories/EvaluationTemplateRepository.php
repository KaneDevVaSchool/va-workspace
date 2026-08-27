<?php

namespace Modules\Evaluation\App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\App\Models\EvaluationTemplate;
use Modules\Evaluation\App\Models\EvaluationTemplateCriterion;
use Modules\Evaluation\App\Models\EvaluationTemplateCustomField;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationTemplateRepositoryInterface;

class EvaluationTemplateRepository implements EvaluationTemplateRepositoryInterface
{
    public function visibleForDepartment(int $departmentId): Collection
    {
        return EvaluationTemplate::query()
            ->with(EvaluationTemplate::WITH_PRESENT)
            ->where(function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->orWhere('is_global', true);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function all(): Collection
    {
        return EvaluationTemplate::query()
            ->with(EvaluationTemplate::WITH_PRESENT)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): ?EvaluationTemplate
    {
        return EvaluationTemplate::query()->with(EvaluationTemplate::WITH_PRESENT)->find($id);
    }

    public function findVisibleForDepartment(int $id, int $departmentId): ?EvaluationTemplate
    {
        return EvaluationTemplate::query()
            ->with(EvaluationTemplate::WITH_PRESENT)
            ->where('id', $id)
            ->where(function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId)
                    ->orWhere('is_global', true);
            })
            ->first();
    }

    public function create(array $data): EvaluationTemplate
    {
        $template = EvaluationTemplate::query()->create($data);
        $template->load(EvaluationTemplate::WITH_PRESENT);

        return $template;
    }

    public function update(EvaluationTemplate $template, array $data): EvaluationTemplate
    {
        $template->update($data);

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function delete(EvaluationTemplate $template): bool
    {
        return (bool) $template->delete();
    }

    public function toggleActive(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate
    {
        $payload = ['is_active' => ! $template->is_active];
        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }
        $template->update($payload);

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function toggleGlobal(EvaluationTemplate $template, ?int $updatedBy = null): EvaluationTemplate
    {
        $payload = ['is_global' => ! $template->is_global];
        if ($updatedBy !== null) {
            $payload['updated_by'] = $updatedBy;
        }
        $template->update($payload);

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function syncCriteria(EvaluationTemplate $template, array $rows): EvaluationTemplate
    {
        DB::transaction(function () use ($template, $rows) {
            EvaluationTemplateCriterion::query()
                ->where('evaluation_template_id', $template->id)
                ->delete();

            foreach ($rows as $index => $row) {
                EvaluationTemplateCriterion::query()->create([
                    'evaluation_template_id' => $template->id,
                    'evaluation_criteria_id' => $row['evaluation_criteria_id'],
                    'weight_percent'         => $row['weight_percent'],
                    'required_score'         => $row['required_score'] ?? null,
                    'count_in_total'         => $row['count_in_total'] ?? true,
                    'sort_order'             => $index,
                ]);
            }
        });

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function syncPositions(EvaluationTemplate $template, array $positionIds): EvaluationTemplate
    {
        $template->positions()->sync($positionIds);

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function syncCustomFields(EvaluationTemplate $template, array $rows): EvaluationTemplate
    {
        DB::transaction(function () use ($template, $rows) {
            EvaluationTemplateCustomField::query()
                ->where('evaluation_template_id', $template->id)
                ->delete();

            foreach ($rows as $index => $row) {
                EvaluationTemplateCustomField::query()->create([
                    'evaluation_template_id' => $template->id,
                    'label'                  => $row['label'],
                    'field_type'             => $row['field_type'],
                    'options'                => $row['options'] ?? null,
                    'is_required'            => $row['is_required'] ?? false,
                    'sort_order'             => $index,
                ]);
            }
        });

        return $template->fresh(EvaluationTemplate::WITH_PRESENT);
    }

    public function nextCodeSequence(): int
    {
        return DB::transaction(function () {
            $last = EvaluationTemplate::query()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('code');

            if ($last === null || ! preg_match('/(\d+)$/', $last, $m)) {
                return 1;
            }

            return ((int) $m[1]) + 1;
        });
    }
}
