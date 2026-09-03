<?php

namespace Modules\Evaluation\App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\App\Models\EvaluationConfigVersion;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Evaluation\App\Models\EvaluationScoreKit;
use Modules\Evaluation\App\Support\PdfWatermark;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Report\App\Models\Report;
use Modules\Report\App\Services\ReportService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảng tổng hợp đánh giá của cả phòng ban trong một kỳ.
 *
 * Đây là màn hình làm việc chính khi chấm điểm cuối kỳ: mỗi nhân sự một dòng,
 * kèm số liệu công việc, điểm theo từng tiêu chí và điểm tổng. Mở rộng một
 * dòng ra thì thấy từng công việc để ghi nhận ngay tại đó.
 *
 * Lớp này chỉ điều phối — toàn bộ phép tính điểm nằm ở
 * EvaluationScoreComputeService, danh sách nhân sự lấy qua repository của
 * Identity. Không truy vấn Eloquent trực tiếp ở đây.
 */
class EvaluationSummaryService
{
    private const TASK_STATUS_LABELS = [
        'not_started' => 'Chưa bắt đầu',
        'in_progress' => 'Đang thực hiện',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];

    private const TIMELINESS_LABELS = [
        'on_time' => 'Đúng hạn',
        'overdue' => 'Quá hạn',
        'unknown' => 'Chưa xác định',
    ];

    private const REPORT_STATUS_LABELS = [
        Report::STATUS_DRAFT => 'Nháp',
        Report::STATUS_SAVED => 'Đã lưu',
    ];

    public function __construct(
        private readonly EvaluationConfigVersionService $versions,
        private readonly EvaluationScoreComputeService $compute,
        private readonly EvaluationEventService $events,
        private readonly UserRepositoryInterface $users,
        private readonly ReportService $reports,
        private readonly PdfWatermark $watermark,
    ) {}

    /**
     * Số liệu cả phòng ban trong kỳ.
     *
     * @return array<string, mixed>
     */
    public function summarize(int $departmentId, string $from, string $to, ?int $reportId = null): array
    {
        $version = $this->versionOrFail($departmentId);

        $people = $this->people($departmentId);

        // $asOf = ngày cuối kỳ: một việc chưa xong được coi là trễ hạn hay
        // không phải xét theo mốc cuối kỳ, không phải theo hôm nay — nếu
        // không thì mở lại cùng một kỳ ở hai thời điểm sẽ ra hai kết quả.
        $result = $this->compute->computeForPeople($people, $version, $from, $to, $to);

        return [
            'rows' => $this->withEmails($result['rows'], $people),
            'summary' => $result['summary'],
            'criteria' => $this->criteriaFor($version, $departmentId),
            'period' => ['from' => $from, 'to' => $to],
            'version_no' => $version->version_no,
            'mode' => $version->kit_snapshot['mode'] ?? null,
            'period_lock' => $this->reports->periodLock($departmentId, $from, $to),
            'report' => $reportId ? $this->reports->displayFor($reportId, $departmentId) : null,
        ];
    }

    /**
     * Xuất PDF form ngang: bảng tổng hợp cả phòng + phiếu chi tiết từng nhân sự.
     *
     * @param  list<int>  $criterionIds  Cột tiêu chí đang hiện trên bảng. Rỗng = theo báo cáo / toàn bộ.
     */
    public function exportPdf(
        int $departmentId,
        string $from,
        string $to,
        ?int $reportId,
        ?User $exportedBy,
        array $criterionIds = [],
    ): Response {
        $payload = $this->summarize($departmentId, $from, $to, $reportId);
        $view = $this->presentPdf($payload, $exportedBy, $criterionIds);

        $filename = 'Danh_gia_nhan_su_'.$from.'_'.$to.'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('evaluation::pdf.summary', $view)
            ->setPaper('a3', 'landscape');

        $this->watermark->stamp($pdf);

        return $pdf->download($filename);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $criterionIds
     * @return array<string, mixed>
     */
    private function presentPdf(array $payload, ?User $exportedBy, array $criterionIds): array
    {
        $report = is_array($payload['report'] ?? null) ? $payload['report'] : null;
        $weighted = ($payload['mode'] ?? null) === EvaluationScoreKit::MODE_WEIGHTED_TASK;
        $columns = $this->pdfColumns($report);
        $criteria = $this->pdfCriteria($payload['criteria'] ?? [], $report, $criterionIds);
        $groups = $this->pdfCriteriaGroups($criteria);

        $rawRows = $payload['rows'] ?? [];
        usort($rawRows, function (array $a, array $b): int {
            $score = $this->visibleFinal($b) <=> $this->visibleFinal($a);
            if ($score !== 0) {
                return $score;
            }

            return strnatcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
        });

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = $this->presentPdfRow($row, $criteria, $weighted);
        }

        $exportedBy?->loadMissing('department');
        $period = $payload['period'] ?? [];

        return [
            'weighted' => $weighted,
            'columns' => $columns,
            'criteria' => $criteria,
            'groups' => $groups,
            'headers' => [
                'work' => $weighted ? 'Hiệu suất việc' : 'Điểm việc',
                'start' => $weighted ? 'Điểm chuẩn' : 'Khởi đầu',
                'task_adj' => $weighted ? 'Hiệu suất (%)' : 'Từ việc',
                'final' => $weighted ? 'Hiệu suất cuối' : 'Cuối',
            ],
            'rows' => $rows,
            'foot' => $this->presentPdfFoot($rows, $criteria, $weighted),
            'people' => array_map(
                fn (array $row) => $this->presentPdfPerson($row, $criteria, $weighted),
                $rawRows,
            ),
            'meta' => [
                'org' => 'VA Schools',
                'title' => 'BÁO CÁO ĐÁNH GIÁ NHÂN SỰ',
                'department' => (string) ($exportedBy?->department?->name ?: ''),
                'period' => $this->dateVi($period['from'] ?? null).' – '.$this->dateVi($period['to'] ?? null),
                'report_title' => (string) ($report['title'] ?? ''),
                'revision' => (string) ($report['revision'] ?? ''),
                'revision_kind' => (($report['kind'] ?? null) === 'appendix') ? 'Phụ lục' : 'Bản gốc',
                'status' => self::REPORT_STATUS_LABELS[$report['status'] ?? ''] ?? '',
                'version_no' => (string) ($payload['version_no'] ?? ''),
                'mode' => $weighted ? 'Hiệu suất theo việc (cách 2)' : 'Điểm gốc ± việc (cách 1)',
                'exported_by' => (string) ($exportedBy?->name ?: 'Hệ thống'),
                'generated_at' => now()->format('d/m/Y H:i'),
                'people_count' => count($rows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, bool>
     */
    private function pdfColumns(?array $report): array
    {
        $keys = is_array($report['column_keys'] ?? null) ? $report['column_keys'] : [];
        $all = ['tasks', 'start_score', 'task_adjustment', 'bonus', 'penalty', 'final_score', 'classification'];
        $out = [];
        foreach ($all as $key) {
            $out[$key] = $keys === [] || in_array($key, $keys, true);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $criteria
     * @param  array<string, mixed>|null  $report
     * @param  list<int>  $criterionIds
     * @return list<array<string, mixed>>
     */
    private function pdfCriteria(array $criteria, ?array $report, array $criterionIds): array
    {
        $allow = $this->intList($criterionIds);
        if ($allow === []) {
            $allow = $this->intList($report['criterion_ids'] ?? []);
        }
        $allowSet = $allow === [] ? null : array_flip($allow);

        $out = [];
        foreach ($criteria as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($allowSet !== null && ! isset($allowSet[$id])) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $criteria
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    private function pdfCriteriaGroups(array $criteria): array
    {
        $map = [];
        foreach ($criteria as $item) {
            $label = trim((string) ($item['criterion_type_name'] ?? '')) ?: 'Tiêu chí khác';
            $map[$label][] = $item;
        }

        $groups = [];
        foreach ($map as $label => $items) {
            $groups[] = ['label' => $label, 'items' => $items];
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function presentPdfRow(array $row, array $criteria, bool $weighted): array
    {
        $counts = $row['task_status_counts'] ?? [];
        $done = (int) ($counts['by_status']['completed'] ?? 0);
        $total = (int) ($counts['total'] ?? 0);
        $overdue = (int) ($counts['by_timeliness']['overdue'] ?? 0);
        $missing = (int) ($row['missing_total'] ?? 0);
        $final = $this->visibleFinal($row);
        $bonus = (float) ($row['bonus'] ?? 0);
        $penalty = (float) ($row['penalty'] ?? 0);

        $cells = [];
        foreach ($criteria as $item) {
            $totalScore = $this->criterionTotal($row, (int) $item['id']);
            $cells[(int) $item['id']] = $totalScore === null
                ? ['text' => '—', 'count' => 0, 'class' => '']
                : [
                    'text' => $this->signed($totalScore['score']),
                    'count' => (int) $totalScore['count'],
                    'class' => $this->toneClass($totalScore['score']),
                ];
        }

        return [
            'name' => (string) ($row['user_name'] ?? ''),
            'email' => (string) ($row['user_email'] ?? ''),
            'tasks' => $done.'/'.$total,
            'overdue' => $overdue,
            'missing' => $missing,
            'start' => $this->number($this->taskBasis($row, $weighted)),
            'task_adj' => $weighted
                ? $this->percent($row['task_adjustment'] ?? 0)
                : $this->signed($row['task_adjustment'] ?? 0),
            'task_adj_class' => $weighted
                ? $this->percentClass($row['task_adjustment'] ?? 0)
                : $this->toneClass($row['task_adjustment'] ?? 0),
            'criteria' => $cells,
            'bonus' => $this->signed($bonus),
            'bonus_class' => $this->toneClass($bonus),
            'penalty' => $penalty ? '-'.$this->number($penalty) : '0',
            'penalty_class' => $penalty ? 'minus' : '',
            'final' => $weighted ? $this->percent($final) : $this->number($final),
            'klass' => (string) ($row['classification_label'] ?? '') ?: '—',
            'final_raw' => $final,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function presentPdfFoot(array $rows, array $criteria, bool $weighted): array
    {
        $n = count($rows);
        if ($n === 0) {
            return [];
        }

        $avg = fn (callable $get) => array_sum(array_map($get, $rows)) / $n;
        $sum = fn (callable $get) => array_sum(array_map($get, $rows));

        $cells = [];
        foreach ($criteria as $item) {
            $id = (int) $item['id'];
            $value = $avg(function (array $row) use ($id) {
                $cell = $row['criteria'][$id] ?? null;
                if (! is_array($cell) || ($cell['text'] ?? '') === '—') {
                    return 0.0;
                }

                return $this->parseSigned((string) $cell['text']);
            });
            $cells[$id] = [
                'text' => $this->signed($value),
                'class' => $this->toneClass($value),
            ];
        }

        $taskAdj = $avg(fn (array $row) => $this->parseSigned((string) ($row['task_adj'] ?? '0')));
        $bonus = $avg(fn (array $row) => $this->parseSigned((string) ($row['bonus'] ?? '0')));
        $penalty = $avg(fn (array $row) => $this->parseSigned((string) ($row['penalty'] ?? '0')));
        $final = $avg(fn (array $row) => (float) ($row['final_raw'] ?? 0));

        return [
            'tasks' => $this->number($sum(fn (array $row) => $this->parseFractionNum((string) ($row['tasks'] ?? '0/0')))).'/'
                .$this->number($sum(fn (array $row) => $this->parseFractionDen((string) ($row['tasks'] ?? '0/0')))),
            'start' => $this->number($avg(fn (array $row) => $this->parseSigned((string) ($row['start'] ?? '0')))),
            'task_adj' => $weighted ? $this->percent($taskAdj) : $this->signed($taskAdj),
            'task_adj_class' => $weighted ? $this->percentClass($taskAdj) : $this->toneClass($taskAdj),
            'criteria' => $cells,
            'bonus' => $this->signed($bonus),
            'bonus_class' => $this->toneClass($bonus),
            'penalty' => $this->signed($penalty),
            'penalty_class' => $this->toneClass($penalty),
            'final' => $weighted ? $this->percent($final) : $this->number($final),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    private function presentPdfPerson(array $row, array $criteria, bool $weighted): array
    {
        $sheet = $this->presentPdfRow($row, $criteria, $weighted);
        $counts = $row['task_status_counts'] ?? [];
        $tasks = [];
        foreach ($row['task_breakdown'] ?? [] as $task) {
            $tasks[] = [
                'title' => (string) ($task['title'] ?? ''),
                'project' => (string) ($task['project_name'] ?? ''),
                'status' => self::TASK_STATUS_LABELS[$task['status'] ?? ''] ?? (string) ($task['status'] ?? ''),
                'timeliness' => self::TIMELINESS_LABELS[$task['on_time_state'] ?? ''] ?? 'Chưa xác định',
                'overdue' => ($task['on_time_state'] ?? '') === 'overdue',
                'end' => $this->dateVi($task['end_date'] ?? null, 'chưa đặt'),
                'actual' => $this->dateVi($task['actual_end_date'] ?? null, 'chưa xong'),
                'difficulty' => $this->factor($task['difficulty_factor'] ?? null),
                'progress' => $this->factor($task['progress_factor'] ?? null),
                'quality' => $this->factor($task['quality_factor'] ?? null),
                'standard' => $this->number($task['standard_score'] ?? 0),
                'actual_score' => $this->number($task['actual_score'] ?? 0),
                'contribution' => $this->signed($task['contribution'] ?? 0),
                'note' => $this->taskZeroReason($task),
                'gap' => $this->taskZeroReason($task) !== '',
            ];
        }

        $taskTitleById = [];
        foreach ($row['task_breakdown'] ?? [] as $task) {
            $taskTitleById[(int) ($task['task_id'] ?? 0)] = (string) ($task['title'] ?? '');
        }

        $events = [];
        foreach ($row['event_breakdown'] ?? [] as $event) {
            $score = (float) ($event['score'] ?? 0);
            $events[] = [
                'date' => $this->dateVi($event['occurred_at'] ?? null),
                'criterion' => (string) ($event['criterion_name'] ?? ''),
                'group' => (string) ($event['criterion_type_name'] ?? ''),
                'level' => (string) ($event['level_label'] ?? ''),
                'score' => $this->signed($score),
                'class' => $this->toneClass($score),
                'task' => $taskTitleById[(int) ($event['task_id'] ?? 0)] ?? '—',
                'recorder' => (string) ($event['recorded_by_name'] ?? '') ?: '—',
                'reason' => (string) ($event['reason'] ?? '') ?: '—',
            ];
        }

        $criterionLines = [];
        foreach ($criteria as $item) {
            $cell = $sheet['criteria'][(int) $item['id']] ?? ['text' => '—', 'count' => 0, 'class' => ''];
            $levels = [];
            foreach ($item['levels'] ?? [] as $level) {
                $levels[] = trim((string) ($level['code'] ?? '')).' '
                    .trim((string) ($level['label'] ?? '')).' ('
                    .$this->signed($level['score'] ?? 0).')';
            }
            $criterionLines[] = [
                'group' => trim((string) ($item['criterion_type_name'] ?? '')) ?: 'Tiêu chí khác',
                'name' => (string) ($item['name'] ?? ''),
                'score' => $cell['text'],
                'count' => (int) ($cell['count'] ?? 0),
                'class' => $cell['class'] ?? '',
                'levels' => implode(' · ', $levels),
            ];
        }

        return [
            'sheet' => $sheet,
            'kpis' => [
                ['label' => $weighted ? 'Hiệu suất cuối' : 'Điểm cuối', 'value' => $sheet['final']],
                ['label' => 'Xếp loại', 'value' => $sheet['klass']],
                ['label' => 'Việc hoàn thành', 'value' => $sheet['tasks']],
                ['label' => $weighted ? 'Điểm chuẩn' : 'Điểm khởi đầu', 'value' => $sheet['start']],
                ['label' => $weighted ? 'Hiệu suất việc' : 'Từ công việc', 'value' => $sheet['task_adj']],
                ['label' => 'Điểm cộng / trừ', 'value' => $sheet['bonus'].' / '.$sheet['penalty']],
                ['label' => 'Trễ hạn', 'value' => (string) $sheet['overdue']],
                ['label' => 'Thiếu dữ liệu', 'value' => (string) $sheet['missing']],
                ['label' => 'Số ghi nhận', 'value' => (string) count($events)],
            ],
            'criteria' => $criterionLines,
            'tasks' => $tasks,
            'events' => $events,
            'task_counts' => [
                'completed' => (int) ($counts['by_status']['completed'] ?? 0),
                'in_progress' => (int) ($counts['by_status']['in_progress'] ?? 0),
                'not_started' => (int) ($counts['by_status']['not_started'] ?? 0),
                'on_hold' => (int) ($counts['by_status']['on_hold'] ?? 0),
                'cancelled' => (int) ($counts['by_status']['cancelled'] ?? 0),
                'on_time' => (int) ($counts['by_timeliness']['on_time'] ?? 0),
                'overdue' => (int) ($counts['by_timeliness']['overdue'] ?? 0),
            ],
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function visibleFinal(array $row): float
    {
        return (float) ($row['final_score'] ?? 0);
    }

    /** @param  array<string, mixed>  $row */
    private function taskBasis(array $row, bool $weighted): float
    {
        if (! $weighted) {
            return (float) ($row['start_score'] ?? 0);
        }

        $sum = 0.0;
        foreach ($row['task_breakdown'] ?? [] as $task) {
            $sum += (float) ($task['standard_score'] ?? 0);
        }

        return round($sum, 2);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{criterion_id: int, score: float, count: int}|null
     */
    private function criterionTotal(array $row, int $criterionId): ?array
    {
        foreach ($row['criterion_totals'] ?? [] as $item) {
            if ((int) ($item['criterion_id'] ?? 0) === $criterionId) {
                return $item;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $task */
    private function taskZeroReason(array $task): string
    {
        if (($task['zeroed_reason'] ?? null) === 'incomplete') {
            return 'Chưa hoàn thành · điểm thực 0';
        }
        if (($task['zeroed_reason'] ?? null) === 'missing_data') {
            $map = [
                'difficulty' => 'thiếu độ khó',
                'progress' => 'thiếu hạn/ngày xong',
                'quality' => 'chưa chấm chất lượng',
            ];
            $bits = [];
            foreach ($task['missing_fields'] ?? [] as $key) {
                $bits[] = $map[$key] ?? (string) $key;
            }

            return $bits !== [] ? implode(', ', $bits) : 'Thiếu dữ liệu · điểm thực 0';
        }

        return '';
    }

    /** @param  list<mixed>  $values */
    private function intList(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    private function number(mixed $value): string
    {
        $num = round((float) $value, 2);
        if (abs($num - round($num)) < 0.00001) {
            return (string) (int) round($num);
        }

        return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
    }

    private function signed(mixed $value): string
    {
        $num = (float) $value;
        if (abs($num) < 0.00001) {
            return '0';
        }

        return $num < 0 ? '−'.$this->number(abs($num)) : $this->number($num);
    }

    private function percent(mixed $value): string
    {
        return $this->number($value).'%';
    }

    private function factor(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return '×'.$this->number($value);
    }

    private function toneClass(mixed $value): string
    {
        $num = (float) $value;
        if ($num > 0) {
            return 'plus';
        }
        if ($num < 0) {
            return 'minus';
        }

        return '';
    }

    private function percentClass(mixed $value): string
    {
        $num = (float) $value;
        if ($num >= 100) {
            return 'plus';
        }
        if ($num <= 0) {
            return 'minus';
        }

        return '';
    }

    private function parseSigned(string $text): float
    {
        $clean = str_replace(['%', '×', '−', '–', ','], ['', '', '-', '-', ''], $text);
        if ($clean === '—' || $clean === '') {
            return 0.0;
        }

        return (float) $clean;
    }

    private function parseFractionNum(string $text): float
    {
        $parts = explode('/', $text, 2);

        return (float) ($parts[0] ?? 0);
    }

    private function parseFractionDen(string $text): float
    {
        $parts = explode('/', $text, 2);

        return (float) ($parts[1] ?? 0);
    }

    private function dateVi(mixed $value, string $empty = '—'): string
    {
        $raw = is_string($value) ? substr($value, 0, 10) : '';
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $empty;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);

        return $dt ? $dt->format('d/m/Y') : $empty;
    }

    /**
     * Tính lại đúng một dòng — dùng ngay sau khi ghi nhận / duyệt / xoá một
     * đánh giá, để giao diện thay dòng đó bằng số liệu máy chủ vừa tính thay
     * vì tự cộng trừ lấy (dễ lệch) hoặc tải lại cả bảng (chậm).
     *
     * @return array<string, mixed>|null
     */
    public function rowFor(int $departmentId, int $userId, string $from, string $to): ?array
    {
        $version = $this->versions->activeForDepartment($departmentId);

        if ($version === null) {
            return null;
        }

        $person = collect($this->people($departmentId))
            ->firstWhere('id', $userId);

        if ($person === null) {
            return null;
        }

        $row = $this->compute->computeForUser(
            $userId,
            (string) $person['name'],
            $version,
            $from,
            $to,
            $to,
        );
        $row['user_email'] = (string) ($person['email'] ?? '');

        return $row;
    }

    /**
     * Tiêu chí dùng làm cột trên bảng.
     *
     * Lấy từ bản chụp của phiên bản đang áp dụng, KHÔNG lấy cấu hình sống:
     * điểm đang được tính theo bản chụp, nên nếu cột hiển thị theo cấu hình
     * hiện tại thì người dùng sẽ thấy một mức điểm khác với mức máy đang cộng.
     *
     * Bản chụp cũ (chốt trước khi có criteria_snapshot) thì đành lấy danh mục
     * sống — vẫn hơn là bảng trống không cột nào.
     *
     * @return list<array<string, mixed>>
     */
    private function criteriaFor(EvaluationConfigVersion $version, int $departmentId): array
    {
        $snapshot = $version->criteria_snapshot ?? [];

        if (! is_array($snapshot) || $snapshot === []) {
            return $this->events->behaviorCatalog($departmentId);
        }

        $out = [];
        foreach ($snapshot as $criterion) {
            if (! is_array($criterion) || ($criterion['type'] ?? null) !== 'behavior') {
                continue;
            }

            $out[] = [
                'id' => (int) ($criterion['id'] ?? 0),
                'name' => (string) ($criterion['name'] ?? ''),
                'criterion_type_id' => $criterion['criterion_type_id'] ?? null,
                // Bản chụp lưu nhóm tiêu chí ở dạng object lồng
                // (EvaluationCriteriaService::present), còn giao diện chỉ cần
                // tên — lấy phẳng ra cho khớp behaviorCatalog().
                'criterion_type_name' => $criterion['criterion_type']['name'] ?? null,
                'levels' => $this->snapshotLevels($criterion),
            ];
        }

        return $out;
    }

    /**
     * Thang mức của một tiêu chí trong bản chụp, chuẩn hoá giống
     * EvaluationEventService::behaviorCatalog() để giao diện dùng chung một
     * dạng dữ liệu dù nguồn là bản chụp hay danh mục sống.
     *
     * @param  array<string, mixed>  $criterion
     * @return list<array{code: string, label: string, score: float}>
     */
    private function snapshotLevels(array $criterion): array
    {
        $levels = $criterion['levels'] ?? [];
        $out = [];

        foreach (array_values(is_array($levels) ? $levels : []) as $index => $level) {
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

    /**
     * Nhân sự đang hoạt động của phòng ban.
     *
     * @return list<array{id: int, name: string, email: string}>
     */
    private function people(int $departmentId): array
    {
        return $this->users
            ->allActiveByDepartment($departmentId)
            ->map(fn ($user) => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Gắn email vào từng dòng tổng hợp — compute service không cần biết email.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{id: int, name: string, email: string}>  $people
     * @return list<array<string, mixed>>
     */
    private function withEmails(array $rows, array $people): array
    {
        $emailById = [];
        foreach ($people as $person) {
            $emailById[(int) $person['id']] = (string) ($person['email'] ?? '');
        }

        foreach ($rows as &$row) {
            $row['user_email'] = $emailById[(int) ($row['user_id'] ?? 0)] ?? '';
        }
        unset($row);

        return $rows;
    }

    private function versionOrFail(int $departmentId): EvaluationConfigVersion
    {
        $version = $this->versions->activeForDepartment($departmentId);

        if ($version === null) {
            throw ValidationException::withMessages([
                'version' => 'Phòng ban chưa chốt khung chấm điểm nào nên chưa tổng hợp được. '
                    .'Vào Khung chấm điểm để chốt phiên bản đầu tiên.',
            ]);
        }

        return $version;
    }
}
