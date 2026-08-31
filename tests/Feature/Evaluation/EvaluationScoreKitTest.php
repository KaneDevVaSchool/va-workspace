<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Evaluation\App\Models\EvaluationCriteria;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class EvaluationScoreKitTest extends TestCase
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

    private function makeScale(Department $dept, string $name, bool $forTaskType = false): EvaluationCriteria
    {
        return EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => $name,
            'type' => 'scale',
            'levels' => [
                ['code' => 'M1', 'label' => 'Khá', 'score' => 80],
                ['code' => 'M2', 'label' => 'Tốt', 'score' => 90],
            ],
            'is_active' => true,
            'use_for_task_type' => $forTaskType,
            'sort_order' => 0,
        ]);
    }

    public function test_director_gets_default_kit_when_none_saved(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->getJson('/api/evaluation/score-kit')
            ->assertOk()
            ->assertJsonPath('kit.department_id', $dept->id)
            ->assertJsonPath('kit.mode', null)
            ->assertJsonPath('kit.base_score', 100)
            ->assertJsonPath('kit.use_project_importance', false)
            ->assertJsonPath('kit.id', null)
            ->assertJsonPath('kit.task_base_score', 100)
            ->assertJsonPath('kit.base_adjust_levels.0.label', 'Xuất sắc')
            ->assertJsonPath('kit.base_adjust_levels.0.sort_order', 0)
            ->assertJsonPath('kit.base_adjust_levels.4.label', 'Chưa đạt')
            ->assertJsonPath('kit.base_adjust_levels.4.sort_order', 4)
            ->assertJsonPath('kit.weighted_task_levels.0.label', 'Rất khó')
            ->assertJsonPath('kit.weighted_task_levels.3.label', 'Dễ')
            ->assertJsonPath('kit.progress_levels.0.label', 'Sớm ≥20%')
            ->assertJsonPath('kit.progress_levels.2.label', 'Đúng hạn')
            ->assertJsonPath('kit.quality_levels.0.label', 'Xuất sắc')
            ->assertJsonPath('kit.quality_levels.0.score', 1)
            ->assertJsonPath('kit.quality_bonus_percent', 5)
            ->assertJsonPath('kit.performance_levels.0.label', 'Vượt kỳ vọng')
            ->assertJsonPath('kit.performance_levels.5.label', 'Không đạt')
            ->assertJsonPath('kit.formula.base', 'on')
            ->assertJsonPath('kit.formula.done', 'add')
            ->assertJsonPath('kit.formula.undone', 'add')
            ->assertJsonPath('kit.formula.weight', 'on')
            ->assertJsonPath('kit.formula.progress', 'on')
            ->assertJsonPath('kit.formula.quality', 'on')
            ->assertJsonPath('kit.formula.contrib', 'off')
            ->assertJsonPath('kit.formula.lock_difficulty', 'on')
            ->assertJsonPath('kit.formula.project', 'off');
    }

    public function test_score_kit_loads_matching_scales_from_department_criteria(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $classification = $this->makeScale($dept, 'Thang xếp loại công việc');
        $difficulty = $this->makeScale($dept, 'Mức độ quan trọng', true);
        $progress = $this->makeScale($dept, 'Đúng hạn & tiến độ');
        $quality = $this->makeScale($dept, 'Chất lượng công việc');

        $this->actingAs($director)
            ->getJson('/api/evaluation/score-kit')
            ->assertOk()
            ->assertJsonPath('kit.classification_criterion_id', $classification->id)
            ->assertJsonPath('kit.difficulty_criterion_id', $difficulty->id)
            ->assertJsonPath('kit.progress_criterion_id', $progress->id)
            ->assertJsonPath('kit.quality_criterion_id', $quality->id)
            ->assertJsonPath('kit.base_adjust_levels.0.label', 'Khá')
            ->assertJsonPath('kit.performance_levels.1.label', 'Tốt')
            ->assertJsonPath('kit.weighted_task_levels.0.code', 'M1')
            ->assertJsonPath('kit.progress_levels.1.score', 90)
            ->assertJsonPath('kit.quality_levels.0.label', 'Khá');
    }

    public function test_selected_scale_criterion_overrides_automatic_match(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $this->makeScale($dept, 'Chất lượng công việc');
        $selected = $this->makeScale($dept, 'Chuẩn nghiệm thu');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'quality_criterion_id' => $selected->id,
            ])
            ->assertOk()
            ->assertJsonPath('kit.quality_criterion_id', $selected->id)
            ->assertJsonPath('kit.quality_criterion.name', 'Chuẩn nghiệm thu')
            ->assertJsonPath('kit.quality_levels.1.label', 'Tốt');

        $this->assertDatabaseHas('evaluation_score_kits', [
            'department_id' => $dept->id,
            'quality_criterion_id' => $selected->id,
        ]);
    }

    public function test_department_can_restore_a_scale_to_system_defaults(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $this->makeScale($dept, 'Chất lượng công việc');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'quality_use_default' => true,
            ])
            ->assertOk()
            ->assertJsonPath('kit.quality_use_default', true)
            ->assertJsonPath('kit.quality_criterion_id', null)
            ->assertJsonPath('kit.quality_levels.0.label', 'Xuất sắc')
            ->assertJsonPath('kit.quality_levels.3.label', 'Không đạt');

        $this->assertDatabaseHas('evaluation_score_kits', [
            'department_id' => $dept->id,
            'quality_criterion_id' => null,
            'quality_use_default' => true,
        ]);
    }

    public function test_director_saves_formula(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'formula' => [
                    'base' => 'on',
                    'done' => 'sub',
                    'undone' => 'off',
                    'weight' => 'on',
                    'project' => 'off',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('kit.formula.base', 'on')
            ->assertJsonPath('kit.formula.done', 'sub')
            ->assertJsonPath('kit.formula.undone', 'off')
            ->assertJsonPath('kit.formula.project', 'off')
            ->assertJsonPath('kit.use_project_importance', false);
    }

    public function test_rejects_invalid_formula_op(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'formula' => ['done' => 'multiply'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['formula.done']);
    }

    public function test_director_saves_base_adjust_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($dept, 'Xếp loại tháng');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_score' => 100,
                'points_per_completed_task' => 2,
                'points_per_incomplete_task' => -3,
                'classification_criterion_id' => $scale->id,
            ])
            ->assertOk()
            ->assertJsonPath('kit.mode', 'base_adjust')
            ->assertJsonPath('kit.base_score', 100)
            ->assertJsonPath('kit.points_per_completed_task', 2)
            ->assertJsonPath('kit.points_per_incomplete_task', -3)
            ->assertJsonPath('kit.classification_criterion_id', $scale->id);

        $this->assertDatabaseHas('evaluation_score_kits', [
            'department_id' => $dept->id,
            'mode' => 'base_adjust',
        ]);
    }

    public function test_director_saves_department_scales(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $baseLevels = [
            ['code' => 'A', 'label' => 'Vượt', 'score' => 120],
            ['code' => 'B', 'label' => 'Giỏi', 'score' => 105],
            ['code' => 'C', 'label' => 'Ổn', 'score' => 95],
            ['code' => 'D', 'label' => 'Đạt', 'score' => 80],
            ['code' => 'E', 'label' => 'Yếu', 'score' => 0],
        ];
        $weightLevels = [
            ['code' => '1', 'label' => 'Cực khó', 'score' => 1.5],
            ['code' => '2', 'label' => 'Khó', 'score' => 1.2],
            ['code' => '3', 'label' => 'Vừa', 'score' => 1],
            ['code' => '4', 'label' => 'Dễ', 'score' => 0.8],
        ];

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_adjust_levels' => $baseLevels,
                'weighted_task_levels' => $weightLevels,
            ])
            ->assertOk()
            ->assertJsonPath('kit.base_adjust_levels.0.label', 'Vượt')
            ->assertJsonPath('kit.base_adjust_levels.0.score', 120)
            ->assertJsonPath('kit.weighted_task_levels.0.label', 'Cực khó')
            ->assertJsonPath('kit.weighted_task_levels.0.score', 1.5);

        $log = ActivityLog::query()->latest('id')->firstOrFail();
        $this->assertSame('XS', $log->properties['changes']['base_adjust_levels.0.code']['before']);
        $this->assertSame('A', $log->properties['changes']['base_adjust_levels.0.code']['after']);
        $this->assertArrayNotHasKey('base_adjust_levels', $log->properties['changes']);
    }

    public function test_director_saves_variable_classification_levels(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $levels = [
            ['code' => 'XS', 'label' => 'Xuất sắc', 'score' => 110, 'sort_order' => 0],
            ['code' => 'DAT', 'label' => 'Đạt', 'score' => 90, 'sort_order' => 1],
            ['code' => 'CD', 'label' => 'Chưa đạt', 'score' => 0, 'sort_order' => 2],
        ];

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_adjust_levels' => $levels,
            ])
            ->assertOk()
            ->assertJsonCount(3, 'kit.base_adjust_levels')
            ->assertJsonPath('kit.base_adjust_levels.0.label', 'Xuất sắc')
            ->assertJsonPath('kit.base_adjust_levels.1.label', 'Đạt')
            ->assertJsonPath('kit.base_adjust_levels.2.label', 'Chưa đạt')
            ->assertJsonPath('kit.base_adjust_levels.2.sort_order', 2);
    }

    public function test_rejects_classification_levels_below_minimum(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_adjust_levels' => [
                    ['code' => 'XS', 'label' => 'Xuất sắc', 'score' => 110],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['base_adjust_levels']);
    }

    public function test_director_saves_weighted_task_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'weighted_task',
                'task_base_score' => 100,
                'formula' => [
                    'weight' => 'on',
                    'progress' => 'on',
                    'quality' => 'off',
                    'lock_difficulty' => 'on',
                ],
                'quality_bonus_percent' => 5,
                'progress_levels' => [
                    ['code' => 'S20', 'label' => 'Sớm ≥20%', 'score' => 1.1],
                    ['code' => 'S5', 'label' => 'Sớm dưới 20%', 'score' => 1.05],
                    ['code' => 'DH', 'label' => 'Đúng hạn', 'score' => 1],
                    ['code' => 'T2', 'label' => 'Trễ 1–2 ngày', 'score' => 0.9],
                    ['code' => 'T5', 'label' => 'Trễ 3–5 ngày', 'score' => 0.75],
                    ['code' => 'T6', 'label' => 'Trễ hơn 5 ngày', 'score' => 0.5],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('kit.mode', 'weighted_task')
            ->assertJsonPath('kit.task_base_score', 100)
            ->assertJsonPath('kit.formula.quality', 'off')
            ->assertJsonPath('kit.formula.lock_difficulty', 'on')
            ->assertJsonPath('kit.quality_bonus_percent', 5)
            ->assertJsonPath('kit.progress_levels.4.score', 0.75);
    }

    public function test_score_kit_change_records_detailed_before_and_after_activity(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_score' => 100,
                'change_context' => 'manual',
            ])
            ->assertOk();

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'weighted_task',
                'base_score' => 120,
                'formula' => ['done' => 'sub'],
                'change_context' => 'mode_change',
            ])
            ->assertOk();

        $log = ActivityLog::query()
            ->where('action', 'evaluation_score_kit.mode_change')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($director->id, $log->actor_id);
        $this->assertSame('evaluation_score_kit', $log->subject_type);
        $this->assertSame($dept->id, $log->properties['department_id']);
        $this->assertSame('Đổi cách tính', $log->properties['change_context']);
        $this->assertSame('base_adjust', $log->properties['changes']['mode']['before']);
        $this->assertSame('weighted_task', $log->properties['changes']['mode']['after']);
        $this->assertEquals(100, $log->properties['changes']['base_score']['before']);
        $this->assertEquals(120, $log->properties['changes']['base_score']['after']);
        $this->assertSame('add', $log->properties['changes']['formula.done']['before']);
        $this->assertSame('sub', $log->properties['changes']['formula.done']['after']);
        $this->assertArrayNotHasKey('formula', $log->properties['changes']);
        $this->assertContains('Cách tính điểm', $log->properties['changed_fields']);
        $this->assertContains('Điểm khởi đầu', $log->properties['changed_fields']);
    }

    public function test_reset_context_is_logged_as_reset_only_when_values_return_to_defaults(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_score' => 135,
                'change_context' => 'manual',
            ])
            ->assertOk();

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'base_score' => 100,
                'change_context' => 'reset',
            ])
            ->assertOk();

        $log = ActivityLog::query()->latest('id')->firstOrFail();

        $this->assertSame('evaluation_score_kit.reset', $log->action);
        $this->assertEquals(135, $log->properties['changes']['base_score']['before']);
        $this->assertEquals(100, $log->properties['changes']['base_score']['after']);
    }

    public function test_rejects_behavior_criterion_as_classification(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $behavior = EvaluationCriteria::query()->create([
            'department_id' => $dept->id,
            'name' => 'Đi muộn',
            'type' => 'behavior',
            'levels' => [
                ['code' => 'H1', 'label' => 'Đi muộn', 'score' => -1],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'classification_criterion_id' => $behavior->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['classification_criterion_id']);
    }

    public function test_rejects_classification_from_other_department(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $other = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($other, 'Thang phòng khác');

        $this->actingAs($director)
            ->putJson('/api/evaluation/score-kit', [
                'mode' => 'base_adjust',
                'classification_criterion_id' => $scale->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['classification_criterion_id']);
    }

    public function test_member_cannot_view_or_update_kit(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $member = $this->makeUser(['department_id' => $dept->id], ['member']);

        $this->actingAs($member)
            ->getJson('/api/evaluation/score-kit')
            ->assertForbidden();

        $this->actingAs($member)
            ->putJson('/api/evaluation/score-kit', ['mode' => 'base_adjust'])
            ->assertForbidden();
    }

    public function test_show_includes_assigned_task_type_criterion(): void
    {
        $this->seed(RoleSeeder::class);
        $dept = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);
        $scale = $this->makeScale($dept, 'Mức độ quan trọng', true);

        $this->actingAs($director)
            ->getJson('/api/evaluation/score-kit')
            ->assertOk()
            ->assertJsonPath('kit.task_type_criterion_id', $scale->id)
            ->assertJsonPath('kit.task_type_criterion.name', 'Mức độ quan trọng');
    }
}
