<?php

namespace Modules\Credential\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Repositories\Contracts\CredentialRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho module Credential.
 */
class CredentialRepository implements CredentialRepositoryInterface
{
    public function paginate(array $filters, int $perPage, int $page, ?array $departmentScope = null, ?int $viewerId = null): LengthAwarePaginator
    {
        $query = Credential::query()->with(['provider', 'creator', 'googleAccountOwner', 'department']);

        $this->applyDepartmentScope($query, $departmentScope, $viewerId);
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id, ?array $departmentScope = null, ?int $viewerId = null): ?Credential
    {
        $query = Credential::query()->with(['provider', 'creator', 'viewers', 'googleAccountOwner', 'department']);

        $this->applyDepartmentScope($query, $departmentScope, $viewerId);

        return $query->find($id);
    }

    public function create(array $data): Credential
    {
        return Credential::query()->create($data);
    }

    public function update(Credential $credential, array $data): Credential
    {
        $credential->update($data);

        return $credential->fresh(['provider', 'creator', 'viewers', 'googleAccountOwner', 'department']);
    }

    public function delete(Credential $credential): bool
    {
        return (bool) $credential->delete();
    }

    public function viewersOf(Credential $credential): Collection
    {
        return $credential->viewers()->get();
    }

    public function addViewer(Credential $credential, int $userId, int $grantedBy): void
    {
        $credential->viewers()->syncWithoutDetaching([
            $userId => ['granted_by' => $grantedBy],
        ]);
    }

    public function removeViewer(Credential $credential, int $userId): void
    {
        $credential->viewers()->detach($userId);
    }

    public function syncViewers(Credential $credential, array $userIds, int $grantedBy): void
    {
        DB::transaction(function () use ($credential, $userIds, $grantedBy) {
            $existingIds = $credential->viewers()->pluck('users.id')->all();
            $toAdd = array_diff($userIds, $existingIds);
            $toRemove = array_diff($existingIds, $userIds);

            if (! empty($toAdd)) {
                $syncData = [];
                foreach ($toAdd as $id) {
                    $syncData[$id] = ['granted_by' => $grantedBy];
                }
                // syncWithoutDetaching (không phải sync() trần) để KHÔNG
                // ghi đè granted_by/created_at của người đã có từ trước.
                $credential->viewers()->syncWithoutDetaching($syncData);
            }

            if (! empty($toRemove)) {
                $credential->viewers()->detach($toRemove);
            }
        });
    }

    public function statusCounts(?array $departmentScope = null, ?int $viewerId = null): array
    {
        $today = Carbon::today();
        $renewingSoonAt = $today->copy()->addDays(CredentialEnums::RENEWING_SOON_DAYS);
        $expiringSoonAt = $today->copy()->addDays(CredentialEnums::EXPIRING_SOON_DAYS);

        return [
            CredentialEnums::STATUS_ACTIVE => (clone $this->baseQuery($departmentScope, $viewerId))
                ->where(function (Builder $q) use ($renewingSoonAt) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $renewingSoonAt);
                })->count(),
            CredentialEnums::STATUS_RENEWING_SOON => (clone $this->baseQuery($departmentScope, $viewerId))
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$expiringSoonAt->copy()->addDay(), $renewingSoonAt])
                ->count(),
            CredentialEnums::STATUS_EXPIRING_SOON => (clone $this->baseQuery($departmentScope, $viewerId))
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $expiringSoonAt])
                ->count(),
            CredentialEnums::STATUS_EXPIRED => (clone $this->baseQuery($departmentScope, $viewerId))
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $today)
                ->count(),
        ];
    }

    public function allUsers(): Collection
    {
        return User::query()->select(['id', 'name', 'email'])->orderBy('name')->get();
    }

    public function allWithCost(?array $departmentScope = null, ?int $viewerId = null): Collection
    {
        $query = Credential::query()
            ->with('provider')
            ->where('cost_hidden', false)
            ->whereNotNull('monthly_cost')
            ->where('monthly_cost', '>', 0);

        $this->applyDepartmentScope($query, $departmentScope, $viewerId);

        return $query->get();
    }

    private function baseQuery(?array $departmentScope = null, ?int $viewerId = null): Builder
    {
        $query = Credential::query();
        $this->applyDepartmentScope($query, $departmentScope, $viewerId);

        return $query;
    }

    /**
     * Giới hạn dữ liệu theo phòng ban — null = không giới hạn (viewer có
     * quyền global/superadmin, xem CredentialService::departmentScopeFor()).
     * Mảng (kể cả rỗng) = chỉ thấy credential có department_id nằm trong
     * danh sách này, CỘNG THÊM credential mà $viewerId là creator hoặc
     * được cấp quyền xem riêng (bảng credential_viewers) dù khác phòng ban
     * — đúng yêu cầu "được cho phép thì chỉ nhân viên đó mới thấy được".
     * Credential department_id = NULL (chưa gán phòng ban) không tự thấy
     * được qua nhánh phòng ban, chỉ qua viewer riêng hoặc khi không giới hạn.
     *
     * @param  list<int>|null  $departmentScope
     */
    private function applyDepartmentScope(Builder $query, ?array $departmentScope, ?int $viewerId = null): void
    {
        if ($departmentScope === null) {
            return;
        }

        $query->where(function (Builder $q) use ($departmentScope, $viewerId) {
            $q->whereIn('department_id', $departmentScope);

            if ($viewerId !== null) {
                $q->orWhere('created_by', $viewerId)
                    ->orWhereHas('viewers', fn (Builder $v) => $v->where('users.id', $viewerId));
            }
        });
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('domain', 'like', "%{$q}%")
                    ->orWhere('server_name', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        if (! empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (! empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        if (! empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $today = Carbon::today();
        $renewingSoonAt = $today->copy()->addDays(CredentialEnums::RENEWING_SOON_DAYS);
        $expiringSoonAt = $today->copy()->addDays(CredentialEnums::EXPIRING_SOON_DAYS);

        match ($status) {
            CredentialEnums::STATUS_EXPIRED => $query->whereNotNull('expires_at')->where('expires_at', '<', $today),
            CredentialEnums::STATUS_EXPIRING_SOON => $query->whereNotNull('expires_at')->whereBetween('expires_at', [$today, $expiringSoonAt]),
            CredentialEnums::STATUS_RENEWING_SOON => $query->whereNotNull('expires_at')->whereBetween('expires_at', [$expiringSoonAt->copy()->addDay(), $renewingSoonAt]),
            CredentialEnums::STATUS_ACTIVE => $query->where(function (Builder $q) use ($renewingSoonAt) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $renewingSoonAt);
            }),
            default => null,
        };
    }
}
