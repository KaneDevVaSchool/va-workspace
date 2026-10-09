<?php

namespace Modules\Identity\App\Hrm\Exceptions;

use RuntimeException;
use Throwable;

/** API VA-HRM không phản hồi thành công (lỗi mạng, timeout, response không 2xx). */
class HrmApiUnavailable extends RuntimeException
{
    public function __construct(
        string $reason,
        ?Throwable $previous = null,
        private readonly bool $connectionFailure = true,
    ) {
        $message = $connectionFailure
            ? "Không thể kết nối API VA-HRM: {$reason}"
            : $reason;

        parent::__construct($message, previous: $previous);
    }

    /** Lỗi nghiệp vụ / validation từ HRM — hiển thị trực tiếp cho người dùng, không prefix “kết nối”. */
    public static function userFacing(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, $previous, connectionFailure: false);
    }

    public function isConnectionFailure(): bool
    {
        return $this->connectionFailure;
    }
}
