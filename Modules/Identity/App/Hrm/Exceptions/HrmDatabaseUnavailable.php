<?php

namespace Modules\Identity\App\Hrm\Exceptions;

use RuntimeException;

/** Không đọc được MySQL VA-HRM (chưa cấu hình hoặc kết nối lỗi). */
class HrmDatabaseUnavailable extends RuntimeException
{
}
