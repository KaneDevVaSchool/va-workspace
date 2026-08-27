<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Người thực hiện dự án — nhiều user cho 1 project (N-N thuần, không có
 * cột phụ ở bước này).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_members_project_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'project_members_user_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id'], 'project_members_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
    }
};
