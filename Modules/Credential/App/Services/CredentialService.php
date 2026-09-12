<?php

namespace Modules\Credential\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Repositories\Contracts\CredentialRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;

/**
 * Business logic module Credential. Nơi DUY NHẤT quyết định field nhạy cảm
 * (password/username thật) có được trả ra cho viewer hay không — tách bạch
 * với quyền CRUD (permission:credential.manage): 1 user có quyền sửa/xoá
 * bản ghi vẫn có thể KHÔNG được xem mật khẩu nếu không phải creator/viewer
 * được cấp riêng (bảng credential_viewers).
 */
class CredentialService
{
    public function __construct(
        private readonly CredentialRepositoryInterface $credentials,
        private readonly PermissionService $permissions,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->credentials->paginate($filters, $perPage, $page);
    }

    public function find(int $id): ?Credential
    {
        return $this->credentials->find($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $creator): Credential
    {
        $data['created_by'] = $creator->id;
        $data['updated_by'] = $creator->id;

        return $this->credentials->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Credential $credential, array $data, User $updater): Credential
    {
        $data['updated_by'] = $updater->id;

        return $this->credentials->update($credential, $data);
    }

    public function delete(Credential $credential): bool
    {
        return $this->credentials->delete($credential);
    }

    public function canSeeSecret(Credential $credential, User $viewer): bool
    {
        if ($credential->created_by === $viewer->id) {
            return true;
        }

        return $credential->viewers->contains('id', $viewer->id);
    }

    public function canManage(User $viewer): bool
    {
        return $this->permissions->allows($viewer, 'credential.manage');
    }

    public function addViewer(Credential $credential, int $userId, User $grantedBy): void
    {
        $this->credentials->addViewer($credential, $userId, $grantedBy->id);
    }

    public function removeViewer(Credential $credential, int $userId): void
    {
        $this->credentials->removeViewer($credential, $userId);
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        return $this->credentials->statusCounts();
    }

    /** Danh sách user tối giản — dùng cho chọn người được cấp quyền xem dữ liệu nhạy cảm. */
    public function allUsers()
    {
        return $this->credentials->allUsers();
    }

    /**
     * Chuẩn hoá dữ liệu trả ra frontend — ẩn password/username thật nếu
     * viewer không phải creator/viewer được cấp. Metadata (provider, loại,
     * trạng thái, server, domain...) luôn hiện đầy đủ cho ai có quyền
     * credential.view. Chi phí bị ẩn riêng theo cost_hidden (mục 14
     * CLAUDE.md — không giữ chỗ rỗng, chỉ đơn giản không trả giá trị).
     *
     * @return array<string, mixed>
     */
    public function present(Credential $credential, User $viewer): array
    {
        $canSeeSecret = $this->canSeeSecret($credential, $viewer);
        $canManage = $this->canManage($viewer);

        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'provider' => $credential->provider ? [
                'id' => $credential->provider->id,
                'name' => $credential->provider->name,
                'category' => $credential->provider->category,
                'icon' => $credential->provider->icon,
            ] : null,
            'account_type' => $credential->account_type,
            'status' => $credential->status,
            'is_google_login' => $credential->is_google_login,
            'google_account_owner' => $credential->google_account_owner,
            'server_name' => $credential->server_name,
            'vps_cluster' => $credential->vps_cluster,
            'domain' => $credential->domain,
            'database_name' => $credential->database_name,
            'is_root_account' => $credential->is_root_account,
            'is_iam_account' => $credential->is_iam_account,
            'notes' => $credential->notes,
            'purchased_at' => optional($credential->purchased_at)->toDateString(),
            'expires_at' => optional($credential->expires_at)->toDateString(),
            'monthly_cost' => $credential->cost_hidden ? null : $credential->monthly_cost,
            'cost_hidden' => $credential->cost_hidden,
            'currency' => $credential->currency,
            'created_by' => $credential->created_by,
            'creator_name' => optional($credential->creator)->name,
            'can_see_secret' => $canSeeSecret,
            'can_manage' => $canManage,
            'username' => $canSeeSecret ? $credential->username : null,
            'email' => $canSeeSecret ? $credential->email : null,
            'password' => $canSeeSecret ? $credential->password : null,
            'viewers' => $canSeeSecret || $canManage
                ? $credential->viewers->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()
                : [],
        ];
    }
}
