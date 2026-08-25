<?php

namespace Modules\Evaluation\App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\App\Models\EvaluationCriterionType;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriterionTypeRepositoryInterface;

class EvaluationCriterionTypeService
{
    public const CODE_PREFIX = 'TCA';

    public const CODE_PAD = 4;

    public function __construct(
        private readonly EvaluationCriterionTypeRepositoryInterface $types,
    ) {}

    public function listForDepartment(int $departmentId): Collection
    {
        return $this->types
            ->allByDepartment($departmentId)
            ->map(fn (EvaluationCriterionType $type) => $this->present($type))
            ->values();
    }

    public function create(int $departmentId, int $createdBy, array $data): EvaluationCriterionType
    {
        $name = trim($data['name']);
        $rawCode = strtoupper(trim((string) ($data['code'] ?? '')));
        $code = (string) preg_replace('/[^A-Z0-9]+/', '', $rawCode);

        if ($code === '') {
            $code = $this->nextCode($departmentId);
        } elseif ($this->types->codeExists($departmentId, $code)) {
            throw ValidationException::withMessages([
                'code' => ['Mã loại tiêu chí đã được dùng trong phòng ban này.'],
            ]);
        }

        return $this->types->create([
            'department_id' => $departmentId,
            'name'          => $name,
            'code'          => $code,
            'description'   => isset($data['description']) ? trim((string) $data['description']) : null,
            'sort_order'    => $data['sort_order'] ?? 0,
            'created_by'    => $createdBy,
        ]);
    }

    public function present(EvaluationCriterionType $type): array
    {
        return [
            'id'          => $type->id,
            'name'        => $type->name,
            'code'        => $type->code,
            'description' => $type->description,
            'sort_order'  => $type->sort_order,
        ];
    }

    public function belongsToDepartment(int $typeId, int $departmentId): bool
    {
        return $this->types->findByDepartment($typeId, $departmentId) !== null;
    }

    public function nextCode(int $departmentId): string
    {
        $max = 0;
        $pattern = '/^'.preg_quote(self::CODE_PREFIX, '/').'(\d+)$/';

        foreach ($this->types->codesForDepartment($departmentId) as $existing) {
            if (preg_match($pattern, (string) $existing, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        $next = $max + 1;
        $width = max(self::CODE_PAD, strlen((string) $next));

        return self::CODE_PREFIX.str_pad((string) $next, $width, '0', STR_PAD_LEFT);
    }
}
