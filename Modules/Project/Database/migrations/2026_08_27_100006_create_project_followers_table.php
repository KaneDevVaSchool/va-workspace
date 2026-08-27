<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theo dõi dự án (follower) — user tự bật/tắt theo dõi 1 dự án để được
 * cộng vào danh sách "xem được" (mục A) + tab "Bạn theo dõi" (mục F).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_followers_project_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'project_followers_user_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'project_followers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_followers');
    }
};
