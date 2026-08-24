<?php

namespace Modules\Identity\App\Exceptions;

use RuntimeException;

/** Người được gán team_lead_id phải thuộc cùng department với team, và đang active. */
class TeamLeadNotInDepartment extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Trưởng nhóm phải là nhân sự đang hoạt động thuộc cùng phòng ban với nhóm.');
    }
}
