<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Các bảng phụ của báo cáo — người được xem, bộ lọc phạm vi, cột hiển thị và
 * tiêu chí được đưa vào báo cáo. Tách bảng thay vì nhồi JSON để còn lọc và
 * kiểm tra quyền xem bằng truy vấn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_viewers_report_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'report_viewers_user_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['report_id', 'user_id'], 'report_viewers_unique');
        });

        Schema::create('report_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_filters_report_fk')
                ->cascadeOnDelete();
            $table->string('filter_key', 60);
            $table->string('filter_value', 120);
            $table->timestamps();

            $table->index(['report_id', 'filter_key'], 'report_filters_report_key_idx');
        });

        Schema::create('report_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_columns_report_fk')
                ->cascadeOnDelete();
            $table->string('column_key', 60);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['report_id', 'column_key'], 'report_columns_unique');
        });

        Schema::create('report_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')
                ->constrained('reports', 'id', 'report_criteria_report_fk')
                ->cascadeOnDelete();
            $table->foreignId('criterion_id')
                ->nullable()
                ->constrained('evaluation_criteria', 'id', 'report_criteria_criterion_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['report_id', 'criterion_id'], 'report_criteria_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_criteria');
        Schema::dropIfExists('report_columns');
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_viewers');
    }
};
