<?php

namespace Modules\WorkspaceConfig\App\Exceptions;

use RuntimeException;

/** Không gán được nhóm cho thành viên (sai phòng ban, tài khoản không hợp lệ, …). */
class MemberTeamNotAssignable extends RuntimeException
{
}
