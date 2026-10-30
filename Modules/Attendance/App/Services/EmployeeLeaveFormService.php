<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Đơn nghỉ phép đã duyệt — mẫu BM.01/HN.QT.02, mã NP-YYMM-id.
 */
class EmployeeLeaveFormService
{
    public const ORG_NAME = 'HỆ THỐNG TRƯỜNG VIỆT MỸ';

    public const FORM_SYMBOL = 'BM.01/HN.QT.02';

    public const EFFECTIVE_DATE = '01/07/2025';

    public const REVISION = '01';

    public function __construct(
        private readonly EmployeeLeaveSubmissionService $submissions,
        private readonly HrmEmployeeDirectory $directory,
    ) {}

    public function downloadForUser(User $user, string $uuid): Response
    {
        $request = $this->findOwnedRequest($user, $uuid);
        if ($request === null) {
            throw new NotFoundHttpException('Không tìm thấy đơn nghỉ phép.');
        }

        if (($request['status'] ?? '') !== 'approved') {
            throw new HttpException(422, 'Chỉ xuất được đơn đã được duyệt.');
        }

        $profile = $this->profileFor($user);
        $data = $this->formData($request, $profile);
        $logo = module_path('Attendance', 'resources/images/va-form-logo.png');
        $data['logoPath'] = is_file($logo) ? str_replace('\\', '/', $logo) : null;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('attendance::pdf.leave-form', $data)
            ->setPaper('a4', 'portrait');

        $filename = $data['documentCode'].' - Đơn nghỉ phép.pdf';
        $fallback = $data['documentCode'].' - Don nghi phep.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($filename),
        ]);
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function formData(array $request, array $profile = []): array
    {
        $dateFrom = $this->dateString($request['date_from'] ?? null);
        $dateTo = $this->dateString($request['date_to'] ?? null) ?: $dateFrom;
        $code = strtoupper(trim((string) ($request['leave_type_code'] ?? '')));
        $typeName = trim((string) ($request['leave_type_name'] ?? ''));
        if ($typeName === '') {
            $typeName = 'Nghỉ phép';
        }

        $days = (float) ($request['total_days'] ?? 0);
        $bucket = $this->bucketKey($code, isset($request['balance_quota']) ? (string) $request['balance_quota'] : null);
        $buckets = [
            ['key' => 'annual', 'label' => 'Phép năm', 'checked' => $bucket === 'annual', 'days' => $this->formatDays($bucket === 'annual' ? $days : 0)],
            ['key' => 'unpaid', 'label' => 'Không hưởng lương', 'checked' => $bucket === 'unpaid', 'days' => $this->formatDays($bucket === 'unpaid' ? $days : 0)],
            ['key' => 'regime', 'label' => 'Phép chế độ', 'checked' => $bucket === 'regime', 'days' => $this->formatDays($bucket === 'regime' ? $days : 0)],
        ];

        $sort = $request['sort_order'] ?? null;
        $typeLabel = $code !== '' ? $typeName.' ('.$code.')' : $typeName;
        if (is_numeric($sort) && (int) $sort > 0) {
            $typeLabel = ((int) $sort).'. '.$typeLabel;
        }

        $approver = is_array($request['approver'] ?? null) ? $request['approver'] : [];
        $approverName = trim((string) ($approver['name'] ?? $approver['full_name'] ?? ''));
        $approverTitle = trim((string) ($approver['job_title'] ?? ''));
        if ($approverTitle === '') {
            $approverTitle = 'Trưởng đơn vị';
        }

        $unit = $this->firstFilled(
            $profile['department_name'] ?? null,
            $profile['profile_department_name'] ?? null,
            $profile['org_unit_name'] ?? null,
            $request['department_name'] ?? null,
        );

        $hired = $this->firstFilled($profile['hired_at'] ?? null, $profile['actual_start_date'] ?? null, $request['hired_at'] ?? null);

        return [
            'orgName' => self::ORG_NAME,
            'formSymbol' => self::FORM_SYMBOL,
            'effectiveDate' => self::EFFECTIVE_DATE,
            'revision' => self::REVISION,
            'documentCode' => $this->documentCode(
                isset($request['id']) && is_numeric($request['id']) ? (int) $request['id'] : null,
                $dateFrom ?: $this->dateString($request['submitted_at'] ?? $request['created_at'] ?? null),
            ),
            'unitName' => $unit !== '' ? $unit : '………………',
            'recipientTitle' => $approverTitle,
            'recipientName' => $approverName,
            'employeeName' => $this->firstFilled($profile['full_name'] ?? null, $request['employee_name'] ?? null) ?: '………………',
            'employeeCode' => $this->firstFilled($profile['code'] ?? null, $request['employee_code'] ?? null) ?: '………………',
            'jobTitle' => $this->firstFilled($profile['job_title'] ?? null, $request['job_title'] ?? null) ?: '………………',
            'workplace' => $unit !== '' ? $unit : '………………',
            'hiredAt' => $this->formatDate($hired),
            'officialAt' => $this->formatDate($request['official_date'] ?? $profile['official_date'] ?? null),
            'dateFromDisplay' => $this->formatDate($dateFrom),
            'dateToDisplay' => $this->formatDate($dateTo),
            'modeLabel' => (string) ($request['mode'] ?? 'day') === 'hour' ? 'Theo giờ' : 'Theo ngày',
            'sessionLabel' => $this->sessionLabel($request),
            'hourLabel' => $this->hourWindow($request),
            'totalDaysLabel' => $this->formatDays($days),
            'periodLabel' => 'từ '.$this->formatDate($dateFrom).' đến '.$this->formatDate($dateTo),
            'detailLabel' => $this->detailLabel($request, $dateFrom, $dateTo),
            'leaveTypeLabel' => $typeLabel,
            'buckets' => $buckets,
            'reason' => trim((string) ($request['reason'] ?? '')) ?: '………………',
            'approvalNote' => trim((string) ($request['approval_note'] ?? '')) ?: null,
            'employeeSignDate' => $this->signatureDate($request['submitted_at'] ?? $request['created_at'] ?? null),
            'approverSignDate' => $this->signatureDate($request['decided_at'] ?? null),
            'approverName' => $approverName !== '' ? $approverName : '………………',
        ];
    }

    public function documentCode(?int $id, ?string $date): string
    {
        $stamp = '0000';
        if (filled($date)) {
            try {
                $stamp = Carbon::parse($date)->format('ym');
            } catch (Throwable) {
                $stamp = '0000';
            }
        }

        if ($id === null || $id < 1) {
            return 'NP-'.$stamp;
        }

        return sprintf('NP-%s-%04d', $stamp, $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOwnedRequest(User $user, string $uuid): ?array
    {
        $fromDatabase = $this->requestFromDatabase($user, $uuid);
        if ($fromDatabase !== null) {
            return $fromDatabase;
        }

        $fromApi = $this->requestFromApi($user, $uuid);
        if ($fromApi === null) {
            return null;
        }

        // API đã chứng minh đơn thuộc người này. UUID portal có thể khác
        // hrm_employee_uuid, nên đọc lại theo uuid đơn để lấy mã NP-YYMM-id.
        $enriched = $this->requestByUuid($uuid);
        if ($enriched !== null) {
            return $enriched;
        }

        $employeeUuid = trim((string) ($fromApi['employee_uuid'] ?? $user->hrm_employee_uuid ?? ''));
        $fromApi['official_date'] = $this->officialDate($employeeUuid);

        return $fromApi;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestFromApi(User $user, string $uuid): ?array
    {
        if (trim((string) $user->hrm_employee_uuid) === '' && trim((string) $user->email) === '') {
            return null;
        }

        foreach ($this->submissions->recentForUser($user, 50) as $row) {
            if (is_array($row) && (string) ($row['uuid'] ?? '') === $uuid) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestFromDatabase(User $user, string $uuid): ?array
    {
        $employeeUuid = trim((string) $user->hrm_employee_uuid);
        if ($employeeUuid === '' || ! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        return $this->requestByUuid($uuid, $employeeUuid);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestByUuid(string $uuid, ?string $employeeUuid = null): ?array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        try {
            return $this->queryLeaveRow($employeeUuid, $uuid, true);
        } catch (Throwable $e) {
            try {
                return $this->queryLeaveRow($employeeUuid, $uuid, false);
            } catch (Throwable) {
                Log::warning('attendance.leave_form.hrm_lookup_failed', [
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function queryLeaveRow(?string $employeeUuid, string $uuid, bool $extended): ?array
    {
        $select = [
            'lr.id',
            'lr.mode',
            'lr.date_from',
            'lr.date_to',
            'lr.session',
            'lr.time_from',
            'lr.time_to',
            'lr.total_days',
            'lr.reason',
            'lr.status',
            'lr.created_at',
            'lr.decided_at',
            'e.full_name as employee_name',
            'e.code as employee_code',
            'e.hired_at',
            'e.job_title_name as job_title',
            'e.department_name',
            'ap.full_name as approver_name',
            'ap.job_title_name as approver_job_title',
            'lt.code as leave_type_code',
            'lt.name as leave_type_name',
        ];

        if ($extended) {
            $select[] = 'e.official_date';
            $select[] = 'lt.sort_order';
            $select[] = 'lt.balance_quota';
        }

        $query = DB::connection('hrm')
            ->table('leave_requests as lr')
            ->join('employees as e', 'e.id', '=', 'lr.employee_id')
            ->leftJoin('employees as ap', 'ap.id', '=', 'lr.approver_employee_id')
            ->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->where('lr.uuid', $uuid)
            ->whereNull('lr.deleted_at');

        if (filled($employeeUuid)) {
            $query->where('e.uuid', $employeeUuid);
        }

        $row = $query->first($select);

        if ($row === null) {
            return null;
        }

        $approverName = trim((string) $this->rowValue($row, 'approver_name'));

        return [
            'id' => (int) $row->id,
            'uuid' => $uuid,
            'status' => (string) ($this->rowValue($row, 'status') ?? ''),
            'mode' => (string) ($this->rowValue($row, 'mode') ?? 'day'),
            'date_from' => $this->rowValue($row, 'date_from'),
            'date_to' => $this->rowValue($row, 'date_to'),
            'session' => $this->rowValue($row, 'session'),
            'time_from' => $this->rowValue($row, 'time_from'),
            'time_to' => $this->rowValue($row, 'time_to'),
            'total_days' => $this->rowValue($row, 'total_days'),
            'reason' => $this->rowValue($row, 'reason'),
            'employee_name' => $this->rowValue($row, 'employee_name'),
            'employee_code' => $this->rowValue($row, 'employee_code'),
            'job_title' => $this->rowValue($row, 'job_title'),
            'department_name' => $this->rowValue($row, 'department_name'),
            'hired_at' => $this->rowValue($row, 'hired_at'),
            'official_date' => $this->rowValue($row, 'official_date'),
            'leave_type_code' => $this->rowValue($row, 'leave_type_code'),
            'leave_type_name' => $this->rowValue($row, 'leave_type_name'),
            'sort_order' => $this->rowValue($row, 'sort_order'),
            'balance_quota' => $this->rowValue($row, 'balance_quota'),
            'submitted_at' => $this->rowValue($row, 'created_at'),
            'created_at' => $this->rowValue($row, 'created_at'),
            'decided_at' => $this->rowValue($row, 'decided_at'),
            'approver' => $approverName === '' ? null : [
                'name' => $approverName,
                'job_title' => filled($this->rowValue($row, 'approver_job_title'))
                    ? (string) $this->rowValue($row, 'approver_job_title')
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profileFor(User $user): array
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            return [];
        }

        try {
            $uuid = trim((string) $user->hrm_employee_uuid);

            return $this->directory->findEmployeeForUser(
                $uuid !== '' ? $uuid : null,
                trim((string) $user->email) !== '' ? (string) $user->email : null,
            ) ?? [];
        } catch (HrmDatabaseUnavailable $e) {
            Log::warning('attendance.leave_form.profile_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    private function officialDate(string $employeeUuid): ?string
    {
        if ($employeeUuid === '' || ! HrmEmployeeDirectory::isConfigured()) {
            return null;
        }

        try {
            $value = DB::connection('hrm')
                ->table('employees')
                ->where('uuid', $employeeUuid)
                ->value('official_date');

            return filled($value) ? (string) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function bucketKey(string $code, ?string $quota): string
    {
        $code = strtoupper(trim($code));
        $quota = strtolower(trim((string) $quota));

        if ($code === 'PN' || $quota === 'annual') {
            return 'annual';
        }

        if ($code === 'KL') {
            return 'unpaid';
        }

        return 'regime';
    }

    private function detailLabel(array $request, string $dateFrom, string $dateTo): string
    {
        $from = $this->formatDate($dateFrom);
        $to = $this->formatDate($dateTo);
        $span = $from === $to || $to === '………………' ? $from : $from.' – '.$to;
        $hours = $this->hourWindow($request);

        return $hours !== '' ? $span.' ('.$hours.')' : $span;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    /**
     * @param  array<string, mixed>  $request
     */
    private function sessionLabel(array $request): string
    {
        return match ((string) ($request['session'] ?? '')) {
            'morning' => 'Ca sáng',
            'afternoon' => 'Ca chiều',
            default => (string) ($request['mode'] ?? 'day') === 'hour' ? 'Theo khung giờ' : 'Cả ngày',
        };
    }

    /**
     * @param  array<string, mixed>  $request
     */
    private function hourWindow(array $request): string
    {
        $mode = (string) ($request['mode'] ?? 'day');
        $from = $this->clock($request['time_from'] ?? null);
        $to = $this->clock($request['time_to'] ?? null);
        if ($mode === 'hour' && $from !== '' && $to !== '') {
            return $from.'-'.$to;
        }

        return match ((string) ($request['session'] ?? '')) {
            'morning' => '07:30-12:00',
            'afternoon' => '13:30-17:00',
            default => '07:30-17:00',
        };
    }

    private function clock(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches) !== 1) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    private function formatDays(float $days): string
    {
        return number_format($days, 2, '.', '');
    }

    private function formatDate(mixed $value): string
    {
        $raw = $this->dateString($value);
        if ($raw === '') {
            return '………………';
        }

        try {
            return Carbon::parse($raw)->timezone(config('app.timezone'))->format('d/m/Y');
        } catch (Throwable) {
            return '………………';
        }
    }

    private function signatureDate(mixed $value): string
    {
        $raw = $this->dateString($value);
        if ($raw === '') {
            return 'Ngày ..../..../......';
        }

        try {
            $date = Carbon::parse($raw)->timezone(config('app.timezone'));

            return sprintf('Ngày %02d tháng %02d năm %d', $date->day, $date->month, $date->year);
        } catch (Throwable) {
            return 'Ngày ..../..../......';
        }
    }

    private function dateString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private function rowValue(object $row, string $key): mixed
    {
        return property_exists($row, $key) ? $row->{$key} : null;
    }

    private function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
