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
        'shortcut.create' => 'Tạo lối tắt',
        'shortcut.update' => 'Cập nhật lối tắt',
        'shortcut.delete' => 'Xoá lối tắt',
    ];

    /** @var array<string, string> */
    public const SUBJECT_TYPE_LABELS = [
        'team' => 'Nhóm',
        'user' => 'Người dùng',
        'permission' => 'Quyền',
        'shortcut' => 'Lối tắt',
        'role' => 'Vai trò',
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
                'description' => mb_substr($description, 0, 255),
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
            'description' => $log->description,
            'actor_id' => $log->actor_id,
            'actor_name' => $log->actor_name,
            'actor_email' => $log->actor_email,
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
}
