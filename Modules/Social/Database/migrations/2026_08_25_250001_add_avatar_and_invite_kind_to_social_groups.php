<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_groups', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('cover_path');
        });

        Schema::table('social_group_join_requests', function (Blueprint $table) {
            $table->string('kind', 16)->default('request')->after('status');
            $table->foreignId('invited_by')->nullable()->after('kind')->constrained('users')->nullOnDelete();
            $table->index(['group_id', 'kind', 'status'], 'sg_join_req_kind_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('social_group_join_requests', function (Blueprint $table) {
            $table->dropIndex('sg_join_req_kind_status_idx');
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('kind');
        });

        Schema::table('social_groups', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
