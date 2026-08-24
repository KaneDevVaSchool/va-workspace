<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** User không có role super_admin nên không được phép thao tác view-as. */
class NotSuperAdmin extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Chỉ super_admin mới được phép xem thử vai trò khác.');
    }
}
