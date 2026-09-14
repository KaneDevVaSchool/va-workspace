<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Ghi và đọc nhật ký thao tác. Ghi thất bại không được làm hỏng thao tác gốc.
 */
class ActivityLogService
{
    public const EXPORT_LIMIT = 10000;

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        'auth.login' => 'Đăng nhập',
        'auth.logout' => 'Đăng xuất',
        'view_as.activate' => 'Xem thử vai trò',
        'view_as.deactivate' => 'Thoát xem thử',
        'permission.grant' => 'Cấp quyền',
        'permission.deny' => 'Tắt quyền',
        'permission.revoke' => 'Khôi phục quyền',
        'team.create' => 'Tạo nhóm',
        'team.update' => 'Cập nhật nhóm',
        'team.delete' => 'Xoá nhóm',
        'role.assign' => 'Gán vai trò',
        'member.team.assign' => 'Gán nhóm cho thành viên',
        'workspace_config.sidebar.update' => 'Cập nhật menu phòng ban',
        'workspace_config.global_menu.update' => 'Cập nhật menu ẩn toàn hệ thống',
        'shortcut.create' => 'Tạo lối tắt',
        'shortcut.update' => 'Cập nhật lối tắt',
        'shortcut.delete' => 'Xoá lối tắt',
        'social_post.update' => 'Sửa bài viết',
        'social_post.pin' => 'Ghim bài viết',
        'social_post.approve' => 'Duyệt bài viết',
        'social_post.reject' => 'Từ chối bài viết',
        'evaluation_criteria.create' => 'Tạo tiêu chí đánh giá',
        'evaluation_criteria.update' => 'Cập nhật tiêu chí đánh giá',
        'evaluation_criteria.delete' => 'Xoá tiêu chí đánh giá',
        'evaluation_criteria.reorder' => 'Sắp xếp lại tiêu chí đánh giá',
        'evaluation_criterion_type.create' => 'Tạo loại tiêu chí đánh giá',
        'evaluation_score_kit.create' => 'Thiết lập khung chấm điểm',
        'evaluation_score_kit.update' => 'Cập nhật khung chấm điểm',
        'evaluation_score_kit.mode_change' => 'Đổi cách tính điểm',
        'evaluation_score_kit.reset' => 'Khôi phục khung chấm điểm',
        'evaluation_config_version.publish' => 'Chốt phiên bản khung chấm điểm',
        'evaluation_event.create' => 'Ghi nhận đánh giá nhân sự',
        'evaluation_event.update' => 'Cập nhật ghi nhận đánh giá',
        'evaluation_event.approve' => 'Duyệt ghi nhận đánh giá',
        'evaluation_event.reject' => 'Từ chối ghi nhận đánh giá',
        'evaluation_event.delete' => 'Xoá ghi nhận đánh giá',
        'report.create' => 'Tạo báo cáo',
        'report.update' => 'Cập nhật báo cáo',
        'report.save' => 'Lưu báo cáo',
        'report.delete' => 'Xoá báo cáo',
        'credential.create' => 'Tạo thông tin đăng nhập',
        'credential.update' => 'Cập nhật thông tin đăng nhập',
        'credential.delete' => 'Xoá thông tin đăng nhập',
        'credential.export_cost_excel' => 'Xuất báo cáo chi phí (Excel)',
        'credential.export_cost_pdf' => 'Xuất báo cáo chi phí (PDF)',
        'credential_provider.create' => 'Tạo nhà cung cấp',
        'credential_provider.update' => 'Cập nhật nhà cung cấp',
        'credential_provider.delete' => 'Xoá nhà cung cấp',
        'credential_viewer.grant' => 'Chia sẻ quyền xem thông tin đăng nhập',
        'credential_viewer.sync' => 'Đồng bộ danh sách người được xem',
        'credential_viewer.revoke' => 'Thu hồi quyền xem thông tin đăng nhập',
        'project.create' => 'Tạo dự án',
        'project.update' => 'Cập nhật dự án',
        'project.delete' => 'Xoá dự án',
        'project.duplicate' => 'Nhân bản dự án',
        'project.tab_config.update' => 'Cập nhật cấu hình tab dự án',
        'project.import' => 'Nhập dự án từ Excel',
        'project.attachment.upload' => 'Tải lên tệp đính kèm dự án',
        'project.attachment.delete' => 'Xoá tệp đính kèm dự án',
        'project.attachment.rename' => 'Đổi tên tệp đính kèm dự án',
        'project.folder.create' => 'Tạo thư mục dự án',
        'project.folder.update' => 'Cập nhật thư mục dự án',
        'project.folder.delete' => 'Xoá thư mục dự án',
        'project.avatar.update' => 'Cập nhật ảnh đại diện dự án',
        'project.avatar.delete' => 'Xoá ảnh đại diện dự án',
        'project_label.create' => 'Tạo nhãn dự án',
        'project_type.create' => 'Tạo loại dự án',
        'project_settings.general.update' => 'Cập nhật cấu hình chung dự án',
        'project_settings.creator_allowlist.update' => 'Cập nhật danh sách được phép tạo dự án',
        'project_quick_item.create' => 'Thêm mục nhanh dự án',
        'task.create' => 'Tạo công việc',
        'task.create_bulk' => 'Tạo nhanh nhiều công việc',
        'task.structure.sync' => 'Sắp xếp cấu trúc công việc',
        'task.update' => 'Cập nhật công việc',
        'task.report_complete' => 'Báo hoàn thành công việc',
        'task.delete' => 'Xoá công việc',
        'task.update_bulk' => 'Cập nhật hàng loạt công việc',
        'task.delegate_bulk' => 'Chuyển giao hàng loạt công việc',
        'task.import' => 'Nhập công việc từ Excel',
        'comment.delete' => 'Xoá bình luận',
        'comment.pin' => 'Ghim bình luận',
        'comment.unpin' => 'Bỏ ghim bình luận',
        'task_attachment.upload' => 'Tải lên tệp đính kèm công việc',
        'task_attachment.rename' => 'Đổi tên tệp đính kèm công việc',
        'task_attachment.replace' => 'Thay thế tệp đính kèm công việc',
        'task_attachment.delete' => 'Xoá tệp đính kèm công việc',
        'task_worklog.create' => 'Ghi nhật ký giờ làm',
        'task_worklog.update' => 'Cập nhật nhật ký giờ làm',
        'task_worklog.delete' => 'Xoá nhật ký giờ làm',
        'task_score.upsert' => 'Chấm điểm công việc',
        'project_feedback.create' => 'Thêm phản hồi dự án',
        'project_feedback.update' => 'Cập nhật phản hồi dự án',
        'project_feedback.delete' => 'Xoá phản hồi dự án',
        'project_testcase.create' => 'Tạo testcase',
        'project_testcase.update' => 'Cập nhật testcase',
        'project_testcase.delete' => 'Xoá testcase',
        'project_testcase.check' => 'Chấm check testcase',
        'project_testcase.attachment.upload' => 'Tải lên tệp đính kèm testcase',
        'project_testcase.attachment.delete' => 'Xoá tệp đính kèm testcase',
    ];

    /** @var array<string, string> */
    public const SUBJECT_TYPE_LABELS = [
        'team' => 'Nhóm',
        'user' => 'Người dùng',
        'permission' => 'Quyền',
        'shortcut' => 'Lối tắt',
        'social_post' => 'Bài viết bảng tin',
        'role' => 'Vai trò',
        'department_sidebar_config' => 'Menu phòng ban',
        'global_menu_visibility' => 'Menu ẩn toàn hệ thống',
        'evaluation_criteria' => 'Tiêu chí đánh giá',
        'evaluation_criterion_type' => 'Loại tiêu chí đánh giá',
        'evaluation_score_kit' => 'Khung chấm điểm',
        'evaluation_config_version' => 'Phiên bản khung chấm điểm',
        'evaluation_event' => 'Ghi nhận đánh giá nhân sự',
        'report' => 'Báo cáo',
        'credential' => 'Thông tin đăng nhập',
        'credential_provider' => 'Nhà cung cấp',
        'project' => 'Dự án',
        'task' => 'Công việc',
        'comment' => 'Bình luận',
        'project_label' => 'Nhãn dự án',
        'project_type' => 'Loại dự án',
    ];

    /** @var array<string, string> */
    private const PROPERTY_LABELS = [
        'role_code' => 'Vai trò',
        'permission_key' => 'Quyền',
        'granted' => 'Đã cấp',
        'scope_type' => 'Phạm vi',
        'scope_id' => 'Mã phạm vi',
        'path' => 'Đường dẫn',
        'title' => 'Tên',
        'revision' => 'Phiên bản',
        'change_context' => 'Ngữ cảnh',
        'changed_fields' => 'Trường thay đổi',
        'changes' => 'Chi tiết trước / sau',
    ];

    public function __construct(
        private readonly ActivityLogRepositoryInterface $logs,
        private readonly ActivityLogExcelExporter $exporter,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        string $description,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
    ): void {
        try {
            $request = request();
            $actor ??= $request?->user();

            $this->logs->create([
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_email' => $actor?->email,
                'action' => $action,
                'description' => mb_substr($this->plainDescription($description), 0, 255),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => $request?->ip(),
                'user_agent' => $request
                    ? mb_substr((string) $request->userAgent(), 0, 512)
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Không ghi được nhật ký hoạt động.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 20): Collection
    {
        return $this->logs->recent($limit);
    }

    /**
     * @param  list<int>  $subjectIds
     * @param  array<string, mixed>  $propertyMatches
     * @return Collection<int, ActivityLog>
     */
    public function recentForSubject(
        string $subjectType,
        array $subjectIds = [],
        array $propertyMatches = [],
        int $limit = 80,
    ): Collection {
        return $this->logs->recentForSubject($subjectType, $subjectIds, $propertyMatches, $limit);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }

    /**
     * @param  iterable<int, ActivityLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $logs): array
    {
        $presented = [];
        foreach ($logs as $log) {
            $presented[] = $this->present($log);
        }

        return $presented;
    }

    /** @return array<string, mixed> */
    public function present(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => self::actionLabel($log->action),
            'description' => $this->plainDescription($log->description),
            'actor_id' => $log->actor_id,
            'actor_name' => $log->actor_name,
            'actor_email' => $log->actor_email,
            'actor' => $this->presentActor($log),
            'subject_type' => $log->subject_type,
            'subject_type_label' => self::subjectTypeLabel($log->subject_type),
            'subject_id' => $log->subject_id,
            'subject_label' => $this->subjectLabel($log),
            'properties' => $log->properties,
            'properties_summary' => $this->propertiesSummary($log->properties),
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'browser' => $this->browserLabel($log->user_agent),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /** @return array{actions: list<array{value: string, label: string}>, actors: list<array<string, mixed>>, subject_types: list<array{value: string, label: string}>} */
    public function filterOptions(): array
    {
        $actions = [
            ['value' => '', 'label' => 'Tất cả thao tác'],
        ];
        foreach (self::ACTION_LABELS as $value => $label) {
            $actions[] = ['value' => $value, 'label' => $label];
        }

        $actors = $this->logs->distinctActors()
            ->map(fn (ActivityLog $log) => [
                'id' => (string) $log->actor_id,
                'name' => $log->actor_name ?: 'Không rõ tên',
                'email' => $log->actor_email,
            ])
            ->values()
            ->all();

        $subjectTypes = [
            ['value' => '', 'label' => 'Tất cả đối tượng'],
        ];
        foreach ($this->logs->distinctSubjectTypes() as $type) {
            $subjectTypes[] = [
                'value' => (string) $type,
                'label' => self::subjectTypeLabel((string) $type),
            ];
        }

        return [
            'actions' => $actions,
            'actors' => $actors,
            'subject_types' => $subjectTypes,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(
        array $filters,
        string $exportKind,
        ?User $exportedBy,
    ): BinaryFileResponse {
        $matched = $this->logs->countFiltered($filters);
        $logs = $this->logs->forExport($filters, self::EXPORT_LIMIT);
        $rows = $this->presentMany($logs);

        $filename = 'Nhat_ky_hoat_dong_'.now()->format('Ymd_His').'.xlsx';

        return $this->exporter->download(
            $rows,
            $filters,
            $this->filterLabels($filters),
            $exportKind,
            $exportedBy,
            $matched,
            self::EXPORT_LIMIT,
            $filename,
        );
    }

    public static function actionLabel(?string $action): string
    {
        if ($action === null || $action === '') {
            return '';
        }

        return self::ACTION_LABELS[$action] ?? $action;
    }

    public static function subjectTypeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        return self::SUBJECT_TYPE_LABELS[$type] ?? $type;
    }

    /** @return array{id: int|null, name: string, email: string|null, avatar_url: string|null, department: array{id: int, name: string}|null} */
    private function presentActor(ActivityLog $log): array
    {
        $actor = $log->relationLoaded('actor') ? $log->actor : null;
        $department = $actor?->department;

        return [
            'id' => $actor?->id ?? $log->actor_id,
            'name' => $actor?->name ?: ($log->actor_name ?: 'Hệ thống'),
            'email' => $actor?->email ?? $log->actor_email,
            'avatar_url' => $actor?->avatar_url,
            'department' => $department ? [
                'id' => $department->id,
                'name' => $department->name,
            ] : null,
        ];
    }

    private function subjectLabel(ActivityLog $log): string
    {
        if (! $log->subject_type) {
            return '—';
        }

        $type = self::subjectTypeLabel($log->subject_type);
        if ($log->subject_id) {
            return $type.' #'.$log->subject_id;
        }

        return $type;
    }

    /** @param  array<string, mixed>|null  $properties */
    private function propertiesSummary(?array $properties): string
    {
        if (! $properties) {
            return '';
        }

        $parts = [];
        foreach ($properties as $key => $value) {
            $label = self::PROPERTY_LABELS[$key] ?? (string) $key;
            $parts[] = $label.': '.$this->scalarText($value);
        }

        return implode(' · ', $parts);
    }

    private function scalarText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value;
    }

    private function browserLabel(?string $userAgent): string
    {
        if (! $userAgent) {
            return '—';
        }

        $map = [
            'Edg/' => 'Microsoft Edge',
            'OPR/' => 'Opera',
            'Chrome/' => 'Google Chrome',
            'Firefox/' => 'Firefox',
            'Safari/' => 'Safari',
        ];
        foreach ($map as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                $device = str_contains($userAgent, 'Mobile') ? ' (điện thoại)' : '';

                return $label.$device;
            }
        }

        return mb_substr($userAgent, 0, 80);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function filterLabels(array $filters): array
    {
        $actorId = trim((string) ($filters['actor_id'] ?? ''));
        $actorLabel = 'Tất cả';
        if ($actorId === 'system') {
            $actorLabel = 'Hệ thống';
        } elseif ($actorId !== '') {
            $match = $this->logs->distinctActors()->first(
                fn (ActivityLog $log) => (string) $log->actor_id === $actorId,
            );
            $actorLabel = $match
                ? trim(($match->actor_name ?: 'Không rõ tên').($match->actor_email ? ' ('.$match->actor_email.')' : ''))
                : 'Mã người dùng '.$actorId;
        }

        return [
            'action' => $filters['action'] !== ''
                ? self::actionLabel((string) $filters['action'])
                : 'Tất cả',
            'actor' => $actorLabel,
            'subject_type' => $filters['subject_type'] !== ''
                ? self::subjectTypeLabel((string) $filters['subject_type'])
                : 'Tất cả',
        ];
    }

    private function plainDescription(?string $description): string
    {
        return trim(html_entity_decode(strip_tags((string) $description), ENT_QUOTES, 'UTF-8'));
    }
}
