<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gắn tệp dự án vào thư mục (null = gốc "Tài liệu dự án").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_folders', 'id', 'project_attachments_folder_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            // Tên constraint đặt tay ở up() ('project_attachments_folder_fk')
            // khác convention mặc định mà dropConstrainedForeignId() đoán
            // ('..._folder_id_foreign') — phải drop đúng tên đã đặt.
            $table->dropForeign('project_attachments_folder_fk');
            $table->dropColumn('folder_id');
        });
    }
};
