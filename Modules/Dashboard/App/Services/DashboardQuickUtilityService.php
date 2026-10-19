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

    /**
     * Bộ lối tắt cố định trên trang chủ app PWA nhân viên — superadmin bật/tắt, sắp xếp, biểu tượng và bảo trì.
     *
     * @var array<string, array{label: string, icon: string, tone: string, link_type: string, link_target: string, sort_order: int}>
     */
    public const CATALOG = [
        'attendance' => [
            'label' => 'Chấm công',
            'icon' => 'clock',
            'tone' => 'tertiary',
            'link_type' => 'route',
            'link_target' => 'employee.attendance',
            'sort_order' => 10,
        ],
        'leave' => [
            'label' => 'Nghỉ phép',
            'icon' => 'calendar',
            'tone' => 'gold',
            'link_type' => 'route',
            'link_target' => 'employee.leave',
            'sort_order' => 20,
        ],
        'contacts' => [
            'label' => 'Danh bạ',
            'icon' => 'users',
            'tone' => 'primary',
            'link_type' => 'route',
            'link_target' => 'employee.contacts',
            'sort_order' => 30,
        ],
        'social' => [
            'label' => 'Bảng tin',
            'icon' => 'megaphone',
            'tone' => 'tertiary',
            'link_type' => 'route',
            'link_target' => 'employee.feed',
            'sort_order' => 40,
        ],
        'chat' => [
            'label' => 'Trò chuyện',
            'icon' => 'messageCircle',
            'tone' => 'secondary',
            'link_type' => 'route',
            'link_target' => 'employee.chat',
            'sort_order' => 50,
        ],
    ];

    public function __construct(
        private readonly DashboardQuickUtilityRepositoryInterface $repository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forDashboard(): array
    {
        $this->syncCatalog();

        return $this->repository->visibleOrdered()
            ->map(fn (DashboardQuickUtility $row) => $this->serialize($row))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function forAdminListing(): array
    {
        $this->syncCatalog();

        return $this->repository->allOrdered()
            ->map(fn (DashboardQuickUtility $row) => $this->serialize($row, true))
            ->values()
            ->all();
    }

    public function syncCatalog(): void
    {
        $keys = array_keys(self::CATALOG);

        foreach ($this->repository->allOrdered() as $row) {
            if (! in_array($row->utility_key, $keys, true)) {
                $this->repository->delete($row);
            }
        }

        foreach (self::CATALOG as $utilityKey => $defaults) {
            $row = $this->repository->findByKey($utilityKey);
            if (! $row) {
                $this->repository->create([
                    'utility_key' => $utilityKey,
                    'label' => $defaults['label'],
                    'icon' => $defaults['icon'],
                    'tone' => $defaults['tone'],
                    'link_type' => $defaults['link_type'],
                    'link_target' => $defaults['link_target'],
                    'sort_order' => $defaults['sort_order'],
                    'is_visible' => true,
                    'is_under_maintenance' => false,
                ]);

                continue;
            }

            $needsLinkFix = $row->link_type !== $defaults['link_type']
                || $row->link_target !== $defaults['link_target'];

            if ($needsLinkFix) {
                $this->repository->update($row, [
                    'link_type' => $defaults['link_type'],
                    'link_target' => $defaults['link_target'],
                ]);
            }
        }
    }

    public function isCatalogKey(string $utilityKey): bool
    {
        return array_key_exists($utilityKey, self::CATALOG);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $updatedBy): DashboardQuickUtility
    {
        throw new \InvalidArgumentException('Chỉ có 5 lối tắt cố định (chấm công, nghỉ phép, danh bạ, bảng tin, trò chuyện). Không thêm mục mới.');
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
        if (! $this->isCatalogKey($utilityKey)) {
            if (array_key_exists('link_type', $data)) {
                $patch['link_type'] = $this->normalizeLinkType($data['link_type']);
            }
            if (array_key_exists('link_target', $data) || array_key_exists('link_type', $data)) {
                $linkType = $patch['link_type'] ?? $row->link_type;
                $target = $data['link_target'] ?? $row->link_target;
                $patch['link_target'] = $this->normalizeLinkTarget($linkType, $target);
            }
        }
        if (array_key_exists('is_visible', $data)) {
            $patch['is_visible'] = (bool) $data['is_visible'];
        }
        if (array_key_exists('is_under_maintenance', $data)) {
            $patch['is_under_maintenance'] = (bool) $data['is_under_maintenance'];
        }

        return $this->repository->update($row, $patch);
    }

    public function delete(string $utilityKey): void
    {
        if ($this->isCatalogKey($utilityKey)) {
            throw new \InvalidArgumentException('Không thể xoá lối tắt cố định. Chỉ có thể tắt hiển thị hoặc bật bảo trì.');
        }

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
            'is_under_maintenance' => $row->is_under_maintenance,
        ];

        if ($includeMeta) {
            $payload['updated_at'] = $row->updated_at?->toIso8601String();
            $payload['is_catalog_fixed'] = $this->isCatalogKey($row->utility_key);
        }

        return $payload;
    }
}
