<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Enums\TaskEnums;
use Modules\Project\App\Models\Task;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use Modules\Project\App\Repositories\Contracts\TaskRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc file Excel (.xlsx) theo đúng cấu trúc cột của TaskExcelExporter::COLUMNS
 * và trả về bảng xem trước (preview) cho từng dòng — KHÔNG ghi DB.
 * TaskService::confirmImport() lo việc ghi thật từ những dòng đã xác nhận.
 *
 * Mỗi dòng có "Mã công việc" khớp 1 task đang tồn tại → action = 'update'
 * (sửa task đó, ô trống = giữ nguyên giá trị cũ). Dòng để trống mã hoặc mã
 * không khớp → action = 'create' — LUÔN cần "Mã dự án" hợp lệ để xác định
 * project đích (khác Project tự thân không cần cha).
 */
class TaskExcelImporter
{
    private const COL_CODE = 2;

    private const COL_PROJECT_CODE = 3;

    private const COL_TITLE = 4;

    private const COL_TYPE = 5;

    private const COL_STATUS = 6;

    private const COL_PRIORITY = 7;

    private const COL_ASSIGNEE = 8;

    private const COL_MANAGER = 9;

    private const COL_START = 10;

    private const COL_END = 11;

    private const COL_PROGRESS_TYPE = 12;

    private const COL_PROGRESS_PERCENT = 13;

    private const COL_PROGRESS_NUMBER = 14;

    private const COL_PROGRESS_TOTAL = 15;

    private const COL_UNIT = 16;

    private const COL_ESTIMATED_HOURS = 17;

    private const COL_WEIGHT = 18;

    private const COL_DESCRIPTION = 19;

    /** Loại issue nào chặn không cho nhập dòng đó. */
    private const BLOCKING_ISSUE_TYPES = [
        'missing_field',
        'invalid_type',
        'invalid_status',
        'invalid_priority',
        'invalid_progress_type',
        'invalid_date',
        'invalid_date_range',
        'invalid_number',
        'unknown_user',
        'unknown_project',
        'unknown_code',
        'no_permission',
    ];

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ProjectRepositoryInterface $projects,
        private readonly PermissionService $permissions,
        private readonly TaskImportanceOptions $importanceOptions,
    ) {}

    /** @return array{rows: list<array<string, mixed>>} */
    public function preview(UploadedFile $file, User $viewer): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Công việc') ?? $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $rows = [];
        $caches = $this->newCaches();

        for ($excelRow = 5; $excelRow <= $highestRow; $excelRow++) {
            $cells = $this->readCells($sheet, $excelRow);
            $row = $this->resolveRow($cells, $viewer, $caches);
            if ($row === null) {
                continue;
            }
            $row['row'] = $excelRow;
            $rows[] = $row;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['rows' => $rows];
    }

    /**
     * Re-resolve 1 dòng đơn (dùng khi người dùng sửa lỗi tại chỗ trong bảng
     * xem trước — KHÔNG đọc lại file).
     *
     * @param  array<string, mixed>  $cells
     * @return array<string, mixed>
     */
    public function resolveSingle(array $cells, User $viewer): array
    {
        $caches = $this->newCaches();
        $row = $this->resolveRow($cells, $viewer, $caches);

        return $row ?? [
            'status' => 'invalid',
            'action' => 'create',
            'provided_fields' => [],
            'task_id' => null,
            'code' => null,
            'data' => [],
            'issues' => [['type' => 'missing_field', 'message' => 'Thiếu tên công việc.']],
        ];
    }

    /** @return array{userCache: array} */
    private function newCaches(): array
    {
        return ['userCache' => []];
    }

    /** @return array<string, mixed> */
    private function readCells(Worksheet $sheet, int $excelRow): array
    {
        return [
            'code' => (string) $this->cellValue($sheet, self::COL_CODE, $excelRow),
            'project_code' => (string) $this->cellValue($sheet, self::COL_PROJECT_CODE, $excelRow),
            'title' => (string) $this->cellValue($sheet, self::COL_TITLE, $excelRow),
            'type_input' => (string) $this->cellValue($sheet, self::COL_TYPE, $excelRow),
            'status_input' => (string) $this->cellValue($sheet, self::COL_STATUS, $excelRow),
            'priority_input' => (string) $this->cellValue($sheet, self::COL_PRIORITY, $excelRow),
            'assignee_input' => (string) $this->cellValue($sheet, self::COL_ASSIGNEE, $excelRow),
            'manager_input' => (string) $this->cellValue($sheet, self::COL_MANAGER, $excelRow),
            'start_input' => $this->cellValue($sheet, self::COL_START, $excelRow),
            'end_input' => $this->cellValue($sheet, self::COL_END, $excelRow),
            'progress_type_input' => (string) $this->cellValue($sheet, self::COL_PROGRESS_TYPE, $excelRow),
            'progress_percent_input' => (string) $this->cellValue($sheet, self::COL_PROGRESS_PERCENT, $excelRow),
            'progress_number_input' => (string) $this->cellValue($sheet, self::COL_PROGRESS_NUMBER, $excelRow),
            'progress_total_input' => (string) $this->cellValue($sheet, self::COL_PROGRESS_TOTAL, $excelRow),
            'unit' => (string) $this->cellValue($sheet, self::COL_UNIT, $excelRow),
            'estimated_hours_input' => (string) $this->cellValue($sheet, self::COL_ESTIMATED_HOURS, $excelRow),
            'weight_input' => (string) $this->cellValue($sheet, self::COL_WEIGHT, $excelRow),
            'description' => (string) $this->cellValue($sheet, self::COL_DESCRIPTION, $excelRow),
        ];
    }

    /**
     * @param  array<string, mixed>  $cells
     * @param  array{userCache: array}  $caches
     * @return array<string, mixed>|null  null nếu dòng trống hoàn toàn.
     */
    private function resolveRow(array $cells, User $viewer, array &$caches): ?array
    {
        $issues = [];

        $rawTitle = (string) ($cells['title'] ?? '');
        $title = $this->normalizeWhitespace($rawTitle);
        if ($rawTitle !== '' && $rawTitle !== $title) {
            $issues[] = ['type' => 'whitespace', 'message' => 'Tên công việc đã được tự động chuẩn hoá khoảng trắng.'];
        }

        $rawDescription = (string) ($cells['description'] ?? '');
        $description = $this->normalizeWhitespace($rawDescription);

        $codeInput = trim((string) ($cells['code'] ?? ''));
        $projectCodeInput = trim((string) ($cells['project_code'] ?? ''));

        if ($title === '' && $codeInput === '' && $projectCodeInput === '') {
            return null;
        }

        // ---- Đối chiếu Mã công việc → create hay update ----
        $action = 'create';
        $task = null;
        if ($codeInput !== '') {
            $task = $this->tasks->findByCode($codeInput);
            if ($task === null) {
                $issues[] = [
                    'type' => 'unknown_code',
                    'message' => "Không tìm thấy công việc với mã \"{$codeInput}\" — bỏ trống Mã công việc nếu muốn tạo mới.",
                ];
            } else {
                $action = 'update';
            }
        }

        $isUpdate = $action === 'update';

        if ($isUpdate && $task instanceof Task && ! $this->canManageProject($viewer, $task->project_id)) {
            $issues[] = ['type' => 'no_permission', 'message' => 'Bạn không có quyền sửa công việc này.'];
        }

        // ---- Mã dự án — LUÔN cần để xác định project đích (Task khác Project, luôn có cha) ----
        $project = null;
        if ($projectCodeInput !== '') {
            $project = $this->projects->findByCode($projectCodeInput);
            if ($project === null) {
                $issues[] = ['type' => 'unknown_project', 'message' => "Không tìm thấy dự án với mã \"{$projectCodeInput}\"."];
            }
        } elseif ($isUpdate && $task instanceof Task) {
            $project = $this->projects->find($task->project_id);
        } else {
            $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu mã dự án.'];
        }

        if ($title === '') {
            if ($isUpdate && $task instanceof Task) {
                $title = $task->title;
            } else {
                $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu tên công việc.'];
            }
        }

        $typeInput = trim((string) ($cells['type_input'] ?? ''));
        $type = $typeInput === '' ? null : TaskEnums::typeFromInput($typeInput);
        if ($typeInput !== '' && $type === null) {
            $issues[] = ['type' => 'invalid_type', 'message' => 'Loại công việc không hợp lệ.'];
        }
        if ($type === null) {
            $type = $isUpdate && $task instanceof Task ? $task->type : 'task';
        }

        $statusInput = trim((string) ($cells['status_input'] ?? ''));
        $status = $statusInput === '' ? null : TaskEnums::statusFromInput($statusInput);
        if ($statusInput !== '' && $status === null) {
            $issues[] = ['type' => 'invalid_status', 'message' => 'Trạng thái không hợp lệ.'];
        }
        if ($status === null) {
            $status = $isUpdate && $task instanceof Task ? $task->status : 'not_started';
        }

        $priorityInput = trim((string) ($cells['priority_input'] ?? ''));
        $priorityDepartmentId = $project?->owner_department_id
            ?? $project?->executing_department_id
            ?? ($isUpdate && $task instanceof Task ? $task->origin_department_id : null)
            ?? $viewer->department_id;
        $priority = $priorityInput === ''
            ? null
            : $this->importanceOptions->matchAccepted($priorityInput, $priorityDepartmentId);
        if ($priorityInput !== '' && $priority === null) {
            $issues[] = ['type' => 'invalid_priority', 'message' => 'Mức độ ưu tiên không hợp lệ.'];
        }
        if ($priority === null && $isUpdate && $task instanceof Task) {
            $priority = $task->priority;
        }

        $progressTypeInput = trim((string) ($cells['progress_type_input'] ?? ''));
        $progressType = $progressTypeInput === '' ? null : TaskEnums::progressTypeFromInput($progressTypeInput);
        if ($progressTypeInput !== '' && $progressType === null) {
            $issues[] = ['type' => 'invalid_progress_type', 'message' => 'Cách tính tiến độ không hợp lệ.'];
        }
        if ($progressType === null) {
            $progressType = $isUpdate && $task instanceof Task ? $task->progress_type : 'percent';
        }

        [$startDate, $startError] = $this->parseDate($cells['start_input'] ?? null);
        if ($startError !== null) {
            $issues[] = ['type' => 'invalid_date', 'message' => 'Ngày bắt đầu không hợp lệ (dùng dd/mm/yyyy).'];
        }
        $startProvided = $this->hasValue($cells['start_input'] ?? null);
        if (! $startProvided && $isUpdate && $task instanceof Task) {
            $startDate = $task->start_date?->toDateString();
        }

        [$endDate, $endError] = $this->parseDate($cells['end_input'] ?? null);
        if ($endError !== null) {
            $issues[] = ['type' => 'invalid_date', 'message' => 'Ngày kết thúc không hợp lệ (dùng dd/mm/yyyy).'];
        }
        $endProvided = $this->hasValue($cells['end_input'] ?? null);
        if (! $endProvided && $isUpdate && $task instanceof Task) {
            $endDate = $task->end_date?->toDateString();
        }

        if ($startDate && $endDate && $endDate < $startDate) {
            $issues[] = ['type' => 'invalid_date_range', 'message' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'];
        }

        [$progressPercent, $progressPercentError] = $this->parseInt($cells['progress_percent_input'] ?? null, 0, 100);
        if ($progressPercentError !== null) {
            $issues[] = ['type' => 'invalid_number', 'message' => 'Tiến độ (%) phải là số nguyên 0–100.'];
        }
        $progressPercentProvided = $this->hasValue($cells['progress_percent_input'] ?? null);
        if (! $progressPercentProvided && $isUpdate && $task instanceof Task) {
            $progressPercent = $task->progress_percent;
        }

        [$progressNumber, $progressNumberError] = $this->parseFloat($cells['progress_number_input'] ?? null);
        if ($progressNumberError !== null) {
            $issues[] = ['type' => 'invalid_number', 'message' => 'Khối lượng hoàn thành không hợp lệ.'];
        }
        $progressNumberProvided = $this->hasValue($cells['progress_number_input'] ?? null);
        if (! $progressNumberProvided && $isUpdate && $task instanceof Task) {
            $progressNumber = $task->progress_number;
        }

        [$progressTotal, $progressTotalError] = $this->parseFloat($cells['progress_total_input'] ?? null);
        if ($progressTotalError !== null) {
            $issues[] = ['type' => 'invalid_number', 'message' => 'Khối lượng cần hoàn thành không hợp lệ.'];
        }
        $progressTotalProvided = $this->hasValue($cells['progress_total_input'] ?? null);
        if (! $progressTotalProvided && $isUpdate && $task instanceof Task) {
            $progressTotal = $task->progress_total;
        }

        if ($progressType === 'quantity' && ($progressNumber === null || $progressTotal === null || (float) $progressTotal <= 0)) {
            $issues[] = ['type' => 'missing_field', 'message' => 'Cách tính tiến độ theo khối lượng cần Khối lượng hoàn thành + Khối lượng cần hoàn thành > 0.'];
        }
        if ($progressType === 'quantity' && $progressNumber !== null && $progressTotal !== null && (float) $progressTotal > 0) {
            $progressPercent = (int) round(((float) $progressNumber / (float) $progressTotal) * 100);
        }

        $unitInput = $this->normalizeWhitespace((string) ($cells['unit'] ?? ''));
        $unitProvided = trim((string) ($cells['unit'] ?? '')) !== '';
        $unit = $unitProvided ? $unitInput : (($isUpdate && $task instanceof Task) ? $task->unit : null);

        [$estimatedHours, $estimatedHoursError] = $this->parseFloat($cells['estimated_hours_input'] ?? null);
        if ($estimatedHoursError !== null) {
            $issues[] = ['type' => 'invalid_number', 'message' => 'Thời gian dự kiến không hợp lệ.'];
        }
        $estimatedHoursProvided = $this->hasValue($cells['estimated_hours_input'] ?? null);
        if (! $estimatedHoursProvided && $isUpdate && $task instanceof Task) {
            $estimatedHours = $task->estimated_hours;
        }

        [$weight, $weightError] = $this->parseFloat($cells['weight_input'] ?? null);
        if ($weightError !== null) {
            $issues[] = ['type' => 'invalid_number', 'message' => 'Tỷ trọng không hợp lệ.'];
        }
        $weightProvided = $this->hasValue($cells['weight_input'] ?? null);
        if (! $weightProvided && $isUpdate && $task instanceof Task) {
            $weight = $task->weight;
        }

        $assigneeInput = trim((string) ($cells['assignee_input'] ?? ''));
        $assigneeId = $isUpdate && $task instanceof Task ? $task->assignee_id : null;
        $assigneeName = $isUpdate && $task instanceof Task ? $task->assignee?->name : null;
        $assigneeProvided = $assigneeInput !== '';
        if ($assigneeProvided) {
            $email = $this->extractEmail($assigneeInput);
            if ($email === '') {
                $issues[] = ['type' => 'unknown_user', 'message' => "Email người thực hiện không hợp lệ \"{$assigneeInput}\"."];
                $assigneeId = null;
            } else {
                $user = $this->cachedUser($email, $caches['userCache']);
                if ($user === null) {
                    $issues[] = ['type' => 'unknown_user', 'message' => "Không tìm thấy người thực hiện với email \"{$email}\"."];
                    $assigneeId = null;
                } else {
                    $assigneeId = $user->id;
                    $assigneeName = $user->name;
                }
            }
        }

        $managerInput = trim((string) ($cells['manager_input'] ?? ''));
        $managerId = $isUpdate && $task instanceof Task ? $task->manager_id : null;
        $managerName = $isUpdate && $task instanceof Task ? $task->manager?->name : null;
        $managerProvided = $managerInput !== '';
        if ($managerProvided) {
            $email = $this->extractEmail($managerInput);
            if ($email === '') {
                $issues[] = ['type' => 'unknown_user', 'message' => "Email người quản lý không hợp lệ \"{$managerInput}\"."];
                $managerId = null;
            } else {
                $user = $this->cachedUser($email, $caches['userCache']);
                if ($user === null) {
                    $issues[] = ['type' => 'unknown_user', 'message' => "Không tìm thấy người quản lý với email \"{$email}\"."];
                    $managerId = null;
                } else {
                    $managerId = $user->id;
                    $managerName = $user->name;
                }
            }
        }

        $isValid = count(array_intersect(
            self::BLOCKING_ISSUE_TYPES,
            array_column($issues, 'type'),
        )) === 0;

        $data = [
            'project_id' => $project?->id,
            'project_code' => $project?->code,
            'title' => $title,
            'type' => $type,
            'status' => $status,
            'priority' => $priority,
            'assignee_id' => $assigneeId,
            'assignee_name' => $assigneeName,
            'manager_id' => $managerId,
            'manager_name' => $managerName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'progress_type' => $progressType,
            'progress_percent' => $progressPercent,
            'progress_number' => $progressNumber,
            'progress_total' => $progressTotal,
            'unit' => $unit,
            'estimated_hours' => $estimatedHours,
            'weight' => $weight,
            'description' => $description !== '' ? $description : null,
        ];

        // Field nào Excel thực sự có giá trị ở dòng này — dùng khi action=update
        // để chỉ ghi đè đúng field có giá trị (TaskService::confirmImport()).
        $providedFields = array_keys(array_filter([
            'title' => $rawTitle !== '',
            'type' => $typeInput !== '',
            'status' => $statusInput !== '',
            'priority' => $priorityInput !== '',
            'assignee_id' => $assigneeProvided,
            'manager_id' => $managerProvided,
            'start_date' => $startProvided,
            'end_date' => $endProvided,
            'progress_type' => $progressTypeInput !== '',
            'progress_percent' => $progressPercentProvided || $progressType === 'quantity',
            'progress_number' => $progressNumberProvided,
            'progress_total' => $progressTotalProvided,
            'unit' => $unitProvided,
            'estimated_hours' => $estimatedHoursProvided,
            'weight' => $weightProvided,
            'description' => $rawDescription !== '',
        ]));

        return [
            'status' => $isValid ? 'valid' : 'invalid',
            'action' => $action,
            'provided_fields' => $providedFields,
            'task_id' => $task?->id,
            'code' => $codeInput !== '' ? $codeInput : $task?->code,
            'data' => $data,
            'issues' => $issues,
        ];
    }

    /** true nếu user có quyền tạo/sửa Task trong project này — cùng rule TaskService (task.create scope global, đơn giản hoá — chi tiết hơn để TaskService::update() tự chặn nếu cần). */
    private function canManageProject(User $viewer, int $projectId): bool
    {
        if ($viewer->isSuperAdmin() || $this->permissions->allows($viewer, 'task.*')) {
            return true;
        }

        return $this->permissions->allows($viewer, 'task.create');
    }

    private function hasValue(mixed $raw): bool
    {
        return $raw !== null && trim((string) $raw) !== '';
    }

    /** @param  array<string, \App\Models\User|null>  $cache */
    private function cachedUser(string $email, array &$cache): ?User
    {
        $key = mb_strtolower($email);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $this->projects->findUserByEmail($email);
        }

        return $cache[$key];
    }

    private function cellValue(Worksheet $sheet, int $col, int $row): mixed
    {
        return $sheet->getCell([$col, $row])->getValue();
    }

    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function extractEmail(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $match)) {
            return mb_strtolower($match[0]);
        }

        return str_contains($text, '@') ? mb_strtolower($this->normalizeWhitespace($text)) : '';
    }

    /** @return array{0: string|null, 1: string|null}  [Y-m-d hoặc null, lỗi hoặc null] */
    private function parseDate(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [null, null];
        }

        if (is_numeric($raw)) {
            try {
                $date = ExcelDate::excelToDateTimeObject((float) $raw);

                return [$date->format('Y-m-d'), null];
            } catch (\Throwable) {
                return [null, 'invalid'];
            }
        }

        $text = trim((string) $raw);
        if ($text === '' || $text === '—') {
            return [null, null];
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $text);
                if ($date !== false) {
                    return [$date->format('Y-m-d'), null];
                }
            } catch (\Throwable) {
                // Thử format tiếp.
            }
        }

        return [null, 'invalid'];
    }

    /** @return array{0: int|null, 1: string|null} */
    private function parseInt(mixed $raw, int $min, int $max): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [null, null];
        }
        if (! is_numeric($raw)) {
            return [null, 'invalid'];
        }
        $value = (int) round((float) $raw);
        if ($value < $min || $value > $max) {
            return [null, 'invalid'];
        }

        return [$value, null];
    }

    /** @return array{0: float|null, 1: string|null} */
    private function parseFloat(mixed $raw): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [null, null];
        }
        if (! is_numeric($raw)) {
            return [null, 'invalid'];
        }

        return [(float) $raw, null];
    }
}
