<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('hrm_employee_uuid')->nullable()->unique()->after('department_id');
            $table->uuid('hrm_user_uuid')->nullable()->unique()->after('hrm_employee_uuid');
            $table->string('employee_code')->nullable()->after('hrm_user_uuid');
            $table->string('job_title_name')->nullable()->after('employee_code');
            $table->string('job_position_level')->nullable()->after('job_title_name');
            $table->foreignId('company_id')->nullable()->after('job_position_level')
                ->constrained('companies')->nullOnDelete();
            $table->uuid('manager_employee_uuid')->nullable()->after('company_id');
            $table->string('manager_display_name')->nullable()->after('manager_employee_uuid');
            $table->timestamp('hrm_terminated_at')->nullable()->after('manager_display_name');
            $table->timestamp('hrm_synced_at')->nullable()->after('hrm_terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn([
                'hrm_employee_uuid',
                'hrm_user_uuid',
                'employee_code',
                'job_title_name',
                'job_position_level',
                'manager_employee_uuid',
                'manager_display_name',
                'hrm_terminated_at',
                'hrm_synced_at',
            ]);
        });
    }
};
