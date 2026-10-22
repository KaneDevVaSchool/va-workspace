<?php

declare(strict_types=1);

$root = '/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;

$dryRun = in_array('--dry-run', $argv, true);

$directory = app(HrmEmployeeDirectory::class);
$employees = $directory->load()['employees'] ?? [];

$map = [];
$dup = [];
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
            $dup[$email] = true;
            continue;
        }
        $map[$email] = $uuid;
    }
}
foreach (array_keys($dup) as $email) {
    unset($map[$email]);
    echo "SKIP duplicate HRM email: {$email}\n";
}

$valid = collect($employees)->pluck('uuid')->filter()->flip();
$fixed = $ok = $noMatch = $conflicts = $orphan = 0;

foreach (User::query()->orderBy('id')->cursor() as $user) {
    $email = Str::lower(trim((string) $user->email));
    $expected = $map[$email] ?? null;

    if ($expected === null) {
        if (filled($user->hrm_employee_uuid) && ! $valid->has($user->hrm_employee_uuid)) {
            $orphan++;
            echo "ORPHAN #{$user->id} {$user->email} uuid={$user->hrm_employee_uuid}\n";
        } else {
            $noMatch++;
        }
        continue;
    }

    if ((string) $user->hrm_employee_uuid === $expected) {
        $ok++;

        continue;
    }

    $holder = User::query()->where('hrm_employee_uuid', $expected)->where('id', '!=', $user->id)->first();
    if ($holder) {
        $conflicts++;
        echo "CONFLICT #{$user->id} {$email} needs {$expected} held by #{$holder->id}\n";
        continue;
    }

    $from = $user->hrm_employee_uuid ?? 'null';
    echo ($dryRun ? 'DRY ' : 'FIX ')."#{$user->id} {$email}: {$from} -> {$expected}\n";
    if (! $dryRun) {
        $user->forceFill(['hrm_employee_uuid' => $expected])->save();
    }
    $fixed++;
}

echo "\nOK={$ok} fixed={$fixed} no_hrm={$noMatch} orphan={$orphan} conflicts={$conflicts}\n";
exit($conflicts > 0 ? 1 : 0);
