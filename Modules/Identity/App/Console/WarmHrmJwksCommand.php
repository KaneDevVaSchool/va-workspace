<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Modules\Identity\App\Hrm\Exceptions\HrmApiUnavailable;
use Modules\Identity\App\Hrm\Services\HrmJwtVerifier;

/**
 * Làm nóng cache JWKS VA-HRM định kỳ (xem app/Console/Kernel.php schedule)
 * để giảm khả năng cache-miss đúng lúc user đang login qua SSO.
 */
class WarmHrmJwksCommand extends Command
{
    protected $signature = 'identity:hrm-warm-jwks';

    protected $description = 'Làm nóng cache JWKS VA-HRM dùng để verify JWT SSO offline';

    public function handle(HrmJwtVerifier $verifier): int
    {
        try {
            $verifier->warmCache();
        } catch (HrmApiUnavailable $e) {
            $this->error("Không lấy được JWKS: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('OK — cache JWKS VA-HRM đã làm nóng.');

        return self::SUCCESS;
    }
}
