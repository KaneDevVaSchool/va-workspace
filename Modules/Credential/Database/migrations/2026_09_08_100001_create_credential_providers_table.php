<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Danh mục nhà cung cấp dịch vụ (Google, Canva, Cursor, Claude, AWS, VPS,
 * Database, IAM, Domain...) — quản lý được qua UI, không hard-code enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('category', 100)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_providers');
    }
};
