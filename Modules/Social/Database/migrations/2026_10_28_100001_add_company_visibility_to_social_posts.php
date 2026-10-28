<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // 'all' | 'include' | 'exclude' — chỉ bài bảng tin chung (department/wall/group null).
            $table->string('company_visibility_mode', 10)->default('all')->after('department_visibility_mode');
        });

        Schema::create('social_post_company_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('social_posts', 'id', 'spc_vis_post_fk')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies', 'id', 'spc_vis_co_fk')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id', 'company_id'], 'social_post_company_visibility_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_company_visibility');

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('company_visibility_mode');
        });
    }
};
