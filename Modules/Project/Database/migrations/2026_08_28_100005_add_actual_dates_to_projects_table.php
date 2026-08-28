<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'actual_start_date')) {
                $table->date('actual_start_date')->nullable()->after('end_date');
            }
            if (! Schema::hasColumn('projects', 'actual_end_date')) {
                $table->date('actual_end_date')->nullable()->after('actual_start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('projects', 'actual_start_date') ? 'actual_start_date' : null,
                Schema::hasColumn('projects', 'actual_end_date') ? 'actual_end_date' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
