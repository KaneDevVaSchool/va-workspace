<?php

namespace Modules\WorkspaceConfig\App\Services;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\GlobalMenuVisibility;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuSectionConfigRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;

/**
 * Ẩn/hiện, đổi tên và sắp xếp menu sidebar Ở MỨC TOÀN HỆ THỐNG — chỉ
 * super_admin cấu hình được (permission workspace_config.manage_global_menu).
 *
 * CATALOG liệt kê TOÀN BỘ mục sidebar hiện có trong AppSidebar.vue
 * (MENU_SECTIONS) — đồng bộ THỦ CÔNG. Khi 1 menu bị ẩn ở đây, áp dụng
 * cho MỌI tài khoản không phải super_admin, thắng tuyệt đối per-department.
 */
class GlobalMenuVisibilityService
{
    /**
     * @var array<string, array{label: string, section: string, icon: string, audience?: string}>
     */
    public const CATALOG = [
        // section: general (Điều hướng)
        'home' => ['label' => 'Tổng quan', 'section' => 'general', 'icon' => 'dashboard'],
        'social.feed' => ['label' => 'Bảng tin nội bộ', 'section' => 'general', 'icon' => 'megaphone'],
        'manager.evaluation.view' => ['label' => 'Tiêu chí đánh giá', 'section' => 'general', 'icon' => 'clipboardCheck'],
        // section: admin (Quản trị)
        'superadmin.permissions' => ['label' => 'Phân quyền', 'section' => 'admin', 'icon' => 'shield'],
        'superadmin.activity' => ['label' => 'Nhật ký hoạt động', 'section' => 'admin', 'icon' => 'clock'],
        // section: manager (Quản lý)
        'manager.workspace-config.hub' => [
            'label' => 'Cấu hình phòng ban',
            'section' => 'manager',
            'icon' => 'settings',
            'audience' => 'Menu này chỉ hiện với trưởng phòng và phó phòng, không phải Super Admin.',
        ],
        'manager.evaluation-templates.index' => [
            'label' => 'Mẫu đánh giá',
            'section' => 'manager',
            'icon' => 'clipboardCheck',
            'audience' => 'Menu này chỉ hiện với trưởng phòng và phó phòng. Super Admin tạo mẫu dùng chung ở một trang riêng khác.',
        ],
        'manager.project.index' => [
            'label' => 'Dự án',
            'section' => 'manager',
            'icon' => 'layers',
            'audience' => 'Super Admin và mọi tài khoản có quyền xem dự án đều thấy mục này.',
        ],
        'manager.project.tasks' => [
            'label' => 'Công việc',
            'section' => 'manager',
            'icon' => 'layoutList',
            'audience' => 'Super Admin và mọi tài khoản có quyền xem công việc (kể cả nhân viên chỉ xem việc được giao) đều thấy mục này.',
        ],
        'manager.social.moderation' => [
            'label' => 'Duyệt bài viết',
            'section' => 'manager',
            'icon' => 'listChecks',
            'audience' => 'Menu này hiện với người được cấp quyền duyệt bài viết, không chỉ riêng Super Admin.',
        ],
        // section: superadmin-workspace-config (Cấu hình Workspace)
        'superadmin.workspace-config.overview' => [
            'label' => 'Cấu hình Workspace theo phòng ban',
            'section' => 'superadmin-workspace-config',
            'icon' => 'settings',
        ],
        'superadmin.workspace-config.global-menu' => [
            'label' => 'Ẩn/hiện menu toàn hệ thống',
            'section' => 'superadmin-workspace-config',
            'icon' => 'eyeOff',
        ],
    ];

    /** @var array<string, string> section_key => nhãn mặc định */
    public const SECTIONS = [
        'general' => 'Điều hướng',
        'admin' => 'Quản trị',
        'manager' => 'Quản lý',
        'superadmin-workspace-config' => 'Cấu hình Workspace',
    ];

    /** menu_key KHÔNG BAO GIỜ được phép tự ẩn — mở chính trang cấu hình này. */
    public const PROTECTED_MENU_KEYS = [
        'superadmin.workspace-config.global-menu',
    ];

    public function __construct(
        private readonly GlobalMenuVisibilityRepositoryInterface $repository,
        private readonly GlobalMenuSectionConfigRepositoryInterface $sectionConfigs,
    ) {}

    public function isKnown(string $menuKey): bool
    {
        return array_key_exists($menuKey, self::CATALOG);
    }

    public function isKnownSection(string $sectionKey): bool
    {
        return array_key_exists($sectionKey, self::SECTIONS);
    }

    public function isProtected(string $menuKey): bool
    {
        return in_array($menuKey, self::PROTECTED_MENU_KEYS, true);
    }

    public function label(string $menuKey): string
    {
        return self::CATALOG[$menuKey]['label'] ?? $menuKey;
    }

    public function hiddenKeys(): array
    {
        return $this->repository->hiddenKeys();
    }

    public function customLabels(): array
    {
        return $this->repository->customLabels();
    }

    public function sortOrders(): array
    {
        return $this->repository->sortOrders();
    }

    public function itemSections(): array
    {
        return $this->repository->itemSections();
    }

    public function sectionLabels(): array
    {
        return $this->sectionConfigs->sectionLabels();
    }

    /**
     * Toàn bộ catalog + trạng thái + tên tuỳ chỉnh + thứ tự, dùng cho trang
     * quản lý superadmin. Trả về mảng đã sắp xếp theo sort_order.
     */
    public function forListing(): Collection
    {
        $rows = $this->repository->all()->keyBy('menu_key');

        return collect(self::CATALOG)->map(function (array $meta, string $menuKey) use ($rows) {
            /** @var GlobalMenuVisibility|null $row */
            $row = $rows->get($menuKey);

            $defaultLabel = $meta['label'];
            $customLabel = $row?->custom_label;
            $effectiveLabel = ($customLabel !== null && $customLabel !== '') ? $customLabel : $defaultLabel;

            $defaultSection = $meta['section'];
            $effectiveSection = ($row?->section_key !== null && $row->section_key !== '') ? $row->section_key : $defaultSection;

            return [
                'menu_key' => $menuKey,
                'label' => $effectiveLabel,
                'default_label' => $defaultLabel,
                'custom_label' => $customLabel,
                'section' => $effectiveSection,
                'section_label' => self::SECTIONS[$defaultSection] ?? $defaultSection,
                'icon' => $meta['icon'],
                'audience' => $meta['audience'] ?? null,
                'is_hidden' => $row?->is_hidden ?? false,
                'is_visible' => ! ($row?->is_hidden ?? false),
                'is_protected' => $this->isProtected($menuKey),
                'sort_order' => $row?->sort_order ?? 0,
                'updated_by_name' => $row?->updatedBy?->name,
                'updated_at' => $row?->updated_at?->toIso8601String(),
            ];
        })->sortBy('sort_order')->values();
    }

    /**
     * Trả đầy đủ dữ liệu cho SidebarMenuConfigPanel: menus + sections array.
     *
     * @return array{menus: array<mixed>, sections: array<mixed>}
     */
    public function forListingFull(): array
    {
        return [
            'menus' => $this->forListing()->all(),
            'sections' => $this->sectionsForPanel(),
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setHidden(string $menuKey, bool $isHidden, ?int $updatedBy): GlobalMenuVisibility
    {
        if (! $this->isKnown($menuKey)) {
            throw new \InvalidArgumentException('Mục menu này không tồn tại.');
        }

        if ($isHidden && $this->isProtected($menuKey)) {
            throw new \InvalidArgumentException('Không thể ẩn mục menu mở trang cấu hình này.');
        }

        return $this->repository->setHidden($menuKey, $isHidden, $updatedBy);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setCustomLabel(string $menuKey, ?string $label, ?int $updatedBy): GlobalMenuVisibility
    {
        if (! $this->isKnown($menuKey)) {
            throw new \InvalidArgumentException('Mục menu này không tồn tại.');
        }

        $trimmed = ($label !== null) ? trim($label) : null;

        return $this->repository->setCustomLabel($menuKey, $trimmed ?: null, $updatedBy);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function updateSectionLabel(string $sectionKey, ?string $label, ?int $updatedBy): void
    {
        if (! $this->isKnownSection($sectionKey)) {
            throw new \InvalidArgumentException('Nhóm menu này không tồn tại.');
        }

        $trimmed = ($label !== null) ? trim($label) : null;

        $this->sectionConfigs->setSectionLabel($sectionKey, $trimmed ?: null, $updatedBy);
    }

    /**
     * Lưu thứ tự + nhóm mới sau khi kéo thả trên trang quản lý.
     *
     * @param  array<array{menu_key: string, section: string, sort_order: int}>  $items
     *
     * @throws \InvalidArgumentException nếu bất kỳ menu_key nào không hợp lệ
     */
    public function reorderItems(array $items, ?int $updatedBy): void
    {
        foreach ($items as $item) {
            if (! $this->isKnown($item['menu_key'])) {
                throw new \InvalidArgumentException('Mục menu "'.$item['menu_key'].'" không tồn tại.');
            }

            // Không cho phép chuyển nhóm sang section không xác định
            if ($item['section'] !== '' && ! $this->isKnownSection($item['section'])) {
                throw new \InvalidArgumentException('Nhóm menu "'.$item['section'].'" không tồn tại.');
            }
        }

        $this->repository->bulkUpdateLayout($items, $updatedBy);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array<array{id: string, label: string, defaultLabel: string, custom_label: string|null}> */
    private function sectionsForPanel(): array
    {
        $customLabels = $this->sectionConfigs->sectionLabels();

        return collect(self::SECTIONS)
            ->map(function (string $defaultLabel, string $sectionKey) use ($customLabels) {
                $custom = $customLabels[$sectionKey] ?? null;

                return [
                    'id' => $sectionKey,
                    'label' => ($custom !== null && $custom !== '') ? $custom : $defaultLabel,
                    'defaultLabel' => $defaultLabel,
                    'custom_label' => $custom,
                ];
            })
            ->values()
            ->all();
    }
}
