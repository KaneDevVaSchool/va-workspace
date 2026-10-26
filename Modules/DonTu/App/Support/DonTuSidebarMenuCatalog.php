<?php

namespace Modules\DonTu\App\Support;

/**
 * menu_key sidebar Đơn từ — đồng bộ thủ công với Modules/DonTu/resources/js/nav.js.
 *
 * Các màn cấu hình (trạng thái, mẫu biểu, quy trình…) không phải mục sidebar riêng;
 * chỉ có hub `manager.don-tu.config`, tab con trong DonTuLayout.vue.
 */
final class DonTuSidebarMenuCatalog
{
    public const SECTION = 'don-tu';

    public const CONFIG_HUB_KEY = 'manager.don-tu.config';

    /** @var array<string, string> menu_key => nhãn mặc định (chỉ mục sidebar) */
    public const LABELS = [
        'manager.don-tu.start' => 'Nộp đơn',
        'manager.don-tu.compose' => 'Soạn đơn',
        'manager.don-tu.mine' => 'Hồ sơ của tôi',
        'manager.don-tu.inbox' => 'Cần tôi duyệt',
        'manager.don-tu.registry' => 'Tra cứu',
        self::CONFIG_HUB_KEY => 'Cấu hình',
        'manager.don-tu.monitor' => 'Tiến độ',
        'manager.don-tu.sla' => 'Báo cáo SLA',
        'manager.don-tu.reports' => 'Báo cáo nghiệp vụ',
        'manager.don-tu.audits' => 'Nhật ký kiểm toán',
    ];

    /** @var array<string, string> menu_key => section_key mặc định */
    public const DEFAULT_SECTIONS = [
        'manager.don-tu.start' => 'don-tu',
        'manager.don-tu.compose' => 'don-tu',
        'manager.don-tu.mine' => 'don-tu',
        'manager.don-tu.inbox' => 'don-tu',
        'manager.don-tu.registry' => 'don-tu',
        self::CONFIG_HUB_KEY => 'don-tu',
        'manager.don-tu.monitor' => 'don-tu',
        'manager.don-tu.sla' => 'don-tu',
        'manager.don-tu.reports' => 'don-tu',
        'manager.don-tu.audits' => 'don-tu',
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
        'manager.don-tu.compose' => ['label' => 'Soạn đơn', 'section' => 'don-tu', 'icon' => 'pencil'],
        'manager.don-tu.mine' => ['label' => 'Hồ sơ của tôi', 'section' => 'don-tu', 'icon' => 'folder'],
        'manager.don-tu.inbox' => ['label' => 'Cần tôi duyệt', 'section' => 'don-tu', 'icon' => 'mail'],
        'manager.don-tu.registry' => ['label' => 'Tra cứu', 'section' => 'don-tu', 'icon' => 'search'],
        self::CONFIG_HUB_KEY => [
            'label' => 'Cấu hình',
            'section' => 'don-tu',
            'icon' => 'settings',
            'audience' => 'Bộ trạng thái, mẫu biểu và quy trình — tab con trong trang, không phải mục sidebar riêng.',
        ],
        'manager.don-tu.monitor' => ['label' => 'Tiến độ', 'section' => 'don-tu', 'icon' => 'activity'],
        'manager.don-tu.sla' => ['label' => 'Báo cáo SLA', 'section' => 'don-tu', 'icon' => 'clock'],
        'manager.don-tu.reports' => ['label' => 'Báo cáo nghiệp vụ', 'section' => 'don-tu', 'icon' => 'barChart'],
        'manager.don-tu.audits' => ['label' => 'Nhật ký kiểm toán', 'section' => 'don-tu', 'icon' => 'clock'],
    ];
}
