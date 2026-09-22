<?php

namespace Modules\Identity\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\Team;

/**
 * Nhân sự THẬT của Phòng Công nghệ thông tin (CNTT) — ban lãnh đạo phòng +
 * 2 team con (Phần mềm, Phần cứng) + nhân viên không thuộc team nào.
 *
 * Idempotent theo email (`updateOrCreate`) — chạy lại không tạo trùng.
 * `hrm_team_uuid` (Team) để trống (null): SSOT nhân sự/tổ chức sẽ chuyển
 * sang API VA-HRM sau này — khi đó chỉ cần điền `hrm_team_uuid` để đối
 * chiếu, KHÔNG cần đổi cấu trúc bảng. Xem
 * Modules/Identity/App/Models/Team.php và ghi chú "HRM employee sync"
 * trong docs/VA_WORKSPACE_OVERVIEW.md §8, §20.2.
 *
 * roles() dùng syncWithoutDetaching (không phải sync) để không gỡ role
 * super_admin mà SuperAdminBootstrap đã gán cho SUPERADMIN_EMAIL
 * (khoana@hcm...) khi SuperAdminSeeder chạy trước seeder này.
 */
class CnttSoftwareTeamSeeder extends Seeder
{
    private const DEPARTMENT_CODE = 'CNTT';

    public function run(): void
    {
        $department = Department::query()->where('code', self::DEPARTMENT_CODE)->first();
        if ($department === null) {
            $this->command?->warn('Không tìm thấy phòng ban CNTT — bỏ qua CnttSoftwareTeamSeeder.');

            return;
        }

        $roles = Role::query()->get()->keyBy('code');

        // 1) Ban lãnh đạo phòng CNTT — không thuộc team con nào.
        $toan = $this->upsertUser('toanbq@vaschools.edu.vn', 'Bùi Quang Toàn', $department->id, null);
        $this->assignRoles($toan, $roles, ['department_director', 'super_admin']);

        $hoang = $this->upsertUser('hoangbh@vaschools.edu.vn', 'Bùi Huy Hoàng', $department->id, null);
        $this->assignRoles($hoang, $roles, ['deputy_department_director', 'super_admin']);

        $hung = $this->upsertUser('hungnv@vaschools.edu.vn', 'Nguyễn Viết Hùng', $department->id, null);
        $this->assignRoles($hung, $roles, ['section_head']);

        // Nhân viên phòng CNTT, không thuộc team nào, có quyền admin.
        $truong = $this->upsertUser('truongnx@vaschools.edu.vn', 'Nguyễn Xuân Trường', $department->id, null);
        $this->assignRoles($truong, $roles, ['member', 'admin']);

        // 2) Team Phần mềm — trưởng nhóm Nguyễn Anh Khoa.
        $teamPhanMem = Team::query()->updateOrCreate(
            ['department_id' => $department->id, 'name' => 'Phần mềm'],
            [],
        );

        $khoa = $this->upsertUser('khoana@hcm.vaschools.edu.vn', 'Nguyễn Anh Khoa', $department->id, $teamPhanMem->id);
        // super_admin: SuperAdminBootstrap (SuperAdminSeeder) đã gán đủ 7
        // role cho SUPERADMIN_EMAIL — chỉ bổ sung team_lead ở đây.
        $this->assignRoles($khoa, $roles, ['team_lead']);

        $ngoc = $this->upsertUser('ngocntk@hcm.vaschools.edu.vn', 'Nguyễn Trần Kim Ngọc', $department->id, $teamPhanMem->id);
        $this->assignRoles($ngoc, $roles, ['member', 'super_admin']);

        $quang = $this->upsertUser('quangtm@hcm.vaschools.edu.vn', 'Trần Minh Quang', $department->id, $teamPhanMem->id);
        $this->assignRoles($quang, $roles, ['member']);

        $binh = $this->upsertUser('binhtl@hcm.vaschools.edu.vn', 'Trần Lê Bình', $department->id, $teamPhanMem->id);
        $this->assignRoles($binh, $roles, ['member']);

        $kieu = $this->upsertUser('kieunlt@hcm.vaschools.edu.vn', 'Nguyễn Lê Thanh Kiều', $department->id, $teamPhanMem->id);
        $this->assignRoles($kieu, $roles, ['member']);

        $huong = $this->upsertUser('huongdt2@vaschools.edu.vn', 'Đỗ Thị Hương', $department->id, $teamPhanMem->id);
        $this->assignRoles($huong, $roles, ['member']);

        if ((int) $teamPhanMem->team_lead_id !== (int) $khoa->id) {
            $teamPhanMem->team_lead_id = $khoa->id;
            $teamPhanMem->save();
        }

        // 3) Team Phần cứng — trưởng nhóm Phạm Quang Thái.
        $teamPhanCung = Team::query()->updateOrCreate(
            ['department_id' => $department->id, 'name' => 'Phần cứng'],
            [],
        );

        $thai = $this->upsertUser('thaipq@hcm.vaschools.edu.vn', 'Phạm Quang Thái', $department->id, $teamPhanCung->id);
        $this->assignRoles($thai, $roles, ['team_lead']);

        $loc = $this->upsertUser('loccd@hcm.vaschools.edu.vn', 'Cam Đức Lộc', $department->id, $teamPhanCung->id);
        $this->assignRoles($loc, $roles, ['member']);

        $vu = $this->upsertUser('vunh@vaschools.edu.vn', 'Nguyễn Hoàng Vũ', $department->id, $teamPhanCung->id);
        $this->assignRoles($vu, $roles, ['member']);

        if ((int) $teamPhanCung->team_lead_id !== (int) $thai->id) {
            $teamPhanCung->team_lead_id = $thai->id;
            $teamPhanCung->save();
        }

        $this->command?->info('Đã seed nhân sự phòng CNTT (ban lãnh đạo + team Phần mềm + team Phần cứng).');
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
