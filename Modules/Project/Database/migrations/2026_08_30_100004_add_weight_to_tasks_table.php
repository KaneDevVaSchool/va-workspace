<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm I — Tỷ trọng. Gắn tạm vào phạm vi Project (không phải Sprint/
 * Evaluation cycle — chưa tồn tại), không validate tổng = 100% ở tầng này —
 * để lại cho module Evaluation quyết định khi dựng thật.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('sort_order'); // % tỷ trọng trong phạm vi Project
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
