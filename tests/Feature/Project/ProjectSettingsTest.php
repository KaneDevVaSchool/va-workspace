<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\ProjectSetting;
use Tests\TestCase;

class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'code_pattern' => 'DA_{date,"m/Y"}_{count}',
            'code_counter' => 344,
            'auto_start_on_begin_date' => false,
            'shift_task_dates_with_project' => false,
            'hide_cross_tasks_from_assignees' => false,
            'hide_child_tasks_from_followers' => false,
            'constrain_task_dates_to_project' => false,
        ], $overrides);
    }

    public function test_defaults_use_amis_code_pattern_and_counter_344(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 8, 27, 9, 0, 0));

        $admin = $this->makeUser([], ['admin']);

        $this->actingAs($admin)
            ->getJson('/api/project/settings/general')
            ->assertOk()
            ->assertJsonPath('code_pattern', 'DA_{date,"m/Y"}_{count}')
            ->assertJsonPath('code_counter', 344)
            ->assertJsonPath('next_code_preview', 'DA_08/2026_344')
            ->assertJsonPath('auto_start_on_begin_date', false)
            ->assertJsonPath('shift_task_dates_with_project', false)
            ->assertJsonPath('hide_cross_tasks_from_assignees', false)
            ->assertJsonPath('hide_child_tasks_from_followers', false)
            ->assertJsonPath('constrain_task_dates_to_project', false);
    }

    public function test_admin_can_update_rules_and_editable_counter(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 3, 15, 9, 0, 0));

        $admin = $this->makeUser([], ['admin']);

        $this->actingAs($admin)
            ->putJson('/api/project/settings/general', $this->settingsPayload([
                'code_pattern' => 'DA_{date,"m/Y"}_{count}',
                'code_counter' => 350,
                'auto_start_on_begin_date' => true,
                'shift_task_dates_with_project' => true,
                'hide_cross_tasks_from_assignees' => true,
                'hide_child_tasks_from_followers' => true,
                'constrain_task_dates_to_project' => true,
            ]))
            ->assertOk()
            ->assertJsonPath('code_counter', 350)
            ->assertJsonPath('next_code_preview', 'DA_03/2026_350')
            ->assertJsonPath('auto_start_on_begin_date', true)
            ->assertJsonPath('shift_task_dates_with_project', true)
            ->assertJsonPath('hide_cross_tasks_from_assignees', true)
            ->assertJsonPath('hide_child_tasks_from_followers', true)
            ->assertJsonPath('constrain_task_dates_to_project', true);

        $this->assertDatabaseHas('project_settings', [
            'code_counter' => 350,
            'auto_start_on_begin_date' => 1,
            'shift_task_dates_with_project' => 1,
        ]);
    }

    public function test_code_pattern_without_count_token_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = $this->makeUser([], ['admin']);

        $this->actingAs($admin)
            ->putJson('/api/project/settings/general', $this->settingsPayload([
                'code_pattern' => 'DA_{date,"m/Y"}',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code_pattern']);
    }

    public function test_creating_project_uses_current_counter_then_increments(): void
    {
        $this->seed(RoleSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 8, 27, 9, 0, 0));

        $dept = Department::query()->create(['code' => 'IT', 'name' => 'Công nghệ', 'is_active' => true]);
        $admin = $this->makeUser(['department_id' => $dept->id], ['admin']);

        $this->actingAs($admin)
            ->postJson('/api/project', [
                'type' => 'internal',
                'name' => 'Dự án A',
            ])
            ->assertCreated()
            ->assertJsonPath('project.code', 'DA_08/2026_344');

        $this->assertSame(345, ProjectSetting::query()->value('code_counter'));
    }

    public function test_team_lead_cannot_manage_settings(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->makeUser([], ['team_lead']);

        $this->actingAs($user)
            ->getJson('/api/project/settings/general')
            ->assertForbidden();
    }
}
