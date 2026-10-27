<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_link_previews', function (Blueprint $table) {
            $table->string('description', 1000)->nullable()->after('title');
            $table->string('site_name', 191)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('social_link_previews', function (Blueprint $table) {
            $table->dropColumn(['description', 'site_name']);
        });
    }
};
