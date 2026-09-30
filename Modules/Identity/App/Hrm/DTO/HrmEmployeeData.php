<?php

namespace Modules\Identity\App\Hrm\DTO;

/**
 * Map từ EmployeeResource của VA-HRM (GET /api/v1/employees/{uuid}).
 * Các field manager_uuid, primary_assignment, concurrent_assignments dùng
 * whenLoaded() bên HRM nhưng EmployeeController luôn eager-load, nên trong
 * thực tế các field này luôn có mặt qua endpoint này.
 */
final class HrmEmployeeData
{
    /** @param list<HrmAssignmentData> $concurrentAssignments */
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly string $fullName,
        public readonly string $status,
        public readonly ?string $companyEmail,
        public readonly ?string $managerEmployeeUuid,
        public readonly ?string $managerDisplayName,
        public readonly ?HrmAssignmentData $primaryAssignment,
        public readonly array $concurrentAssignments,
    ) {}

    /**
     * @param array<string, mixed> $payload EmployeeResource
     * @param string|null $managerFullName resolve riêng qua GET /employees/{uuid}/manager
     *        (EmployeeResource không có field tên đầy đủ của manager, chỉ uuid/code/email)
     */
    public static function fromArray(array $payload, ?string $managerFullName = null): self
    {
        $primary = $payload['primary_assignment'] ?? null;
        $concurrent = $payload['concurrent_assignments'] ?? [];

        return new self(
            uuid: (string) $payload['uuid'],
            code: (string) $payload['code'],
            fullName: (string) $payload['full_name'],
            status: (string) $payload['status'],
            companyEmail: $payload['company_email'] ?? null,
            managerEmployeeUuid: $payload['manager_uuid'] ?? null,
            managerDisplayName: $managerFullName ?? $payload['manager_code'] ?? $payload['manager_email'] ?? null,
            primaryAssignment: $primary !== null ? HrmAssignmentData::fromArray($primary) : null,
            concurrentAssignments: array_map(
                fn (array $assignment) => HrmAssignmentData::fromArray($assignment),
                $concurrent,
            ),
        );
    }
}
