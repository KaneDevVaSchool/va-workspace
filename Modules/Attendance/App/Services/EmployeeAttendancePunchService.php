<?php

namespace Modules\Attendance\App\Services;

use App\Models\User;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmApiClient;

/**
 * Gửi giờ vào/ra của người đang đăng nhập về HRM.
 * Danh tính = hrm_employee_uuid trên tài khoản — không nhận uuid từ trình duyệt.
 * Giờ lấy theo server. Mỗi lần chỉ ghi đè chiều đang chấm, chiều kia giữ nguyên.
 */
class EmployeeAttendancePunchService
{
    public function __construct(
        private readonly HrmApiClient $hrmApi,
    ) {}

    /**
     * Giờ đã chấm hôm nay. Bốn trường null khi HRM chưa có bản ghi.
     *
     * @return array{check_in_time: ?string, check_out_time: ?string, check_in_method: ?string, check_out_method: ?string}
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
     * @param  'in'|'out'  $direction
     * @return array{check_in_time: ?string, check_out_time: ?string, check_in_method: ?string, check_out_method: ?string}
     *
     * @throws HrmApiUnavailable
     */
    public function punch(User $user, string $direction, string $method): array
    {
        $uuid = $this->employeeUuid($user);
        $workDate = now()->toDateString();
        $existing = $this->present($this->hrmApi->getWorkspacePunch($uuid, $workDate));
        $time = now()->format('H:i');

        if ($direction === 'out') {
            $existing['check_out_time'] = $time;
            $existing['check_out_method'] = $method;
        } else {
            $existing['check_in_time'] = $time;
            $existing['check_in_method'] = $method;
        }

        $fields = ['work_date' => $workDate];
        foreach (['check_in_time', 'check_out_time', 'check_in_method', 'check_out_method'] as $key) {
            if ($existing[$key] !== null) {
                $fields[$key] = $existing[$key];
            }
        }

        return $this->present($this->hrmApi->upsertWorkspacePunch($uuid, $fields));
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{check_in_time: ?string, check_out_time: ?string, check_in_method: ?string, check_out_method: ?string}
     */
    private function present(?array $row): array
    {
        return [
            'check_in_time' => $this->clock($row['check_in_time'] ?? null),
            'check_out_time' => $this->clock($row['check_out_time'] ?? null),
            'check_in_method' => $this->text($row['check_in_method'] ?? null),
            'check_out_method' => $this->text($row['check_out_method'] ?? null),
        ];
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
