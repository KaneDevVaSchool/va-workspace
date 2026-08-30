<?php

namespace Modules\WorkspaceConfig\App\Services;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;

/**
 * Bật/tắt, đổi tên, đổi thứ tự và đổi tên nhóm menu sidebar theo phòng ban.
 * CHỈ các menu_key trong whitelist dưới đây được phép cấu hình — đồng bộ
 * THỦ CÔNG với các item đánh dấu `configurableByDepartment: true` trong
 * AppSidebar.vue. Không bao giờ đưa menu superadmin/admin, hay tab con của
 * hub Cấu hình phòng ban (Menu / Thành viên / Tiêu chí), vào whitelist này.
 *
 * Menu đã bị superadmin ẩn TOÀN HỆ THỐNG (GlobalMenuVisibilityService) bị
 * loại khỏi danh sách cấu hình được ở đây — director không còn thấy/toggle
 * được nữa, vì bật cỡ nào cũng vô nghĩa (global luôn thắng tuyệt đối).
 */
class DepartmentSidebarConfigService
{
    public const LABEL_MAX_LENGTH = 40;

    public const SECTION_MENU_PREFIX = 'section:';

    /** @var array<string, string> section_key => nhãn tiếng Việt mặc định */
    public const SECTIONS = [
        'general' => 'Điều hướng',
        'manager' => 'Quản lý',
    ];

    /** @var array<string, string> menu_key => nhãn tiếng Việt mặc định */
    private const CONFIGURABLE_MENUS = [
        'home' => 'Tổng quan',
        'social.feed' => 'Bảng tin nội bộ',
        'manager.evaluation.view' => 'Tiêu chí đánh giá',
        'manager.evaluation-templates.index' => 'Mẫu đánh giá',
        'manager.evaluation-score-kit.index' => 'Khung chấm điểm',
        'manager.project.index' => 'Dự án',
        'manager.project.tasks' => 'Công việc',
    ];

    /** @var array<string, string> menu_key => section_key mặc định */
    private const MENU_DEFAULT_SECTIONS = [
        'home' => 'general',
        'social.feed' => 'general',
        'manager.evaluation.view' => 'general',
        'manager.evaluation-templates.index' => 'manager',
        'manager.evaluation-score-kit.index' => 'manager',
        'manager.project.index' => 'manager',
        'manager.project.tasks' => 'manager',
    ];

    public function __construct(
        private readonly DepartmentSidebarConfigRepositoryInterface $configs,
        private readonly GlobalMenuVisibilityRepositoryInterface $globalMenus,
    ) {}

    public function isConfigurable(string $menuKey): bool
    {
        return array_key_exists($menuKey, self::CONFIGURABLE_MENUS) && ! $this->isGloballyHidden($menuKey);
    }

    public function isConfigurableSection(string $sectionKey): bool
    {
        return array_key_exists($sectionKey, self::SECTIONS);
    }

    /** Menu đã bị superadmin ẩn TOÀN HỆ THỐNG — director không cấu hình được nữa. */
    public function isGloballyHidden(string $menuKey): bool
    {
        return $this->globalMenus->isHidden($menuKey);
    }

    /** Menu_key còn cấu hình được — đã loại bỏ những mục bị ẩn toàn hệ thống. */
    public function configurableMenuKeys(): array
    {
        $globallyHidden = $this->globalMenus->hiddenKeys();

        return array_values(array_diff(array_keys(self::CONFIGURABLE_MENUS), $globallyHidden));
    }

    /** Nhãn mặc định của 1 menu_key, trả về chính key nếu không nằm trong whitelist. */
    public function menuLabel(string $menuKey): string
    {
        return self::CONFIGURABLE_MENUS[$menuKey] ?? $menuKey;
    }

    public function sectionLabel(string $sectionKey): string
    {
        return self::SECTIONS[$sectionKey] ?? $sectionKey;
    }

    public function defaultSection(string $menuKey): string
    {
        return self::MENU_DEFAULT_SECTIONS[$menuKey] ?? 'other';
    }

    public function sectionMenuKey(string $sectionKey): string
    {
        return self::SECTION_MENU_PREFIX.$sectionKey;
    }

    public function normalizeCustomLabel(?string $label, string $menuKey): ?string
    {
        $trimmed = trim((string) $label);
        if ($trimmed === '' || $trimmed === $this->menuLabel($menuKey)) {
            return null;
        }

        return mb_substr($trimmed, 0, self::LABEL_MAX_LENGTH);
    }

    public function normalizeSectionLabel(?string $label, string $sectionKey): ?string
    {
        $trimmed = trim((string) $label);
        if ($trimmed === '' || $trimmed === $this->sectionLabel($sectionKey)) {
            return null;
        }

        return mb_substr($trimmed, 0, self::LABEL_MAX_LENGTH);
    }

    public function normalizeSection(?string $sectionKey): ?string
    {
        $trimmed = trim((string) $sectionKey);
        if ($trimmed === '' || ! $this->isConfigurableSection($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    public function effectiveLabel(?DepartmentSidebarConfig $override, string $menuKey): string
    {
        $custom = $this->normalizeCustomLabel($override?->custom_label, $menuKey);

        return $custom ?? $this->menuLabel($menuKey);
    }

    /**
     * Toàn bộ menu có thể cấu hình + trạng thái hiện tại của 1 phòng ban —
     * đã loại bỏ menu bị superadmin ẩn TOÀN HỆ THỐNG (xem configurableMenuKeys).
     */
    public function forDepartment(int $departmentId): Collection
    {
        $overrides = $this->configs->allByDepartment($departmentId)->keyBy('menu_key');
        $availableKeys = $this->configurableMenuKeys();
        $index = 0;

        return collect(self::CONFIGURABLE_MENUS)
            ->only($availableKeys)
            ->map(function (string $defaultLabel, string $menuKey) use ($overrides, &$index) {
                $override = $overrides->get($menuKey);
                $customLabel = $this->normalizeCustomLabel($override?->custom_label, $menuKey);
                $defaultSection = $this->defaultSection($menuKey);
                $section = $this->normalizeSection($override?->section_key) ?? $defaultSection;
                $sortOrder = $override?->sort_order;
                $row = [
                    'menu_key' => $menuKey,
                    'default_label' => $defaultLabel,
                    'custom_label' => $customLabel,
                    'label' => $customLabel ?? $defaultLabel,
                    'is_visible' => $override?->is_visible ?? true,
                    'section' => $section,
                    'default_section' => $defaultSection,
                    'sort_order' => $sortOrder === null ? $index : (int) $sortOrder,
                ];
                $index++;

                return $row;
            })->sortBy(function (array $item) {
            $sectionOrder = array_search($item['section'], array_keys(self::SECTIONS), true);
            if ($sectionOrder === false) {
                $sectionOrder = 99;
            }

            return sprintf('%02d-%05d-%s', $sectionOrder, $item['sort_order'], $item['menu_key']);
        })->values();
    }

    /**
     * @return list<array{id: string, default_label: string, custom_label: string|null, label: string}>
     */
    public function sectionsForDepartment(int $departmentId): array
    {
        $overrides = $this->configs->sectionLabelsForDepartment($departmentId);

        return collect(self::SECTIONS)->map(function (string $defaultLabel, string $sectionKey) use ($overrides) {
            $custom = $this->normalizeSectionLabel($overrides[$sectionKey] ?? null, $sectionKey);

            return [
                'id' => $sectionKey,
                'default_label' => $defaultLabel,
                'custom_label' => $custom,
                'label' => $custom ?? $defaultLabel,
            ];
        })->values()->all();
    }

    /**
     * @throws \InvalidArgumentException nếu menu_key không nằm trong whitelist
     */
    public function updateMenu(
        int $departmentId,
        string $menuKey,
        ?bool $isVisible,
        bool $updateLabel,
        ?string $customLabel,
        ?int $updatedBy,
    ): DepartmentSidebarConfig {
        if (! $this->isConfigurable($menuKey)) {
            throw new \InvalidArgumentException('Mục menu này không được phép cấu hình.');
        }

        $existing = $this->configs->findByDepartmentAndKey($departmentId, $menuKey);

        return $this->configs->upsert(
            $departmentId,
            $menuKey,
            $isVisible ?? $existing?->is_visible ?? true,
            $updatedBy,
            $updateLabel,
            $updateLabel ? $this->normalizeCustomLabel($customLabel, $menuKey) : null,
        );
    }

    /**
     * @param  list<array{menu_key: string, section: string}>  $items
     *
     * @throws \InvalidArgumentException
     */
    public function reorderMenus(int $departmentId, array $items, ?int $updatedBy): Collection
    {
        $expected = $this->configurableMenuKeys();
        $incoming = array_column($items, 'menu_key');
        sort($expected);
        $sortedIncoming = $incoming;
        sort($sortedIncoming);

        if ($expected !== $sortedIncoming) {
            throw new \InvalidArgumentException('Danh sách mục menu không khớp với các mục được phép cấu hình.');
        }

        foreach ($items as $index => $item) {
            $section = $this->normalizeSection($item['section'] ?? null);
            if ($section === null) {
                throw new \InvalidArgumentException('Nhóm menu không hợp lệ.');
            }

            $existing = $this->configs->findByDepartmentAndKey($departmentId, $item['menu_key']);

            $this->configs->upsert(
                $departmentId,
                $item['menu_key'],
                $existing?->is_visible ?? true,
                $updatedBy,
                false,
                null,
                true,
                $index,
                $section,
            );
        }

        return $this->forDepartment($departmentId);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function updateSection(int $departmentId, string $sectionKey, ?string $customLabel, ?int $updatedBy): array
    {
        if (! $this->isConfigurableSection($sectionKey)) {
            throw new \InvalidArgumentException('Nhóm menu này không được phép cấu hình.');
        }

        $existing = $this->configs->findByDepartmentAndKey($departmentId, $this->sectionMenuKey($sectionKey));

        $this->configs->upsert(
            $departmentId,
            $this->sectionMenuKey($sectionKey),
            true,
            $updatedBy,
            true,
            $this->normalizeSectionLabel($customLabel, $sectionKey),
            false,
            $existing?->sort_order,
            $sectionKey,
        );

        return $this->sectionsForDepartment($departmentId);
    }
}
