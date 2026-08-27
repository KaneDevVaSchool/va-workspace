<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng nối gán nhãn cho dự án (N-N project <-> project_labels).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_label_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects', 'id', 'project_label_assignments_project_fk')
                ->cascadeOnDelete();
            $table->foreignId('project_label_id')
                ->constrained('project_labels', 'id', 'project_label_assignments_label_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'project_label_id'], 'project_label_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_label_assignments');
    }
};
