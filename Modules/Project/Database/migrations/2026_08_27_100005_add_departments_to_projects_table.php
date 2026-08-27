<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm "phòng ban sở hữu" (owner_department_id — phòng ban của người tạo
 * tại thời điểm tạo dự án, luôn set ở Service::create(), KHÔNG sửa được
 * sau đó) và "phòng ban thực hiện" (executing_department_id — khi phòng ban
 * sở hữu giao dự án cho phòng ban khác thực hiện, nullable, sửa được nếu
 * đủ quyền). Dùng cho lọc quyền xem dự án theo phòng ban (mục A).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('owner_department_id')
                ->nullable()
                ->after('lead_user_id')
                ->constrained('departments', 'id', 'projects_owner_department_fk')
                ->nullOnDelete();
            $table->foreignId('executing_department_id')
                ->nullable()
                ->after('owner_department_id')
                ->constrained('departments', 'id', 'projects_executing_department_fk')
                ->nullOnDelete();

            $table->index(['owner_department_id']);
            $table->index(['executing_department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_department_id');
            $table->dropConstrainedForeignId('executing_department_id');
        });
    }
};
