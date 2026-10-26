<?php

namespace Modules\Dashboard\App\Services;

use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

class MyDashboardBirthdayGreetingService
{
    public function __construct(
        private readonly HrmEmployeeDirectory $hrmEmployees,
    ) {}

    /**
     * Sinh nhật hôm nay trên HRM — mọi nhân viên đăng nhập đều nhận cùng danh sách
     * để cùng thấy lời chúc.
     *
     * @return array{people: list<array{hrm_employee_uuid: string, name: string, department_name: ?string}>}
     *
     * @throws HrmDatabaseUnavailable
     */
    public function status(): array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return ['people' => []];
        }

        $today = now()->toDateString();

        $people = collect($this->hrmEmployees->upcomingBirthdays(1))
            ->filter(fn (array $row) => ($row['next_birthday'] ?? null) === $today)
            ->map(fn (array $row) => [
                'hrm_employee_uuid' => (string) ($row['hrm_employee_uuid'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '')),
                'department_name' => filled($row['department_name'] ?? null)
                    ? (string) $row['department_name']
                    : null,
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->unique(fn (array $row) => $row['hrm_employee_uuid'] !== '' ? $row['hrm_employee_uuid'] : $row['name'])
            ->values()
            ->all();

        return ['people' => $people];
    }
}
