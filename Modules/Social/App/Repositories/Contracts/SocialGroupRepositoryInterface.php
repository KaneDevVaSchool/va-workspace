<?php

namespace Modules\Social\App\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Social\App\Models\SocialGroup;
use Modules\Social\App\Models\SocialGroupJoinRequest;
use Modules\Social\App\Models\SocialGroupMember;

interface SocialGroupRepositoryInterface
{
    public function find(int $id): ?SocialGroup;

    public function create(array $data): SocialGroup;

    public function update(SocialGroup $group, array $data): SocialGroup;

    public function delete(SocialGroup $group): void;

    /** Nhóm công khai + nhóm riêng tư mà $userId đã là thành viên (Khám phá nhóm). */
    public function paginateDiscoverable(int $perPage, int $page, int $userId, ?string $search = null): LengthAwarePaginator;

    /** Nhóm mà $userId là thành viên (Nhóm của tôi). */
    public function paginateForMember(int $perPage, int $page, int $userId, ?string $search = null): LengthAwarePaginator;

    public function membership(int $groupId, int $userId): ?SocialGroupMember;

    public function addMember(int $groupId, int $userId, string $role): SocialGroupMember;

    public function removeMember(int $groupId, int $userId): void;

    public function updateMemberRole(SocialGroupMember $member, string $role): SocialGroupMember;

    public function paginateMembers(int $groupId, int $perPage, int $page, ?string $search = null): LengthAwarePaginator;

    public function membersCount(int $groupId): int;

    /** @return list<int> */
    public function ownerAndAdminIds(int $groupId): array;

    public function findPendingJoinRequest(int $groupId, int $userId, ?string $kind = null): ?SocialGroupJoinRequest;

    public function createJoinRequest(array $data): SocialGroupJoinRequest;

    public function findJoinRequest(int $id): ?SocialGroupJoinRequest;

    public function updateJoinRequest(SocialGroupJoinRequest $request, array $data): SocialGroupJoinRequest;

    public function paginateJoinRequests(int $groupId, int $perPage, int $page, string $status = 'pending'): LengthAwarePaginator;

    public function paginateMyJoinRequests(int $perPage, int $page, int $userId): LengthAwarePaginator;
}
