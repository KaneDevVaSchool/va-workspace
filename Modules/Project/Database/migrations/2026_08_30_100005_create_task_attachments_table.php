<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đính kèm của Task — khuôn theo project_attachments/ProjectAttachment
 * (Modules/Project/Database/migrations/2026_08_27_100004_create_project_attachments_table.php),
 * rút gọn theo decision đã chốt: chỉ file upload (không kind/drive_link/url
 * như Project — Task chỉ cần đính kèm file đơn giản).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks', 'id', 'task_attachments_task_fk')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users', 'id', 'task_attachments_uploader_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
