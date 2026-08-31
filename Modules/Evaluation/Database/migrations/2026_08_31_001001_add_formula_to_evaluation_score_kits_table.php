<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Công thức phòng tự chỉnh: dùng/cộng/trừ từng hạng mục đã set. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('weighted_task_levels');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
