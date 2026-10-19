<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Modules\Identity\App\Hrm\Services\HrmOutboundHttp;

/**
 * Kiểm tra kết nối catalog nghỉ phép (HRM_LEAVE_API_*) từ máy chủ workspace.
 */
class DiagnoseHrmLeaveCommand extends Command
{
    protected $signature = 'identity:hrm-leave-diagnose {--employee-uuid= : UUID nhân sự HRM (tuỳ chọn, thử GET balances)}';

    protected $description = 'Chẩn đoán HRM_LEAVE_API_BASE_URL / token cho leave-balance & leave-requests';

    public function handle(): int
    {
        $base = HrmOutboundHttp::leaveCatalogBaseUrl();
        if ($base === '') {
            $this->error('HRM_LEAVE_API_BASE_URL và HRM_API_BASE_URL đều trống.');

            return self::FAILURE;
        }

        $token = config('services.hrm.leave_api_token');
        $token = filled($token) ? (string) $token : (string) config('services.hrm.api_token');
        if ($token === '') {
            $this->error('HRM_LEAVE_API_TOKEN / HRM_API_TOKEN chưa cấu hình.');

            return self::FAILURE;
        }

        $this->line("Leave API base: {$base}");
        $this->line('Token: '.substr($token, 0, 4).'… (độ dài '.strlen($token).')');
        $this->newLine();

        $this->probe('GET /api/v1/leave/types');
        $uuid = $this->option('employee-uuid');
        if (filled($uuid)) {
            $this->probe('GET /api/v1/leave/balances', ['employee_uuid' => (string) $uuid, 'year' => (int) now()->format('Y')]);
        } else {
            $this->warn('Bỏ qua GET /api/v1/leave/balances — truyền --employee-uuid=… để thử.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function probe(string $label, array $query = []): void
    {
        $path = str_contains($label, 'types')
            ? '/api/v1/leave/types'
            : '/api/v1/leave/balances';

        $this->info($label);

        try {
            $response = HrmOutboundHttp::leaveCatalogClient(10)->get($path, $query);
        } catch (ConnectionException $e) {
            $this->error("  Không kết nối được: {$e->getMessage()}");
            $this->warn('  → Workspace trả 503 cho /api/attendance/leave-* khi gặp lỗi mạng/timeout.');

            return;
        }

        $this->line("  HTTP {$response->status()}");

        if (! $response->successful()) {
            $body = $response->json('message') ?? substr((string) $response->body(), 0, 200);
            $this->warn("  → {$body}");
            if ($response->status() === 401 || $response->status() === 403) {
                $this->warn('  → Kiểm tra HRM_LEAVE_API_TOKEN (ability leave:read / leave:write).');
            }

            return;
        }

        $this->info('  → OK');
    }
}
