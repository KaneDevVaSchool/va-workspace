<?php

namespace Modules\Contract\App\Enums;

use Illuminate\Support\Carbon;

class ContractEnums
{
    public const SUPPLIER_DRAFT = 'draft';
    public const SUPPLIER_PENDING = 'pending_confirmation';
    public const SUPPLIER_ACTIVE = 'active';
    public const SUPPLIER_SUSPENDED = 'suspended';
    public const SUPPLIER_TERMINATED = 'terminated';

    public const SUPPLIER_STATUSES = [
        self::SUPPLIER_DRAFT,
        self::SUPPLIER_PENDING,
        self::SUPPLIER_ACTIVE,
        self::SUPPLIER_SUSPENDED,
        self::SUPPLIER_TERMINATED,
    ];

    public const SUPPLIER_STATUS_LABELS = [
        self::SUPPLIER_DRAFT => 'Nháp',
        self::SUPPLIER_PENDING => 'Chờ xác nhận',
        self::SUPPLIER_ACTIVE => 'Đang hợp tác',
        self::SUPPLIER_SUSPENDED => 'Tạm ngưng',
        self::SUPPLIER_TERMINATED => 'Ngừng hợp tác',
    ];

    public const DOCUMENT_NOT_PROVIDED = 'not_provided';
    public const DOCUMENT_PROVIDED = 'provided';
    public const DOCUMENT_REVIEWING = 'reviewing';
    public const DOCUMENT_VALID = 'valid';
    public const DOCUMENT_NEEDS_SUPPLEMENT = 'needs_supplement';
    public const DOCUMENT_INVALID = 'invalid';
    public const DOCUMENT_EXPIRED = 'expired';

    public const DOCUMENT_STATUSES = [
        self::DOCUMENT_NOT_PROVIDED,
        self::DOCUMENT_PROVIDED,
        self::DOCUMENT_REVIEWING,
        self::DOCUMENT_VALID,
        self::DOCUMENT_NEEDS_SUPPLEMENT,
        self::DOCUMENT_INVALID,
        self::DOCUMENT_EXPIRED,
    ];

    public const DOCUMENT_STATUS_LABELS = [
        self::DOCUMENT_NOT_PROVIDED => 'Chưa cung cấp',
        self::DOCUMENT_PROVIDED => 'Đã cung cấp',
        self::DOCUMENT_REVIEWING => 'Đang kiểm tra',
        self::DOCUMENT_VALID => 'Hợp lệ',
        self::DOCUMENT_NEEDS_SUPPLEMENT => 'Yêu cầu bổ sung',
        self::DOCUMENT_INVALID => 'Không hợp lệ',
        self::DOCUMENT_EXPIRED => 'Đã hết hạn',
    ];

    public const CONTRACT_DRAFTING = 'drafting';
    public const CONTRACT_INTERNAL_REVIEW = 'internal_review';
    public const CONTRACT_PENDING_APPROVAL = 'pending_approval';
    public const CONTRACT_PENDING_SIGNATURE = 'pending_signature';
    public const CONTRACT_EFFECTIVE = 'effective';
    public const CONTRACT_EXPIRED = 'expired';
    public const CONTRACT_LIQUIDATION_REQUESTED = 'liquidation_requested';
    public const CONTRACT_LIQUIDATED = 'liquidated';

    public const CONTRACT_STATUSES = [
        self::CONTRACT_DRAFTING,
        self::CONTRACT_INTERNAL_REVIEW,
        self::CONTRACT_PENDING_APPROVAL,
        self::CONTRACT_PENDING_SIGNATURE,
        self::CONTRACT_EFFECTIVE,
        self::CONTRACT_EXPIRED,
        self::CONTRACT_LIQUIDATION_REQUESTED,
        self::CONTRACT_LIQUIDATED,
    ];

    public const CONTRACT_STATUS_LABELS = [
        self::CONTRACT_DRAFTING => 'Đang soạn',
        self::CONTRACT_INTERNAL_REVIEW => 'Kiểm tra nội bộ',
        self::CONTRACT_PENDING_APPROVAL => 'Chờ phê duyệt',
        self::CONTRACT_PENDING_SIGNATURE => 'Chờ ký',
        self::CONTRACT_EFFECTIVE => 'Đang hiệu lực',
        self::CONTRACT_EXPIRED => 'Đã hết hạn',
        self::CONTRACT_LIQUIDATION_REQUESTED => 'Đề nghị thanh lý',
        self::CONTRACT_LIQUIDATED => 'Đã thanh lý',
    ];

    public const EXPIRY_WARNING_DAYS = [90, 30, 15, 7];
    public const DEFAULT_EXPIRY_DAYS = 30;

    public static function daysLeft(?Carbon $date): ?int
    {
        if ($date === null) {
            return null;
        }

        return now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
    }

    public static function expiryTone(?int $daysLeft): string
    {
        if ($daysLeft === null) {
            return 'neutral';
        }
        if ($daysLeft < 0 || $daysLeft <= 7) {
            return 'danger';
        }
        if ($daysLeft <= 30) {
            return 'warning';
        }
        if ($daysLeft <= 90) {
            return 'info';
        }

        return 'neutral';
    }
}
