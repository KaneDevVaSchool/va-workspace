<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bonus chất lượng xuất sắc — cộng % riêng, không nhân vào điểm chuẩn. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->decimal('quality_bonus_percent', 8, 2)->default(5)->after('task_base_score');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn('quality_bonus_percent');
        });
    }
};
