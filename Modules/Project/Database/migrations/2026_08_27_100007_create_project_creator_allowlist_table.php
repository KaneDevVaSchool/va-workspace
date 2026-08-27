<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh sách nhân sự được phép TẠO dự án ngoài các role đã có sẵn
 * 'project.create' trong config/permissions.php — CHỈ MỞ RỘNG quyền, không
 * thu hẹp quyền của role đã có (xem ProjectService::userCanCreate()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_creator_allowlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users', 'id', 'project_creator_allowlist_user_fk')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_creator_allowlist');
    }
};
