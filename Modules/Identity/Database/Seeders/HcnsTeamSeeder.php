<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;

/**
 * Nhân sự THẬT của Phòng Hành chính Nhân sự (HCNS).
 *
 * Idempotent theo email (`updateOrCreate`) — chạy lại không tạo trùng.
 * `hrm_team_uuid` (Team) để trống (null): SSOT nhân sự/tổ chức sẽ chuyển
 * sang API VA-HRM sau này — khi đó chỉ cần điền `hrm_team_uuid` để đối
 * chiếu, KHÔNG cần đổi cấu trúc bảng. Xem
 * Modules/Identity/App/Models/Team.php và ghi chú "HRM employee sync"
 * trong docs/VA_WORKSPACE_OVERVIEW.md §8, §20.2.
 */
class HcnsTeamSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'HCNS';

    public function run(): void
    {
        $department = Department::query()->where('code', self::DEPARTMENT_CODE)->first();
        if ($department === null) {
            $this->command?->warn('Không tìm thấy phòng ban HCNS — bỏ qua HcnsTeamSeeder.');

            return;
        }

        $roles = Role::query()->get()->keyBy('code');

        $linh = $this->upsertUser('linhhdk@hcm.vaschools.edu.vn', 'Hoàng Đào Khánh Linh', $department->id, null);
        $this->assignRoles($linh, $roles, ['member']);

        $this->command?->info('Đã seed nhân sự phòng Hành chính Nhân sự (HCNS).');
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
