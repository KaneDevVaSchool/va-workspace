<?php

namespace Modules\Project\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Project\App\Services\TaskService;

/**
 * Nhắc "sắp quá hạn" tối thiểu — không phải Reminder Engine đầy đủ (xem
 * plans/2026-08-28-notification-reminder-engine-proposal.md, KHÔNG áp dụng
 * ở đây). Ngưỡng cố định: end_date trong [hôm nay, hôm nay+1]. Mỗi task
 * chỉ nhắc 1 lần (due_soon_notified_at) cho tới khi end_date đổi.
 *
 * Đăng ký lịch chạy trong ProjectServiceProvider::boot() — dailyAt('07:00').
 */
class NotifyTasksDueSoonCommand extends Command
{
    protected $signature = 'project:notify-tasks-due-soon';

    protected $description = 'Gửi thông báo cho các công việc sắp đến hạn (chưa từng được nhắc).';

    public function handle(TaskService $service): int
    {
        $count = $service->notifyDueSoonTasks();

        $this->info("Đã gửi thông báo sắp quá hạn cho {$count} công việc.");

        return self::SUCCESS;
    }
}
