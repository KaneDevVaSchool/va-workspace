<?php

namespace Modules\Social\App\Services;

use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

class SocialBirthdayService
{
    public function __construct(
        private readonly HrmEmployeeDirectory $directory,
        private readonly HrmEmployeeDatabaseMirrorService $mirror,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return list<array{user_id: ?int, name: string, avatar_url: ?string, department_name: ?string, next_birthday: string}>
     */
    public function upcoming(int $withinDays = 14): array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return [];
        }

        $this->mirror->mirrorIfStale();

        try {
            $rows = $this->directory->upcomingBirthdays($withinDays);
        } catch (HrmDatabaseUnavailable) {
            return [];
        }

        return collect($rows)
            ->map(function (array $row) {
                $user = $this->users->findByHrmEmployeeUuid($row['hrm_employee_uuid']);

                return [
                    'user_id' => $user?->id,
                    'name' => $row['name'],
                    'avatar_url' => $row['avatar_url'] ?? $user?->avatar_url,
                    'department_name' => $row['department_name'],
                    'next_birthday' => $row['next_birthday'],
                ];
            })
            ->filter(fn (array $row) => filled($row['name']))
            ->values()
            ->all();
    }
}
