<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('evaluation_criteria', 'allow_half')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->boolean('allow_half')->default(false)->after('is_active');
            });
        }

        $rows = DB::table('evaluation_criteria')->select('id', 'levels')->get();

        foreach ($rows as $row) {
            $levels = is_string($row->levels)
                ? json_decode($row->levels, true)
                : $row->levels;
            if (! is_array($levels)) {
                continue;
            }

            foreach ($levels as $level) {
                $score = (float) ($level['score'] ?? 0);
                if (abs($score - round($score)) > 0.001) {
                    DB::table('evaluation_criteria')->where('id', $row->id)->update(['allow_half' => true]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluation_criteria', 'allow_half')) {
            Schema::table('evaluation_criteria', function (Blueprint $table) {
                $table->dropColumn('allow_half');
            });
        }
    }
};
