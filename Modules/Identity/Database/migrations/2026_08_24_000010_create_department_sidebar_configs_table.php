<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình hiển thị menu sidebar THEO PHÒNG BAN — department_director/
 * deputy_department_director tự bật/tắt các mục menu đã đánh dấu
 * `configurableByDepartment: true` trong AppSidebar.vue, chỉ ảnh hưởng
 * phòng ban của chính họ.
 *
 * Chỉ lưu row khi KHÁC mặc định (is_visible=false, tức đã bị tắt) —
 * không có row cho 1 cặp (department_id, menu_key) = mặc định hiện. Cách
 * này tránh phải seed hàng loạt row khi thêm phòng ban mới, và giữ bảng
 * nhỏ gọn vì đa số phòng ban không tắt gì.
 *
 * Đặt trong module Identity (không phải WorkspaceConfig) vì gắn chặt vòng
 * đời User/Department, theo đúng tiền lệ bảng `teams` — WorkspaceConfig
 * chỉ chứa Controller/Service điều phối UI, gọi lại Repository interface
 * ở đây.
 *
 * Xem plans/... (Giai đoạn A — Cấu hình Workspace theo phòng ban).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_sidebar_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('menu_key');
            $table->boolean('is_visible')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'menu_key'], 'dept_sidebar_configs_department_menu_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_sidebar_configs');
    }
};
