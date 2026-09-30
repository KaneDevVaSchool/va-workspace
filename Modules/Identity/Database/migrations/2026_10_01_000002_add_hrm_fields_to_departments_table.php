<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->uuid('hrm_org_unit_uuid')->nullable()->unique()->after('code');
            $table->string('external_code')->nullable()->after('hrm_org_unit_uuid');
            $table->foreignId('company_id')->nullable()->after('external_code')
                ->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['hrm_org_unit_uuid', 'external_code']);
        });
    }
};
