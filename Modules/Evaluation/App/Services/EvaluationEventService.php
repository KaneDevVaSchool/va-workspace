<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationCriteriaRepositoryInterface;
use Modules\Evaluation\App\Repositories\Contracts\EvaluationEventRepositoryInterface;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\PermissionService;

/**
 * Ghi nhận điểm cộng / trừ theo hành vi cho từng nhân sự.
 *
 * Người có quyền quản lý đánh giá của phòng ban ghi nhận là duyệt luôn — hiện
 * không có vai trò nào khác được ghi nhận, nên không dựng thêm vòng duyệt.
 * Các trường trạng thái / người duyệt vẫn giữ để sau này mở cho nhân sự tự đề
 * xuất rồi trưởng phòng duyệt.
 */
class EvaluationEventService
{
    public function __construct(
        private readonly EvaluationEventRepositoryInterface $events,
        private readonly EvaluationCriteriaRepositoryInterface $criteria,
        private readonly PermissionService $permissions,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Đã có ghi nhận y hệt trong phòng ban chưa.
     *
     * Gọi TRƯỚC create() — gọi sau thì bản ghi vừa tạo sẽ tự khớp với chính
     * nó. Kết quả chỉ dùng để cảnh báo người dùng ("bạn vừa ghi nhận đúng nội
     * dung này rồi"), không dùng để chặn: cùng một hành vi lặp lại trong ngày
     * là chuyện có thật và đáng ghi nhận nhiều lần.
     *
     * @param  array<string, mixed>  $data
     */
    public function isDuplicate(int $departmentId, array $data): bool
    {
        if (empty($data['user_id']) || empty($data['criterion_id']) || empty($data['level_code'])) {
            return false;
        }

        return $this->events->existsSimilar(
            $departmentId,
            (int) $data['user_id'],
            (int) $data['criterion_id'],
            (string) $data['level_code'],
            (string) ($data['occurred_at'] ?? ''),
            ! empty($data['task_id']) ? (int) $data['task_id'] : null,
        );
    }

    /**
     * @param  array{user_id: int, criterion_id: int, level_code: string, occurred_at: string, reason?: string|null, evidence_path?: string|null, task_id?: int|null}  $data
     */
    public function create(int $departmentId, array $data, User $actor): EvaluationEvent
    {
        $criterion = $this->behaviorCriterionOrFail((int) $data['criterion_id'], $departmentId);
        $level = $this->levelOrFail($criterion, (string) $data['level_code']);
        $selfApproved = $this->canManage($actor, $departmentId);
        return $this->events->create([
            'department_id' => $departmentId,
            'user_id' => (int) $data['user_id'],
            'criterion_id' => $criterion->id,
            'criterion_snapshot' => $this->criterionSnapshot($criterion),
            'level_code' => $level['code'],
            'level_label' => $level['label'],
            'score' => $level['score'],
            'occurred_at' => $data['occurred_at'],
            'reason' => $this->trimOrNull($data['reason'] ?? null, 500),
            'evidence_path' => $data['evidence_path'] ?? null,
            'task_id' => ! empty($data['task_id']) ? (int) $data['task_id'] : null,
            'status' => $selfApproved
                ? EvaluationEvent::STATUS_APPROVED
                : EvaluationEvent::STATUS_PENDING,
            'recorded_by' => (int) $actor->id,
            'approved_by' => $selfApproved ? (int) $actor->id : null,
            'approved_at' => $selfApproved ? now() : null,
        ]);
    }

    /**
     * Sửa sự kiện — chỉ khi còn chờ duyệt. Sự kiện đã duyệt là bất biến để
     * không làm lệch số liệu của báo cáo đã lưu.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(EvaluationEvent $event, array $data): EvaluationEvent
    {
        $this->assertPending($event, 'Chỉ sửa được sự kiện đang chờ duyệt.');

        $payload = [];

        if (array_key_exists('criterion_id', $data) || array_key_exists('level_code', $data)) {
            $criterion = $this->behaviorCriterionOrFail(
                (int) ($data['criterion_id'] ?? $event->criterion_id),
                (int) $event->department_id,
            );
            $level = $this->levelOrFail(
                $criterion,
                (string) ($data['level_code'] ?? $event->level_code),
            );
            $payload['criterion_id'] = $criterion->id;
            $payload['criterion_snapshot'] = $this->criterionSnapshot($criterion);
            $payload['level_code'] = $level['code'];
            $payload['level_label'] = $level['label'];
            $payload['score'] = $level['score'];
        }

        foreach (['user_id', 'task_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = ! empty($data[$field]) ? (int) $data[$field] : null;
            }
        }

        if (array_key_exists('occurred_at', $data)) {
            $payload['occurred_at'] = $data['occurred_at'];
        }

        if (array_key_exists('reason', $data)) {
            $payload['reason'] = $this->trimOrNull($data['reason'], 500);
        }

        if (array_key_exists('evidence_path', $data)) {
            $payload['evidence_path'] = $data['evidence_path'];
        }

        return $this->events->update($event, $payload);
    }

    public function approve(EvaluationEvent $event, User $approver): EvaluationEvent
    {
        $this->assertPending($event, 'Sự kiện này đã được xử lý.');

        return $this->events->update($event, [
            'status' => EvaluationEvent::STATUS_APPROVED,
            'approved_by' => (int) $approver->id,
            'approved_at' => now(),
            'reject_reason' => null,
        ]);
    }

    public function reject(EvaluationEvent $event, User $approver, string $reason): EvaluationEvent
    {
        $this->assertPending($event, 'Sự kiện này đã được xử lý.');

        return $this->events->update($event, [
            'status' => EvaluationEvent::STATUS_REJECTED,
            'approved_by' => (int) $approver->id,
            'approved_at' => now(),
            'reject_reason' => $this->trimOrNull($reason, 500),
        ]);
    }

    /**
     * Gỡ một ghi nhận khỏi kỳ đang tính.
     *
     * Trưởng phòng ghi nhận là duyệt luôn, nên nếu chỉ cho xoá bản chờ duyệt
     * thì nút xoá trên màn tổng hợp không bao giờ chạy được. Báo cáo đã lưu
     * giữ số liệu chụp sẵn — gỡ ở đây chỉ đổi bảng đang xem, không sửa báo
     * cáo cũ.
     */
    public function delete(EvaluationEvent $event): void
    {
        $this->events->delete($event);
    }

    public function find(int $id): ?EvaluationEvent
    {
        return $this->events->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function listForDepartment(int $departmentId, array $filters = []): Collection
    {
        return $this->events
            ->allByDepartment($departmentId, $filters)
            ->map(fn (EvaluationEvent $event) => $this->present($event))
            ->values();
    }

    /**
     * Cùng bộ lọc với listForDepartment nhưng phân trang ở máy chủ.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function paginateForDepartment(
        int $departmentId,
        array $filters,
        int $perPage,
        int $page,
    ): array {
        $paginator = $this->events->paginateByDepartment($departmentId, $filters, $perPage, $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (EvaluationEvent $event) => $this->present($event))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Danh mục tiêu chí hành vi kèm các mức để dựng form ghi nhận.
     *
     * @return list<array<string, mixed>>
     */
    public function behaviorCatalog(int $departmentId): array
    {
        return $this->criteria
            ->allByDepartment($departmentId)
            ->filter(fn (EvaluationCriteria $c) => $c->type === 'behavior' && $c->is_active)
            ->map(fn (EvaluationCriteria $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'criterion_type_id' => $c->criterion_type_id,
                'criterion_type_name' => $c->criterionType?->name,
                'levels' => $this->normalizedLevels($c),
            ])
            ->values()
            ->all();
    }

    /**
     * Nhân sự đang hoạt động của phòng ban — để chọn người được ghi nhận mà
     * không phải phụ thuộc API của module khác.
     *
     * @return list<array{id: int, name: string}>
     */
    public function departmentMembers(int $departmentId): array
    {
        return $this->users
            ->allActiveByDepartment($departmentId)
            ->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function present(EvaluationEvent $event): array
    {
        $snapshot = $event->criterion_snapshot ?? [];

        return [
            'id' => $event->id,
            'department_id' => $event->department_id,
            'user_id' => $event->user_id,
            'user_name' => $event->user?->name,
            'criterion_id' => $event->criterion_id,
            'criterion_name' => $snapshot['name'] ?? $event->criterion?->name,
            'criterion_type_name' => $snapshot['criterion_type_name'] ?? null,
            'level_code' => $event->level_code,
            'level_label' => $event->level_label,
            'score' => (float) $event->score,
            'is_bonus' => $event->isBonus(),
            'occurred_at' => $event->occurred_at?->toDateString(),
            'reason' => $event->reason,
            'evidence_path' => $event->evidence_path,
            'task_id' => $event->task_id,
            'task_title' => $event->task?->title,
            'status' => $event->status,
            'recorded_by' => $event->recorded_by,
            'recorded_by_name' => $event->recorder?->name,
            'approved_by' => $event->approved_by,
            'approved_by_name' => $event->approver?->name,
            'approved_at' => $event->approved_at?->toIso8601String(),
            'reject_reason' => $event->reject_reason,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    private function canManage(User $actor, int $departmentId): bool
    {
        return $this->permissions->allows(
            $actor,
            'evaluation.manage_department',
            'department',
            $departmentId,
        );
    }

    private function behaviorCriterionOrFail(int $criterionId, int $departmentId): EvaluationCriteria
    {
        $criterion = $this->criteria->findByDepartment($criterionId, $departmentId);

        if ($criterion === null) {
            throw ValidationException::withMessages([
                'criterion_id' => 'Tiêu chí phải thuộc phòng ban này.',
            ]);
        }

        if ($criterion->type !== 'behavior') {
            throw ValidationException::withMessages([
                'criterion_id' => 'Chỉ ghi nhận được tiêu chí dạng cộng / trừ điểm theo hành vi.',
            ]);
        }

        if (! $criterion->is_active) {
            throw ValidationException::withMessages([
                'criterion_id' => 'Tiêu chí này đang tắt, không ghi nhận được.',
            ]);
        }

        return $criterion;
    }

    /**
     * @return array{code: string, label: string, score: float}
     */
    private function levelOrFail(EvaluationCriteria $criterion, string $levelCode): array
    {
        foreach ($this->normalizedLevels($criterion) as $level) {
            if ($level['code'] === $levelCode) {
                return $level;
            }
        }

        throw ValidationException::withMessages([
            'level_code' => 'Mức điểm không thuộc tiêu chí đã chọn.',
        ]);
    }

    /**
     * @return list<array{code: string, label: string, score: float}>
     */
    private function normalizedLevels(EvaluationCriteria $criterion): array
    {
        $out = [];
        foreach (array_values($criterion->levels ?? []) as $index => $level) {
            if (! is_array($level)) {
                continue;
            }
            $out[] = [
                'code' => EvaluationCriteria::levelKey($level, $index),
                'label' => (string) ($level['label'] ?? ''),
                'score' => (float) ($level['score'] ?? 0),
            ];
        }

        return $out;
    }

    /** @return array{name: string, criterion_type_id: int|null, criterion_type_name: string|null} */
    private function criterionSnapshot(EvaluationCriteria $criterion): array
    {
        return [
            'name' => (string) $criterion->name,
            'criterion_type_id' => $criterion->criterion_type_id,
            'criterion_type_name' => $criterion->criterionType?->name,
        ];
    }

    private function assertPending(EvaluationEvent $event, string $message): void
    {
        if ($event->status !== EvaluationEvent::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => $message]);
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
