<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('delivery_id')->unique();
            $table->string('event');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_webhook_deliveries');
    }
};
