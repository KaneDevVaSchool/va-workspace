<?php

namespace Modules\Identity\App\Hrm\Exceptions;

use RuntimeException;
use Throwable;

/** API VA-HRM không phản hồi thành công (lỗi mạng, timeout, response không 2xx). */
class HrmApiUnavailable extends RuntimeException
{
    public function __construct(string $reason, ?Throwable $previous = null)
    {
        parent::__construct("Không thể kết nối API VA-HRM: {$reason}", previous: $previous);
    }
}
