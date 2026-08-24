<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lối tắt trang (bookmark) theo từng tài khoản — mở từ header, giống
 * popover "Lối tắt" của 1Office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_shortcuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('path', 512);
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shortcuts');
    }
};
