<?php

namespace Modules\Chat\App\Console;

use Illuminate\Console\Command;
use Modules\Chat\App\Services\ChatService;

class ExpireChatMessagesCommand extends Command
{
    protected $signature = 'chat:expire-messages';

    protected $description = 'Thu hồi tin nhắn đã hết hạn tự xoá.';

    public function handle(ChatService $chat): int
    {
        $count = $chat->expireDueMessages();
        $this->info('Đã thu hồi '.$count.' tin nhắn.');

        return self::SUCCESS;
    }
}
