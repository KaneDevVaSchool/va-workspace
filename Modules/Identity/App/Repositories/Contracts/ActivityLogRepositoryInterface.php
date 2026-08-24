<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Identity\App\Models\ActivityLog;

interface ActivityLogRepositoryInterface
{
    public function create(array $data): ActivityLog;

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 20): Collection;

    /**
     * @param  array{q?: string, action?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;
}
