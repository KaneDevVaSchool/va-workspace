<?php

namespace Modules\Attendance\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Services\EmployeeLeaveSubmissionService;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;

class EmployeeLeaveRequestController extends Controller
{
    public function index(Request $request, EmployeeLeaveSubmissionService $submissions): JsonResponse
    {
        try {
            $items = $submissions->recentForUser($request->user(), (int) $request->query('limit', 20));
        } catch (HrmApiUnavailable $e) {
            return response()->json([
                'message' => 'Không tải được lịch sử đơn nghỉ từ HRM.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, EmployeeLeaveSubmissionService $submissions): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:5000'],
            'document_link' => ['nullable', 'string', 'max:2048'],
            'approver_employee_uuid' => ['nullable', 'uuid'],
            'follower_employee_uuid' => ['nullable', 'uuid'],
            'periods' => ['required', 'array', 'min:1', 'max:8'],
            'periods.*.mode' => ['required', Rule::in(['day', 'hour'])],
            'periods.*.date_from' => ['required', 'date'],
            'periods.*.date_to' => ['nullable', 'date'],
            'periods.*.session' => ['nullable', Rule::in(['morning', 'afternoon'])],
            'periods.*.time_from' => ['nullable', 'date_format:H:i'],
            'periods.*.time_to' => ['nullable', 'date_format:H:i'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'max:10240'],
        ], [], [
            'periods' => 'khoảng nghỉ',
            'attachments' => 'tệp đính kèm',
        ]);

        $attachments = array_values(array_filter(
            $request->file('attachments', []),
            fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));

        try {
            $created = $submissions->submitForUser(
                $request->user(),
                (int) $validated['leave_type_id'],
                trim($validated['reason']),
                $validated['periods'],
                filled($validated['document_link'] ?? null) ? trim((string) $validated['document_link']) : null,
                $validated['approver_employee_uuid'] ?? null,
                $validated['follower_employee_uuid'] ?? null,
                $attachments,
            );
        } catch (HrmApiUnavailable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Không gửi được đơn nghỉ qua HRM.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 422);
        }

        return response()->json([
            'message' => count($created) > 1
                ? 'Đã gửi '.count($created).' đơn nghỉ, chờ duyệt trên HRM.'
                : 'Đã gửi đơn nghỉ, chờ duyệt trên HRM.',
            'data' => $created,
        ], 201);
    }
}
