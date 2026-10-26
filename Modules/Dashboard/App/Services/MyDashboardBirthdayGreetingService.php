<?php

namespace Modules\Dashboard\App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

class MyDashboardBirthdayGreetingService
{
    public function __construct(
        private readonly HrmEmployeeDirectory $hrmEmployees,
    ) {}

    /**
     * @return array{is_birthday_today: bool, first_name: ?string}
     *
     * @throws HrmDatabaseUnavailable
     */
    public function status(User $user): array
    {
        $firstName = $this->firstNameFrom($user->name);

        if (! HrmEmployeeDirectory::isConfigured()) {
            return [
                'is_birthday_today' => false,
                'first_name' => $firstName,
            ];
        }

        $uuid = trim((string) $user->hrm_employee_uuid);
        $email = trim((string) $user->email);

        $hrm = $this->hrmEmployees->findEmployeeForUser(
            $uuid !== '' ? $uuid : null,
            $email !== '' ? $email : null,
        );

        $birthdate = is_array($hrm) ? ($hrm['birthdate'] ?? null) : null;

        return [
            'is_birthday_today' => $this->isBirthdayToday(is_string($birthdate) ? $birthdate : null),
            'first_name' => $firstName,
        ];
    }

    private function isBirthdayToday(?string $birthdate): bool
    {
        if (! filled($birthdate)) {
            return false;
        }

        try {
            $parsed = Carbon::parse($birthdate);
        } catch (\Throwable) {
            return false;
        }

        $today = now()->startOfDay();

        return $parsed->month === $today->month && $parsed->day === $today->day;
    }

    private function firstNameFrom(?string $fullName): ?string
    {
        if (! filled($fullName)) {
            return null;
        }

        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return null;
        }

        return (string) end($parts);
    }
}
