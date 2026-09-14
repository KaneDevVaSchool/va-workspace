<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'identity:vapid-keys
                            {--write : Ghi khóa vào file .env}
                            {--force : Ghi đè khóa VAPID đã có trong .env}';

    protected $description = 'Tạo cặp khóa VAPID cho thông báo đẩy trình duyệt';

    public function handle(): int
    {
        try {
            $keys = $this->createVapidKeys();
        } catch (Throwable $e) {
            $this->error('Không tạo được khóa VAPID: '.$e->getMessage());
            $this->line('Chuông in-app vẫn hoạt động. Thông báo đẩy khi đóng tab cần VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY trong .env.');

            return self::FAILURE;
        }

        $subject = 'mailto:'.(config('mail.from.address') ?: 'workspace@vaschools.edu.vn');

        $this->info('Thêm vào file .env:');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT='.$subject);

        if ($this->option('write')) {
            return $this->writeEnv($keys, $subject);
        }

        $this->newLine();
        $this->comment('Ghi vào .env: php artisan identity:vapid-keys --write');

        return self::SUCCESS;
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    private function createVapidKeys(): array
    {
        if (class_exists(VAPID::class)) {
            try {
                return VAPID::createVapidKeys();
            } catch (Throwable) {
                // PHP CLI thường không tìm thấy openssl.cnf — tạo khóa bằng config tường minh.
            }
        }

        if (! function_exists('openssl_pkey_new')) {
            throw new \RuntimeException('PHP CLI thiếu extension openssl.');
        }

        $key = $this->newEcKey();
        if ($key === false) {
            throw new \RuntimeException($this->openSslFailure('openssl_pkey_new thất bại'));
        }

        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new \RuntimeException('OpenSSL không trả về khóa EC P-256.');
        }

        $public = "\x04"
            .str_pad((string) $details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            .str_pad((string) $details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $private = str_pad((string) $details['ec']['d'], 32, "\0", STR_PAD_LEFT);

        return [
            'publicKey' => $this->base64Url($public),
            'privateKey' => $this->base64Url($private),
        ];
    }

    /**
     * PHP CLI thường không tìm thấy openssl.cnf — thử từng file config đến khi tạo được khóa EC.
     *
     * @return \OpenSSLAsymmetricKey|false
     */
    private function newEcKey(): mixed
    {
        $lastError = 'openssl_pkey_new thất bại';
        $candidates = $this->opensslConfigCandidates();
        $candidates[] = '';

        foreach ($candidates as $configPath) {
            if ($configPath !== '' && ! is_file($configPath)) {
                continue;
            }

            $this->drainOpenSslErrors();
            $args = [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ];
            if ($configPath !== '') {
                $args['config'] = $configPath;
            }

            try {
                $key = openssl_pkey_new($args);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                continue;
            }

            if ($key !== false) {
                return $key;
            }

            $lastError = $this->openSslFailure('openssl_pkey_new thất bại');
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @return list<string>
     */
    private function opensslConfigCandidates(): array
    {
        $fromEnv = (string) getenv('OPENSSL_CONF');
        $phpDir = dirname(PHP_BINARY);

        return array_values(array_filter([
            $fromEnv,
            $phpDir.'/extras/ssl/openssl.cnf',
            PHP_BINDIR.'/extras/ssl/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
            '/opt/homebrew/etc/openssl@3/openssl.cnf',
            module_path('Identity', 'resources/openssl.cnf'),
        ], fn ($path) => is_string($path) && $path !== ''));
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param  array{publicKey: string, privateKey: string}  $keys
     */
    private function writeEnv(array $keys, string $subject): int
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $this->error('Không thấy file .env');

            return self::FAILURE;
        }
        if (! is_writable($path)) {
            $this->error('Không ghi được file .env (kiểm tra quyền ghi).');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);
        $existing = $this->envValue($env, 'VAPID_PUBLIC_KEY');
        if ($existing !== '' && ! $this->option('force')) {
            $this->warn('Đã có VAPID_PUBLIC_KEY. Dùng --force nếu muốn ghi đè.');

            return self::FAILURE;
        }

        $pairs = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
            'VAPID_SUBJECT' => $subject,
        ];

        foreach ($pairs as $name => $value) {
            if (preg_match('/^'.$name.'=/m', $env) === 1) {
                $env = preg_replace('/^'.$name.'=.*$/m', $name.'='.$value, $env, 1) ?? $env;
            } else {
                $env = rtrim($env)."\n{$name}={$value}\n";
            }
        }

        if (file_put_contents($path, $env) === false) {
            $this->error('Ghi .env thất bại.');

            return self::FAILURE;
        }

        $this->call('config:clear');
        $this->info('Đã ghi vào .env.');

        return self::SUCCESS;
    }

    private function envValue(string $env, string $name): string
    {
        if (preg_match('/^'.$name.'=(.*)$/m', $env, $m) !== 1) {
            return '';
        }

        return trim($m[1], " \t\"'");
    }

    private function drainOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // Bỏ lỗi tồn đọng từ lần gọi OpenSSL trước.
        }
    }

    private function openSslFailure(string $fallback): string
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors === [] ? $fallback : $fallback.': '.implode('; ', $errors);
    }
}
