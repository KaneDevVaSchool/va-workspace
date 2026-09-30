<?php

namespace Modules\Identity\App\Hrm\DTO;

/**
 * Map từ AssignmentResource của VA-HRM (GET /api/v1/employees/{uuid}).
 * Không có uuid ổn định riêng cho từng assignment — chỉ company/org_unit/
 * position bên trong mới có uuid, xem HrmEmployeeSyncService.
 */
final class HrmAssignmentData
{
    public function __construct(
        public readonly bool $isPrimary,
        public readonly bool $isCurrent,
        public readonly ?string $jobTitleName,
        public readonly ?string $jobPositionLevel,
        public readonly ?string $companyHrmUuid,
        public readonly ?string $companyCode,
        public readonly ?string $companyName,
        public readonly ?string $orgUnitName,
        public readonly ?string $orgUnitPath,
        public readonly ?string $effectiveFrom,
        public readonly ?string $effectiveTo,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $company = $payload['company'] ?? null;
        $orgUnit = $payload['org_unit'] ?? null;
        $position = $payload['position'] ?? null;

        return new self(
            isPrimary: (bool) ($payload['is_primary'] ?? false),
            isCurrent: (bool) ($payload['is_current'] ?? false),
            jobTitleName: $position['title'] ?? null,
            jobPositionLevel: isset($position['level']) ? (string) $position['level'] : null,
            companyHrmUuid: $company['uuid'] ?? null,
            companyCode: $company['code'] ?? null,
            companyName: $company['name'] ?? null,
            orgUnitName: $orgUnit['name'] ?? null,
            orgUnitPath: $orgUnit['path'] ?? null,
            effectiveFrom: $payload['effective_from'] ?? null,
            effectiveTo: $payload['effective_to'] ?? null,
        );
    }
}
