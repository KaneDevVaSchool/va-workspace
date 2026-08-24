<?php

namespace Modules\Identity\App\Repositories\Contracts;

use App\Models\User;

/**
 * Contract cho tầng Repository — Service (GoogleAuthenticator) chỉ phụ
 * thuộc interface này, không phụ thuộc trực tiếp Eloquent.
 *
 * TẠM THỜI: UserRepository (Eloquent) là implementation duy nhất, dữ liệu
 * user giả lập ngay trong app này. Khi HRM cung cấp API, tạo implementation
 * mới (vd. HrmApiUserRepository) gọi API HRM và đổi binding trong
 * IdentityServiceProvider::register() — Controller/Service không cần sửa.
 */
interface UserRepositoryInterface
{
    public function findByGoogleId(string $googleId): ?User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    /**
     * Danh sách user active thuộc 1 phòng ban — dùng cho dropdown chọn
     * team_lead_id (chỉ liệt kê user cùng phòng ban với team).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function allActiveByDepartment(int $departmentId): \Illuminate\Support\Collection;
}
