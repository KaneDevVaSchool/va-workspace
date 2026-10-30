<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm bảng tin gắn một pháp nhân (bảng companies đồng bộ từ HRM).
 * Null = nhóm công khai / bảo mật như trước, không giới hạn pháp nhân.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_groups', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('visibility')
                ->constrained('companies')->nullOnDelete();
            $table->unique('company_id');
            $table->index(['company_id', 'visibility'], 'social_groups_company_visibility_idx');
        });
    }

    public function down(): void
    {
        Schema::table('social_groups', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->dropIndex('social_groups_company_visibility_idx');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
