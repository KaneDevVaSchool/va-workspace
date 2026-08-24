<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mở rộng bảng users có sẵn cho đăng nhập Google + liên kết phòng ban.
 * TẠM THỜI: user/department là dữ liệu giả lập trong app này — sẽ thay
 * bằng dữ liệu từ API HRM, xem Modules/Identity/README.md.
 *
 * Dùng raw SQL để nới lỏng `password` thành nullable (Google-only account
 * không có mật khẩu) — tránh phụ thuộc doctrine/dbal chỉ để đổi 1 cột.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')
                ->constrained('departments')->nullOnDelete();
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('google_id');
            $table->string('status')->default('active')->after('avatar_url');
        });

        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}users` MODIFY `password` VARCHAR(255) NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['google_id', 'avatar_url', 'status']);
        });

        $prefix = DB::getTablePrefix();
        DB::statement("UPDATE `{$prefix}users` SET `password` = '' WHERE `password` IS NULL");
        DB::statement("ALTER TABLE `{$prefix}users` MODIFY `password` VARCHAR(255) NOT NULL");
    }
};
