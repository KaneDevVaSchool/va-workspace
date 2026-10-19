<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu chỉnh sửa hồ sơ cá nhân trên /dashboard/me/profile — lưu tạm,
 * luồng duyệt HRM sẽ bổ sung sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('changes');
            $table->text('note')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'dash_prof_chg_req_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_profile_change_requests');
    }
};
