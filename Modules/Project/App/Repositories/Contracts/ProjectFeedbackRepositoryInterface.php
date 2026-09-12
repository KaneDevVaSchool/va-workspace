<?php

namespace Modules\Project\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Project\App\Models\ProjectFeedback;

/**
 * Contract cho tầng Repository của ProjectFeedback — Service chỉ phụ thuộc
 * interface này, không phụ thuộc trực tiếp Eloquent.
 */
interface ProjectFeedbackRepositoryInterface
{
    /** @return Collection<int, ProjectFeedback> */
    public function listForProject(int $projectId): Collection;

    public function find(int $id): ?ProjectFeedback;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): ProjectFeedback;

    /** @param  array<string, mixed>  $data */
    public function update(ProjectFeedback $feedback, array $data): ProjectFeedback;

    public function delete(ProjectFeedback $feedback): bool;
}
