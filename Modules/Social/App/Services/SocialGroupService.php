<?php

namespace Modules\Social\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Services\NotificationService;
use Modules\Social\App\Models\SocialGroup;
use Modules\Social\App\Models\SocialGroupJoinRequest;
use Modules\Social\App\Models\SocialGroupMember;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialGroupRepositoryInterface;

class SocialGroupService
{
    public function __construct(
        private readonly SocialGroupRepositoryInterface $groups,
        private readonly NotificationService $notifications,
    ) {}

    public function listMine(User $viewer, int $perPage, int $page, ?string $search): array
    {
        $paginator = $this->groups->paginateForMember(
            $perPage,
            $page,
            $viewer->id,
            $search,
            SocialGroup::companyIdOf($viewer),
        );

        return $this->presentPaginator($paginator, $viewer);
    }

    public function listDiscoverable(User $viewer, int $perPage, int $page, ?string $search): array
    {
        $paginator = $this->groups->paginateDiscoverable(
            $perPage,
            $page,
            $viewer->id,
            $search,
            SocialGroup::companyIdOf($viewer),
            $viewer->isSuperAdmin(),
        );

        return $this->presentPaginator($paginator, $viewer);
    }

    private function presentPaginator($paginator, User $viewer): array
    {
        return [
            'groups' => collect($paginator->items())
                ->map(function (SocialGroup $group) use ($viewer) {
                    $membership = $this->groups->membership($group->id, $viewer->id);

                    return $membership !== null || $group->visibility === SocialGroup::VISIBILITY_PUBLIC
                        ? $this->present($group, $viewer, $membership)
                        : $this->presentPreview($group, $viewer);
                })
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function myJoinRequests(User $viewer, int $perPage, int $page): array
    {
        $paginator = $this->groups->paginateMyJoinRequests($perPage, $page, $viewer->id);

        return [
            'requests' => collect($paginator->items())
                ->map(fn (SocialGroupJoinRequest $request) => $this->presentJoinRequest($request))
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function find(int $id): ?SocialGroup
    {
        return $this->groups->find($id);
    }

    public function assertCanView(SocialGroup $group, User $viewer): bool
    {
        if ($viewer->isSuperAdmin()) {
            return true;
        }

        $isMember = $this->groups->membership($group->id, $viewer->id) !== null;
        if ($group->company_id !== null && ! $group->includesCompanyOf($viewer) && ! $isMember) {
            return false;
        }

        if ($group->visibility === SocialGroup::VISIBILITY_PUBLIC) {
            return $group->company_id === null || $group->includesCompanyOf($viewer) || $isMember;
        }

        return $isMember;
    }

    public function present(SocialGroup $group, User $viewer, ?SocialGroupMember $membership = null): array
    {
        $membership ??= $this->groups->membership($group->id, $viewer->id);
        $isManager = $membership !== null && in_array($membership->role, [SocialGroupMember::ROLE_OWNER, SocialGroupMember::ROLE_ADMIN], true);

        $pending = $membership === null
            ? $this->groups->findPendingJoinRequest($group->id, $viewer->id)
            : null;
        $implicitMember = $membership === null
            && $group->visibility === SocialGroup::VISIBILITY_PUBLIC
            && $group->includesCompanyOf($viewer);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'visibility' => $group->visibility,
            'company' => $this->presentCompany($group),
            'cover_url' => $group->cover_path ? Storage::disk('public')->url($group->cover_path) : null,
            'avatar_url' => $group->avatar_path ? Storage::disk('public')->url($group->avatar_path) : null,
            'creator' => $group->creator ? [
                'id' => $group->creator->id,
                'name' => $group->creator->name,
                'avatar_url' => $group->creator->avatar_url,
            ] : null,
            'members_count' => $this->audienceCount($group),
            'my_role' => $membership?->role ?? ($implicitMember ? SocialGroupMember::ROLE_MEMBER : null),
            'is_member' => $membership !== null || $implicitMember,
            'has_pending_request' => $pending?->kind === SocialGroupJoinRequest::KIND_REQUEST,
            'has_pending_invite' => $pending?->kind === SocialGroupJoinRequest::KIND_INVITE,
            'pending_invite_id' => $pending?->kind === SocialGroupJoinRequest::KIND_INVITE ? $pending->id : null,
            'can_manage' => $isManager,
            'can_delete' => $membership?->role === SocialGroupMember::ROLE_OWNER || $viewer->isSuperAdmin(),
            'created_at' => $group->created_at?->toIso8601String(),
        ];
    }

    /** Bản rút gọn cho người ngoài xem nhóm bảo mật — chỉ tên + mô tả, không lộ thành viên/bài viết. */
    public function presentPreview(SocialGroup $group, User $viewer): array
    {
        $pending = $this->groups->findPendingJoinRequest($group->id, $viewer->id);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'visibility' => $group->visibility,
            'company' => $this->presentCompany($group),
            'cover_url' => $group->cover_path ? Storage::disk('public')->url($group->cover_path) : null,
            'avatar_url' => $group->avatar_path ? Storage::disk('public')->url($group->avatar_path) : null,
            'members_count' => $this->audienceCount($group),
            'my_role' => null,
            'is_member' => false,
            'has_pending_request' => $pending?->kind === SocialGroupJoinRequest::KIND_REQUEST,
            'has_pending_invite' => $pending?->kind === SocialGroupJoinRequest::KIND_INVITE,
            'pending_invite_id' => $pending?->kind === SocialGroupJoinRequest::KIND_INVITE ? $pending->id : null,
            'can_manage' => false,
            'can_delete' => false,
            'created_at' => $group->created_at?->toIso8601String(),
        ];
    }

    private function presentJoinRequest(SocialGroupJoinRequest $request): array
    {
        $group = $request->group;

        return [
            'id' => $request->id,
            'status' => $request->status,
            'kind' => $request->kind ?: SocialGroupJoinRequest::KIND_REQUEST,
            'message' => $request->message,
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'visibility' => $group->visibility,
                'cover_url' => $group->cover_path ? Storage::disk('public')->url($group->cover_path) : null,
                'avatar_url' => $group->avatar_path ? Storage::disk('public')->url($group->avatar_path) : null,
            ] : null,
            'user' => $request->user ? [
                'id' => $request->user->id,
                'name' => $request->user->name,
                'avatar_url' => $request->user->avatar_url,
                'department' => $request->user->department?->name,
            ] : null,
            'invited_by' => $request->invitedBy ? [
                'id' => $request->invitedBy->id,
                'name' => $request->invitedBy->name,
                'department' => $request->invitedBy->department?->name,
            ] : null,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    public function create(User $creator, array $data, ?UploadedFile $cover = null, ?UploadedFile $avatar = null): SocialGroup
    {
        return DB::transaction(function () use ($creator, $data, $cover, $avatar) {
            $companyId = $this->resolveCompanyId($creator, $data['company_id'] ?? null);
            $this->assertCompanyGroupAvailable($companyId);

            $group = $this->groups->create([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'visibility' => $data['visibility'] ?? SocialGroup::VISIBILITY_PUBLIC,
                'company_id' => $companyId,
                'cover_path' => $cover ? $this->storeImage($cover) : null,
                'avatar_path' => $avatar ? $this->storeImage($avatar) : null,
                'created_by' => $creator->id,
            ]);

            $this->groups->addMember($group->id, $creator->id, SocialGroupMember::ROLE_OWNER);

            return $this->groups->find($group->id);
        });
    }

    public function update(SocialGroup $group, User $actor, array $data, ?UploadedFile $cover = null, ?UploadedFile $avatar = null): SocialGroup
    {
        $this->assertIsOwnerOrAdmin($group, $actor);

        $payload = array_intersect_key($data, array_flip(['name', 'description', 'visibility']));
        if (array_key_exists('company_id', $data)) {
            $companyId = $this->resolveCompanyId($actor, $data['company_id']);
            $this->assertCompanyGroupAvailable($companyId, $group->id);
            $payload['company_id'] = $companyId;
        }

        if ($cover) {
            if ($group->cover_path) {
                Storage::disk('public')->delete($group->cover_path);
            }
            $payload['cover_path'] = $this->storeImage($cover);
        }

        if ($avatar) {
            if ($group->avatar_path) {
                Storage::disk('public')->delete($group->avatar_path);
            }
            $payload['avatar_path'] = $this->storeImage($avatar);
        }

        return $this->groups->update($group, $payload);
    }

    public function delete(SocialGroup $group, User $actor): void
    {
        $group->loadMissing('company');
        if ($group->company !== null && $this->isOfficialLegalEntity($group->company)) {
            throw ValidationException::withMessages([
                'group_id' => ['Nhóm pháp nhân không xoá được.'],
            ]);
        }

        $this->assertIsOwner($group, $actor);

        DB::transaction(function () use ($group) {
            SocialPost::where('group_id', $group->id)->delete();
            $this->groups->delete($group);
        });

        if ($group->cover_path) {
            Storage::disk('public')->delete($group->cover_path);
        }
        if ($group->avatar_path) {
            Storage::disk('public')->delete($group->avatar_path);
        }
    }

    public function join(SocialGroup $group, User $actor, ?string $message = null): array
    {
        $this->assertCompanyAudience($group, $actor);

        $pending = $this->groups->findPendingJoinRequest($group->id, $actor->id);
        if ($pending?->kind === SocialGroupJoinRequest::KIND_INVITE) {
            $this->acceptInvite($pending, $actor);

            return ['status' => 'joined'];
        }

        if ($group->visibility === SocialGroup::VISIBILITY_PRIVATE) {
            $this->requestJoin($group, $actor, $message);

            return ['status' => 'requested'];
        }

        if ($this->groups->membership($group->id, $actor->id) === null) {
            $this->groups->addMember($group->id, $actor->id, SocialGroupMember::ROLE_MEMBER);
        }

        return ['status' => 'joined'];
    }

    public function requestJoin(SocialGroup $group, User $actor, ?string $message): SocialGroupJoinRequest
    {
        $this->assertCompanyAudience($group, $actor);

        if ($this->groups->membership($group->id, $actor->id) !== null) {
            throw ValidationException::withMessages([
                'group_id' => ['Bạn đã là thành viên của nhóm này.'],
            ]);
        }

        if ($this->groups->findPendingJoinRequest($group->id, $actor->id) !== null) {
            throw ValidationException::withMessages([
                'group_id' => ['Bạn đã gửi yêu cầu tham gia, vui lòng chờ duyệt.'],
            ]);
        }

        $request = $this->groups->createJoinRequest([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'kind' => SocialGroupJoinRequest::KIND_REQUEST,
            'status' => SocialGroupJoinRequest::STATUS_PENDING,
            'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
        ]);

        $this->notifications->notifyUsers(
            $this->groups->ownerAndAdminIds($group->id),
            $actor,
            NotificationService::TYPE_GROUP_JOIN_REQUEST,
            $actor->name.' muốn tham gia nhóm "'.$group->name.'"',
            $message,
            '/social/groups/'.$group->id,
        );

        return $request;
    }

    public function approveJoinRequest(SocialGroupJoinRequest $request, User $actor): void
    {
        $group = $request->group;
        $this->assertIsOwnerOrAdmin($group, $actor);

        if ($request->kind !== SocialGroupJoinRequest::KIND_REQUEST) {
            throw ValidationException::withMessages([
                'request' => ['Đây là lời mời, người được mời phải tự chấp nhận.'],
            ]);
        }

        if ($request->status !== SocialGroupJoinRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Yêu cầu này đã được xử lý.'],
            ]);
        }

        $applicant = User::query()->find($request->user_id);
        if ($applicant !== null) {
            $this->assertCompanyAudience($group, $applicant);
        }

        DB::transaction(function () use ($request, $actor, $group) {
            $this->groups->updateJoinRequest($request, [
                'status' => SocialGroupJoinRequest::STATUS_APPROVED,
                'responded_by' => $actor->id,
                'responded_at' => now(),
            ]);

            if ($this->groups->membership($group->id, $request->user_id) === null) {
                $this->groups->addMember($group->id, $request->user_id, SocialGroupMember::ROLE_MEMBER);
            }
        });

        $this->notifications->notify(
            $request->user,
            $actor,
            NotificationService::TYPE_GROUP_JOIN_APPROVED,
            'Yêu cầu tham gia nhóm "'.$group->name.'" đã được duyệt',
            null,
            '/social/groups/'.$group->id,
        );
    }

    public function rejectJoinRequest(SocialGroupJoinRequest $request, User $actor): void
    {
        $group = $request->group;
        $this->assertIsOwnerOrAdmin($group, $actor);

        if ($request->kind !== SocialGroupJoinRequest::KIND_REQUEST) {
            throw ValidationException::withMessages([
                'request' => ['Đây là lời mời, người được mời phải tự từ chối.'],
            ]);
        }

        if ($request->status !== SocialGroupJoinRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Yêu cầu này đã được xử lý.'],
            ]);
        }

        $this->groups->updateJoinRequest($request, [
            'status' => SocialGroupJoinRequest::STATUS_REJECTED,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->notifications->notify(
            $request->user,
            $actor,
            NotificationService::TYPE_GROUP_JOIN_REJECTED,
            'Yêu cầu tham gia nhóm "'.$group->name.'" đã bị từ chối',
            null,
            '/social/groups',
        );
    }

    public function cancelMyJoinRequest(SocialGroupJoinRequest $request, User $actor): void
    {
        if ((int) $request->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'request' => ['Bạn không thể huỷ yêu cầu của người khác.'],
            ]);
        }

        if ($request->status !== SocialGroupJoinRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Yêu cầu này đã được xử lý.'],
            ]);
        }

        $this->groups->updateJoinRequest($request, [
            'status' => SocialGroupJoinRequest::STATUS_REJECTED,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);
    }

    public function invite(SocialGroup $group, User $actor, int $targetUserId): array
    {
        $this->assertIsOwnerOrAdmin($group, $actor);

        $target = User::query()->find($targetUserId);
        if ($target === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Không tìm thấy người được mời.'],
            ]);
        }
        $this->assertCompanyAudience($group, $target);

        if ((int) $targetUserId === (int) $actor->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Bạn đã là thành viên của nhóm này.'],
            ]);
        }

        if ($this->groups->membership($group->id, $targetUserId) !== null) {
            throw ValidationException::withMessages([
                'user_id' => ['Người này đã là thành viên của nhóm.'],
            ]);
        }

        $pending = $this->groups->findPendingJoinRequest($group->id, $targetUserId);
        if ($pending?->kind === SocialGroupJoinRequest::KIND_REQUEST) {
            $this->approveJoinRequest($pending, $actor);

            return ['status' => 'joined'];
        }
        if ($pending?->kind === SocialGroupJoinRequest::KIND_INVITE) {
            throw ValidationException::withMessages([
                'user_id' => ['Đã gửi lời mời cho người này, vui lòng chờ họ chấp nhận.'],
            ]);
        }

        $target = User::query()->find($targetUserId);
        if ($target === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Không tìm thấy người dùng.'],
            ]);
        }

        $this->groups->createJoinRequest([
            'group_id' => $group->id,
            'user_id' => $targetUserId,
            'kind' => SocialGroupJoinRequest::KIND_INVITE,
            'invited_by' => $actor->id,
            'status' => SocialGroupJoinRequest::STATUS_PENDING,
        ]);

        $this->notifications->notify(
            $target,
            $actor,
            NotificationService::TYPE_GROUP_INVITE,
            $actor->name.' mời bạn tham gia nhóm "'.$group->name.'"',
            $group->visibility === SocialGroup::VISIBILITY_PRIVATE
                ? 'Nhóm bảo mật — hãy chấp nhận lời mời để xem nội dung.'
                : null,
            '/social/groups',
        );

        return ['status' => 'invited'];
    }

    public function acceptInvite(SocialGroupJoinRequest $request, User $actor): void
    {
        if ($request->kind !== SocialGroupJoinRequest::KIND_INVITE) {
            throw ValidationException::withMessages([
                'request' => ['Đây không phải lời mời tham gia nhóm.'],
            ]);
        }

        if ((int) $request->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'request' => ['Bạn không thể chấp nhận lời mời của người khác.'],
            ]);
        }

        if ($request->status !== SocialGroupJoinRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Lời mời này đã được xử lý.'],
            ]);
        }

        $group = $request->group;
        if ($group !== null) {
            $this->assertCompanyAudience($group, $actor);
        }

        DB::transaction(function () use ($request, $actor, $group) {
            $this->groups->updateJoinRequest($request, [
                'status' => SocialGroupJoinRequest::STATUS_APPROVED,
                'responded_by' => $actor->id,
                'responded_at' => now(),
            ]);

            if ($this->groups->membership($group->id, $actor->id) === null) {
                $this->groups->addMember($group->id, $actor->id, SocialGroupMember::ROLE_MEMBER);
            }
        });
    }

    public function declineInvite(SocialGroupJoinRequest $request, User $actor): void
    {
        if ($request->kind !== SocialGroupJoinRequest::KIND_INVITE) {
            throw ValidationException::withMessages([
                'request' => ['Đây không phải lời mời tham gia nhóm.'],
            ]);
        }

        if ((int) $request->user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'request' => ['Bạn không thể từ chối lời mời của người khác.'],
            ]);
        }

        if ($request->status !== SocialGroupJoinRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['Lời mời này đã được xử lý.'],
            ]);
        }

        $this->groups->updateJoinRequest($request, [
            'status' => SocialGroupJoinRequest::STATUS_REJECTED,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);
    }

    public function leave(SocialGroup $group, User $actor): void
    {
        $membership = $this->groups->membership($group->id, $actor->id);
        if ($membership === null) {
            return;
        }

        if ($membership->role === SocialGroupMember::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'group_id' => ['Chủ nhóm phải chuyển quyền sở hữu hoặc xoá nhóm trước khi rời.'],
            ]);
        }

        $this->groups->removeMember($group->id, $actor->id);
    }

    public function listMembers(SocialGroup $group, User $viewer, int $perPage, int $page, ?string $search): array
    {
        if (! $this->assertCanView($group, $viewer)) {
            throw ValidationException::withMessages([
                'group_id' => ['Bạn không có quyền xem thành viên nhóm này.'],
            ]);
        }

        $paginator = $this->groups->paginateMembers($group->id, $perPage, $page, $search);

        return [
            'members' => collect($paginator->items())
                ->map(fn (SocialGroupMember $member) => [
                    'user_id' => $member->user_id,
                    'name' => $member->user?->name,
                    'avatar_url' => $member->user?->avatar_url,
                    'department' => $member->user?->department?->name,
                    'role' => $member->role,
                    'joined_at' => $member->joined_at?->toIso8601String(),
                ])
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function removeMember(SocialGroup $group, User $actor, int $targetUserId): void
    {
        $this->assertIsOwnerOrAdmin($group, $actor);

        if ((int) $targetUserId === (int) $actor->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Dùng chức năng "Rời nhóm" để tự rời khỏi nhóm.'],
            ]);
        }

        $actorMembership = $this->groups->membership($group->id, $actor->id);
        $targetMembership = $this->groups->membership($group->id, $targetUserId);

        if ($targetMembership === null) {
            return;
        }

        $actorIsOwner = $actorMembership?->role === SocialGroupMember::ROLE_OWNER || $actor->isSuperAdmin();
        $targetIsManager = in_array($targetMembership->role, [SocialGroupMember::ROLE_OWNER, SocialGroupMember::ROLE_ADMIN], true);

        if (! $actorIsOwner && $targetIsManager) {
            throw ValidationException::withMessages([
                'user_id' => ['Chỉ chủ nhóm mới được xoá quản trị viên khác.'],
            ]);
        }

        $this->groups->removeMember($group->id, $targetUserId);
    }

    public function changeMemberRole(SocialGroup $group, User $actor, int $targetUserId, string $role): void
    {
        $this->assertIsOwner($group, $actor);

        if (! in_array($role, [SocialGroupMember::ROLE_ADMIN, SocialGroupMember::ROLE_MEMBER], true)) {
            throw ValidationException::withMessages([
                'role' => ['Vai trò không hợp lệ.'],
            ]);
        }

        if ((int) $targetUserId === (int) $actor->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Dùng chức năng "Chuyển quyền chủ nhóm" để đổi vai trò của chính bạn.'],
            ]);
        }

        $member = $this->groups->membership($group->id, $targetUserId);
        if ($member === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Người này không phải thành viên của nhóm.'],
            ]);
        }

        $this->groups->updateMemberRole($member, $role);
    }

    public function transferOwnership(SocialGroup $group, User $actor, int $newOwnerUserId): void
    {
        $this->assertIsOwner($group, $actor);

        $newOwner = $this->groups->membership($group->id, $newOwnerUserId);
        if ($newOwner === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Người này chưa là thành viên của nhóm.'],
            ]);
        }

        DB::transaction(function () use ($group, $actor, $newOwner) {
            $currentOwner = $this->groups->membership($group->id, $actor->id);
            if ($currentOwner !== null) {
                $this->groups->updateMemberRole($currentOwner, SocialGroupMember::ROLE_ADMIN);
            }
            $this->groups->updateMemberRole($newOwner, SocialGroupMember::ROLE_OWNER);
        });
    }

    public function joinRequests(SocialGroup $group, User $actor, int $perPage, int $page): array
    {
        $this->assertIsOwnerOrAdmin($group, $actor);

        $paginator = $this->groups->paginateJoinRequests($group->id, $perPage, $page);

        return [
            'requests' => collect($paginator->items())
                ->map(fn (SocialGroupJoinRequest $request) => $this->presentJoinRequest($request))
                ->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Tạo nhóm pháp nhân VM, Viễn Đông, Hoàng Việt, CIT nếu công ty đã đồng bộ và chưa có nhóm.
     * Mở danh sách nhóm cũng gọi hàm này — không chờ quản trị viên bấm tay.
     *
     * @return list<SocialGroup>
     */
    public function ensureLegalEntityGroups(User $actor): array
    {
        $created = [];
        $companies = Company::query()->where('is_active', true)->orderBy('name')->get();
        foreach ($companies as $company) {
            if (! $this->isOfficialLegalEntity($company)) {
                continue;
            }
            if (SocialGroup::query()->where('company_id', $company->id)->exists()) {
                continue;
            }

            $group = $this->groups->create([
                'name' => $company->name,
                'description' => 'Nhóm của pháp nhân '.$company->name.' ('.$company->code.').',
                'visibility' => SocialGroup::VISIBILITY_PUBLIC,
                'company_id' => $company->id,
                'created_by' => $actor->id,
            ]);
            $created[] = $this->groups->find($group->id) ?? $group;
        }

        return $created;
    }

    /**
     * @return list<SocialGroup>
     */
    public function provisionLegalEntityGroups(User $actor): array
    {
        if (! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'company_id' => ['Chỉ quản trị viên được khởi tạo nhóm pháp nhân.'],
            ]);
        }

        return $this->ensureLegalEntityGroups($actor);
    }

    private function isOfficialLegalEntity(Company $company): bool
    {
        $code = strtoupper(trim($company->code));
        if (in_array($code, SocialGroup::LEGAL_ENTITY_CODES, true)) {
            return true;
        }

        $name = mb_strtolower($company->name);
        if (str_contains($name, 'hoàng việt') || str_contains($name, 'hoang viet')) {
            return true;
        }
        if (str_contains($name, 'việt mỹ') || str_contains($name, 'viet my')) {
            return true;
        }

        $isVienDong = str_contains($name, 'viễn đông') || str_contains($name, 'vien dong');
        $isSavitech = str_contains($name, 'savitech')
            || str_contains($name, 'công nghệ')
            || str_contains($name, 'technology');
        if ($isVienDong && ! $isSavitech) {
            return true;
        }

        return preg_match('/(^|[^a-z0-9])cit([^a-z0-9]|$)/i', $company->name) === 1;
    }

    private function assertCompanyGroupAvailable(?int $companyId, ?int $ignoreGroupId = null): void
    {
        if ($companyId === null) {
            return;
        }

        $query = SocialGroup::query()->where('company_id', $companyId);
        if ($ignoreGroupId !== null) {
            $query->where('id', '!=', $ignoreGroupId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'company_id' => ['Pháp nhân này đã có nhóm.'],
            ]);
        }
    }

    private function resolveCompanyId(User $actor, mixed $companyId): ?int
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }

        if (! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'company_id' => ['Chỉ quản trị viên được gắn nhóm với pháp nhân.'],
            ]);
        }

        $id = (int) $companyId;
        if (! Company::query()->whereKey($id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'company_id' => ['Pháp nhân không tồn tại.'],
            ]);
        }

        return $id;
    }

    private function assertCompanyAudience(SocialGroup $group, User $user): void
    {
        if ($group->company_id === null || $group->includesCompanyOf($user) || $user->isSuperAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'group_id' => ['Nhóm này chỉ dành cho nhân sự của pháp nhân đã gắn.'],
        ]);
    }

    /** @return array{id: int, code: string, name: string}|null */
    private function presentCompany(SocialGroup $group): ?array
    {
        $company = $group->company;
        if ($company === null) {
            return null;
        }

        return [
            'id' => $company->id,
            'code' => $company->code,
            'name' => $company->name,
        ];
    }

    private function audienceCount(SocialGroup $group): int
    {
        if ($group->company_id !== null && $group->visibility === SocialGroup::VISIBILITY_PUBLIC) {
            return User::query()
                ->where('status', 'active')
                ->where(function ($query) use ($group) {
                    $query->where('company_id', $group->company_id)
                        ->orWhereHas('department', fn ($department) => $department->where('company_id', $group->company_id));
                })
                ->count();
        }

        return (int) ($group->members_count ?? $this->groups->membersCount($group->id));
    }

    private function assertIsOwnerOrAdmin(SocialGroup $group, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $membership = $this->groups->membership($group->id, $actor->id);
        if ($membership === null || ! in_array($membership->role, [SocialGroupMember::ROLE_OWNER, SocialGroupMember::ROLE_ADMIN], true)) {
            throw ValidationException::withMessages([
                'group_id' => ['Bạn không có quyền quản trị nhóm này.'],
            ]);
        }
    }

    private function assertIsOwner(SocialGroup $group, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $membership = $this->groups->membership($group->id, $actor->id);
        if ($membership === null || $membership->role !== SocialGroupMember::ROLE_OWNER) {
            throw ValidationException::withMessages([
                'group_id' => ['Chỉ chủ nhóm mới được thực hiện thao tác này.'],
            ]);
        }
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store('social/groups', 'public');
    }
}
