<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm F — Người quản lý (nhập tay, không mặc định = creator/assignee) và
 * Người đã nhận thực hiện (derived — TaskService tự set accepted_by/
 * accepted_at khi status rời 'not_started' lần đầu, KHÔNG có input UI).
 * Đặt tên FK constraint tường minh vì bảng tasks đã có nhiều FK tới users
 * (assignee_id, delegated_to_employee_id, created_by, updated_by).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('assignee_id')
                ->constrained('users', 'id', 'tasks_manager_fk')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->after('manager_id')
                ->constrained('users', 'id', 'tasks_accepted_by_fk')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // dropConstrainedForeignId() suy tên constraint theo convention
            // mặc định — không khớp tên tường minh đã đặt lúc up(), nên drop
            // foreign key bằng đúng tên constraint rồi mới drop cột.
            $table->dropForeign('tasks_accepted_by_fk');
            $table->dropForeign('tasks_manager_fk');
            $table->dropColumn(['accepted_at', 'accepted_by', 'manager_id']);
        });
    }
};
