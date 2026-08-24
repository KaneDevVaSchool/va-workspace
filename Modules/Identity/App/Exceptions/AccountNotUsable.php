<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** Tài khoản tồn tại nhưng không thể đăng nhập (bị khóa/vô hiệu hoá). */
class AccountNotUsable extends RuntimeException
{
    public function __construct(public readonly string $email, public readonly string $reason)
    {
        parent::__construct("Tài khoản {$email} không thể đăng nhập: {$reason}");
    }
}
