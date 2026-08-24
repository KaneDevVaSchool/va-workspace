<?php

namespace Modules\WorkspaceConfig\App\Exceptions;

use RuntimeException;

/** Trưởng phòng chỉ gán được một số vai trò cho nhân sự cùng phòng ban. */
class RoleNotAssignable extends RuntimeException
{
}
