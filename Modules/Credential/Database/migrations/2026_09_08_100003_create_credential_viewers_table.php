<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh sách user được người tạo 1 credential cấp quyền xem dữ liệu nhạy
 * cảm (password/username thật) của riêng credential đó — giống chia sẻ
 * file. Không liên quan đến permission `credential.manage` (quyền CRUD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['credential_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_viewers');
    }
};
