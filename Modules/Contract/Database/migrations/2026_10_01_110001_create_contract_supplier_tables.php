<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_supplier_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique('contract_supplier_types_code_unique');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_supplier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_type_id')->constrained('contract_supplier_types')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['supplier_type_id', 'code'], 'contract_supplier_groups_type_code_unique');
        });

        Schema::create('contract_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique('contract_suppliers_code_unique');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('tax_code', 30)->nullable();
            $table->string('normalized_tax_code', 30)->nullable()->unique('contract_suppliers_tax_unique');
            $table->foreignId('supplier_type_id')->nullable()->constrained('contract_supplier_types')->nullOnDelete();
            $table->foreignId('supplier_group_id')->nullable()->constrained('contract_supplier_groups')->nullOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('representative_title')->nullable();
            $table->string('registered_address_line')->nullable();
            $table->string('registered_ward')->nullable();
            $table->string('registered_province')->nullable();
            $table->boolean('transaction_address_same_as_registered')->default(true);
            $table->string('transaction_address_line')->nullable();
            $table->string('transaction_ward')->nullable();
            $table->string('transaction_province')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('cooperation_started_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status'], 'contract_suppliers_dept_status_idx');
            $table->index(['owner_user_id'], 'contract_suppliers_owner_idx');
            $table->index(['supplier_type_id', 'supplier_group_id'], 'contract_suppliers_type_group_idx');
        });

        Schema::create('contract_supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('contract_suppliers')->cascadeOnDelete();
            $table->string('contact_type', 50)->nullable();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['supplier_id', 'is_primary'], 'contract_supplier_contacts_primary_idx');
        });

        Schema::create('contract_supplier_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('contract_suppliers')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_holder');
            $table->string('account_number');
            $table->string('currency', 10)->default('VND');
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'is_default'], 'contract_supplier_banks_default_idx');
        });

        Schema::create('contract_supplier_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique('contract_doc_types_code_unique');
            $table->string('name');
            $table->string('group_label', 80)->default('Khác');
            $table->boolean('has_expiry')->default(false);
            $table->boolean('allows_no_expiry')->default(true);
            $table->boolean('requires_review')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_supplier_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('contract_supplier_document_types')->cascadeOnDelete();
            $table->foreignId('supplier_type_id')->nullable()->constrained('contract_supplier_types')->cascadeOnDelete();
            $table->foreignId('supplier_group_id')->nullable()->constrained('contract_supplier_groups')->cascadeOnDelete();
            $table->string('requirement', 20)->default('required');
            $table->string('reviewer_role', 80)->nullable();
            $table->timestamps();

            $table->unique(['document_type_id', 'supplier_type_id', 'supplier_group_id'], 'contract_doc_req_unique');
        });

        Schema::create('contract_supplier_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('contract_suppliers')->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('contract_supplier_document_types')->nullOnDelete();
            $table->string('custom_name')->nullable();
            $table->string('status', 30)->default('not_provided');
            $table->unsignedInteger('current_version')->default(0);
            $table->string('document_number')->nullable();
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('no_expiry')->default(false);
            $table->text('uploader_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'document_type_id'], 'contract_supplier_doc_unique');
            $table->index(['supplier_id', 'status'], 'contract_supplier_docs_status_idx');
            $table->index(['expires_at'], 'contract_supplier_docs_expires_idx');
        });

        Schema::create('contract_supplier_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_document_id')->constrained('contract_supplier_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 30)->default('provided');
            $table->text('uploader_note')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_document_id', 'version'], 'contract_doc_versions_unique');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('contract_suppliers')->nullOnDelete();
            $table->string('code', 30)->unique('contracts_code_unique');
            $table->string('contract_number')->nullable();
            $table->string('title');
            $table->string('type', 80)->nullable();
            $table->string('status', 40)->default('drafting');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('signed_at')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->decimal('value_before_tax', 18, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('value_after_tax', 18, 2)->nullable();
            $table->string('currency', 10)->default('VND');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status'], 'contracts_supplier_status_idx');
            $table->index(['department_id'], 'contracts_department_idx');
            $table->index(['ends_at'], 'contracts_ends_at_idx');
        });

        Schema::create('contract_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_code')->nullable();
            $table->string('action', 80);
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'contract_audit_subject_idx');
            $table->index(['action'], 'contract_audit_action_idx');
            $table->index(['actor_id'], 'contract_audit_actor_idx');
            $table->index(['created_at'], 'contract_audit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_audit_logs');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_supplier_document_versions');
        Schema::dropIfExists('contract_supplier_documents');
        Schema::dropIfExists('contract_supplier_document_requirements');
        Schema::dropIfExists('contract_supplier_document_types');
        Schema::dropIfExists('contract_supplier_bank_accounts');
        Schema::dropIfExists('contract_supplier_contacts');
        Schema::dropIfExists('contract_suppliers');
        Schema::dropIfExists('contract_supplier_groups');
        Schema::dropIfExists('contract_supplier_types');
    }
};
