<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phòng ban — bảng phẳng, KHÔNG cây phân cấp (giữ đơn giản cho tới khi
 * có API HRM thật). Xem Modules/Identity/App/Repositories/Contracts/
 * DepartmentRepositoryInterface.php — điểm sẽ đổi thành client gọi API HRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
