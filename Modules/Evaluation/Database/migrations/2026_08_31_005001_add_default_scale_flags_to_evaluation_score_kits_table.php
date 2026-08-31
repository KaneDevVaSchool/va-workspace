<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->boolean('classification_use_default')->default(false)->after('quality_criterion_id');
            $table->boolean('difficulty_use_default')->default(false)->after('classification_use_default');
            $table->boolean('progress_use_default')->default(false)->after('difficulty_use_default');
            $table->boolean('quality_use_default')->default(false)->after('progress_use_default');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_score_kits', function (Blueprint $table) {
            $table->dropColumn([
                'quality_use_default',
                'progress_use_default',
                'difficulty_use_default',
                'classification_use_default',
            ]);
        });
    }
};
