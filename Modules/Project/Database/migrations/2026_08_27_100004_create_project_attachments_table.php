<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đính kèm của dự án — file tải từ máy, ảnh (thư viện ảnh riêng để hiện
 * dạng lưới thumbnail), hoặc link Google Drive. `kind = image` tách khỏi
 * `file` để frontend hiện đúng khối "Thư viện ảnh" dạng lưới, không lẫn
 * vào danh sách "Tệp đính kèm" thông thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_attachments_project_fk')
                ->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users', 'id', 'project_attachments_uploader_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachments');
    }
};
