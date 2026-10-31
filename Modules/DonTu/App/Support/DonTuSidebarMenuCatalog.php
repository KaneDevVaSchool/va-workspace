<?php

namespace Modules\DonTu\App\Support;

/**
 * menu_key sidebar Đơn từ — đồng bộ thủ công với Modules/DonTu/resources/js/nav.js.
 *
 * Sidebar: chỉ hub Cấu hình. Nộp đơn, duyệt, tra cứu, giám sát còn placeholder — không đưa vào menu.
 */
final class DonTuSidebarMenuCatalog
{
    public const SECTION = 'don-tu';

    public const CONFIG_HUB_KEY = 'manager.don-tu.config';

    public const MONITOR_HUB_KEY = 'manager.don-tu.monitor-hub';

    /** @var array<string, string> menu_key => nhãn mặc định (chỉ mục sidebar) */
    public const LABELS = [
        self::CONFIG_HUB_KEY => 'Cấu hình',
    ];

    /** @var array<string, string> menu_key => section_key mặc định */
    public const DEFAULT_SECTIONS = [
        self::CONFIG_HUB_KEY => 'don-tu',
    ];

    /**
     * GlobalMenuVisibilityService::CATALOG — thứ tự key khớp nav.js / AppSidebar.
     *
     * @var array<string, array{label: string, section: string, icon: string, audience?: string}>
     */
    public const GLOBAL_CATALOG = [
        self::CONFIG_HUB_KEY => [
            'label' => 'Cấu hình',
            'section' => 'don-tu',
            'icon' => 'settings',
            'audience' => 'Bộ trạng thái, mẫu biểu và quy trình. Nộp đơn và duyệt chưa mở trên menu.',
        ],
    ];
}
