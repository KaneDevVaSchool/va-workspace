<?php

namespace Modules\FeatureRequest\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\FeatureRequest\App\Models\FeatureRequest;
use Modules\FeatureRequest\App\Repositories\Contracts\FeatureRequestRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\NotificationService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ghi nhận yêu cầu tính năng từ nhân viên và luồng xử lý của superadmin.
 *
 * Trạng thái: pending (chờ ghi nhận) -> reviewing (đang xem xét, tự chuyển
 * khi superadmin mở chi tiết lần đầu) -> approved (đã duyệt) -> done (đã
 * hoàn thành). rejected có thể xảy ra ở pending/reviewing/approved.
 */
class FeatureRequestService
{
    /** Trần số dòng mỗi lần xuất Excel, giống Nhật ký hoạt động. */
    public const EXPORT_LIMIT = 10000;

    public function __construct(
        private readonly FeatureRequestRepositoryInterface $requests,
        private readonly UserRepositoryInterface $users,
        private readonly NotificationService $notifications,
        private readonly FeatureRequestExcelExporter $exporter,
    ) {}

    public function find(int $id): ?FeatureRequest
    {
        return $this->requests->find($id);
    }

    public function ownList(User $user): array
    {
        return $this->requests->forCreator((int) $user->id)
            ->map(fn (FeatureRequest $r) => $this->present($r))
            ->values()
            ->all();
    }

    /**
     * Nhóm toàn bộ ghi nhận theo phòng ban cho superadmin — mỗi khối gồm
     * thông tin phòng ban, đếm theo trạng thái, và danh sách ghi nhận.
     *
     * @return array{groups: list<array<string, mixed>>, overall_counts: array<string, int>}
     */
    public function groupedByDepartment(?string $status = null): array
    {
        // Luôn lấy toàn bộ (không lọc status ở tầng query) để overall_counts
        // phản ánh đúng tổng thể — không đổi theo filter đang chọn, giống
        // thẻ thống kê ở trang Chi tiết dự án.
        $all = $this->requests->allWithRelations(null);
        $filtered = ($status === null || $status === '')
            ? $all
            : $all->filter(fn (FeatureRequest $r) => $r->status === $status)->values();

        $groups = $filtered->groupBy(fn (FeatureRequest $r) => $r->department_id ?? 0);

        $groupList = $groups->map(function (Collection $items, $departmentId) {
            $first = $items->first();

            return [
                'department_id' => $departmentId ?: null,
                'department_name' => $first?->department?->name ?? 'Chưa xác định',
                'counts' => $this->countByStatus($items),
                'items' => $items->map(fn (FeatureRequest $r) => $this->present($r))->values()->all(),
            ];
        })
            ->sortByDesc(fn ($group) => $group['counts']['total'])
            ->values()
            ->all();

        return [
            'groups' => $groupList,
            'overall_counts' => $this->countByStatus($all),
        ];
    }

    /**
     * Xuất Excel theo điều kiện lọc — lọc lại ở tầng Service bằng đúng logic
     * mà bảng trên trang đang dùng, để file tải về khớp với những gì
     * superadmin đang nhìn thấy.
     *
     * @param  array<string, string>  $filters
     */
    public function export(array $filters, string $exportKind, ?User $exportedBy): BinaryFileResponse
    {
        $all = $this->requests->allWithRelations(null);
        $matching = $this->applyFilters($all, $filters);

        $rows = $matching
            ->take(self::EXPORT_LIMIT)
            ->map(fn (FeatureRequest $r) => $this->present($r))
            ->values()
            ->all();

        $filename = 'Ghi_nhan_yeu_cau_tinh_nang_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download(
            $rows,
            $filters,
            $this->filterLabels($filters, $all),
            $this->countByStatus($all),
            $exportKind,
            $exportedBy,
            $matching->count(),
            self::EXPORT_LIMIT,
            $filename,
        );
    }

    /**
     * Cùng điều kiện lọc với bảng trên trang superadmin: tìm kiếm theo nội
     * dung/người gửi/trang/phòng ban, lọc trạng thái, phòng ban, khoảng ngày
     * gửi (so theo ngày local, không theo giờ).
     *
     * @param  Collection<int, FeatureRequest>  $items
     * @param  array<string, string>  $filters
     * @return Collection<int, FeatureRequest>
     */
    private function applyFilters(Collection $items, array $filters): Collection
    {
        $needle = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $status = (string) ($filters['status'] ?? '');
        $departmentKey = (string) ($filters['department_id'] ?? '');
        $from = (string) ($filters['date_from'] ?? '');
        $to = (string) ($filters['date_to'] ?? '');

        return $items->filter(function (FeatureRequest $request) use ($needle, $status, $departmentKey, $from, $to) {
            if ($status !== '' && $request->status !== $status) {
                return false;
            }

            if ($departmentKey !== '') {
                $key = $request->department_id ? (string) $request->department_id : 'none';
                if ($key !== $departmentKey) {
                    return false;
                }
            }

            $day = $request->created_at?->toDateString() ?? '';
            if ($from !== '' && $day !== '' && $day < $from) {
                return false;
            }
            if ($to !== '' && $day !== '' && $day > $to) {
                return false;
            }

            if ($needle === '') {
                return true;
            }

            foreach ([
                $request->description,
                $request->creator?->name,
                $request->creator?->email,
                $request->page_title,
                $request->department?->name,
            ] as $haystack) {
                if ($haystack !== null && str_contains(mb_strtolower((string) $haystack), $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Nhãn tiếng Việt của điều kiện lọc, để in ra sheet "Thông tin xuất".
     *
     * @param  array<string, string>  $filters
     * @param  Collection<int, FeatureRequest>  $all
     * @return array<string, string>
     */
    private function filterLabels(array $filters, Collection $all): array
    {
        $statusLabels = [
            FeatureRequest::STATUS_PENDING => 'Chờ ghi nhận',
            FeatureRequest::STATUS_REVIEWING => 'Đang xem xét',
            FeatureRequest::STATUS_APPROVED => 'Đã duyệt',
            FeatureRequest::STATUS_REJECTED => 'Từ chối',
            FeatureRequest::STATUS_DONE => 'Đã hoàn thành',
        ];

        $status = (string) ($filters['status'] ?? '');
        $departmentKey = (string) ($filters['department_id'] ?? '');

        $departmentLabel = 'Tất cả';
        if ($departmentKey === 'none') {
            $departmentLabel = 'Chưa xác định';
        } elseif ($departmentKey !== '') {
            $match = $all->first(
                fn (FeatureRequest $r) => (string) ($r->department_id ?? '') === $departmentKey,
            );
            $departmentLabel = $match?->department?->name ?? ('Phòng ban #'.$departmentKey);
        }

        return [
            'status' => $status !== '' ? ($statusLabels[$status] ?? $status) : 'Tất cả',
            'department' => $departmentLabel,
        ];
    }

    /** @return array<string, int> */
    private function countByStatus(Collection $items): array
    {
        $counts = $items->countBy('status');

        return [
            'pending' => (int) ($counts[FeatureRequest::STATUS_PENDING] ?? 0),
            'reviewing' => (int) ($counts[FeatureRequest::STATUS_REVIEWING] ?? 0),
            'approved' => (int) ($counts[FeatureRequest::STATUS_APPROVED] ?? 0),
            'rejected' => (int) ($counts[FeatureRequest::STATUS_REJECTED] ?? 0),
            'done' => (int) ($counts[FeatureRequest::STATUS_DONE] ?? 0),
            'total' => $items->count(),
        ];
    }

    public function create(User $user, array $data): FeatureRequest
    {
        $request = $this->requests->create([
            'created_by' => (int) $user->id,
            'department_id' => $user->department_id,
            'page_title' => $this->trimOrNull($data['page_title'] ?? null, 255),
            'page_url' => $this->trimOrNull($data['page_url'] ?? null, 500),
            'description' => trim((string) $data['description']),
            'status' => FeatureRequest::STATUS_PENDING,
        ]);

        $handler = $this->users->allActiveSuperAdmins()->sortBy('id')->first();
        if ($handler !== null) {
            $this->notifications->notify(
                $handler,
                $user,
                NotificationService::TYPE_FEATURE_REQUEST_CREATED,
                'Có yêu cầu tính năng mới cần xem xét',
                mb_substr($request->description, 0, 120),
                '/superadmin/feature-requests',
                $this->notificationData($request),
            );
        }

        return $request;
    }

    public function updateOwn(FeatureRequest $request, User $user, array $data): FeatureRequest
    {
        $this->assertOwnedAndPending($request, $user);

        return $this->requests->update($request, [
            'page_title' => $this->trimOrNull($data['page_title'] ?? null, 255),
            'page_url' => $this->trimOrNull($data['page_url'] ?? null, 500),
            'description' => trim((string) $data['description']),
        ]);
    }

    public function deleteOwn(FeatureRequest $request, User $user): void
    {
        $this->assertOwnedAndPending($request, $user);

        $this->requests->delete($request);
    }

    /**
     * Đánh dấu đang xem xét khi superadmin mở chi tiết lần đầu — không có
     * action riêng để tránh nhân viên thấy nút bấm không cần thiết.
     */
    public function markReviewingIfPending(FeatureRequest $request): FeatureRequest
    {
        if ($request->status !== FeatureRequest::STATUS_PENDING) {
            return $request;
        }

        return $this->requests->update($request, [
            'status' => FeatureRequest::STATUS_REVIEWING,
        ]);
    }

    public function approve(FeatureRequest $request, User $approver, ?string $expectedDoneAt, ?string $progressNote): FeatureRequest
    {
        $this->assertNotFinal($request);

        $updated = $this->requests->update($request, [
            'status' => FeatureRequest::STATUS_APPROVED,
            'reviewed_by' => (int) $approver->id,
            'reviewed_at' => now(),
            'expected_done_at' => $expectedDoneAt,
            'progress_note' => $this->trimOrNull($progressNote, 500),
            'reject_reason' => null,
        ]);

        $this->notifyCreator(
            $updated,
            $approver,
            NotificationService::TYPE_FEATURE_REQUEST_APPROVED,
            'Yêu cầu tính năng của bạn đã được duyệt',
        );

        return $updated;
    }

    public function reject(FeatureRequest $request, User $approver, string $reason): FeatureRequest
    {
        $this->assertNotFinal($request);

        $updated = $this->requests->update($request, [
            'status' => FeatureRequest::STATUS_REJECTED,
            'reviewed_by' => (int) $approver->id,
            'reviewed_at' => now(),
            'reject_reason' => $this->trimOrNull($reason, 500),
        ]);

        $this->notifyCreator(
            $updated,
            $approver,
            NotificationService::TYPE_FEATURE_REQUEST_REJECTED,
            'Yêu cầu tính năng của bạn đã bị từ chối',
        );

        return $updated;
    }

    public function markDone(FeatureRequest $request, User $approver): FeatureRequest
    {
        if ($request->status !== FeatureRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ đánh dấu hoàn thành được khi đã duyệt.',
            ]);
        }

        $updated = $this->requests->update($request, [
            'status' => FeatureRequest::STATUS_DONE,
            'done_at' => now(),
        ]);

        $this->notifyCreator(
            $updated,
            $approver,
            NotificationService::TYPE_FEATURE_REQUEST_DONE,
            'Yêu cầu tính năng của bạn đã hoàn thành',
        );

        return $updated;
    }

    /** @return array<string, mixed> */
    public function present(FeatureRequest $request): array
    {
        return [
            'id' => $request->id,
            'created_by' => $request->created_by,
            'created_by_name' => $request->creator?->name,
            'created_by_email' => $request->creator?->email,
            'created_by_avatar_url' => $request->creator?->avatar_url,
            'department_id' => $request->department_id,
            'department_name' => $request->department?->name,
            'page_title' => $request->page_title,
            'page_url' => $request->page_url,
            'description' => $request->description,
            'status' => $request->status,
            'reviewed_by' => $request->reviewed_by,
            'reviewed_by_name' => $request->reviewer?->name,
            'reviewed_by_email' => $request->reviewer?->email,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'expected_done_at' => $request->expected_done_at?->toDateString(),
            'progress_note' => $request->progress_note,
            'reject_reason' => $request->reject_reason,
            'done_at' => $request->done_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function notifyCreator(FeatureRequest $request, User $actor, string $type, string $title): void
    {
        $creator = $this->users->findById((int) $request->created_by);
        if ($creator === null) {
            return;
        }

        $this->notifications->notify(
            $creator,
            $actor,
            $type,
            $title,
            mb_substr($request->description, 0, 120),
            '/feature-requests/mine',
            $this->notificationData($request, $actor),
        );
    }

    /** @return array<string, int|null> */
    private function notificationData(FeatureRequest $request, ?User $reviewer = null): array
    {
        return [
            'feature_request_id' => (int) $request->id,
            'creator_id' => (int) $request->created_by,
            'reviewer_id' => $reviewer ? (int) $reviewer->id : ($request->reviewed_by ? (int) $request->reviewed_by : null),
        ];
    }

    private function assertOwnedAndPending(FeatureRequest $request, User $user): void
    {
        if ((int) $request->created_by !== (int) $user->id) {
            throw ValidationException::withMessages([
                'status' => 'Bạn không có quyền sửa ghi nhận này.',
            ]);
        }

        if ($request->status !== FeatureRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Ghi nhận đã được xem, không thể sửa hoặc xoá.',
            ]);
        }
    }

    private function assertNotFinal(FeatureRequest $request): void
    {
        if (in_array($request->status, [FeatureRequest::STATUS_DONE, FeatureRequest::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Ghi nhận này đã xử lý xong, không thể duyệt lại.',
            ]);
        }
    }

    private function trimOrNull(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? mb_substr($text, 0, $max) : null;
    }
}
