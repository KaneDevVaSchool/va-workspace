<?php

namespace Modules\Credential\App\Enums;

/**
 * Hằng số dùng chung cho module Credential — loại tài khoản và ngưỡng
 * ngày tính trạng thái hạn dùng (xem Credential::getStatusAttribute()).
 */
class CredentialEnums
{
    public const ACCOUNT_TYPES = ['admin', 'superadmin', 'user', 'demo', 'testing'];

    public const ACCOUNT_TYPE_LABELS = [
        'admin' => 'Quản trị viên',
        'superadmin' => 'Quản trị viên cấp cao',
        'user' => 'Người dùng',
        'demo' => 'Dùng thử',
        'testing' => 'Kiểm thử',
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
}
