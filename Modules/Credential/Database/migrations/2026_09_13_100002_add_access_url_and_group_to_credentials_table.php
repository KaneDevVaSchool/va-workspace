<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `access_url` — link truy cập tài khoản dịch vụ (trang đăng nhập, app
 * store...), hiển thị dạng liên kết bấm được trên bảng danh sách.
 * `group` — phân nhóm external (dịch vụ/phần mềm bên thứ ba) vs internal
 * (công cụ VA Schools tự phát triển) để tách 2 tab trên trang danh sách.
 * Mặc định 'external' để 2 bản ghi Claude Pro đã seed hợp lý (dịch vụ AI
 * bên ngoài công ty) mà không cần backfill thủ công.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->string('access_url', 2048)->nullable()->after('domain');
            $table->string('group', 20)->default('external')->after('account_type');
            $table->index(['group']);
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn(['access_url', 'group']);
        });
    }
};
