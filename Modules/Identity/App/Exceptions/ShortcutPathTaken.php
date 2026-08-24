<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

class ShortcutPathTaken extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Trang này đã có trong lối tắt.');
    }
}
