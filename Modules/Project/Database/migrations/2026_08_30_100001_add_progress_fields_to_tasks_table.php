<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm H — Khối lượng/Đơn vị/Cách tính tiến độ (docs/VA_WORKSPACE_OVERVIEW.md,
 * plan mở rộng Task). Khi progress_type = 'quantity', progress_percent (đã có
 * sẵn từ migration gốc) được TaskService tự tính = round(progress_number /
 * progress_total * 100) — KHÔNG nhập tay trong trường hợp đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('progress_type', 20)->default('percent')->after('progress_percent'); // percent | quantity
            $table->decimal('progress_number', 12, 2)->nullable()->after('progress_type'); // khối lượng đã hoàn thành
            $table->decimal('progress_total', 12, 2)->nullable()->after('progress_number'); // khối lượng cần hoàn thành (mẫu số)
            $table->string('unit', 50)->nullable()->after('progress_total'); // đơn vị đo, tự do — không enum cứng
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['unit', 'progress_total', 'progress_number', 'progress_type']);
        });
    }
};
