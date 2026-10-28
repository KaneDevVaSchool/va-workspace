<?php

namespace Modules\Identity\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Identity\App\Services\WebPushService;

/**
 * Gửi Web Push tới mọi trình duyệt của một user, ngoài request của người gây ra sự kiện.
 * Chỉ dùng khi bật services.webpush.queue (cần queue worker đang chạy).
 */
class SendWebPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Push chỉ "cố gắng hết sức": lỗi mạng tạm thời thử lại vài lần rồi bỏ, không dồn queue. */
    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
    ) {}

    public function handle(WebPushService $webPush): void
    {
        $webPush->sendToUser($this->userId, $this->payload);
    }
}
