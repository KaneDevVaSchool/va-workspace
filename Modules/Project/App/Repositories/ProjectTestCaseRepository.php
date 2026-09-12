<?php

namespace Modules\Project\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\ProjectTestCase;
use Modules\Project\App\Repositories\Contracts\ProjectTestCaseRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho entity ProjectTestCase.
 */
class ProjectTestCaseRepository implements ProjectTestCaseRepositoryInterface
{
    public function listForProject(int $projectId): Collection
    {
        return ProjectTestCase::query()
            ->with(['assignee', 'creator'])
            ->where('project_id', $projectId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function find(int $id): ?ProjectTestCase
    {
        return ProjectTestCase::query()->with(['assignee', 'creator'])->find($id);
    }

    public function create(array $data): ProjectTestCase
    {
        $testCase = ProjectTestCase::query()->create($data);

        return $testCase->fresh(['assignee', 'creator']);
    }

    public function update(ProjectTestCase $testCase, array $data): ProjectTestCase
    {
        $testCase->fill($data);
        $testCase->save();

        return $testCase->fresh(['assignee', 'creator']);
    }

    public function delete(ProjectTestCase $testCase): bool
    {
        return (bool) $testCase->delete();
    }
}
