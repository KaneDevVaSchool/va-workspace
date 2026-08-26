<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Vị trí đánh giá" (Evaluation Giai đoạn C) — bổ sung phân loại `kind` để
 * phân biệt đánh giá theo CHỨC DANH ('position', vd. Trưởng phòng Marketing)
 * và đánh giá theo CẢ PHÒNG BAN ('department', vd. Phòng Marketing).
 *
 * Chuẩn bị cho tích hợp VA-HRM tương lai: khi có API thật, ô nhập tên sẽ
 * autocomplete theo đúng `kind` (danh sách chức danh hoặc danh sách phòng
 * ban từ HRM) và lưu định danh vào cột `hrm_position_uuid` đã có sẵn từ
 * migration 2026_08_26_120001 — không cần thêm cột mới cho việc đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_positions', function (Blueprint $table) {
            $table->string('kind', 20)->default('position')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_positions', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
