<?php

namespace Modules\Dashboard\App\Repositories\Contracts;

use Illuminate\Support\Collection;

/**
 * Tầng duy nhất được phép gọi Eloquent trực tiếp cho Dashboard cá nhân —
 * luôn scope theo đúng 1 user (chính người đăng nhập), không giới hạn theo
 * project/phòng ban — 1 nhân viên có thể có task ở bất kỳ dự án nào họ
 * được gán, không chỉ dự án của phòng ban mình.
 */
interface MyDashboardRepositoryInterface
{
    /** Toàn bộ task lá (type=task) mà user này là assignee, kèm project name. */
    public function tasksForUser(int $userId): Collection;
}
