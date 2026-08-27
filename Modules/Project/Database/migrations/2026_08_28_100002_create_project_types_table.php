<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loại dự án — trước đây là enum cứng (ProjectEnums::TYPES), nay chuyển
 * thành danh mục lưu DB dùng chung toàn hệ thống (giống ProjectLabel — mục
 * E) để người dùng tự thêm loại mới ngay trong form tạo/sửa dự án (nút "+").
 * `projects.type` vẫn là cột string tự do, giờ lưu đúng tên loại (name ở
 * đây) thay vì key enum — xem migration
 * 2026_08_28_100003_convert_projects_type_to_free_text.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_types');
    }
};
