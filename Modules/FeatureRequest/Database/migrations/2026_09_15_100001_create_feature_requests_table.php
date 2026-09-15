<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghi nhận yêu cầu tính năng do nhân viên gửi từ trang họ đang dùng.
 *
 * department_id chụp lại phòng ban của người gửi ngay lúc tạo, để danh sách
 * theo phòng ban của superadmin không đổi nếu người đó chuyển phòng ban sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')
                ->constrained('users', 'id', 'feat_req_creator_fk')
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'feat_req_dept_fk')
                ->nullOnDelete();
            $table->string('page_title', 255)->nullable();
            $table->string('page_url', 500)->nullable();
            $table->text('description');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users', 'id', 'feat_req_reviewer_fk')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->date('expected_done_at')->nullable();
            $table->string('progress_note', 500)->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status'], 'feat_req_dept_status_idx');
            $table->index('status', 'feat_req_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_requests');
    }
};
