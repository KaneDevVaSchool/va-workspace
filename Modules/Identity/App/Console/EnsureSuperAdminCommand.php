<?php

namespace Modules\Identity\App\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Identity\App\Services\SuperAdminBootstrap;
use Modules\Identity\Database\Seeders\DepartmentSeeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class EnsureSuperAdminCommand extends Command
{
    protected $signature = 'identity:ensure-super-admin
                            {email? : Email super admin (mặc định SUPERADMIN_EMAIL / khoana@hcm.vaschools.edu.vn)}';

    protected $description = 'Seed 7 role hệ thống và gán đủ role cho super admin (view-as)';

    public function handle(SuperAdminBootstrap $bootstrap): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $bootstrap->configuredEmail())));

        $this->info('Seeding roles…');
        $this->callSilent(RoleSeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->callSilent(DepartmentSeeder::class);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user === null) {
            $user = User::query()->create([
                'email' => $email,
                'name' => 'Super Admin',
                'status' => 'active',
            ]);
            $this->warn("Chưa có user Google — đã tạo bản ghi placeholder (email: {$email}).");
        }

        $user->status = 'active';
        $user->save();

        $bootstrap->ensureRolesForUser($user);
        $user->load('roles');

        $roles = $user->roles->pluck('code')->implode(', ');
        $this->info("OK — {$user->email} (id {$user->id})");
        $this->line("Roles: {$roles}");
        $this->line('can_view_as: '.($user->isSuperAdmin() ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
