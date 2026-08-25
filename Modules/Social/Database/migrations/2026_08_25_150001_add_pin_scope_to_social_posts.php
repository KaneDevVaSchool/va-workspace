<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->string('pin_scope', 16)->nullable()->after('is_pinned');
        });

        DB::table('social_posts')
            ->where('is_pinned', true)
            ->whereNull('pin_scope')
            ->update(['pin_scope' => 'company']);
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('pin_scope');
        });
    }
};
