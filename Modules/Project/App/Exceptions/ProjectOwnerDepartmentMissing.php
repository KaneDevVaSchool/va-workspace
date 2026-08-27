<?php

namespace Modules\Project\App\Exceptions;

/**
 * Ném khi user tạo dự án không có department_id — không xác định được
 * "phòng ban sở hữu" (owner_department_id) bắt buộc phải set lúc tạo.
 */
class ProjectOwnerDepartmentMissing extends \Exception
{
    public function __construct()
    {
        parent::__construct('Tài khoản chưa gắn với phòng ban nào, không thể tạo dự án.');
    }
}
