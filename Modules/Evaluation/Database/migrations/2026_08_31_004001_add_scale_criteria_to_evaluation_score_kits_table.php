<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->foreignId('difficulty_criterion_id')
                ->nullable()
                ->after('classification_criterion_id')
                ->constrained('evaluation_criteria', 'id', 'eval_score_kits_difficulty_fk')
                ->nullOnDelete();
            $table->foreignId('progress_criterion_id')
                ->nullable()
                ->after('difficulty_criterion_id')
                ->constrained('evaluation_criteria', 'id', 'eval_score_kits_progress_fk')
                ->nullOnDelete();
            $table->foreignId('quality_criterion_id')
                ->nullable()
                ->after('progress_criterion_id')
                ->constrained('evaluation_criteria', 'id', 'eval_score_kits_quality_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropForeign('eval_score_kits_quality_fk');
            $table->dropForeign('eval_score_kits_progress_fk');
            $table->dropForeign('eval_score_kits_difficulty_fk');
            $table->dropColumn([
                'quality_criterion_id',
                'progress_criterion_id',
                'difficulty_criterion_id',
            ]);
        });
    }
};
