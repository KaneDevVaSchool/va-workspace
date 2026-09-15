<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * URL avatar Google (kèm token/param) có thể dài hơn 255 ký tự — cột
 * `avatar_url` tạo ở 2026_08_24_000002 là VARCHAR(255) mặc định của
 * string(), gây lỗi 1406 "Data too long" khi login Google trả avatar dài.
 *
 * GoogleAuthenticator::resolveAvatarUrl() đã cắt về tối đa 2048 ký tự —
 * nới cột cho khớp giới hạn đó, tránh phụ thuộc doctrine/dbal bằng raw SQL
 * (theo đúng cách 2026_08_24_000002 đã làm với cột password).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}users` MODIFY `avatar_url` VARCHAR(2048) NULL");
    }

    public function down(): void
    {
        $prefix = DB::getTablePrefix();
        DB::statement("UPDATE `{$prefix}users` SET `avatar_url` = LEFT(`avatar_url`, 255) WHERE `avatar_url` IS NOT NULL");
        DB::statement("ALTER TABLE `{$prefix}users` MODIFY `avatar_url` VARCHAR(255) NULL");
    }
};
