<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('global_menu_visibilities')
            ->where('menu_key', 'superadmin.workspace-config.unassigned')
            ->update(['menu_key' => 'superadmin.workspace-config.employees']);
    }

    public function down(): void
    {
        DB::table('global_menu_visibilities')
            ->where('menu_key', 'superadmin.workspace-config.employees')
            ->update(['menu_key' => 'superadmin.workspace-config.unassigned']);
    }
};
