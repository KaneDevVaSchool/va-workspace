<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `department_id` — phòng ban sở hữu credential này. Mặc định gán theo
 * phòng ban của người tạo (CredentialService::create()) — chỉ người có
 * `credential.manage` scope global (hoặc superadmin) mới xem/quản lý được
 * credential của MỌI phòng ban; các role khác (kể cả credential.manage
 * scope department) chỉ thấy credential thuộc đúng phòng ban mình, trừ
 * khi được cấp quyền xem riêng qua bảng credential_viewers (không đổi).
 * Nullable để không phá dữ liệu cũ (2 bản ghi Claude Pro đã seed trước khi
 * có cột này) — NULL coi như "chưa gán phòng ban", chỉ superadmin/quyền
 * global thấy được cho tới khi được gán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')
                ->constrained('departments')->nullOnDelete();
            $table->index(['department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
