<?php

namespace Modules\Social\App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Social\App\Models\SocialGroup;
use Modules\Social\App\Models\SocialGroupJoinRequest;
use Modules\Social\App\Models\SocialGroupMember;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;

class SocialGroupRepository implements SocialGroupRepositoryInterface
{
    private function baseQuery()
    {
        return SocialGroup::query()
            ->with(['creator'])
            ->withCount(['members']);
    }

    public function find(int $id): ?SocialGroup
    {
        return $this->baseQuery()->find($id);
    }

    public function create(array $data): SocialGroup
    {
        $group = SocialGroup::create($data);

        return $this->find($group->id);
    }

    public function update(SocialGroup $group, array $data): SocialGroup
    {
        $group->update($data);

        return $this->find($group->id);
    }

    public function delete(SocialGroup $group): void
    {
        $group->delete();
    }

    private function applySearch($query, ?string $search): void
    {
        $needle = trim((string) $search);
        if ($needle === '') {
            return;
        }

        $like = '%'.addcslashes($needle, '%_\\').'%';
        $query->where('name', 'like', $like);
    }

    public function paginateDiscoverable(int $perPage, int $page, int $userId, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $this->applySearch($query, $search);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateForMember(int $perPage, int $page, int $userId, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->whereHas('members', fn ($members) => $members->where('user_id', $userId));

        $this->applySearch($query, $search);

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function membership(int $groupId, int $userId): ?SocialGroupMember
    {
        return SocialGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();
    }

    public function addMember(int $groupId, int $userId, string $role): SocialGroupMember
    {
        return SocialGroupMember::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => now(),
        ]);
    }

    public function removeMember(int $groupId, int $userId): void
    {
        SocialGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function updateMemberRole(SocialGroupMember $member, string $role): SocialGroupMember
    {
        $member->update(['role' => $role]);

        return $member->fresh();
    }

    public function paginateMembers(int $groupId, int $perPage, int $page, ?string $search = null): LengthAwarePaginator
    {
        $query = SocialGroupMember::query()
            ->where('group_id', $groupId)
            ->with(['user.department']);

        $needle = trim((string) $search);
        if ($needle !== '') {
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->whereHas('user', fn ($users) => $users->where('name', 'like', $like));
        }

        return $query
            ->orderByRaw("field(role, 'owner', 'admin', 'member')")
            ->orderBy('joined_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function membersCount(int $groupId): int
    {
        return SocialGroupMember::query()->where('group_id', $groupId)->count();
    }

    public function ownerAndAdminIds(int $groupId): array
    {
        return SocialGroupMember::query()
            ->where('group_id', $groupId)
            ->whereIn('role', [SocialGroupMember::ROLE_OWNER, SocialGroupMember::ROLE_ADMIN])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function findPendingJoinRequest(int $groupId, int $userId): ?SocialGroupJoinRequest
    {
        return SocialGroupJoinRequest::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', SocialGroupJoinRequest::STATUS_PENDING)
            ->first();
    }

    public function createJoinRequest(array $data): SocialGroupJoinRequest
    {
        return SocialGroupJoinRequest::create($data);
    }

    public function findJoinRequest(int $id): ?SocialGroupJoinRequest
    {
        return SocialGroupJoinRequest::query()->with(['group', 'user'])->find($id);
    }

    public function updateJoinRequest(SocialGroupJoinRequest $request, array $data): SocialGroupJoinRequest
    {
        $request->update($data);

        return $request->fresh(['group', 'user']);
    }

    public function paginateJoinRequests(int $groupId, int $perPage, int $page, string $status = 'pending'): LengthAwarePaginator
    {
        return SocialGroupJoinRequest::query()
            ->where('group_id', $groupId)
            ->where('status', $status)
            ->with(['user.department'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateMyJoinRequests(int $perPage, int $page, int $userId): LengthAwarePaginator
    {
        return SocialGroupJoinRequest::query()
            ->where('user_id', $userId)
            ->where('status', SocialGroupJoinRequest::STATUS_PENDING)
            ->with(['group'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
