<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Sinh cặp khoá VAPID cho Web Push — chạy một lần, dán kết quả vào .env
 * (không tự ghi .env vì key cũ có thể đang được client đăng ký sử dụng).
 */
class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'push:vapid-keys';

    protected $description = 'Sinh cặp khoá VAPID để dán vào .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->warn('Dán 2 dòng dưới đây vào .env, không commit VAPID_PRIVATE_KEY lên git.');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("VAPID_PRIVATE_KEY={$keys['privateKey']}");
        $this->newLine();

        return self::SUCCESS;
    }
}
