<?php

namespace Modules\Identity\App\Hrm\Exceptions;

use RuntimeException;

/** Không đọc được MySQL VA-HRM (chưa cấu hình hoặc kết nối lỗi). */
class HrmDatabaseUnavailable extends RuntimeException
{
    public static function wrap(\Throwable $previous, string $message): self
    {
        return new self($message, 0, $previous);
    }

    public function detailForClient(): string
    {
        $message = $this->getMessage();
        $root = $this->getPrevious();
        if ($root === null) {
            return $message;
        }

        if (config('app.debug')) {
            return $message.' Chi tiết: '.$root->getMessage();
        }

        return $message;
    }
}
