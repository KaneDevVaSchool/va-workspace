<?php

namespace Modules\DonTu\App\Support;

/**
 * menu_key sidebar Đơn từ — đồng bộ thủ công với Modules/DonTu/resources/js/nav.js.
 */
final class DonTuSidebarMenuCatalog
{
    public const SECTION = 'don-tu';

    /** @var array<string, string> menu_key => nhãn mặc định */
    public const LABELS = [
        'manager.don-tu.start' => 'Nộp đơn',
        'manager.don-tu.compose' => 'Soạn đơn',
        'manager.don-tu.mine' => 'Hồ sơ của tôi',
        'manager.don-tu.show' => 'Chi tiết hồ sơ',
        'manager.don-tu.inbox' => 'Cần tôi duyệt',
        'manager.don-tu.registry' => 'Tra cứu',
        'manager.don-tu.statuses' => 'Bộ trạng thái',
        'manager.don-tu.forms' => 'Mẫu biểu',
        'manager.don-tu.form' => 'Cấu hình mẫu biểu',
        'manager.don-tu.workflows' => 'Quy trình',
        'manager.don-tu.workflow' => 'Cấu hình quy trình',
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
        'manager.don-tu.show' => 'don-tu',
        'manager.don-tu.inbox' => 'don-tu',
        'manager.don-tu.registry' => 'don-tu',
        'manager.don-tu.statuses' => 'don-tu',
        'manager.don-tu.forms' => 'don-tu',
        'manager.don-tu.form' => 'don-tu',
        'manager.don-tu.workflows' => 'don-tu',
        'manager.don-tu.workflow' => 'don-tu',
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
        'manager.don-tu.show' => ['label' => 'Chi tiết hồ sơ', 'section' => 'don-tu', 'icon' => 'fileText'],
        'manager.don-tu.inbox' => ['label' => 'Cần tôi duyệt', 'section' => 'don-tu', 'icon' => 'mail'],
        'manager.don-tu.registry' => ['label' => 'Tra cứu', 'section' => 'don-tu', 'icon' => 'search'],
        'manager.don-tu.statuses' => ['label' => 'Bộ trạng thái', 'section' => 'don-tu', 'icon' => 'layers'],
        'manager.don-tu.forms' => ['label' => 'Mẫu biểu', 'section' => 'don-tu', 'icon' => 'clipboardCheck'],
        'manager.don-tu.form' => ['label' => 'Cấu hình mẫu biểu', 'section' => 'don-tu', 'icon' => 'settings'],
        'manager.don-tu.workflows' => ['label' => 'Quy trình', 'section' => 'don-tu', 'icon' => 'gitBranch'],
        'manager.don-tu.workflow' => ['label' => 'Cấu hình quy trình', 'section' => 'don-tu', 'icon' => 'settings'],
        'manager.don-tu.monitor' => ['label' => 'Tiến độ', 'section' => 'don-tu', 'icon' => 'activity'],
        'manager.don-tu.sla' => ['label' => 'Báo cáo SLA', 'section' => 'don-tu', 'icon' => 'clock'],
        'manager.don-tu.reports' => ['label' => 'Báo cáo nghiệp vụ', 'section' => 'don-tu', 'icon' => 'barChart'],
        'manager.don-tu.audits' => ['label' => 'Nhật ký kiểm toán', 'section' => 'don-tu', 'icon' => 'clock'],
    ];
}
