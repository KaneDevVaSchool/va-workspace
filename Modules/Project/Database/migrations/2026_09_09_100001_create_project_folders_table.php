<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư mục tài liệu dự án — cây lồng nhau (parent_id), dùng cho tab Đính kèm
 * dạng file manager (Tài liệu dự án / vào thư mục / quay lại).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_folders_project_fk')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('project_folders', 'id', 'project_folders_parent_fk')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'project_folders_creator_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_folders');
    }
};
