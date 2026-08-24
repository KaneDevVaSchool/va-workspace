<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng DB override cho PermissionCatalog (config-based defaults).
 *
 * Ma trận mặc định nằm trong config/permissions.php — super_admin có thể
 * mở rộng/thu hẹp quyền của từng role theo scope cụ thể thông qua bảng này.
 *
 * scope_type:
 *   - global     : áp dụng toàn hệ thống
 *   - department : chỉ trong phòng ban (scope_id = department_id)
 *   - team       : chỉ trong nhóm (scope_id = team_id — Phase 1 thêm bảng teams)
 *
 * granted = true  → cấp thêm quyền ngoài config defaults
 * granted = false → thu hồi quyền dù config defaults cho phép
 *
 * Unique: mỗi (role_code, permission_key, scope_type, scope_id) chỉ có 1 dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_grants', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->index();
            $table->string('permission_key');
            $table->boolean('granted')->default(true);
            $table->string('scope_type')->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['role_code', 'permission_key', 'scope_type', 'scope_id'], 'perm_grants_unique');
            $table->foreign('role_code')->references('code')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_grants');
    }
};
