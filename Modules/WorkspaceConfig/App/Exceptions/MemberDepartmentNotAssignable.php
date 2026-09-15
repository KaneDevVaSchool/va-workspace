<?php

namespace Modules\WorkspaceConfig\App\Exceptions;

use RuntimeException;

/** Không gán được phòng ban cho thành viên (tài khoản không hợp lệ, phòng ban không tồn tại, …). */
class MemberDepartmentNotAssignable extends RuntimeException
{
}
