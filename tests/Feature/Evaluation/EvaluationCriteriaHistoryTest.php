<?php

namespace Tests\Feature\Evaluation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\ActivityLog;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class EvaluationCriteriaHistoryTest extends TestCase
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
    private function criterionPayload(string $name): array
    {
        return [
            'name' => $name,
            'type' => 'scale',
            'levels' => [
                ['code' => 'M1', 'label' => 'Đạt', 'score' => 1],
            ],
        ];
    }

    public function test_director_sees_department_criteria_history(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $other = Department::query()->create(['code' => 'IT', 'name' => 'CNTT', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id, 'name' => 'Lê Thùy Dương'], ['department_director']);
        $otherDirector = $this->makeUser(['department_id' => $other->id], ['department_director']);

        $created = $this->actingAs($director)
            ->postJson('/api/evaluation/criteria', $this->criterionPayload('Sàn, trần, tường sạch sẽ'))
            ->assertCreated()
            ->json('criterion');

        $this->actingAs($director)
            ->putJson('/api/evaluation/criteria/'.$created['id'], array_merge(
                $this->criterionPayload('Sàn, trần, tường sạch sẽ'),
                ['description' => 'Cập nhật mô tả'],
            ))
            ->assertOk();

        $this->actingAs($otherDirector)
            ->postJson('/api/evaluation/criteria', $this->criterionPayload('Tiêu chí phòng khác'))
            ->assertCreated();

        ActivityLog::query()->create([
            'actor_id' => $director->id,
            'actor_name' => $director->name,
            'actor_email' => $director->email,
            'action' => 'auth.login',
            'description' => 'Đăng nhập',
            'created_at' => now(),
        ]);

        $this->actingAs($director)
            ->getJson('/api/evaluation/criteria/history')
            ->assertOk()
            ->assertJsonCount(2, 'logs')
            ->assertJsonPath('logs.0.verb', 'đã sửa')
            ->assertJsonPath('logs.0.actor_name', 'Lê Thùy Dương')
            ->assertJsonPath('logs.0.subject_id', $created['id'])
            ->assertJsonPath('logs.0.can_open', true)
            ->assertJsonPath('logs.1.verb', 'đã tạo')
            ->assertJsonFragment(['detail' => 'ID: '.$created['id'].' - Sàn, trần, tường sạch sẽ']);
    }

    public function test_history_includes_deleted_criteria_of_same_department(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'HR', 'name' => 'Nhân sự', 'is_active' => true]);
        $director = $this->makeUser(['department_id' => $dept->id], ['department_director']);

        $created = $this->actingAs($director)
            ->postJson('/api/evaluation/criteria', $this->criterionPayload('Tiêu chí sẽ xoá'))
            ->assertCreated()
            ->json('criterion');

        $this->actingAs($director)
            ->deleteJson('/api/evaluation/criteria/'.$created['id'])
            ->assertOk();

        $this->actingAs($director)
            ->getJson('/api/evaluation/criteria/history')
            ->assertOk()
            ->assertJsonPath('logs.0.verb', 'đã xoá')
            ->assertJsonPath('logs.0.can_open', false)
            ->assertJsonPath('logs.0.subject_id', $created['id']);
    }

    public function test_user_without_department_cannot_view_history(): void
    {
        $this->seed(RoleSeeder::class);

        $user = $this->makeUser(['department_id' => null], ['department_director']);

        $this->actingAs($user)
            ->getJson('/api/evaluation/criteria/history')
            ->assertStatus(422);
    }
}
