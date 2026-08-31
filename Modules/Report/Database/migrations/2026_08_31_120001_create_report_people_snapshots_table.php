<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh sách nhân sự đã chụp lại của một báo cáo đã lưu.
 *
 * Báo cáo còn nháp thì lấy nhân sự động theo phòng ban; nhưng đã lưu thì phải
 * đọc từ bảng này, nếu không người nghỉ việc sau kỳ sẽ biến mất khỏi báo cáo
 * cũ và người mới chuyển đến sẽ hiện ra trong kỳ họ chưa làm ở đó.
 *
 * Cố ý KHÔNG có khoá ngoại tới users và có lưu sẵn tên: bản chụp phải sống sót
 * cả khi tài khoản bị xoá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_people_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_people_report_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_id', 'user_id'], 'report_people_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_people_snapshots');
    }
};
