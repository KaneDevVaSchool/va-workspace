<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC tối giản — chỉ role (chưa có permission chi tiết theo từng hành
 * động, xem docs/VA_WORKSPACE_OVERVIEW.md §4 và §19 lộ trình Phase 1).
 * 9 role hệ thống được seed trong RoleSeeder (7 role gốc + phó phòng,
 * trưởng bộ phận — xem RoleSeeder để biết chi tiết).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
