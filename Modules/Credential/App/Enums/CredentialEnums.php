<?php

namespace Modules\Credential\App\Enums;

/**
 * Hằng số dùng chung cho module Credential — loại tài khoản và ngưỡng
 * ngày tính trạng thái hạn dùng (xem Credential::getStatusAttribute()).
 */
class CredentialEnums
{
    public const ACCOUNT_TYPES = [
        'admin', 'superadmin', 'user', 'demo', 'testing',
        'phu_huynh', 'giao_vien', 'hoc_sinh',
    ];

    public const ACCOUNT_TYPE_LABELS = [
        'admin' => 'Quản trị viên',
        'superadmin' => 'Quản trị viên cấp cao',
        'user' => 'Người dùng',
        'demo' => 'Dùng thử',
        'testing' => 'Kiểm thử',
        'phu_huynh' => 'Phụ huynh',
        'giao_vien' => 'Giáo viên',
        'hoc_sinh' => 'Học sinh',
    ];

    /**
     * Phân nhóm tài khoản — hiển thị thành 2 tab riêng trên trang danh
     * sách (external: dịch vụ/phần mềm bên thứ ba; internal: công cụ do
     * VA Schools tự phát triển, đăng nhập qua Google Workspace công ty).
     * Mặc định 'external' (xem migration add_access_url_and_group).
     */
    public const GROUP_EXTERNAL = 'external';

    public const GROUP_INTERNAL = 'internal';

    public const GROUP_LABELS = [
        self::GROUP_EXTERNAL => 'Phần mềm/Dịch vụ bên ngoài',
        self::GROUP_INTERNAL => 'Công cụ nội bộ VA Schools',
    ];

    /** Còn ≤ số ngày này đến hạn → trạng thái "Chuẩn bị gia hạn". */
    public const RENEWING_SOON_DAYS = 30;

    /** Còn ≤ số ngày này đến hạn → trạng thái "Sắp hết hạn" (thắng RENEWING_SOON_DAYS). */
    public const EXPIRING_SOON_DAYS = 7;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_RENEWING_SOON = 'renewing_soon';
    public const STATUS_EXPIRING_SOON = 'expiring_soon';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Đang sử dụng',
        self::STATUS_RENEWING_SOON => 'Chuẩn bị gia hạn',
        self::STATUS_EXPIRING_SOON => 'Sắp hết hạn',
        self::STATUS_EXPIRED => 'Đã hết hạn',
    ];

    /**
     * Tỷ giá quy đổi USD → VND dùng riêng cho dự toán chi phí (Tab dự toán
     * — CredentialService::costSummary()), để cộng dồn được các bản ghi
     * currency khác nhau về cùng 1 đơn vị. Không dùng cho hiển thị số tiền
     * gốc của từng tài khoản (vẫn giữ nguyên currency đã lưu).
     */
    public const USD_TO_VND = 26000;

    /**
     * Số lát tối đa hiện riêng màu trên biểu đồ dự toán chi phí — vượt quá
     * sẽ gộp phần còn lại vào 1 lát "Khác" (xem
     * CredentialService::collapseToSlices()). Tab dashboard này KHÔNG dùng
     * --color-primary (#9a0036) theo yêu cầu — chỉ còn 2 hue categorical
     * (secondary/tertiary) đã chạy validate_palette.js (skill dataviz) xác
     * nhận PASS mọi check colorblind-safe; mọi ứng viên thứ 3 (gold/umber
     * các bậc, --color-info) đều FAIL chroma/lightness hoặc trùng nghĩa màu
     * trạng thái đã dùng trong chính trang này (--color-warning).
     */
    public const COST_CHART_MAX_SLICES = 2;
}
