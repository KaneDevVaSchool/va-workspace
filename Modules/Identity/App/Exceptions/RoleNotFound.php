<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** Mã role không khớp với bất kỳ role nào đã seed (xem RoleSeeder). */
class RoleNotFound extends RuntimeException
{
    public function __construct(public readonly string $roleCode)
    {
        parent::__construct("Vai trò \"{$roleCode}\" không tồn tại.");
    }
}
