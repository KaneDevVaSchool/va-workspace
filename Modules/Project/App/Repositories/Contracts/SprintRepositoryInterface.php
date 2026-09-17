<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\Sprint;

/**
 * Contract cho tầng Repository của Sprint — Service chỉ phụ thuộc interface
 * này, không phụ thuộc trực tiếp Eloquent.
 */
interface SprintRepositoryInterface
{
    /** @return Collection<int, Sprint> */
    public function listForProject(int $projectId): Collection;

    /** @return Collection<int, Sprint> */
    public function listForPhase(int $phaseId): Collection;

    public function find(int $id): ?Sprint;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Sprint;

    /** @param  array<string, mixed>  $data */
    public function update(Sprint $sprint, array $data): Sprint;

    public function delete(Sprint $sprint): bool;
}
