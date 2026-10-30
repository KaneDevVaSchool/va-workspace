<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Gửi giờ vào/ra của người đang đăng nhập về HRM.
 * Danh tính = hrm_employee_uuid trên tài khoản — không nhận uuid từ trình duyệt.
 * Giờ lấy theo server. Màn nhân viên chỉ gửi vào sáng và ra chiều.
 * API vẫn nhận ra sáng / vào chiều; ô đã lưu thì không ghi đè.
 */
class EmployeeAttendancePunchService
{
    /** @var list<string> */
    private const PUNCH_TIME_KEYS = [
        'check_in_time',
        'morning_check_out_time',
        'afternoon_check_in_time',
        'check_out_time',
    ];

    /** @var list<string> */
    private const PUNCH_METHOD_KEYS = [
        'check_in_method',
        'morning_check_out_method',
        'afternoon_check_in_method',
        'check_out_method',
    ];

    public function __construct(
        private readonly HrmApiClient $hrmApi,
        private readonly EmployeeAttendanceCheckInContextService $checkInContext,
    ) {}

    /**
     * Giờ đã chấm hôm nay. Tám trường null khi HRM chưa có bản ghi.
     *
     * @return array{
     *     check_in_time: ?string,
     *     morning_check_out_time: ?string,
     *     afternoon_check_in_time: ?string,
     *     check_out_time: ?string,
     *     check_in_method: ?string,
     *     morning_check_out_method: ?string,
     *     afternoon_check_in_method: ?string,
     *     check_out_method: ?string
     * }
     *
     * @throws HrmApiUnavailable
     */
    public function today(User $user): array
    {
        return $this->present(
            $this->hrmApi->getWorkspacePunch($this->employeeUuid($user), now()->toDateString()),
        );
    }

    /**
     * @param  'morning'|'afternoon'  $session
     * @param  'in'|'out'  $direction
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}|null  $deviceLocation
     * @return array{
     *     check_in_time: ?string,
     *     morning_check_out_time: ?string,
     *     afternoon_check_in_time: ?string,
     *     check_out_time: ?string,
     *     check_in_method: ?string,
     *     morning_check_out_method: ?string,
     *     afternoon_check_in_method: ?string,
     *     check_out_method: ?string
     * }
     *
     * @throws HrmApiUnavailable
     */
    public function punch(
        User $user,
        string $session,
        string $direction,
        string $method,
        ?array $deviceLocation = null,
        ?string $branchUuid = null,
    ): array {
        $this->assertCheckInLocation($user, $deviceLocation, $branchUuid);

        $uuid = $this->employeeUuid($user);
        $workDate = now()->toDateString();
        $existing = $this->present($this->hrmApi->getWorkspacePunch($uuid, $workDate));
        [$timeKey, $methodKey] = $this->slotKeys($session, $direction);

        if ($existing[$timeKey] !== null) {
            throw HrmApiUnavailable::userFacing('Giờ chấm này đã lưu, không thể thay đổi.');
        }

        $time = now()->format('H:i');
        $existing[$timeKey] = $time;
        $existing[$methodKey] = $method;

        return $this->present($this->hrmApi->upsertWorkspacePunch($uuid, $this->fieldsPayload($workDate, $existing)));
    }

    /**
     * Đẩy lại giờ đã ghi trên máy (khi HRM tạm lỗi) — chỉ điền ô còn trống trên HRM.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     check_in_time: ?string,
     *     morning_check_out_time: ?string,
     *     afternoon_check_in_time: ?string,
     *     check_out_time: ?string,
     *     check_in_method: ?string,
     *     morning_check_out_method: ?string,
     *     afternoon_check_in_method: ?string,
     *     check_out_method: ?string
     * }
     *
     * @throws HrmApiUnavailable
     */
    public function syncToday(User $user, array $payload): array
    {
        $uuid = $this->employeeUuid($user);
        $workDate = now()->toDateString();
        $incoming = $this->present($payload);
        if (! $this->hasAnyTime($incoming)) {
            throw HrmApiUnavailable::userFacing('Chưa có giờ chấm để đồng bộ.');
        }

        $this->assertSyncTimesAllowed($incoming);

        $existing = $this->present($this->hrmApi->getWorkspacePunch($uuid, $workDate));

        foreach (self::PUNCH_TIME_KEYS as $index => $timeKey) {
            $methodKey = self::PUNCH_METHOD_KEYS[$index];
            if ($incoming[$timeKey] === null) {
                continue;
            }
            if ($existing[$timeKey] !== null && $existing[$timeKey] !== $incoming[$timeKey]) {
                throw HrmApiUnavailable::userFacing('Giờ chấm này đã lưu, không thể thay đổi.');
            }
            if ($existing[$timeKey] === null) {
                $existing[$timeKey] = $incoming[$timeKey];
                $existing[$methodKey] = $incoming[$methodKey] ?? 'Chấm bằng nút';
            }
        }

        return $this->present($this->hrmApi->upsertWorkspacePunch($uuid, $this->fieldsPayload($workDate, $existing)));
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{
     *     check_in_time: ?string,
     *     morning_check_out_time: ?string,
     *     afternoon_check_in_time: ?string,
     *     check_out_time: ?string,
     *     check_in_method: ?string,
     *     morning_check_out_method: ?string,
     *     afternoon_check_in_method: ?string,
     *     check_out_method: ?string
     * }
     */
    private function present(?array $row): array
    {
        $out = [];
        foreach (self::PUNCH_TIME_KEYS as $key) {
            $out[$key] = $this->clock($row[$key] ?? null);
        }
        foreach (self::PUNCH_METHOD_KEYS as $key) {
            $out[$key] = $this->text($row[$key] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function fieldsPayload(string $workDate, array $existing): array
    {
        $fields = ['work_date' => $workDate];
        foreach ([...self::PUNCH_TIME_KEYS, ...self::PUNCH_METHOD_KEYS] as $key) {
            if (($existing[$key] ?? null) !== null) {
                $fields[$key] = $existing[$key];
            }
        }

        return $fields;
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws HrmApiUnavailable
     */
    private function slotKeys(string $session, string $direction): array
    {
        return match ($session.':'.$direction) {
            'morning:in' => ['check_in_time', 'check_in_method'],
            'morning:out' => ['morning_check_out_time', 'morning_check_out_method'],
            'afternoon:in' => ['afternoon_check_in_time', 'afternoon_check_in_method'],
            'afternoon:out' => ['check_out_time', 'check_out_method'],
            default => throw HrmApiUnavailable::userFacing('Ca hoặc chiều chấm không hợp lệ.'),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasAnyTime(array $row): bool
    {
        foreach (self::PUNCH_TIME_KEYS as $key) {
            if ($row[$key] !== null) {
                return true;
            }
        }

        return false;
    }

    private function clock(mixed $value): ?string
    {
        $text = trim((string) $value);
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $text, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $times
     *
     * @throws HrmApiUnavailable
     */
    private function assertSyncTimesAllowed(array $times): void
    {
        $now = now()->format('H:i');

        foreach (self::PUNCH_TIME_KEYS as $key) {
            $time = $times[$key] ?? null;
            if ($time !== null && $time > $now) {
                throw HrmApiUnavailable::userFacing('Giờ chấm không được ở tương lai.');
            }
        }

        $ordered = array_values(array_filter([
            $times['check_in_time'] ?? null,
            $times['morning_check_out_time'] ?? null,
            $times['afternoon_check_in_time'] ?? null,
            $times['check_out_time'] ?? null,
        ], fn ($t) => $t !== null));

        for ($i = 1, $iMax = count($ordered); $i < $iMax; $i++) {
            if ($ordered[$i - 1] >= $ordered[$i]) {
                throw HrmApiUnavailable::userFacing('Giờ chấm phải theo thứ tự trong ngày.');
            }
        }
    }

    /**
     * @param  array{latitude: float, longitude: float, accuracy_meters?: float|null}|null  $deviceLocation
     *
     * @throws HrmApiUnavailable
     */
    private function assertCheckInLocation(User $user, ?array $deviceLocation, ?string $branchUuid = null): void
    {
        $context = $this->checkInContext->forUser($user);
        if ($branchUuid !== null && trim($branchUuid) !== '') {
            $context = $this->contextForSelectedBranch($context, trim($branchUuid));
        } elseif ($this->needsBranchChoice($context)) {
            throw HrmApiUnavailable::userFacing('Hồ sơ chưa khớp chi nhánh. Hãy chọn cơ sở đang đứng rồi chấm công.');
        }
        if (! ($context['gps_enforced'] ?? false)) {
            return;
        }

        if ($deviceLocation === null) {
            throw HrmApiUnavailable::userFacing('Cần bật định vị và cho phép truy cập vị trí để chấm công tại cơ sở.');
        }

        $lat = $deviceLocation['latitude'] ?? null;
        $lon = $deviceLocation['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lon)) {
            throw HrmApiUnavailable::userFacing('Chưa xác định được vị trí thiết bị.');
        }

        $accuracy = $deviceLocation['accuracy_meters'] ?? null;
        if (! EmployeeAttendanceGeo::isWithinTolerance(
            $context,
            (float) $lat,
            (float) $lon,
            is_numeric($accuracy) ? (float) $accuracy : null,
        )) {
            $branchName = is_array($context['branch'] ?? null)
                ? ($context['branch']['short_name'] ?? $context['branch']['name'] ?? 'cơ sở')
                : 'cơ sở';
            $tolerance = $context['gps_tolerance_meters'] ?? 100;
            throw HrmApiUnavailable::userFacing(
                "Bạn đang ngoài vùng chấm công của {$branchName} (dung sai {$tolerance} m). Hãy đến đúng cơ sở rồi thử lại.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function contextForSelectedBranch(array $context, string $branchUuid): array
    {
        $branches = $context['branches'] ?? null;
        if (! is_array($branches)) {
            throw HrmApiUnavailable::userFacing('Không tải được danh sách chi nhánh để đổi vị trí chấm công.');
        }

        foreach ($branches as $row) {
            if (! is_array($row) || ($row['uuid'] ?? null) !== $branchUuid) {
                continue;
            }
            $lat = $row['latitude'] ?? null;
            $lon = $row['longitude'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lon)) {
                break;
            }
            $tolerance = $row['gps_tolerance_meters'] ?? 100;
            if (! is_numeric($tolerance) || (int) $tolerance < 1) {
                $tolerance = 100;
            }

            return array_merge($context, [
                'branch' => [
                    'uuid' => $row['uuid'],
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? 'Cơ sở',
                    'short_name' => $row['short_name'] ?? null,
                ],
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
                'gps_tolerance_meters' => (int) $tolerance,
                'gps_enforced' => true,
            ]);
        }

        throw HrmApiUnavailable::userFacing('Chi nhánh chọn không hợp lệ hoặc chưa bật GPS trên HRM.');
    }

    /**
     * Hồ sơ không khớp chi nhánh, nhưng HRM vẫn trả danh sách GPS để chọn.
     *
     * @param  array<string, mixed>  $context
     */
    private function needsBranchChoice(array $context): bool
    {
        $profile = $context['branch'] ?? null;
        if (is_array($profile) && trim((string) ($profile['uuid'] ?? '')) !== '') {
            return false;
        }

        $branches = $context['branches'] ?? null;

        return is_array($branches) && $branches !== [];
    }

    /** @throws HrmApiUnavailable */
    private function employeeUuid(User $user): string
    {
        $uuid = trim((string) $user->hrm_employee_uuid);
        if ($uuid === '') {
            throw HrmApiUnavailable::userFacing('Tài khoản chưa liên kết nhân sự HRM.');
        }

        return $uuid;
    }
}
