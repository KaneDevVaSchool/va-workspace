<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cấu hình ẩn/hiện menu sidebar Ở MỨC TOÀN HỆ THỐNG — chỉ super_admin cấu
 * hình được (permission workspace_config.manage_global_menu, reserved).
 * Khi 1 menu_key bị ẩn ở đây, mọi tài khoản KHÔNG PHẢI super_admin (bất kỳ
 * phòng ban nào) đều không thấy/dùng được, kể cả khi department_director
 * đã bật riêng qua department_sidebar_configs — global thắng tuyệt đối.
 *
 * Khác với department_sidebar_configs (theo phòng ban), bảng này không có
 * department_id — chỉ 1 dòng cho mỗi menu_key áp dụng toàn hệ thống.
 *
 * Chỉ lưu row khi đã từng bị đổi trạng thái (is_hidden=true) — không có
 * row cho 1 menu_key = mặc định hiện, giữ bảng nhỏ gọn.
 *
 * Đặt trong module Identity (không phải WorkspaceConfig) theo đúng tiền lệ
 * department_sidebar_configs — WorkspaceConfig chỉ chứa Controller/Service
 * điều phối UI, gọi lại Repository interface ở đây.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_menu_visibilities', function (Blueprint $table) {
            $table->id();
            $table->string('menu_key')->unique();
            $table->boolean('is_hidden')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_menu_visibilities');
    }
};
