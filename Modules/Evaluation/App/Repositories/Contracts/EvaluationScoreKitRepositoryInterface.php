<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Modules\Evaluation\App\Models\EvaluationScoreKit;

interface EvaluationScoreKitRepositoryInterface
{
    public function findByDepartment(int $departmentId): ?EvaluationScoreKit;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EvaluationScoreKit;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EvaluationScoreKit $kit, array $data): EvaluationScoreKit;
}
