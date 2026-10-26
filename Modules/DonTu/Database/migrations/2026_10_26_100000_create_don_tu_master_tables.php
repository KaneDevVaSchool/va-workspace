<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('don_tu_status_sets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('don_tu_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_set_id')
                ->constrained('don_tu_status_sets', 'id', 'dt_status_set_fk')
                ->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('label', 255);
            $table->string('group_kind', 32);
            $table->string('terminal_kind', 32)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['status_set_id', 'code'], 'dt_status_set_code_uq');
        });

        Schema::create('don_tu_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_set_id')
                ->constrained('don_tu_status_sets', 'id', 'dt_trans_set_fk')
                ->cascadeOnDelete();
            $table->string('from_code', 64);
            $table->string('to_code', 64);
            $table->string('action_key', 64);
            $table->string('actor_policy', 64);
            $table->json('guard_json')->nullable();
            $table->unique(
                ['status_set_id', 'from_code', 'action_key', 'to_code'],
                'dt_trans_uq',
            );
        });

        Schema::create('don_tu_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('scope', 16)->default('internal');
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'dt_form_dept_fk')
                ->nullOnDelete();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->json('schema_json')->nullable();
            $table->json('branding_json')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'dt_form_creator_fk')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users', 'id', 'dt_form_updater_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'status'], 'dt_form_dept_status_idx');
        });

        Schema::create('don_tu_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('status_set_id')
                ->constrained('don_tu_status_sets', 'id', 'dt_wf_set_fk')
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments', 'id', 'dt_wf_dept_fk')
                ->nullOnDelete();
            $table->boolean('is_financial')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('initiator_policy', 16)->default('staff');
            $table->string('closure_actor_policy', 32)->default('requester');
            $table->string('closure_role', 64)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'id', 'dt_wf_creator_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'is_active'], 'dt_wf_dept_active_idx');
        });

        Schema::create('don_tu_workflow_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('don_tu_workflows', 'id', 'dt_wff_wf_fk')
                ->cascadeOnDelete();
            $table->foreignId('form_template_id')
                ->constrained('don_tu_form_templates', 'id', 'dt_wff_form_fk')
                ->restrictOnDelete();
            $table->string('form_key', 32);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->boolean('is_start')->default(false);
            $table->unique(['workflow_id', 'form_key'], 'dt_wff_key_uq');
        });

        Schema::create('don_tu_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('don_tu_workflows', 'id', 'dt_wfs_wf_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name', 255);
            $table->string('status_group_code', 64);
            $table->string('step_type', 32);
            $table->json('target_form_keys')->nullable();
            $table->string('assignee_policy', 32)->default('role');
            $table->string('assignee_role', 64)->nullable();
            $table->foreignId('assignee_user_id')
                ->nullable()
                ->constrained('users', 'id', 'dt_wfs_user_fk')
                ->nullOnDelete();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->string('sign_mode', 16)->default('online');
            $table->boolean('workspace_task')->default(false);
            $table->boolean('allow_reject')->default(true);
            $table->boolean('allow_request_changes')->default(true);
            $table->boolean('blocks_cancel')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('don_tu_workflow_steps');
        Schema::dropIfExists('don_tu_workflow_forms');
        Schema::dropIfExists('don_tu_workflows');
        Schema::dropIfExists('don_tu_form_templates');
        Schema::dropIfExists('don_tu_transitions');
        Schema::dropIfExists('don_tu_statuses');
        Schema::dropIfExists('don_tu_status_sets');
    }
};
