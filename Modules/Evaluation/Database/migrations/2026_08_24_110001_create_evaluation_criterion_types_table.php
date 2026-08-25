<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục loại tiêu chí theo phòng ban.
 * Trưởng phòng tự tạo loại (tên, mã tự sinh dạng TCA0001, mô tả) rồi gắn vào từng tiêu chí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_criterion_types')) {
            Schema::create('evaluation_criterion_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 40);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['department_id', 'code'], 'eval_criterion_types_dept_code_uq');
            });
        }

        if (! Schema::hasColumn('evaluation_criteria', 'criterion_type_id')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->foreignId('criterion_type_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('evaluation_criterion_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('criterion_type_id');
        });

        Schema::dropIfExists('evaluation_criterion_types');
    }
};
