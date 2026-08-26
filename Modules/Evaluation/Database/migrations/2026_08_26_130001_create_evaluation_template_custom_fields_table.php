<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Trường tùy biến" trên Mẫu đánh giá (Evaluation Giai đoạn C, PR5) — cho
 * phép mẫu khai báo thêm field ngoài bộ tiêu chí chuẩn (ví dụ "Nhận xét
 * thêm của quản lý"), chuẩn bị cho phiếu đánh giá thực tế (Giai đoạn D).
 *
 * PR5 CHỈ lưu định nghĩa field, KHÔNG có UI nhập giá trị thật (chưa có
 * phiếu). Xem plans/2026-08-26-mau-danh-gia.md §6.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_template_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates', 'id', 'eval_tpl_custom_fields_template_fk')
                ->cascadeOnDelete();
            $table->string('label');
            $table->enum('field_type', ['text', 'number', 'select', 'date'])->default('text');

            /** Danh sách lựa chọn, chỉ dùng khi field_type = select. VD: ["Tốt", "Khá", "Cần cải thiện"]. */
            $table->json('options')->nullable();

            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_template_custom_fields');
    }
};
