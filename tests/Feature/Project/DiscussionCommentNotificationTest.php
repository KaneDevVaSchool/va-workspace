<?php

namespace Tests\Feature\Project;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\App\Models\Department;
use Modules\Identity\App\Models\Role;
use Modules\Identity\App\Models\UserNotification;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\Task;
use Tests\TestCase;

class DiscussionCommentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = [], array $roles = ['department_director']): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->whereIn('code', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        return $user;
    }

    private function makeProject(array $attributes = []): Project
    {
        return Project::query()->create(array_merge([
            'code' => 'PRJ'.random_int(1000, 999999),
            'type' => 'internal',
            'name' => 'Dự án thử nghiệm',
            'progress_method' => 'average',
            'status' => 'planning',
            'importance' => 'important',
        ], $attributes));
    }

    private function makeTask(Project $project, array $attributes = []): Task
    {
        return Task::query()->create(array_merge([
            'project_id' => $project->id,
            'type' => 'task',
            'title' => 'Công việc thử nghiệm',
            'status' => 'not_started',
        ], $attributes));
    }

    public function test_commenting_on_task_notifies_previous_participant(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $userA = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người A']);
        $userB = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người B']);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $userA->id]);
        $task = $this->makeTask($project);

        $this->actingAs($userA)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận đầu tiên',
        ])->assertCreated();

        $this->actingAs($userB)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận tiếp theo',
        ])->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $userA->id,
            'actor_id' => $userB->id,
            'type' => 'discussion_comment',
        ]);
    }

    public function test_mentioning_user_in_task_comment_notifies_them_even_if_not_participant(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $author = $this->makeUser(['department_id' => $dept->id, 'name' => 'Tác giả']);
        $mentioned = $this->makeUser(['department_id' => $dept->id, 'name' => 'Được nhắc']);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $author->id]);
        $task = $this->makeTask($project);

        $this->actingAs($author)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => '<p>Nhắc <span class="mention" data-mention-id="'.$mentioned->id.'">@Được nhắc</span></p>',
        ])->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $mentioned->id,
            'actor_id' => $author->id,
            'type' => 'discussion_comment',
        ]);
    }

    public function test_author_is_not_notified_for_their_own_comment(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $author = $this->makeUser(['department_id' => $dept->id]);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $author->id]);
        $task = $this->makeTask($project);

        $this->actingAs($author)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận của chính mình',
        ])->assertCreated();

        $this->actingAs($author)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận tiếp theo, vẫn của chính mình',
        ])->assertCreated();

        $this->assertSame(0, UserNotification::query()->where('type', 'discussion_comment')->count());
    }

    public function test_commenting_on_project_discussion_notifies_previous_participant(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $userA = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người A']);
        $userB = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người B']);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $userA->id]);

        $this->actingAs($userA)->postJson("/api/project/{$project->id}/comments", [
            'content' => 'Thảo luận dự án',
        ])->assertCreated();

        $this->actingAs($userB)->postJson("/api/project/{$project->id}/comments", [
            'content' => 'Tiếp tục thảo luận',
        ])->assertCreated();

        $notification = UserNotification::query()
            ->where('user_id', $userA->id)
            ->where('type', 'discussion_comment')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/manager/project/{$project->id}?tab=discussion", $notification->url);
    }

    public function test_user_who_never_participated_and_not_mentioned_does_not_get_notified(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $userA = $this->makeUser(['department_id' => $dept->id]);
        $userB = $this->makeUser(['department_id' => $dept->id]);
        $outsider = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người ngoài cuộc']);

        $project = $this->makeProject([
            'owner_department_id' => $dept->id,
            'created_by' => $userA->id,
        ]);
        $task = $this->makeTask($project, ['assignee_id' => $outsider->id]);

        $this->actingAs($userA)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận đầu tiên',
        ])->assertCreated();

        $this->actingAs($userB)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận tiếp theo',
        ])->assertCreated();

        $this->assertSame(0, UserNotification::query()
            ->where('user_id', $outsider->id)
            ->where('type', 'discussion_comment')
            ->count());
    }

    public function test_reply_mention_via_parent_comment_notifies_original_commenter(): void
    {
        $this->seed(RoleSeeder::class);

        $dept = Department::query()->create(['code' => 'A', 'name' => 'Phòng A', 'is_active' => true]);
        $original = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người viết gốc']);
        $replier = $this->makeUser(['department_id' => $dept->id, 'name' => 'Người trả lời']);

        $project = $this->makeProject(['owner_department_id' => $dept->id, 'created_by' => $original->id]);
        $task = $this->makeTask($project);

        $parentId = $this->actingAs($original)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Bình luận gốc',
        ])->assertCreated()->json('comment.id');

        // $replier chưa từng comment trước đó — chỉ nhận noti qua
        // mentioned_user_id (tự resolve khi reply), không phải qua
        // latestCommentAtByAuthor().
        $this->actingAs($original)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Đã xử lý xong chưa nhỉ',
            'parent_comment_id' => $parentId,
        ])->assertCreated();

        // original tự trả lời chính mình ở trên — không notify vì actor==recipient.
        // Giờ để $replier trả lời tiếp để original nhận noti qua participant.
        $this->actingAs($replier)->postJson("/api/project/tasks/{$task->id}/comments", [
            'content' => 'Rồi ạ',
            'parent_comment_id' => $parentId,
        ])->assertCreated();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $original->id,
            'actor_id' => $replier->id,
            'type' => 'discussion_comment',
        ]);
    }
}
