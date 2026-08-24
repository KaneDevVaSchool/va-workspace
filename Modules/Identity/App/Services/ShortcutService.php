<?php

namespace Modules\Identity\App\Services;

use Modules\Identity\App\Exceptions\ShortcutPathTaken;
use Modules\Identity\App\Models\UserShortcut;
use Modules\Identity\App\Repositories\Contracts\UserShortcutRepositoryInterface;

class ShortcutService
{
    public function __construct(
        private readonly UserShortcutRepositoryInterface $shortcuts,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function listFor(int $userId): array
    {
        return $this->shortcuts->allForUser($userId)
            ->map(fn (UserShortcut $shortcut) => $this->present($shortcut))
            ->values()
            ->all();
    }

    /**
     * @param  array{title: string, description?: string|null, path: string}  $data
     *
     * @throws ShortcutPathTaken
     */
    public function create(int $userId, array $data): UserShortcut
    {
        $path = $this->normalizePath($data['path']);

        if ($this->shortcuts->findByPath($userId, $path) !== null) {
            throw new ShortcutPathTaken();
        }

        return $this->shortcuts->create([
            'user_id' => $userId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'path' => $path,
            'is_favorite' => false,
            'sort_order' => $this->shortcuts->nextSortOrder($userId),
        ]);
    }

    /**
     * @param  array{title?: string, description?: string|null}  $data
     */
    public function update(UserShortcut $shortcut, array $data): UserShortcut
    {
        return $this->shortcuts->update($shortcut, $data);
    }

    public function toggleFavorite(UserShortcut $shortcut): UserShortcut
    {
        return $this->shortcuts->update($shortcut, [
            'is_favorite' => ! $shortcut->is_favorite,
        ]);
    }

    public function delete(UserShortcut $shortcut): void
    {
        $this->shortcuts->delete($shortcut);
    }

    /** @return array<string, mixed> */
    public function present(UserShortcut $shortcut): array
    {
        return [
            'id' => $shortcut->id,
            'title' => $shortcut->title,
            'description' => $shortcut->description,
            'path' => $shortcut->path,
            'is_favorite' => $shortcut->is_favorite,
            'sort_order' => $shortcut->sort_order,
        ];
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parts = parse_url($path);
            $path = ($parts['path'] ?? '/')
                .(isset($parts['query']) ? '?'.$parts['query'] : '')
                .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }

        if ($path === '' || str_starts_with($path, '//') || ! str_starts_with($path, '/')) {
            return '/';
        }

        return mb_substr($path, 0, 512);
    }
}
