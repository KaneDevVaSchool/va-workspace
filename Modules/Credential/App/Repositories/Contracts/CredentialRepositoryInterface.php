<?php

namespace Modules\Credential\App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Credential\App\Models\Credential;

interface CredentialRepositoryInterface
{
    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator;

    public function find(int $id): ?Credential;

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Credential;

    /** @param  array<string, mixed>  $data */
    public function update(Credential $credential, array $data): Credential;

    public function delete(Credential $credential): bool;

    /** @return Collection<int, User> */
    public function viewersOf(Credential $credential): Collection;

    public function addViewer(Credential $credential, int $userId, int $grantedBy): void;

    public function removeViewer(Credential $credential, int $userId): void;

    /** @return array<string, int> tổng số theo trạng thái hạn dùng, dùng cho ô đếm bộ lọc */
    public function statusCounts(): array;

    /** Danh sách user tối giản (id/name/email) — dùng cho chọn người được cấp quyền xem. */
    public function allUsers(): Collection;
}
