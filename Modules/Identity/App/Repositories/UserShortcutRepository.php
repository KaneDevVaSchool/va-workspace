<?php

namespace Modules\Identity\App\Repositories;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\UserShortcut;
use Modules\Identity\App\Repositories\Contracts\UserShortcutRepositoryInterface;

class UserShortcutRepository implements UserShortcutRepositoryInterface
{
    public function allForUser(int $userId): Collection
    {
        return UserShortcut::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_favorite')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function findForUser(int $userId, int $id): ?UserShortcut
    {
        return UserShortcut::query()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();
    }

    public function findByPath(int $userId, string $path): ?UserShortcut
    {
        return UserShortcut::query()
            ->where('user_id', $userId)
            ->where('path', $path)
            ->first();
    }

    public function create(array $data): UserShortcut
    {
        return UserShortcut::query()->create($data);
    }

    public function update(UserShortcut $shortcut, array $data): UserShortcut
    {
        $shortcut->fill($data)->save();

        return $shortcut;
    }

    public function delete(UserShortcut $shortcut): void
    {
        $shortcut->delete();
    }

    public function nextSortOrder(int $userId): int
    {
        return (int) UserShortcut::query()
            ->where('user_id', $userId)
            ->max('sort_order') + 1;
    }
}
