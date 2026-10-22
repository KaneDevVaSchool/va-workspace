<?php

namespace Modules\Identity\App\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDatabaseMirrorService;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

/**
 * Đồng bộ hrm_employee_uuid trên users workspace theo email nhân sự trên DB HRM (SSOT).
 */
class ReconcileHrmEmployeeUuidsCommand extends Command
{
    protected $signature = 'identity:reconcile-hrm-employee-uuids
                            {--dry-run : Chỉ liệt kê, không ghi DB}
                            {--email= : Chỉ xử lý một email}';

    protected $description = 'Cập nhật hrm_employee_uuid users workspace khớp nhân sự HRM (theo email công ty / cá nhân)';

    public function handle(HrmEmployeeDirectory $directory): int
    {
        if (! HrmEmployeeDatabaseMirrorService::isConfigured()) {
            $this->error('Chưa cấu hình HRM_DB_* — chạy php artisan identity:hrm-db-diagnose.');

            return self::FAILURE;
        }

        try {
            $employees = $directory->load()['employees'] ?? [];
        } catch (HrmDatabaseUnavailable $e) {
            $this->error('Không đọc được DB HRM: '.$e->getMessage());

            return self::FAILURE;
        }

        $emailToUuid = $this->emailToUuidMap($employees);
        $validUuids = collect($employees)
            ->pluck('uuid')
            ->filter(fn ($u) => is_string($u) && $u !== '')
            ->flip();

        $onlyEmail = $this->option('email');
        if (filled($onlyEmail)) {
            $onlyEmail = Str::lower(trim((string) $onlyEmail));
        }

        $query = User::query()->orderBy('id');
        if ($onlyEmail !== null && $onlyEmail !== '') {
            $query->whereRaw('LOWER(email) = ?', [$onlyEmail]);
        }

        $fixed = 0;
        $ok = 0;
        $noMatch = 0;
        $conflicts = 0;
        $orphanUuid = 0;

        foreach ($query->cursor() as $user) {
            $email = Str::lower(trim((string) $user->email));
            $expected = $emailToUuid[$email] ?? null;

            if ($expected === null) {
                if (filled($user->hrm_employee_uuid) && ! $validUuids->has($user->hrm_employee_uuid)) {
                    $orphanUuid++;
                    $this->warn("  [orphan uuid] #{$user->id} {$user->email} uuid={$user->hrm_employee_uuid} — không có trên HRM, không khớp email");
                } else {
                    $noMatch++;
                    if ($onlyEmail) {
                        $this->warn("  Không tìm nhân sự HRM cho email {$email}");
                    }
                }

                continue;
            }

            if ((string) $user->hrm_employee_uuid === $expected) {
                $ok++;

                continue;
            }

            $holder = User::query()
                ->where('hrm_employee_uuid', $expected)
                ->where('id', '!=', $user->id)
                ->first();

            if ($holder !== null) {
                $conflicts++;
                $this->error("  [conflict] #{$user->id} {$email} cần uuid {$expected} — đã gán cho #{$holder->id} {$holder->email}");

                continue;
            }

            $from = $user->hrm_employee_uuid ?? '(null)';
            if ($this->option('dry-run')) {
                $this->line("  [dry-run] #{$user->id} {$email}: {$from} → {$expected}");
            } else {
                $user->forceFill(['hrm_employee_uuid' => $expected])->save();
                $this->line("  [fixed] #{$user->id} {$email}: {$from} → {$expected}");
            }
            $fixed++;
        }

        $this->newLine();
        $this->info("Khớp sẵn: {$ok} · Sửa: {$fixed} · Không có nhân sự HRM: {$noMatch} · UUID mồ côi: {$orphanUuid} · Xung đột: {$conflicts}");

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @return array<string, string> email lower → employee uuid
     */
    private function emailToUuidMap(array $employees): array
    {
        $map = [];
        $duplicates = [];

        foreach ($employees as $employee) {
            if (! is_array($employee) || empty($employee['uuid'])) {
                continue;
            }
            $uuid = (string) $employee['uuid'];
            foreach (['company_email', 'personal_email'] as $key) {
                $raw = $employee[$key] ?? null;
                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }
                $email = Str::lower(trim($raw));
                if (isset($map[$email]) && $map[$email] !== $uuid) {
                    $duplicates[$email] = true;
                    continue;
                }
                $map[$email] = $uuid;
            }
        }

        foreach (array_keys($duplicates) as $email) {
            unset($map[$email]);
            $this->warn("  Bỏ qua email trùng nhiều nhân sự HRM: {$email}");
        }

        return $map;
    }
}
