<?php

namespace Modules\Evaluation\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Evaluation\App\Http\Requests\RejectEvaluationEventRequest;
use Modules\Evaluation\App\Http\Requests\StoreEvaluationEventRequest;
use Modules\Evaluation\App\Http\Requests\UpdateEvaluationEventRequest;
use Modules\Evaluation\App\Models\EvaluationEvent;
use Modules\Evaluation\App\Services\EvaluationEventService;
use Modules\Evaluation\App\Services\EvaluationSummaryService;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Identity\App\Services\PermissionService;

/**
 * Manager JSON:
 *   GET    /api/evaluation/events                — danh sách sự kiện phòng ban
 *   POST   /api/evaluation/events                — ghi nhận điểm cộng / trừ
 *   PUT    /api/evaluation/events/{id}           — sửa (chỉ khi chờ duyệt)
 *   PATCH  /api/evaluation/events/{id}/approve   — duyệt
 *   PATCH  /api/evaluation/events/{id}/reject    — từ chối
 *   DELETE /api/evaluation/events/{id}           — xoá (chỉ khi chờ duyệt)
 */
class EvaluationEventController extends Controller
{
    public function __construct(
        private readonly EvaluationEventService $service,
        private readonly EvaluationSummaryService $summaries,
        private readonly PermissionService $permissions,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền xem ghi nhận đánh giá.'], 403);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $result = $this->service->paginateForDepartment(
            $departmentId,
            [
                'user_id' => $request->query('user_id'),
                'status' => $request->query('status'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'q' => $request->query('q'),
            ],
            $perPage,
            max(1, (int) $request->query('page', 1)),
        );

        return response()->json([
            'events' => $result['data'],
            'meta' => $result['meta'],
            'criteria' => $this->service->behaviorCatalog($departmentId),
            'members' => $this->service->departmentMembers($departmentId),
        ]);
    }

    public function store(StoreEvaluationEventRequest $request): JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền ghi nhận đánh giá.'], 403);
        }

        $data = $request->validated();

        // Hỏi trước khi ghi — hỏi sau thì bản ghi vừa tạo tự khớp với chính nó.
        $duplicate = $this->service->isDuplicate($departmentId, $data);

        $event = $this->service->create($departmentId, $data, $request->user());

        $this->activityLogs->record(
            'evaluation_event.create',
            $this->describe($event, 'Ghi nhận'),
            $request->user(),
            'evaluation_event',
            (int) $event->id,
            [
                'department_id' => $departmentId,
                'user_id' => $event->user_id,
                'score' => (float) $event->score,
            ],
        );

        return response()->json(array_filter([
            'event' => $this->service->present($event),
            'row' => $this->recomputedRow($request, $departmentId, (int) $event->user_id),
            'duplicate_warning' => $duplicate ?: null,
        ], static fn ($value) => $value !== null), 201);
    }

    public function update(UpdateEvaluationEventRequest $request, int $id): JsonResponse
    {
        $result = $this->eventOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $event = $this->service->update($result, $request->validated());

        $this->activityLogs->record(
            'evaluation_event.update',
            $this->describe($event, 'Cập nhật ghi nhận'),
            $request->user(),
            'evaluation_event',
            (int) $event->id,
            ['department_id' => $event->department_id],
        );

        return $this->eventResponse($request, $event);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $result = $this->eventOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $event = $this->service->approve($result, $request->user());

        $this->activityLogs->record(
            'evaluation_event.approve',
            $this->describe($event, 'Duyệt ghi nhận'),
            $request->user(),
            'evaluation_event',
            (int) $event->id,
            ['department_id' => $event->department_id],
        );

        return $this->eventResponse($request, $event);
    }

    public function reject(RejectEvaluationEventRequest $request, int $id): JsonResponse
    {
        $result = $this->eventOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $event = $this->service->reject(
            $result,
            $request->user(),
            (string) $request->validated()['reject_reason'],
        );

        $this->activityLogs->record(
            'evaluation_event.reject',
            $this->describe($event, 'Từ chối ghi nhận'),
            $request->user(),
            'evaluation_event',
            (int) $event->id,
            ['department_id' => $event->department_id],
        );

        return $this->eventResponse($request, $event);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $result = $this->eventOrFail($request, $id);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $describe = $this->describe($result, 'Xoá ghi nhận');
        $departmentId = (int) $result->department_id;
        $userId = (int) $result->user_id;
        $this->service->delete($result);

        $this->activityLogs->record(
            'evaluation_event.delete',
            $describe,
            $request->user(),
            'evaluation_event',
            $id,
            ['department_id' => $departmentId],
        );

        return response()->json(array_filter([
            'deleted' => true,
            'row' => $this->recomputedRow($request, $departmentId, $userId),
        ], static fn ($value) => $value !== null));
    }

    /**
     * Phản hồi cho các thao tác đổi một sự kiện — kèm dòng số liệu đã tính lại
     * khi trình duyệt có gửi kỳ đang xem.
     */
    private function eventResponse(Request $request, EvaluationEvent $event): JsonResponse
    {
        return response()->json(array_filter([
            'event' => $this->service->present($event),
            'row' => $this->recomputedRow(
                $request,
                (int) $event->department_id,
                (int) $event->user_id,
            ),
        ], static fn ($value) => $value !== null));
    }

    /**
     * Dòng số liệu của nhân sự sau thay đổi, tính lại ở máy chủ.
     *
     * Giao diện KHÔNG tự cộng trừ điểm: điểm cuối phụ thuộc khung chấm điểm,
     * công việc, xếp loại và cả các sự kiện khác trong kỳ, nên tự cộng ở trình
     * duyệt sẽ sớm lệch với máy chủ. Trả sẵn dòng đã tính lại để bảng thay
     * đúng một dòng mà vẫn không phải tải lại toàn bộ.
     *
     * Chỉ tính khi trình duyệt gửi kèm kỳ đang xem — nơi khác gọi API này
     * (không có kỳ) thì phản hồi giữ nguyên như trước.
     *
     * @return array<string, mixed>|null
     */
    private function recomputedRow(Request $request, int $departmentId, int $userId): ?array
    {
        $from = $request->input('period_from');
        $to = $request->input('period_to');

        if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
            return null;
        }

        return $this->summaries->rowFor($departmentId, $userId, $from, $to);
    }

    private function eventOrFail(Request $request, int $id): EvaluationEvent|JsonResponse
    {
        $departmentId = $this->departmentIdOrFail($request);
        if ($departmentId instanceof JsonResponse) {
            return $departmentId;
        }

        if (! $this->allowed($request, $departmentId)) {
            return response()->json(['message' => 'Bạn không có quyền thao tác ghi nhận đánh giá.'], 403);
        }

        $event = $this->service->find($id);
        if ($event === null || (int) $event->department_id !== $departmentId) {
            return response()->json(['message' => 'Không tìm thấy ghi nhận đánh giá.'], 404);
        }

        return $event;
    }

    private function describe(EvaluationEvent $event, string $prefix): string
    {
        $name = $event->user?->name ?? 'nhân sự';
        $score = (float) $event->score;
        $sign = $score > 0 ? '+' : '';

        return $prefix.' '.($event->level_label ?? '').' ('.$sign.$score.') cho '.$name;
    }

    private function allowed(Request $request, int $departmentId): bool
    {
        return $this->permissions->allows(
            $request->user(),
            'evaluation.manage_department',
            'department',
            $departmentId,
        );
    }

    private function departmentIdOrFail(Request $request): int|JsonResponse
    {
        $departmentId = $request->user()?->department_id;

        return $departmentId
            ? (int) $departmentId
            : response()->json(['message' => 'Tài khoản chưa gắn với phòng ban nào.'], 422);
    }
}
