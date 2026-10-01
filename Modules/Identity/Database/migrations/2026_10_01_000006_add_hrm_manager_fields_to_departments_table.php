<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->uuid('hrm_manager_employee_uuid')->nullable()->after('company_id');
            $table->string('hrm_manager_name')->nullable()->after('hrm_manager_employee_uuid');
            $table->string('hrm_manager_email')->nullable()->after('hrm_manager_name');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn([
                'hrm_manager_employee_uuid',
                'hrm_manager_name',
                'hrm_manager_email',
            ]);
        });
    }
};
