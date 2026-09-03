<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch sử cột điểm / cột tiêu chí của báo cáo đánh giá nhân sự.
 *
 * Bản 1.0 ghi lúc tạo. Mỗi lần đổi cột trên bảng chấm điểm lưu phụ lục
 * 1.1, 1.2… — bản gốc không bị ghi đè.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('display_revision', 12)
                ->default('1.0')
                ->after('status');
        });

        Schema::create('report_display_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_display_revs_report_fk')
                ->cascadeOnDelete();
            $table->string('revision', 12);
            $table->string('kind', 20)->default('original');
            $table->json('criterion_ids');
            $table->json('column_keys');
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'report_display_revs_creator_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_id', 'revision'], 'report_display_revs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_display_revisions');

        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('display_revision');
        });
    }
};
