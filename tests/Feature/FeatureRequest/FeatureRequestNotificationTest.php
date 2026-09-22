<?php

namespace Tests\Feature\FeatureRequest;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Tests\TestCase;

class FeatureRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(array $attributes = [], array $roles = []): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));

        if ($roles !== []) {
            $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');
        }

        return $user;
    }

    public function test_creating_a_request_notifies_only_one_super_admin(): void
    {
        $author = $this->makeUser(['name' => 'Chị Giàu'], ['member']);
        $firstAdmin = $this->makeUser(['name' => 'Admin A'], ['super_admin']);
        $secondAdmin = $this->makeUser(['name' => 'Admin B'], ['super_admin']);
        $other = $this->makeUser(['name' => 'Người khác']);

        $this->actingAs($author)
            ->postJson('/api/feature-requests', [
                'description' => 'Cần thêm bộ lọc theo phòng ban.',
            ])
            ->assertCreated();

        $this->assertSame(1, UserNotification::query()->where('type', 'feature_request_created')->count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $firstAdmin->id,
            'actor_id' => $author->id,
            'type' => 'feature_request_created',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $secondAdmin->id,
            'type' => 'feature_request_created',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $other->id,
            'type' => 'feature_request_created',
        ]);
    }

    public function test_review_result_is_only_sent_to_the_creator(): void
    {
        $author = $this->makeUser(['name' => 'Chị Giàu'], ['member']);
        $reviewer = $this->makeUser(['name' => 'Admin A'], ['super_admin']);
        $other = $this->makeUser(['name' => 'Người khác']);

        $id = $this->actingAs($author)
            ->postJson('/api/feature-requests', [
                'description' => 'Cần thêm bộ lọc theo phòng ban.',
            ])
            ->assertCreated()
            ->json('item.id');

        UserNotification::query()->delete();

        $this->actingAs($reviewer)
            ->patchJson("/api/superadmin/feature-requests/{$id}/reject", [
                'reject_reason' => 'Chưa đủ thông tin.',
            ])
            ->assertOk();

        $this->assertSame(1, UserNotification::query()->count());
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $author->id,
            'actor_id' => $reviewer->id,
            'type' => 'feature_request_rejected',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $other->id,
        ]);
    }
}
