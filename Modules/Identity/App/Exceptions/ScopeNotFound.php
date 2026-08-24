<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** scope_type = team|department nhưng scope_id không tồn tại trong DB. */
class ScopeNotFound extends RuntimeException
{
    public function __construct(string $scopeType)
    {
        parent::__construct("Không tìm thấy {$scopeType} tương ứng với scope_id đã chọn.");
    }
}
