<?php

namespace Modules\WorkspaceConfig\App\Services;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;

/**
 * Bật/tắt và đổi tên menu sidebar theo phòng ban. CHỈ các menu_key trong
 * whitelist dưới đây được phép cấu hình — đồng bộ THỦ CÔNG với các item
 * đánh dấu `configurableByDepartment: true` trong AppSidebar.vue.
 * Không bao giờ đưa menu superadmin/admin, hay tab con của hub
 * Cấu hình phòng ban (Menu / Thành viên / Tiêu chí), vào whitelist này.
 */
class DepartmentSidebarConfigService
{
    public const LABEL_MAX_LENGTH = 40;

    /** @var array<string, string> menu_key => nhãn tiếng Việt mặc định */
    private const CONFIGURABLE_MENUS = [
        'home' => 'Tổng quan',
        'social.feed' => 'Bảng tin nội bộ',
        'manager.evaluation.view' => 'Tiêu chí đánh giá',
        'manager.evaluation-templates.index' => 'Mẫu đánh giá',
    ];

    public function __construct(
        private readonly DepartmentSidebarConfigRepositoryInterface $configs,
    ) {}

    public function isConfigurable(string $menuKey): bool
    {
        return array_key_exists($menuKey, self::CONFIGURABLE_MENUS);
    }

    /** Nhãn mặc định của 1 menu_key, trả về chính key nếu không nằm trong whitelist. */
    public function menuLabel(string $menuKey): string
    {
        return self::CONFIGURABLE_MENUS[$menuKey] ?? $menuKey;
    }

    public function normalizeCustomLabel(?string $label, string $menuKey): ?string
    {
        $trimmed = trim((string) $label);
        if ($trimmed === '' || $trimmed === $this->menuLabel($menuKey)) {
            return null;
        }

        return mb_substr($trimmed, 0, self::LABEL_MAX_LENGTH);
    }

    public function effectiveLabel(?DepartmentSidebarConfig $override, string $menuKey): string
    {
        $custom = $this->normalizeCustomLabel($override?->custom_label, $menuKey);

        return $custom ?? $this->menuLabel($menuKey);
    }

    /** Toàn bộ menu có thể cấu hình + trạng thái hiện tại của 1 phòng ban. */
    public function forDepartment(int $departmentId): Collection
    {
        $overrides = $this->configs->allByDepartment($departmentId)->keyBy('menu_key');

        return collect(self::CONFIGURABLE_MENUS)->map(function (string $defaultLabel, string $menuKey) use ($overrides) {
            $override = $overrides->get($menuKey);
            $customLabel = $this->normalizeCustomLabel($override?->custom_label, $menuKey);

            return [
                'menu_key' => $menuKey,
                'default_label' => $defaultLabel,
                'custom_label' => $customLabel,
                'label' => $customLabel ?? $defaultLabel,
                'is_visible' => $override?->is_visible ?? true,
            ];
        })->values();
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
}
