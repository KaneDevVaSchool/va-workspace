<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Exceptions\HrmTokenInvalid;
use Modules\Identity\App\Hrm\Services\HrmApiClient;
use Modules\Identity\App\Hrm\Services\HrmOutboundHttp;

/**
 * Kiểm tra nhanh JWKS + verify-token từ máy chủ workspace (cùng TLS/env
 * với luồng đăng nhập SSO thật).
 */
class DiagnoseHrmSsoCommand extends Command
{
    protected $signature = 'identity:hrm-sso-diagnose';

    protected $description = 'Chẩn đoán kết nối JWKS và verify-token VA-HRM cho SSO';

    public function handle(HrmApiClient $hrmApi): int
    {
        $base = HrmOutboundHttp::baseUrl();
        if ($base === '') {
            $this->error('HRM_API_BASE_URL chưa cấu hình.');

            return self::FAILURE;
        }

        $this->line("HRM base: {$base}");
        $this->probeJwks();
        $this->probeVerifyToken($hrmApi);

        return self::SUCCESS;
    }

    private function probeJwks(): void
    {
        $path = (string) config('services.hrm.jwks_path', '/.well-known/jwks.json');
        $this->newLine();
        $this->info("JWKS GET {$path}");

        try {
            $response = HrmOutboundHttp::client(timeoutSeconds: 5)->get($path);
        } catch (ConnectionException $e) {
            $this->error("  Không kết nối được: {$e->getMessage()}");
            $this->warn('  → Workspace sẽ fallback verify-token nếu có HRM_API_TOKEN.');

            return;
        }

        $this->line("  HTTP {$response->status()}");

        if (! $response->successful()) {
            $this->warn('  → JWKS không dùng được; cần verify-token hoặc sửa HRM_JWKS_PATH.');

            return;
        }

        $keys = $response->json('keys');
        $count = is_array($keys) ? count($keys) : 0;
        $this->line("  keys: {$count}");

        if ($count === 0) {
            $this->warn('  → JWKS trả 200 nhưng không có khoá — SSO offline verify sẽ lỗi (kid).');
        } else {
            $this->info('  → JWKS OK (ưu tiên verify offline, không cần verify-token khi login).');
        }
    }

    private function probeVerifyToken(HrmApiClient $hrmApi): void
    {
        $this->newLine();
        $this->info('POST /api/v1/auth/verify-token (token thử — kỳ vọng 401/422, không phải 403 ability)');

        if (! filled(config('services.hrm.api_token'))) {
            $this->warn('  HRM_API_TOKEN trống — không gọi được fallback verify-token.');

            return;
        }

        try {
            $hrmApi->verifySsoToken('eyJhbGciOiJub25lIn0.eyJzdWIiOiJkaWFnbm9zZSJ9.');
        } catch (HrmTokenInvalid $e) {
            $this->info("  ApiClient gọi được endpoint: {$e->getMessage()}");

            return;
        } catch (HrmApiUnavailable $e) {
            $this->error("  {$e->getMessage()}");
            $this->warn('  → Nếu vừa cấp ability trên HRM: tạo lại Bearer token ApiClient và cập nhật HRM_API_TOKEN.');
            $this->warn('  → Hoặc bật JWKS đúng path (php artisan identity:hrm-warm-jwks).');

            return;
        }

        $this->warn('  Phản hồi bất thường (200 với token giả).');
    }
}
