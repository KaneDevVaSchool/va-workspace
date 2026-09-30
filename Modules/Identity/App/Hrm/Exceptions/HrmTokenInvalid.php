<?php

namespace Modules\Identity\App\Hrm\Exceptions;

use RuntimeException;

/** JWT SSO của VA-HRM không hợp lệ (chữ ký sai, hết hạn, iss/aud không khớp). */
class HrmTokenInvalid extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct("Token SSO không hợp lệ: {$reason}");
    }
}
