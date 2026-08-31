<?php

namespace Modules\Evaluation\App\Repositories;

use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationScoreKitRepositoryInterface;

class EvaluationScoreKitRepository implements EvaluationScoreKitRepositoryInterface
{
    public function findByDepartment(int $departmentId): ?EvaluationScoreKit
    {
        return EvaluationScoreKit::query()
            ->with($this->criterionRelations())
            ->where('department_id', $departmentId)
            ->first();
    }

    public function create(array $data): EvaluationScoreKit
    {
        $kit = EvaluationScoreKit::query()->create($data);

        return $kit->fresh($this->criterionRelations());
    }

    public function update(EvaluationScoreKit $kit, array $data): EvaluationScoreKit
    {
        $kit->update($data);

        return $kit->fresh($this->criterionRelations());
    }

    /** @return list<string> */
    private function criterionRelations(): array
    {
        return [
            'classificationCriterion',
            'difficultyCriterion',
            'progressCriterion',
            'qualityCriterion',
        ];
    }
}
