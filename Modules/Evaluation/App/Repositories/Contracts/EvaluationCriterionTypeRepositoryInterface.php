<?php

namespace Modules\Evaluation\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Evaluation\App\Models\EvaluationCriterionType;

interface EvaluationCriterionTypeRepositoryInterface
{
    public function allByDepartment(int $departmentId): Collection;

    public function findByDepartment(int $id, int $departmentId): ?EvaluationCriterionType;

    public function codeExists(int $departmentId, string $code, ?int $ignoreId = null): bool;

    /** Mã loại đang dùng trong phòng ban, để cấp số tuần tự TCA0001. */
    public function codesForDepartment(int $departmentId): Collection;

    public function create(array $data): EvaluationCriterionType;
}
