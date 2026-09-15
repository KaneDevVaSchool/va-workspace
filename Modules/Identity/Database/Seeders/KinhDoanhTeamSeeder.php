<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;

/**
 * Nhân sự THẬT của Phòng Kinh doanh.
 *
 * Idempotent theo email (`updateOrCreate`) — chạy lại không tạo trùng.
 * SSOT nhân sự/tổ chức sẽ chuyển sang API VA-HRM sau này — khi đó sẽ tự
 * động mapping theo email, KHÔNG cần đổi cấu trúc bảng. Xem ghi chú "HRM
 * employee sync" trong docs/VA_WORKSPACE_OVERVIEW.md §8, §20.2.
 */
class KinhDoanhTeamSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'KD';

    public function run(): void
    {
        $department = Department::query()->where('code', self::DEPARTMENT_CODE)->first();
        if ($department === null) {
            $this->command?->warn('Không tìm thấy phòng ban Kinh doanh — bỏ qua KinhDoanhTeamSeeder.');

            return;
        }

        $roles = Role::query()->get()->keyBy('code');

        $team = Team::query()->updateOrCreate(
            ['department_id' => $department->id, 'name' => 'Kinh doanh'],
            [],
        );

        $giau = $this->upsertUser('giaultt@hcm.vaschools.edu.vn', 'Lê Thị Thanh Giàu', $department->id, $team->id);
        $this->assignRoles($giau, $roles, ['team_lead']);

        $duc = $this->upsertUser('ducdm@hcm.vaschools.edu.vn', 'Đỗ Minh Đức', $department->id, $team->id);
        $this->assignRoles($duc, $roles, ['member']);

        if ((int) $team->team_lead_id !== (int) $giau->id) {
            $team->team_lead_id = $giau->id;
            $team->save();
        }

        $this->command?->info('Đã seed nhân sự phòng Kinh doanh (team Kinh doanh).');
    }

    private function upsertUser(string $email, string $name, int $departmentId, ?int $teamId): User
    {
        $email = strtolower(trim($email));

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->status = 'active';
        $user->department_id = $departmentId;
        $user->team_id = $teamId;
        $user->save();

        return $user;
    }

    /**
     * @param  array<int, string>  $roleCodes
     */
    private function assignRoles(User $user, \Illuminate\Support\Collection $roles, array $roleCodes): void
    {
        $roleIds = collect($roleCodes)
            ->map(fn (string $code) => $roles->get($code)?->id)
            ->filter()
            ->values();

        if ($roleIds->isEmpty()) {
            return;
        }

        $user->roles()->syncWithoutDetaching($roleIds);
    }
}
