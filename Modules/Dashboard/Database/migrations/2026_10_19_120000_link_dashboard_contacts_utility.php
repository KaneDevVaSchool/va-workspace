<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashboard_quick_utilities')
            ->where('utility_key', 'contacts')
            ->update([
                'link_type' => 'route',
                'link_target' => 'employee.contacts',
            ]);
    }

    public function down(): void
    {
        DB::table('dashboard_quick_utilities')
            ->where('utility_key', 'contacts')
            ->update([
                'link_type' => 'none',
                'link_target' => null,
            ]);
    }
};
