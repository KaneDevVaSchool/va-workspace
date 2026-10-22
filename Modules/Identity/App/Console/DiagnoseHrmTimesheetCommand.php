<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Modules\Identity\App\Hrm\Services\HrmOutboundHttp;

/**
 * Kiểm tra token chấm công / duyệt bảng chấm (HRM_TIMESHEET_API_*) từ máy chủ workspace.
 */
class DiagnoseHrmTimesheetCommand extends Command
{
    protected $signature = 'identity:hrm-timesheet-diagnose {--employee-uuid= : UUID nhân sự HRM (tuỳ chọn, thử GET punches hôm nay)}';

    protected $description = 'Chẩn đoán HRM_API_BASE_URL / HRM_TIMESHEET_API_TOKEN cho giờ chấm công & duyệt bảng chấm';

    public function handle(): int
    {
        $base = HrmOutboundHttp::baseUrl();
        if ($base === '') {
            $this->error('HRM_API_BASE_URL chưa cấu hình.');

            return self::FAILURE;
        }

        $dedicated = config('services.hrm.timesheet_api_token');
        $token = filled($dedicated) ? (string) $dedicated : (string) config('services.hrm.api_token');
        if ($token === '') {
            $this->error('HRM_TIMESHEET_API_TOKEN và HRM_API_TOKEN đều trống.');

            return self::FAILURE;
        }

        $this->line("Timesheet API base: {$base}");
        if (filled($dedicated)) {
            $this->line('Token: HRM_TIMESHEET_API_TOKEN ('.substr($token, 0, 4).'…, độ dài '.strlen($token).')');
        } else {
            $this->warn('HRM_TIMESHEET_API_TOKEN trống — đang dùng HRM_API_TOKEN (token nghỉ phép riêng không áp dụng).');
            $this->line('Token: HRM_API_TOKEN ('.substr($token, 0, 4).'…, độ dài '.strlen($token).')');
        }
        $this->newLine();

        $uuid = $this->option('employee-uuid');
        $today = now()->toDateString();
        $query = filled($uuid)
            ? ['employee_uuid' => (string) $uuid, 'from' => $today, 'to' => $today]
            : ['employee_uuid' => '00000000-0000-4000-8000-000000000001', 'from' => $today, 'to' => $today];

        $this->probe('GET /api/v1/attendance/punches', $query);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function probe(string $label, array $query): void
    {
        $this->info($label);

        try {
            $response = HrmOutboundHttp::timesheetClient(10)->get('/api/v1/attendance/punches', $query);
        } catch (ConnectionException $e) {
            $this->error("  Không kết nối được: {$e->getMessage()}");
            $this->warn('  → Workspace trả 503 cho /api/attendance/punches* khi gặp lỗi mạng/timeout.');

            return;
        }

        $this->line("  HTTP {$response->status()}");

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? substr((string) $response->body(), 0, 240);
            $code = $response->json('error.code');
            $this->warn("  → {$message}".($code ? " ({$code})" : ''));
            if ($response->status() === 401 || $code === 'UNAUTHENTICATED') {
                $this->warn('  → Cấp HRM_TIMESHEET_API_TOKEN có quyền attendance:punch (khác HRM_LEAVE_API_TOKEN).');
            }
            if ($response->status() === 403 || $code === 'MISSING_ABILITY') {
                $this->warn('  → Token thiếu ability attendance:punch / attendance:approve trên HRM chính.');
            }

            return;
        }

        $rows = $response->json('data');
        $count = is_array($rows) ? count($rows) : 0;
        $this->line("  OK — {$count} bản ghi trong khoảng ngày.");
    }
}
