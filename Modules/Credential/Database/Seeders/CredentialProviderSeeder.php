<?php

namespace Modules\Credential\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Credential\App\Models\CredentialProvider;

/**
 * Danh mục nhà cung cấp mặc định — chỉ để bắt đầu, quản trị viên có thể
 * thêm/sửa/xoá qua UI sau khi seed. Idempotent theo `name`.
 */
class CredentialProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'Google', 'category' => 'Tài khoản Google'],
            ['name' => 'Canva', 'category' => 'Công cụ thiết kế'],
            ['name' => 'Cursor', 'category' => 'Công cụ lập trình AI'],
            ['name' => 'Claude', 'category' => 'Công cụ lập trình AI'],
            ['name' => 'AWS', 'category' => 'Hạ tầng cloud'],
            ['name' => 'VPS', 'category' => 'Hạ tầng server'],
            ['name' => 'Database', 'category' => 'Cơ sở dữ liệu'],
            ['name' => 'Domain', 'category' => 'Tên miền'],
            ['name' => 'IAM Console', 'category' => 'Hạ tầng cloud'],
        ];

        foreach ($providers as $provider) {
            CredentialProvider::query()->updateOrCreate(
                ['name' => $provider['name']],
                ['category' => $provider['category'], 'is_active' => true],
            );
        }
    }
}
