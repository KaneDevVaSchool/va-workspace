<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đính kèm của bình luận — rút gọn giống task_attachments (chỉ file upload,
 * không kind/drive_link/url như ProjectAttachment). Cột "type" phân biệt
 * ảnh/file khác để frontend hiển thị dạng lưới ảnh riêng, giống cách
 * SocialPostComment phân loại attachments JSON theo type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')
                ->constrained('comments', 'id', 'comment_attachments_comment_fk')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('type', 10)->default('file');
            $table->timestamps();

            $table->index('comment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_attachments');
    }
};
