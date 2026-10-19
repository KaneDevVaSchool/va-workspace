<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Str;
use Modules\Dashboard\App\Models\DashboardQuickUtility;
use Modules\Dashboard\App\Repositories\Contracts\DashboardQuickUtilityRepositoryInterface;

class DashboardQuickUtilityService
{
    /** @var list<string> */
    public const TONES = ['primary', 'secondary', 'tertiary', 'gold', 'muted'];

    /** @var list<string> */
    public const LINK_TYPES = ['route', 'url', 'none'];

    public function __construct(
        private readonly DashboardQuickUtilityRepositoryInterface $repository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forDashboard(): array
    {
        return $this->repository->visibleOrdered()
            ->map(fn (DashboardQuickUtility $row) => $this->serialize($row))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function forAdminListing(): array
    {
        return $this->repository->allOrdered()
            ->map(fn (DashboardQuickUtility $row) => $this->serialize($row, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $updatedBy): DashboardQuickUtility
    {
        $key = $this->normalizeKey($data['utility_key'] ?? null, $data['label']);

        if ($this->repository->findByKey($key)) {
            throw new \InvalidArgumentException('Mã tiện ích đã tồn tại.');
        }

        $sortOrder = $this->nextSortOrder();

        return $this->repository->create([
            'utility_key' => $key,
            'label' => trim((string) $data['label']),
            'icon' => $this->normalizeIcon($data['icon'] ?? 'layoutGrid'),
            'tone' => $this->normalizeTone($data['tone'] ?? 'primary'),
            'link_type' => $this->normalizeLinkType($data['link_type'] ?? 'none'),
            'link_target' => $this->normalizeLinkTarget($data['link_type'] ?? 'none', $data['link_target'] ?? null),
            'sort_order' => $sortOrder,
            'is_visible' => (bool) ($data['is_visible'] ?? true),
            'updated_by' => $updatedBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $utilityKey, array $data, ?int $updatedBy): DashboardQuickUtility
    {
        $row = $this->repository->findByKey($utilityKey);
        if (! $row) {
            throw new \InvalidArgumentException('Không tìm thấy tiện ích.');
        }

        $patch = ['updated_by' => $updatedBy];

        if (array_key_exists('label', $data)) {
            $patch['label'] = trim((string) $data['label']);
        }
        if (array_key_exists('icon', $data)) {
            $patch['icon'] = $this->normalizeIcon($data['icon']);
        }
        if (array_key_exists('tone', $data)) {
            $patch['tone'] = $this->normalizeTone($data['tone']);
        }
        if (array_key_exists('link_type', $data)) {
            $patch['link_type'] = $this->normalizeLinkType($data['link_type']);
        }
        if (array_key_exists('link_target', $data) || array_key_exists('link_type', $data)) {
            $linkType = $patch['link_type'] ?? $row->link_type;
            $target = $data['link_target'] ?? $row->link_target;
            $patch['link_target'] = $this->normalizeLinkTarget($linkType, $target);
        }
        if (array_key_exists('is_visible', $data)) {
            $patch['is_visible'] = (bool) $data['is_visible'];
        }

        return $this->repository->update($row, $patch);
    }

    public function delete(string $utilityKey): void
    {
        $row = $this->repository->findByKey($utilityKey);
        if (! $row) {
            throw new \InvalidArgumentException('Không tìm thấy tiện ích.');
        }

        $this->repository->delete($row);
    }

    /**
     * @param  array<int, array{utility_key: string, sort_order: int}>  $items
     */
    public function reorder(array $items, ?int $updatedBy): void
    {
        $known = $this->repository->allOrdered()->keyBy('utility_key');
        foreach ($items as $item) {
            if (! $known->has($item['utility_key'])) {
                throw new \InvalidArgumentException('Tiện ích không hợp lệ: '.$item['utility_key']);
            }
        }

        $this->repository->reorder($items);
    }

    private function nextSortOrder(): int
    {
        $max = $this->repository->allOrdered()->max('sort_order');

        return ((int) $max) + 10;
    }

    private function normalizeKey(?string $key, string $label): string
    {
        $base = $key ? Str::slug($key, '_') : Str::slug($label, '_');
        if ($base === '') {
            $base = 'utility_'.Str::lower(Str::random(6));
        }

        return Str::limit($base, 64, '');
    }

    private function normalizeIcon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return 'layoutGrid';
        }

        return Str::limit($icon, 64, '');
    }

    private function normalizeTone(string $tone): string
    {
        $tone = strtolower(trim($tone));

        return in_array($tone, self::TONES, true) ? $tone : 'primary';
    }

    private function normalizeLinkType(string $linkType): string
    {
        $linkType = strtolower(trim($linkType));

        return in_array($linkType, self::LINK_TYPES, true) ? $linkType : 'none';
    }

    private function normalizeLinkTarget(string $linkType, mixed $target): ?string
    {
        $linkType = $this->normalizeLinkType($linkType);
        if ($linkType === 'none') {
            return null;
        }

        $target = trim((string) ($target ?? ''));
        if ($target === '') {
            throw new \InvalidArgumentException('Cần nhập đích liên kết khi chọn loại route hoặc URL.');
        }

        if ($linkType === 'url' && ! filter_var($target, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL không hợp lệ.');
        }

        return Str::limit($target, 512, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DashboardQuickUtility $row, bool $includeMeta = false): array
    {
        $payload = [
            'utility_key' => $row->utility_key,
            'label' => $row->label,
            'icon' => $row->icon,
            'tone' => $row->tone,
            'link_type' => $row->link_type,
            'link_target' => $row->link_target,
            'sort_order' => $row->sort_order,
            'is_visible' => $row->is_visible,
        ];

        if ($includeMeta) {
            $payload['updated_at'] = $row->updated_at?->toIso8601String();
        }

        return $payload;
    }
}
