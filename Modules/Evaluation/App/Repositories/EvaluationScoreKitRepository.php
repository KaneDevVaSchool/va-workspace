<?php

namespace Modules\Evaluation\App\Repositories;

use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationScoreKitRepositoryInterface;

class EvaluationScoreKitRepository implements EvaluationScoreKitRepositoryInterface
{
    public function findByDepartment(int $departmentId): ?EvaluationScoreKit
    {
        return EvaluationScoreKit::query()
            ->with(['classificationCriterion'])
            ->where('department_id', $departmentId)
            ->first();
    }

    public function create(array $data): EvaluationScoreKit
    {
        $kit = EvaluationScoreKit::query()->create($data);

        return $kit->fresh(['classificationCriterion']);
    }

    public function update(EvaluationScoreKit $kit, array $data): EvaluationScoreKit
    {
        $kit->update($data);

        return $kit->fresh(['classificationCriterion']);
    }
}
