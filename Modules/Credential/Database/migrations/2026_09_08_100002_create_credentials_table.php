<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tài khoản dịch vụ (Credential module). `password` được cast `encrypted`
 * trong Model (Modules/Credential/App/Models/Credential.php) — cột lưu
 * chuỗi mã hoá, KHÔNG bao giờ lưu plaintext.
 *
 * Trạng thái (đang dùng/chuẩn bị gia hạn/sắp hết hạn/đã hết hạn) không lưu
 * cột riêng — tính từ `expires_at` qua accessor Credential::getStatusAttribute().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('provider_id')->nullable()->constrained('credential_providers')->nullOnDelete();
            $table->string('account_type', 30)->default('user');

            // Đăng nhập
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->text('password')->nullable();

            // Đăng nhập Google công ty
            $table->boolean('is_google_login')->default(false);
            $table->string('google_account_owner')->nullable();

            // Hạ tầng (server/VPS/domain/database)
            $table->string('server_name')->nullable();
            $table->string('vps_cluster')->nullable();
            $table->string('domain')->nullable();
            $table->string('database_name')->nullable();
            $table->boolean('is_root_account')->default(false);
            $table->boolean('is_iam_account')->default(false);

            $table->text('notes')->nullable();

            // Nâng cao
            $table->date('purchased_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('monthly_cost', 12, 2)->nullable();
            $table->boolean('cost_hidden')->default(false);
            $table->string('currency', 10)->default('VND');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id']);
            $table->index(['account_type']);
            $table->index(['expires_at']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
