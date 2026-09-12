<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\ProjectTestCase;

/**
 * Contract cho tầng Repository của ProjectTestCase — Service chỉ phụ thuộc
 * interface này, không phụ thuộc trực tiếp Eloquent.
 */
interface ProjectTestCaseRepositoryInterface
{
    /** @return Collection<int, ProjectTestCase> */
    public function listForProject(int $projectId): Collection;

    public function find(int $id): ?ProjectTestCase;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): ProjectTestCase;

    /** @param  array<string, mixed>  $data */
    public function update(ProjectTestCase $testCase, array $data): ProjectTestCase;

    public function delete(ProjectTestCase $testCase): bool;
}
