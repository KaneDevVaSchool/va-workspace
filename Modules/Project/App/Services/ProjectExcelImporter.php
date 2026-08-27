<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Modules\Identity\App\Services\PermissionService;
use Modules\Project\App\Enums\ProjectEnums;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectType;
use Modules\Project\App\Repositories\Contracts\ProjectRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc file Excel (.xlsx) theo đúng cấu trúc cột của ProjectExcelExporter::HEADERS
 * và trả về bảng xem trước (preview) cho từng dòng — KHÔNG ghi DB.
 * ProjectService::confirmImport() lo việc ghi thật từ những dòng đã xác nhận.
 *
 * Mỗi dòng có "Mã dự án" khớp 1 dự án đang tồn tại → action = 'update' (sửa
 * dự án đó, ô trống = giữ nguyên giá trị cũ). Dòng để trống mã hoặc mã không
 * khớp → action = 'create' (hành vi cũ — thiếu field bắt buộc là lỗi).
 */
class ProjectExcelImporter
{
    private const COL_CODE = 2;

    private const COL_NAME = 3;

    private const COL_TYPE = 4;

    private const COL_EXEC_DEPT = 6;

    private const COL_LEAD = 7;

    private const COL_MEMBERS = 8;

    private const COL_FOLLOWERS = 9;

    private const COL_LABELS = 10;

    private const COL_STATUS = 11;

    private const COL_IMPORTANCE = 12;

    private const COL_START = 13;

    private const COL_END = 14;

    private const COL_PROGRESS_METHOD = 15;

    private const COL_DESCRIPTION = 18;

    /** Loại issue nào chặn không cho nhập dòng đó. */
    private const BLOCKING_ISSUE_TYPES = [
        'missing_field',
        'invalid_status',
        'invalid_importance',
        'invalid_progress_method',
        'invalid_date',
        'invalid_date_range',
        'unknown_user',
        'unknown_department',
        'unknown_label',
        'unknown_code',
        'no_permission',
    ];

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * @return array{rows: list<array{row:int, status:string, action:string, project_id:?int, data:array<string,mixed>, issues:list<array{type:string,message:string}>}>}
     */
    public function preview(UploadedFile $file, User $viewer): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Dự án') ?? $spreadsheet->getActiveSheet();
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
     * Re-resolve 1 dòng đơn (dùng khi người dùng sửa lỗi tại chỗ trong bảng xem
     * trước — KHÔNG đọc lại file, nhận thẳng các giá trị text đã sửa).
     *
     * @param  array<string, mixed>  $cells  cùng shape với readCells(): name, code, type_input, …
     * @return array{status:string, action:string, project_id:?int, data:array<string,mixed>, issues:list<array{type:string,message:string}>}
     */
    public function resolveSingle(array $cells, User $viewer): array
    {
        $caches = $this->newCaches();
        $row = $this->resolveRow($cells, $viewer, $caches);

        return $row ?? [
            'status' => 'invalid',
            'action' => 'create',
            'provided_fields' => [],
            'project_id' => null,
            'code' => null,
            'data' => [],
            'issues' => [['type' => 'missing_field', 'message' => 'Thiếu tên dự án.']],
        ];
    }

    /** @return array{userCache: array, deptCache: array, labelCache: array} */
    private function newCaches(): array
    {
        return ['userCache' => [], 'deptCache' => [], 'labelCache' => []];
    }

    /** @return array<string, mixed> */
    private function readCells(Worksheet $sheet, int $excelRow): array
    {
        return [
            'code' => (string) $this->cellValue($sheet, self::COL_CODE, $excelRow),
            'name' => (string) $this->cellValue($sheet, self::COL_NAME, $excelRow),
            'type_input' => (string) $this->cellValue($sheet, self::COL_TYPE, $excelRow),
            'exec_dept_name' => (string) $this->cellValue($sheet, self::COL_EXEC_DEPT, $excelRow),
            'lead_input' => (string) $this->cellValue($sheet, self::COL_LEAD, $excelRow),
            'members_input' => (string) $this->cellValue($sheet, self::COL_MEMBERS, $excelRow),
            'followers_input' => (string) $this->cellValue($sheet, self::COL_FOLLOWERS, $excelRow),
            'labels_input' => (string) $this->cellValue($sheet, self::COL_LABELS, $excelRow),
            'status_input' => (string) $this->cellValue($sheet, self::COL_STATUS, $excelRow),
            'importance_input' => (string) $this->cellValue($sheet, self::COL_IMPORTANCE, $excelRow),
            'start_input' => $this->cellValue($sheet, self::COL_START, $excelRow),
            'end_input' => $this->cellValue($sheet, self::COL_END, $excelRow),
            'progress_method_input' => (string) $this->cellValue($sheet, self::COL_PROGRESS_METHOD, $excelRow),
            'description' => (string) $this->cellValue($sheet, self::COL_DESCRIPTION, $excelRow),
        ];
    }

    /**
     * Logic đọc + chuẩn hoá + validate 1 dòng — dùng chung cho preview() (đọc
     * từ sheet) và resolveSingle() (đọc từ payload sửa lỗi tại chỗ).
     *
     * @param  array<string, mixed>  $cells
     * @param  array{userCache: array, deptCache: array, labelCache: array}  $caches
     * @return array{status:string, action:string, project_id:?int, data:array<string,mixed>, issues:list<array{type:string,message:string}>}|null
     *         null nếu dòng trống hoàn toàn (bỏ qua, chỉ preview() dùng đến).
     */
    private function resolveRow(array $cells, User $viewer, array &$caches): ?array
    {
        $issues = [];

        $rawName = (string) ($cells['name'] ?? '');
        $name = $this->normalizeWhitespace($rawName);
        if ($rawName !== '' && $rawName !== $name) {
            $issues[] = ['type' => 'whitespace', 'message' => 'Tên đã được tự động chuẩn hoá khoảng trắng.'];
        }

        $rawDescription = (string) ($cells['description'] ?? '');
        $description = $this->normalizeWhitespace($rawDescription);
        if ($rawDescription !== '' && $rawDescription !== $description) {
            $issues[] = ['type' => 'whitespace', 'message' => 'Mô tả đã được tự động chuẩn hoá khoảng trắng.'];
        }

        $typeInput = trim((string) ($cells['type_input'] ?? ''));
        $codeInput = trim((string) ($cells['code'] ?? ''));

        if ($name === '' && $typeInput === '' && $codeInput === '') {
            return null;
        }

        // ---- Đối chiếu Mã dự án → create hay update ----
        $action = 'create';
        $project = null;
        if ($codeInput !== '') {
            $project = $this->projects->findByCode($codeInput);
            if ($project === null) {
                $issues[] = [
                    'type' => 'unknown_code',
                    'message' => "Không tìm thấy dự án với mã \"{$codeInput}\" — bỏ trống Mã dự án nếu muốn tạo mới.",
                ];
            } else {
                $action = 'update';
            }
        }

        if ($action === 'update' && $project instanceof Project) {
            if (! $this->canManageDepartment($viewer, $project)) {
                $issues[] = [
                    'type' => 'no_permission',
                    'message' => 'Bạn không có quyền sửa dự án này.',
                ];
            }
        }

        $isUpdate = $action === 'update';

        if ($name === '') {
            if ($isUpdate && $project instanceof Project) {
                $name = $project->name;
            } else {
                $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu tên dự án.'];
            }
        }

        // Loại dự án là danh mục tự do (bảng project_types, mục A) — Excel ghi
        // đúng tên loại đã có thì dùng lại, ghi tên chưa từng có thì coi như
        // người dùng đang tạo loại mới (giữ nguyên tên đã chuẩn hoá khoảng
        // trắng), không còn danh sách cố định để đối chiếu "hợp lệ/không hợp lệ".
        $type = $typeInput === '' ? null : $this->normalizeWhitespace($typeInput);
        if ($typeInput === '') {
            if ($isUpdate && $project instanceof Project) {
                $type = $project->type;
            } else {
                $issues[] = ['type' => 'missing_field', 'message' => 'Thiếu loại dự án.'];
            }
        }

        $statusInput = trim((string) ($cells['status_input'] ?? ''));
        $status = $statusInput === '' ? null : ProjectEnums::statusFromInput($statusInput);
        if ($statusInput !== '' && $status === null) {
            $issues[] = ['type' => 'invalid_status', 'message' => 'Trạng thái không hợp lệ.'];
        }
        if ($status === null) {
            $status = $isUpdate && $project instanceof Project ? $project->status : 'planning';
        }

        $importanceInput = trim((string) ($cells['importance_input'] ?? ''));
        $importance = $importanceInput === '' ? null : ProjectEnums::importanceFromInput($importanceInput);
        if ($importanceInput !== '' && $importance === null) {
            $issues[] = ['type' => 'invalid_importance', 'message' => 'Mức độ quan trọng không hợp lệ.'];
        }
        if ($importance === null) {
            $importance = $isUpdate && $project instanceof Project ? $project->importance : 'important';
        }

        $methodInput = trim((string) ($cells['progress_method_input'] ?? ''));
        $progressMethod = $methodInput === '' ? null : ProjectEnums::progressMethodFromInput($methodInput);
        if ($methodInput !== '' && $progressMethod === null) {
            $issues[] = ['type' => 'invalid_progress_method', 'message' => 'Cách tính tiến độ không hợp lệ.'];
        }
        if ($progressMethod === null) {
            $progressMethod = $isUpdate && $project instanceof Project ? $project->progress_method : 'average';
        }

        [$startDate, $startError] = $this->parseDate($cells['start_input'] ?? null);
        if ($startError !== null) {
            $issues[] = ['type' => 'invalid_date', 'message' => 'Ngày bắt đầu không hợp lệ (dùng dd/mm/yyyy).'];
        }
        $startProvided = $this->hasValue($cells['start_input'] ?? null);
        if (! $startProvided && $isUpdate && $project instanceof Project) {
            $startDate = $project->start_date?->toDateString();
        }

        [$endDate, $endError] = $this->parseDate($cells['end_input'] ?? null);
        if ($endError !== null) {
            $issues[] = ['type' => 'invalid_date', 'message' => 'Ngày kết thúc không hợp lệ (dùng dd/mm/yyyy).'];
        }
        $endProvided = $this->hasValue($cells['end_input'] ?? null);
        if (! $endProvided && $isUpdate && $project instanceof Project) {
            $endDate = $project->end_date?->toDateString();
        }

        if ($startDate && $endDate && $endDate < $startDate) {
            $issues[] = ['type' => 'invalid_date_range', 'message' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.'];
        }

        $execDeptName = $this->normalizeWhitespace((string) ($cells['exec_dept_name'] ?? ''));
        $executingDepartmentIds = $isUpdate && $project instanceof Project
            ? $project->executingDepartments->pluck('id')->all()
            : [];
        if ($executingDepartmentIds === [] && $isUpdate && $project instanceof Project && $project->executing_department_id) {
            $executingDepartmentIds = [$project->executing_department_id];
        }
        $executingDepartmentNames = $isUpdate && $project instanceof Project
            ? $project->executingDepartments->pluck('name')->all()
            : [];
        if ($executingDepartmentNames === [] && $isUpdate && $project instanceof Project) {
            $name = $project->executingDepartment?->name;
            $executingDepartmentNames = $name ? [$name] : [];
        }
        if ($execDeptName !== '') {
            $executingDepartmentIds = [];
            $executingDepartmentNames = [];
            foreach ($this->splitList($execDeptName) as $chunk) {
                $chunk = $this->normalizeWhitespace($chunk);
                if ($chunk === '') {
                    continue;
                }
                $dept = $this->cachedDepartment($chunk, $caches['deptCache']);
                if ($dept === null) {
                    $issues[] = [
                        'type' => 'unknown_department',
                        'message' => "Không tìm thấy phòng ban \"{$chunk}\".",
                    ];
                } else {
                    $executingDepartmentIds[] = $dept->id;
                    $executingDepartmentNames[] = $dept->name;
                }
            }
        }
        $executingDepartmentIds = array_values(array_unique($executingDepartmentIds));
        $executingDepartmentId = $executingDepartmentIds[0] ?? null;
        $executingDepartmentName = $executingDepartmentNames === [] ? null : implode('; ', $executingDepartmentNames);

        $leadInput = trim((string) ($cells['lead_input'] ?? ''));
        $leadEmail = $this->extractEmail($leadInput);
        $leadUserId = $isUpdate && $project instanceof Project ? $project->lead_user_id : null;
        $leadName = $isUpdate && $project instanceof Project ? $project->lead?->name : null;
        if ($leadInput !== '') {
            if ($leadEmail === '') {
                $issues[] = ['type' => 'unknown_user', 'message' => "Email phụ trách chính không hợp lệ \"{$leadInput}\"."];
                $leadUserId = null;
                $leadName = null;
            } else {
                $leadUser = $this->cachedUser($leadEmail, $caches['userCache']);
                if ($leadUser === null) {
                    $issues[] = [
                        'type' => 'unknown_user',
                        'message' => "Không tìm thấy phụ trách chính với email \"{$leadEmail}\".",
                    ];
                    $leadUserId = null;
                    $leadName = null;
                } else {
                    $leadUserId = $leadUser->id;
                    $leadName = $leadUser->name;
                }
            }
        }

        $memberIds = [];
        $memberNames = [];
        $membersProvided = trim((string) ($cells['members_input'] ?? '')) !== '';
        foreach ($this->splitList((string) ($cells['members_input'] ?? '')) as $chunk) {
            $email = $this->extractEmail($chunk);
            if ($email === '') {
                continue;
            }
            $user = $this->cachedUser($email, $caches['userCache']);
            if ($user === null) {
                $issues[] = [
                    'type' => 'unknown_user',
                    'message' => "Không tìm thấy người tham gia với email \"{$email}\".",
                ];
            } else {
                $memberIds[] = $user->id;
                $memberNames[] = $user->name;
            }
        }
        if (! $membersProvided && $isUpdate && $project instanceof Project) {
            $memberIds = $project->members->pluck('id')->all();
            $memberNames = $project->members->pluck('name')->all();
        }

        $followerIds = [];
        $followerNames = [];
        $followersProvided = trim((string) ($cells['followers_input'] ?? '')) !== '';
        foreach ($this->splitList((string) ($cells['followers_input'] ?? '')) as $chunk) {
            $email = $this->extractEmail($chunk);
            if ($email === '') {
                continue;
            }
            $user = $this->cachedUser($email, $caches['userCache']);
            if ($user === null) {
                $issues[] = [
                    'type' => 'unknown_user',
                    'message' => "Không tìm thấy người theo dõi với email \"{$email}\".",
                ];
            } else {
                $followerIds[] = $user->id;
                $followerNames[] = $user->name;
            }
        }
        if (! $followersProvided && $isUpdate && $project instanceof Project) {
            $followerIds = $project->followers->pluck('id')->all();
            $followerNames = $project->followers->pluck('name')->all();
        }

        $labelIds = [];
        $labelNames = [];
        $labelsProvided = trim((string) ($cells['labels_input'] ?? '')) !== '';
        foreach ($this->splitList((string) ($cells['labels_input'] ?? '')) as $labelName) {
            $labelName = $this->normalizeWhitespace($labelName);
            if ($labelName === '') {
                continue;
            }
            $label = $this->cachedLabel($labelName, $caches['labelCache']);
            if ($label === null) {
                $issues[] = [
                    'type' => 'unknown_label',
                    'message' => "Không tìm thấy nhãn \"{$labelName}\". Hãy tạo nhãn này trước.",
                ];
            } else {
                $labelIds[] = $label->id;
                $labelNames[] = $label->name;
            }
        }
        if (! $labelsProvided && $isUpdate && $project instanceof Project) {
            $labelIds = $project->labels->pluck('id')->all();
            $labelNames = $project->labels->pluck('name')->all();
        }

        $isValid = count(array_intersect(
            self::BLOCKING_ISSUE_TYPES,
            array_column($issues, 'type'),
        )) === 0;

        $data = [
            'name' => $name,
            'type' => $type ?? 'other',
            'lead_user_id' => $leadUserId,
            'lead_name' => $leadName,
            'executing_department_id' => $executingDepartmentId,
            'executing_department_ids' => $executingDepartmentIds,
            'executing_department_name' => $executingDepartmentName,
            'member_ids' => array_values(array_unique($memberIds)),
            'member_names' => array_values(array_unique($memberNames)),
            'follower_ids' => array_values(array_unique($followerIds)),
            'follower_names' => array_values(array_unique($followerNames)),
            'label_ids' => array_values(array_unique($labelIds)),
            'label_names' => array_values(array_unique($labelNames)),
            'status' => $status,
            'importance' => $importance,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'progress_method' => $progressMethod,
            'description' => $description !== '' ? $description : null,
        ];

        // Field nào Excel thực sự có giá trị ở dòng này — dùng khi action=update
        // để chỉ ghi đè đúng field có giá trị, ô trống = giữ nguyên (ProjectService::confirmImport()).
        $providedFields = array_keys(array_filter([
            'name' => $rawName !== '',
            'type' => $typeInput !== '',
            'lead_user_id' => $leadInput !== '',
            'executing_department_id' => $execDeptName !== '',
            'executing_department_ids' => $execDeptName !== '',
            'member_ids' => $membersProvided,
            'follower_ids' => $followersProvided,
            'label_ids' => $labelsProvided,
            'status' => $statusInput !== '',
            'importance' => $importanceInput !== '',
            'start_date' => $startProvided,
            'end_date' => $endProvided,
            'progress_method' => $methodInput !== '',
            'description' => $rawDescription !== '',
        ]));

        return [
            'status' => $isValid ? 'valid' : 'invalid',
            'action' => $action,
            'provided_fields' => $providedFields,
            'project_id' => $project?->id,
            'code' => $codeInput !== '' ? $codeInput : $project?->code,
            'data' => $data,
            'issues' => $issues,
        ];
    }

    /** true nếu người xem có quyền quản lý phòng ban của dự án (đúng rule ProjectService::userCanManageDepartment). */
    private function canManageDepartment(User $viewer, Project $project): bool
    {
        if ($viewer->isSuperAdmin() || $this->permissions->allows($viewer, 'project.*')) {
            return true;
        }

        return $this->permissions->allows($viewer, 'project.manage_department', 'department', $project->owner_department_id)
            || $this->permissions->allows($viewer, 'project.update_department', 'department', $project->owner_department_id);
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

    private function cachedDepartment(string $name, array &$cache): mixed
    {
        $key = mb_strtolower($name);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $this->projects->findDepartmentByName($name);
        }

        return $cache[$key];
    }

    private function cachedLabel(string $name, array &$cache): mixed
    {
        $key = mb_strtolower($name);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $this->projects->findLabelByName($name);
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

    /** @return list<string> */
    private function splitList(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        return preg_split('/[;,\n]+/u', $text) ?: [];
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
}
