<?php

namespace Modules\Identity\App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\UserShortcut;

interface UserShortcutRepositoryInterface
{
    /** @return Collection<int, UserShortcut> */
    public function allForUser(int $userId): Collection;

    public function findForUser(int $userId, int $id): ?UserShortcut;

    public function findByPath(int $userId, string $path): ?UserShortcut;

    public function create(array $data): UserShortcut;

    public function update(UserShortcut $shortcut, array $data): UserShortcut;

    public function delete(UserShortcut $shortcut): void;

    public function nextSortOrder(int $userId): int;
}
