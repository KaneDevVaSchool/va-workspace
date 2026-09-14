<?php

namespace Modules\Credential\App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Credential\App\Models\Credential;

interface CredentialRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>|null  $departmentScope  null = không giới hạn phòng ban (viewer có quyền global/superadmin);
     *                                            mảng (kể cả rỗng) = chỉ thấy credential thuộc các phòng ban này,
     *                                            CỘNG THÊM credential viewer được cấp quyền xem riêng dù khác phòng ban
     *                                            (xem $viewerId) — credential department_id NULL không tự thấy được.
     * @param  int|null  $viewerId  user đang xem — dùng để luôn thấy credential mình là creator/viewer riêng dù khác phòng ban.
     *                              Bỏ qua khi $departmentScope = null.
     */
    public function paginate(array $filters, int $perPage, int $page, ?array $departmentScope = null, ?int $viewerId = null): LengthAwarePaginator;

    /**
     * @param  list<int>|null  $departmentScope  cùng ý nghĩa với paginate() — null = không giới hạn
     * @param  int|null  $viewerId  cùng ý nghĩa với paginate()
     */
    public function find(int $id, ?array $departmentScope = null, ?int $viewerId = null): ?Credential;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Credential;

    /** @param  array<string, mixed>  $data */
    public function update(Credential $credential, array $data): Credential;

    public function delete(Credential $credential): bool;

    /** @return Collection<int, User> */
    public function viewersOf(Credential $credential): Collection;

    public function addViewer(Credential $credential, int $userId, int $grantedBy): void;

    public function removeViewer(Credential $credential, int $userId): void;

    /**
     * Đồng bộ toàn bộ viewer về đúng danh sách $userIds trong 1 giao dịch
     * — thêm người mới (granted_by = $grantedBy), xoá người bị bỏ, GIỮ
     * NGUYÊN pivot (granted_by/created_at) của người không đổi.
     *
     * @param  list<int>  $userIds
     */
    public function syncViewers(Credential $credential, array $userIds, int $grantedBy): void;

    /**
     * @param  list<int>|null  $departmentScope  cùng ý nghĩa với paginate() — null = không giới hạn
     * @param  int|null  $viewerId  cùng ý nghĩa với paginate()
     * @return array<string, int> tổng số theo trạng thái hạn dùng, dùng cho ô đếm bộ lọc
     */
    public function statusCounts(?array $departmentScope = null, ?int $viewerId = null): array;

    /** Danh sách user tối giản (id/name/email) — dùng cho chọn người được cấp quyền xem. */
    public function allUsers(): Collection;

    /**
     * Toàn bộ tài khoản còn tính phí (chưa xoá, không ẩn chi phí, có
     * monthly_cost) kèm provider — nguồn cho dự toán chi phí (tab riêng).
     *
     * @param  list<int>|null  $departmentScope  cùng ý nghĩa với paginate() — null = không giới hạn
     * @param  int|null  $viewerId  cùng ý nghĩa với paginate()
     * @return Collection<int, Credential>
     */
    public function allWithCost(?array $departmentScope = null, ?int $viewerId = null): Collection;
}
