<?php

namespace Modules\DonTu\App\Support;

/**
 * menu_key sidebar Đơn từ — đồng bộ thủ công với Modules/DonTu/resources/js/nav.js.
 *
 * Sidebar: 4 mục làm việc + hub Cấu hình + hub Giám sát.
 * Soạn đơn, chi tiết hồ sơ, sửa mẫu/quy trình: route only (không menu_key sidebar).
 */
final class DonTuSidebarMenuCatalog
{
    public const SECTION = 'don-tu';

    public const CONFIG_HUB_KEY = 'manager.don-tu.config';

    public const MONITOR_HUB_KEY = 'manager.don-tu.monitor-hub';

    /** @var array<string, string> menu_key => nhãn mặc định (chỉ mục sidebar) */
    public const LABELS = [
        'manager.don-tu.start' => 'Nộp đơn',
        'manager.don-tu.mine' => 'Hồ sơ của tôi',
        'manager.don-tu.inbox' => 'Cần tôi duyệt',
        'manager.don-tu.registry' => 'Tra cứu',
        self::CONFIG_HUB_KEY => 'Cấu hình',
        self::MONITOR_HUB_KEY => 'Giám sát',
    ];

    /** @var array<string, string> menu_key => section_key mặc định */
    public const DEFAULT_SECTIONS = [
        'manager.don-tu.start' => 'don-tu',
        'manager.don-tu.mine' => 'don-tu',
        'manager.don-tu.inbox' => 'don-tu',
        'manager.don-tu.registry' => 'don-tu',
        self::CONFIG_HUB_KEY => 'don-tu',
        self::MONITOR_HUB_KEY => 'don-tu',
    ];

    /**
     * GlobalMenuVisibilityService::CATALOG — thứ tự key khớp nav.js / AppSidebar.
     *
     * @var array<string, array{label: string, section: string, icon: string, audience?: string}>
     */
    public const GLOBAL_CATALOG = [
        'manager.don-tu.start' => [
            'label' => 'Nộp đơn',
            'section' => 'don-tu',
            'icon' => 'fileText',
            'audience' => 'Mọi tài khoản đã đăng nhập. Bản xem trước các màn đơn từ, chưa khóa theo quyền.',
        ],
        'manager.don-tu.mine' => ['label' => 'Hồ sơ của tôi', 'section' => 'don-tu', 'icon' => 'folder'],
        'manager.don-tu.inbox' => ['label' => 'Cần tôi duyệt', 'section' => 'don-tu', 'icon' => 'mail'],
        'manager.don-tu.registry' => ['label' => 'Tra cứu', 'section' => 'don-tu', 'icon' => 'search'],
        self::CONFIG_HUB_KEY => [
            'label' => 'Cấu hình',
            'section' => 'don-tu',
            'icon' => 'settings',
            'audience' => 'Bộ trạng thái và trạng thái hồ sơ đơn.',
        ],
        self::MONITOR_HUB_KEY => [
            'label' => 'Giám sát',
            'section' => 'don-tu',
            'icon' => 'activity',
            'audience' => 'Tiến độ, SLA, báo cáo và nhật ký — tab trong trang.',
        ],
    ];
}
