<?php

namespace Modules\Identity\App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Hrm\Exceptions\HrmDatabaseUnavailable;
use Modules\Identity\App\Hrm\Services\HrmEmployeeDirectory;
use Throwable;

class DiagnoseHrmDatabaseCommand extends Command
{
    protected $signature = 'identity:hrm-db-diagnose';

    protected $description = 'Chẩn đoán kết nối MySQL VA-HRM (HRM_DB_*) — dùng khi API roster trả 503';

    public function handle(HrmEmployeeDirectory $directory): int
    {
        if (! HrmEmployeeDirectory::isConfigured()) {
            $this->error('Chưa cấu hình HRM_DB_DATABASE (hoặc trống) — workspace dùng danh sách users local, không đọc HRM.');

            return self::FAILURE;
        }

        $connection = config('database.connections.hrm');
        $this->line('Host: '.($connection['host'] ?? '?').':'.($connection['port'] ?? '?'));
        $this->line('Database: '.($connection['database'] ?? '?'));
        $this->line('Prefix bảng: '.($connection['prefix'] ?? ''));

        $this->newLine();
        $this->info('1) PDO…');
        try {
            DB::connection('hrm')->getPdo();
            $this->line('  OK');
        } catch (Throwable $e) {
            $this->error('  Lỗi: '.$e->getMessage());
            $this->warn('  → Kiểm tra HRM_DB_HOST (127.0.0.1 nếu MySQL cùng máy), user/pass, firewall.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('2) Bảng employees…');
        try {
            $count = DB::connection('hrm')->table('employees')->whereNull('deleted_at')->count();
            $this->line("  OK — {$count} nhân sự (chưa xóa mềm).");
        } catch (Throwable $e) {
            $this->error('  Lỗi: '.$e->getMessage());
            $this->warn('  → Kiểm tra HRM_DB_PREFIX (mặc định va_hrm_) khớp app HRM.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('3) Roster (HrmEmployeeDirectory::load)…');
        try {
            $payload = $directory->load();
            $employeeCount = count($payload['employees'] ?? []);
            $unitCount = count($payload['org_units'] ?? []);
            $this->line("  OK — {$employeeCount} nhân sự, {$unitCount} đơn vị gán được.");
        } catch (HrmDatabaseUnavailable $e) {
            $this->error('  '.$e->detailForClient());
            if ($e->getPrevious()) {
                $this->line('  Gốc: '.$e->getPrevious()->getMessage());
            }

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('  Lỗi: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Kết nối HRM DB ổn. Nếu trình duyệt vẫn 503: php artisan config:cache lại sau khi sửa .env.');

        return self::SUCCESS;
    }
}
