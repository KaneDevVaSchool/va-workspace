<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_menu_visibilities', function (Blueprint $table) {
            $table->string('custom_label', 120)->nullable()->after('is_hidden');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('custom_label');
            $table->string('section_key', 80)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('global_menu_visibilities', function (Blueprint $table) {
            $table->dropColumn(['custom_label', 'sort_order', 'section_key']);
        });
    }
};
