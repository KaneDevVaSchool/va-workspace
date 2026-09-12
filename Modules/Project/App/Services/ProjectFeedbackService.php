<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectFeedback;
use Modules\Project\App\Repositories\Contracts\ProjectFeedbackRepositoryInterface;

/**
 * Business logic của "Phản hồi" (tab tuỳ chọn trong trang chi tiết dự án) —
 * phản hồi/đánh giá tổng thể kết quả dự án, khác Thảo luận (CommentService):
 * list phẳng, chỉ tác giả sửa/xoá được.
 */
class ProjectFeedbackService
{
    public function __construct(
        private readonly ProjectFeedbackRepositoryInterface $feedbacks,
    ) {}

    public function listForProject(Project $project): array
    {
        return $this->feedbacks->listForProject($project->id)
            ->map(fn (ProjectFeedback $fb) => $this->present($fb))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    public function create(Project $project, array $data, User $author): ProjectFeedback
    {
        return $this->feedbacks->create([
            'project_id' => $project->id,
            'author_id' => $author->id,
            'rating' => $data['rating'] ?? null,
            'content' => trim($data['content']),
        ]);
    }

    /** Chỉ tác giả phản hồi mới sửa/xoá được — không permission tĩnh. */
    public function canEdit(ProjectFeedback $feedback, User $actor): bool
    {
        return (int) $feedback->author_id === (int) $actor->id;
    }

    /** @param  array<string, mixed>  $data */
    public function update(ProjectFeedback $feedback, array $data): ProjectFeedback
    {
        $payload = [];
        foreach (['rating', 'content'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'content' ? trim($data[$field]) : $data[$field];
            }
        }

        return $this->feedbacks->update($feedback, $payload);
    }

    public function delete(ProjectFeedback $feedback): bool
    {
        return $this->feedbacks->delete($feedback);
    }

    public function present(ProjectFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'project_id' => $feedback->project_id,
            'rating' => $feedback->rating,
            'content' => $feedback->content,
            'author' => $feedback->author ? [
                'id' => $feedback->author->id,
                'name' => $feedback->author->name,
                'avatar_url' => $feedback->author->avatar_url,
            ] : null,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ];
    }
}
