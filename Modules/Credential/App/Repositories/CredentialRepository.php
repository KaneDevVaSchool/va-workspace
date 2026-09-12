<?php

namespace Modules\Credential\App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Credential\App\Enums\CredentialEnums;
use Modules\Credential\App\Models\Credential;
use Modules\Credential\App\Repositories\Contracts\CredentialRepositoryInterface;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho module Credential.
 */
class CredentialRepository implements CredentialRepositoryInterface
{
    public function paginate(array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $query = Credential::query()->with(['provider', 'creator']);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(int $id): ?Credential
    {
        return Credential::query()->with(['provider', 'creator', 'viewers'])->find($id);
    }

    public function create(array $data): Credential
    {
        return Credential::query()->create($data);
    }

    public function update(Credential $credential, array $data): Credential
    {
        $credential->update($data);

        return $credential->fresh(['provider', 'creator', 'viewers']);
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

    public function statusCounts(): array
    {
        $today = Carbon::today();
        $renewingSoonAt = $today->copy()->addDays(CredentialEnums::RENEWING_SOON_DAYS);
        $expiringSoonAt = $today->copy()->addDays(CredentialEnums::EXPIRING_SOON_DAYS);

        return [
            CredentialEnums::STATUS_ACTIVE => (clone $this->baseQuery())
                ->where(function (Builder $q) use ($renewingSoonAt) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $renewingSoonAt);
                })->count(),
            CredentialEnums::STATUS_RENEWING_SOON => (clone $this->baseQuery())
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$expiringSoonAt->copy()->addDay(), $renewingSoonAt])
                ->count(),
            CredentialEnums::STATUS_EXPIRING_SOON => (clone $this->baseQuery())
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $expiringSoonAt])
                ->count(),
            CredentialEnums::STATUS_EXPIRED => (clone $this->baseQuery())
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $today)
                ->count(),
        ];
    }

    public function allUsers(): Collection
    {
        return User::query()->select(['id', 'name', 'email'])->orderBy('name')->get();
    }

    private function baseQuery(): Builder
    {
        return Credential::query();
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
