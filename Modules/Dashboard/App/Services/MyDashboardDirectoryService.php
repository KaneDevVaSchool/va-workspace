<?php

namespace Modules\Dashboard\App\Services;

use Illuminate\Support\Str;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\WorkspaceConfig\App\Services\WorkspaceConfigMemberService;

/**
 * Danh bạ nội bộ trên Dashboard của tôi — chỉ trả về thông tin liên hệ công khai
 * (tên, email, avatar, chức danh, phòng ban), không lộ hồ sơ HRM đầy đủ.
 */
class MyDashboardDirectoryService
{
    public function __construct(
        private readonly WorkspaceConfigMemberService $members,
    ) {}

    /**
     * @return array{members: list<array<string, mixed>>}
     *
     * @throws HrmDatabaseUnavailable
     */
    public function listing(): array
    {
        $roster = HrmEmployeeDirectory::isConfigured()
            ? $this->members->rosterFromHrmDatabase()
            : $this->members->workspaceRoster();

        $members = collect($roster['members'] ?? [])
            ->map(fn (array $row) => $this->presentEntry($row))
            ->filter(fn (array $row) => filled($row['name']))
            ->sortBy(
                fn (array $row) => Str::lower($row['name']),
                SORT_NATURAL,
            )
            ->values()
            ->all();

        return ['members' => $members];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentEntry(array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $department = $row['department']['name']
            ?? $row['hrm_department_name']
            ?? $row['profile_department_name']
            ?? null;

        return [
            'id' => $row['id'] ?? $row['hrm_employee_uuid'] ?? null,
            'name' => $name,
            'email' => filled($row['email'] ?? null) ? $row['email'] : null,
            'avatar_url' => filled($row['avatar_url'] ?? null) ? $row['avatar_url'] : null,
            'job_title' => filled($row['job_title_name'] ?? null) ? $row['job_title_name'] : null,
            'department_name' => filled($department) ? $department : null,
        ];
    }
}
