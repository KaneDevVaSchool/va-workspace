<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhãn tự do gán cho dự án — dùng chung toàn hệ thống, số lượng nhỏ (không
 * phân trang phía API, load 1 lần rồi filter phía client). Màu chỉ nhận 1
 * trong 5 giá trị cố định: primary/success/info/warning/danger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->default('primary');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_labels');
    }
};
