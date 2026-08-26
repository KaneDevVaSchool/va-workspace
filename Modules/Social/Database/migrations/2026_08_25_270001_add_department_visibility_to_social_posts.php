<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // 'all' (mặc định, mọi phòng ban đều thấy), 'include' (chỉ các phòng ban
            // trong social_post_department_visibility), 'exclude' (mọi phòng ban trừ
            // các phòng ban trong social_post_department_visibility). Chỉ áp dụng cho
            // bài đăng trên bảng tin chung (department_id, wall_user_id, group_id đều null).
            $table->string('department_visibility_mode', 10)->default('all')->after('department_id');
        });

        Schema::create('social_post_department_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts', 'id', 'spd_vis_post_fk')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments', 'id', 'spd_vis_dept_fk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'department_id'], 'social_post_dept_visibility_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_department_visibility');

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('department_visibility_mode');
        });
    }
};
