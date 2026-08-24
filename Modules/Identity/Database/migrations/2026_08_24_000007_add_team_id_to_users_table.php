<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `team_id` chưa từng tồn tại dù PermissionService::userMatchesScope() và
 * User::allowsScoped() đã tham chiếu `$user->team_id` (chưa lộ lỗi vì scope
 * `team` chưa được dùng thực tế ở đâu trước khi có UI ma trận phân quyền).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('department_id')
                ->constrained('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
