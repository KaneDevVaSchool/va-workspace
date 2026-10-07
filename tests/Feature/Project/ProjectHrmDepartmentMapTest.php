<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Services\ProjectHrmDepartmentMapper;
use Tests\TestCase;

class ProjectHrmDepartmentMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.hrm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'va_hrm_',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('hrm');

        $schema = Schema::connection('hrm');
        $schema->create('companies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
        });
        $schema->create('employees', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('company_email')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('org_units', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('type')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        $schema->create('employee_assignments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $table->unsignedBigInteger('open_primary_employee_id')->nullable();
        });

        $hrm = DB::connection('hrm');
        $hrm->table('companies')->insert([
            'id' => 1,
            'uuid' => 'co-1',
            'code' => 'VAS',
            'name' => 'VA Schools',
        ]);
        $hrm->table('org_units')->insert([
            'id' => 3,
            'uuid' => 'ou-dept',
            'company_id' => 1,
            'type' => 'department',
            'code' => 'VM_PCN',
            'name' => 'Phòng Công nghệ',
            'status' => 'active',
        ]);
        $hrm->table('org_units')->insert([
            'id' => 4,
            'uuid' => 'ou-unit',
            'parent_id' => 3,
            'company_id' => 1,
            'type' => 'unit',
            'code' => 'VM_PCN_PM',
            'name' => 'Phần mềm',
            'status' => 'active',
        ]);
        $hrm->table('employees')->insert([
            'id' => 9,
            'uuid' => 'emp-1',
            'company_email' => 'khoana@hcm.vaschools.edu.vn',
        ]);
        $hrm->table('employee_assignments')->insert([
            'employee_id' => 9,
            'org_unit_id' => 4,
            'open_primary_employee_id' => 9,
        ]);
    }

    public function test_project_department_follows_lead_hrm_department(): void
    {
        $this->seed(RoleSeeder::class);

        $stale = Department::query()->create([
            'code' => 'CN',
            'name' => 'Phòng Công nghệ',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'other-ou',
        ]);
        $hrm = Department::query()->create([
            'code' => 'VM_PCN',
            'name' => 'Phòng Công nghệ',
            'is_active' => true,
            'hrm_org_unit_uuid' => 'ou-dept',
        ]);

        $lead = User::factory()->create([
            'status' => 'active',
            'email' => 'khoana@hcm.vaschools.edu.vn',
            'hrm_employee_uuid' => 'emp-1',
            'department_id' => $stale->id,
        ]);
        $lead->roles()->sync(Role::query()->where('code', 'member')->pluck('id'));

        $project = Project::query()->create([
            'code' => 'PRJ9001',
            'type' => 'internal',
            'name' => 'Ticket',
            'progress_method' => 'average',
            'status' => 'planning',
            'importance' => 'important',
            'lead_user_id' => $lead->id,
            'created_by' => $lead->id,
            'executing_department_id' => $stale->id,
        ]);
        $project->executingDepartments()->sync([$stale->id]);
        $task = $project->tasks()->create([
            'type' => 'task',
            'title' => 'Việc của phòng',
            'status' => 'not_started',
            'assignee_id' => $lead->id,
            'created_by' => $lead->id,
        ]);

        $updated = app(ProjectHrmDepartmentMapper::class)->mapAll();

        $this->assertSame(1, $updated);
        $project->refresh();
        $this->assertSame($hrm->id, $project->owner_department_id);
        $this->assertSame($hrm->id, $project->lead_department_id);
        $this->assertSame($hrm->id, $project->executing_department_id);
        $this->assertSame([$hrm->id], $project->executingDepartments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($hrm->id, $task->fresh()->origin_department_id);
        $this->assertSame($stale->id, $lead->fresh()->department_id);

        $projectIds = collect($this->actingAs($lead)->getJson('/api/project')->json('projects'))->pluck('id')->all();
        $this->assertContains($project->id, $projectIds);

        $taskIds = collect($this->actingAs($lead)->getJson('/api/project/tasks')->json('tasks'))->pluck('id')->all();
        $this->assertContains($task->id, $taskIds);
    }
}
