<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phiên bản công thức Cách 2 — snapshot cũ giữ schema 1, cấu hình mới dùng 2. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->unsignedTinyInteger('kit_schema_version')
                ->default(1)
                ->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn('kit_schema_version');
        });
    }
};
