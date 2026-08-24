<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiêu chí đánh giá nhân sự — scoped theo phòng ban (department_director
 * tự tạo tiêu chí riêng cho phòng ban mình, không chia sẻ sang PB khác).
 *
 * Hai kiểu tiêu chí (cột `type`):
 *   scale    — thang điểm nhiều mức (Xuất sắc 5 / Tốt 4 / Khá 3…).
 *   behavior — cộng/trừ theo hành vi (Đi muộn −1 / Hoàn thành sớm +2…).
 *
 * Cấu hình mức điểm lưu trong `levels` (JSON), tránh bảng con —
 * đơn giản cho Phase B, đủ để Phase C dùng lại làm input đánh giá.
 *
 * Xem docs/VA_WORKSPACE_OVERVIEW.md §7, §21.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['scale', 'behavior'])->default('scale');
            $table->text('description')->nullable();

            /**
             * Mảng JSON các mức điểm, tuỳ type:
             *   scale:    [{label: 'Xuất sắc', score: 5}, {label: 'Tốt', score: 4}, ...]
             *   behavior: [{label: 'Đi muộn', score: -1}, {label: 'Hoàn thành sớm', score: 2}]
             * Tối thiểu 1 mức; score là số nguyên khác 0 (scale: dương; behavior: bất kỳ).
             */
            $table->json('levels');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
