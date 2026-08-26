<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_criteria', 'use_in_evaluation')) {
                $table->boolean('use_in_evaluation')->default(true)->after('allow_half');
            }

            if (! Schema::hasColumn('evaluation_criteria', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('evaluation_criteria', 'updated_by')) {
            DB::table('evaluation_criteria')
                ->whereNull('updated_by')
                ->update(['updated_by' => DB::raw('created_by')]);
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_criteria', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('evaluation_criteria', 'use_in_evaluation')) {
                $table->dropColumn('use_in_evaluation');
            }
        });
    }
};
