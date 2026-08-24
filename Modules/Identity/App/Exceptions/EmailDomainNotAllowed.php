<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** Email Google không thuộc domain được phép đăng nhập (GOOGLE_ALLOWED_DOMAINS). */
class EmailDomainNotAllowed extends RuntimeException
{
    public function __construct(public readonly string $email)
    {
        parent::__construct("Email domain không được phép đăng nhập: {$email}");
    }
}
