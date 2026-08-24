<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** Permission key thuộc danh sách reserved — chỉ super_admin giữ được, không grant qua UI. */
class PermissionKeyReserved extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("Quyền \"{$key}\" là quyền hệ thống, chỉ super_admin mới có — không thể cấp qua UI.");
    }
}
