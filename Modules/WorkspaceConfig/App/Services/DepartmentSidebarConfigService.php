<?php

namespace Modules\WorkspaceConfig\App\Services;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\DepartmentSidebarConfig;
use Modules\Identity\App\Repositories\Contracts\DepartmentSidebarConfigRepositoryInterface;

/**
 * Bật/tắt menu sidebar theo phòng ban. CHỈ các menu_key trong whitelist
 * dưới đây được phép cấu hình — đồng bộ THỦ CÔNG với các item đánh dấu
 * `configurableByDepartment: true` trong resources/js/components/AppSidebar.vue.
 * Không bao giờ đưa menu superadmin/admin, hay tab con của hub
 * Cấu hình phòng ban (Thành viên / Menu / Tiêu chí), vào whitelist này.
 */
class DepartmentSidebarConfigService
{
    /** @var array<string, string> menu_key => nhãn tiếng Việt hiển thị trên UI cấu hình */
    private const CONFIGURABLE_MENUS = [
        'home' => 'Tổng quan',
        'social.feed' => 'Bảng tin nội bộ',
        'manager.evaluation.view' => 'Tiêu chí đánh giá',
    ];

    public function __construct(
        private readonly DepartmentSidebarConfigRepositoryInterface $configs,
    ) {}

    public function isConfigurable(string $menuKey): bool
    {
        return array_key_exists($menuKey, self::CONFIGURABLE_MENUS);
    }

    /** Nhãn tiếng Việt của 1 menu_key, trả về chính key nếu không nằm trong whitelist. */
    public function menuLabel(string $menuKey): string
    {
        return self::CONFIGURABLE_MENUS[$menuKey] ?? $menuKey;
    }

    /** Toàn bộ menu có thể cấu hình + trạng thái is_visible hiện tại của 1 phòng ban. */
    public function forDepartment(int $departmentId): Collection
    {
        $overrides = $this->configs->allByDepartment($departmentId)->keyBy('menu_key');

        return collect(self::CONFIGURABLE_MENUS)->map(function (string $label, string $menuKey) use ($overrides) {
            $override = $overrides->get($menuKey);

            return [
                'menu_key' => $menuKey,
                'label' => $label,
                'is_visible' => $override?->is_visible ?? true,
            ];
        })->values();
    }

    /**
     * @throws \InvalidArgumentException nếu menu_key không nằm trong whitelist
     */
    public function setVisibility(int $departmentId, string $menuKey, bool $isVisible, ?int $updatedBy): DepartmentSidebarConfig
    {
        if (! $this->isConfigurable($menuKey)) {
            throw new \InvalidArgumentException('Mục menu này không được phép cấu hình.');
        }

        return $this->configs->setVisibility($departmentId, $menuKey, $isVisible, $updatedBy);
    }
}
