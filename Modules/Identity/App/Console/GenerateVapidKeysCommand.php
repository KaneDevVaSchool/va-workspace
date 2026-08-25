<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'identity:vapid-keys';

    protected $description = 'Tạo cặp khóa VAPID cho thông báo đẩy trình duyệt';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Không tạo được khóa VAPID trên máy này (OpenSSL EC): '.$e->getMessage());
            $this->line('Chuông in-app vẫn hoạt động. Thông báo đẩy khi đóng tab cần VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY trong .env.');

            return self::FAILURE;
        }

        $this->info('Thêm vào file .env:');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:'.(config('mail.from.address') ?: 'workspace@vaschools.edu.vn'));

        return self::SUCCESS;
    }
}
