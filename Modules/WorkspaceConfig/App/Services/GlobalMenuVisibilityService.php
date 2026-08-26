<?php

namespace Modules\WorkspaceConfig\App\Services;

use Illuminate\Support\Collection;
use Modules\Identity\App\Models\GlobalMenuVisibility;
use Modules\Identity\App\Repositories\Contracts\GlobalMenuVisibilityRepositoryInterface;

/**
 * Ẩn/hiện menu sidebar Ở MỨC TOÀN HỆ THỐNG — chỉ super_admin cấu hình
 * được (permission workspace_config.manage_global_menu). Danh sách
 * CATALOG dưới đây liệt kê TOÀN BỘ mục sidebar hiện có trong
 * AppSidebar.vue (MENU_SECTIONS) — đồng bộ THỦ CÔNG, khác với whitelist
 * hẹp CONFIGURABLE_MENUS của DepartmentSidebarConfigService (per-department,
 * chỉ 4 menu). Khi 1 menu bị ẩn ở đây, áp dụng cho MỌI tài khoản không
 * phải super_admin, thắng tuyệt đối per-department override.
 */
class GlobalMenuVisibilityService
{
    /**
     * @var array<string, array{label: string, section: string, audience?: string}>
     * `audience` chỉ khai báo cho mục dễ hiểu nhầm là dành cho super_admin —
     * hiển thị thêm 1 dòng chú thích ở trang quản lý để tránh nhầm lẫn.
     */
    public const CATALOG = [
        // section: general (Điều hướng)
        'home' => ['label' => 'Tổng quan', 'section' => 'general'],
        'social.feed' => ['label' => 'Bảng tin nội bộ', 'section' => 'general'],
        'manager.evaluation.view' => ['label' => 'Tiêu chí đánh giá', 'section' => 'general'],
        // section: admin (Quản trị) — chỉ super_admin thấy, liệt kê để đồng bộ
        'superadmin.permissions' => ['label' => 'Phân quyền', 'section' => 'admin'],
        'superadmin.activity' => ['label' => 'Nhật ký hoạt động', 'section' => 'admin'],
        // section: manager (Quản lý) — các mục này KHÔNG dành cho super_admin,
        // mà cho trưởng/phó phòng ban (hoặc người được cấp quyền tương ứng).
        'manager.workspace-config.hub' => [
            'label' => 'Cấu hình phòng ban',
            'section' => 'manager',
            'audience' => 'Menu này chỉ hiện với trưởng phòng và phó phòng, không phải Super Admin.',
        ],
        'manager.evaluation-templates.index' => [
            'label' => 'Mẫu đánh giá',
            'section' => 'manager',
            'audience' => 'Menu này chỉ hiện với trưởng phòng và phó phòng. Super Admin tạo mẫu dùng chung ở một trang riêng khác.',
        ],
        'manager.social.moderation' => [
            'label' => 'Duyệt bài viết',
            'section' => 'manager',
            'audience' => 'Menu này hiện với người được cấp quyền duyệt bài viết, không chỉ riêng Super Admin.',
        ],
        // section: superadmin-workspace-config (Cấu hình Workspace)
        'superadmin.workspace-config.overview' => ['label' => 'Cấu hình Workspace theo phòng ban', 'section' => 'superadmin-workspace-config'],
        'superadmin.workspace-config.global-menu' => ['label' => 'Ẩn/hiện menu toàn hệ thống', 'section' => 'superadmin-workspace-config'],
    ];

    /** @var array<string, string> section_key => nhãn tiếng Việt */
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
    ) {}

    public function isKnown(string $menuKey): bool
    {
        return array_key_exists($menuKey, self::CATALOG);
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

    /** Toàn bộ catalog + trạng thái hiện tại, dùng cho trang quản lý superadmin. */
    public function forListing(): Collection
    {
        $rows = $this->repository->all()->keyBy('menu_key');

        return collect(self::CATALOG)->map(function (array $meta, string $menuKey) use ($rows) {
            /** @var GlobalMenuVisibility|null $row */
            $row = $rows->get($menuKey);

            return [
                'menu_key' => $menuKey,
                'label' => $meta['label'],
                'section' => $meta['section'],
                'section_label' => self::SECTIONS[$meta['section']] ?? $meta['section'],
                'audience' => $meta['audience'] ?? null,
                'is_hidden' => $row?->is_hidden ?? false,
                'is_protected' => $this->isProtected($menuKey),
                'updated_by_name' => $row?->updatedBy?->name,
                'updated_at' => $row?->updated_at?->toIso8601String(),
            ];
        })->values();
    }

    /**
     * @throws \InvalidArgumentException nếu menu_key không nằm trong catalog,
     *         hoặc cố ẩn 1 menu_key được bảo vệ
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
}
