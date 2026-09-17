<?php

namespace Modules\Project\App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Models\ProjectTestCase;
use Modules\Project\App\Repositories\Contracts\ProjectTestCaseRepositoryInterface;

/**
 * Business logic của "Testcase" (tab tuỳ chọn trong trang chi tiết dự án).
 *
 * Nghiệm thu 2 lượt: assignee tự chấm Check lần 1, người tạo testcase
 * chấm Check lần 2. Check lần 2 = "failed" tự động reset Check lần 1 về
 * "pending" (assignee cần sửa và check lại) — xem updateCheck2().
 */
class ProjectTestCaseService
{
    public function __construct(
        private readonly ProjectTestCaseRepositoryInterface $testCases,
    ) {}

    public function listForProject(Project $project): array
    {
        return $this->testCases->listForProject($project->id)
            ->map(fn (ProjectTestCase $tc) => $this->present($tc))
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    public function create(Project $project, array $data, User $actor): ProjectTestCase
    {
        return $this->testCases->create([
            'project_id' => $project->id,
            'title' => trim($data['title']),
            'steps' => $data['steps'] ?? null,
            'expected_result' => $data['expected_result'] ?? null,
            'actual_result' => $data['actual_result'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'phase_id' => $data['phase_id'] ?? null,
            'check1_status' => 'pending',
            'check2_status' => 'pending',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * Sửa thông tin cơ bản của testcase — KHÔNG sửa check1/check2 ở đây
     * (tách riêng qua updateCheck1()/updateCheck2() để kiểm soát quyền
     * theo đúng người: assignee vs. người tạo).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ProjectTestCase $testCase, array $data, User $actor): ProjectTestCase
    {
        $payload = ['updated_by' => $actor->id];

        foreach (['title', 'steps', 'expected_result', 'actual_result', 'assignee_id', 'link_url', 'phase_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'title' ? trim($data[$field]) : $data[$field];
            }
        }

        return $this->testCases->update($testCase, $payload);
    }

    public function delete(ProjectTestCase $testCase): bool
    {
        if ($testCase->attachment_path) {
            Storage::disk('public')->delete($testCase->attachment_path);
        }

        return $this->testCases->delete($testCase);
    }

    /** Chỉ assignee của chính testcase mới được chấm Check lần 1. */
    public function canCheck1(ProjectTestCase $testCase, User $actor): bool
    {
        return $testCase->assignee_id !== null && (int) $testCase->assignee_id === (int) $actor->id;
    }

    /** Chỉ người tạo testcase mới được chấm Check lần 2. */
    public function canCheck2(ProjectTestCase $testCase, User $actor): bool
    {
        return $testCase->created_by !== null && (int) $testCase->created_by === (int) $actor->id;
    }

    public function updateCheck1(ProjectTestCase $testCase, string $status, User $actor): ProjectTestCase
    {
        return $this->testCases->update($testCase, [
            'check1_status' => $status,
            'check1_by' => $actor->id,
            'check1_at' => now(),
        ]);
    }

    /**
     * Check lần 2 = "failed" tự động đặt lại Check lần 1 về "pending" —
     * assignee cần sửa và check lại từ đầu.
     */
    public function updateCheck2(ProjectTestCase $testCase, string $status, User $actor): ProjectTestCase
    {
        $payload = [
            'check2_status' => $status,
            'check2_by' => $actor->id,
            'check2_at' => now(),
        ];

        if ($status === 'failed') {
            $payload['check1_status'] = 'pending';
            $payload['check1_by'] = null;
            $payload['check1_at'] = null;
        }

        return $this->testCases->update($testCase, $payload);
    }

    /** Cập nhật ảnh đính kèm testcase — xoá ảnh cũ (nếu có) rồi lưu ảnh mới. */
    public function updateAttachment(ProjectTestCase $testCase, UploadedFile $file, User $actor): ProjectTestCase
    {
        if ($testCase->attachment_path) {
            Storage::disk('public')->delete($testCase->attachment_path);
        }

        $path = $file->store('project/'.$testCase->project_id.'/testcase/'.$testCase->id.'/attachment', 'public');

        return $this->testCases->update($testCase, [
            'attachment_path' => $path,
            'updated_by' => $actor->id,
        ]);
    }

    public function deleteAttachment(ProjectTestCase $testCase, User $actor): ProjectTestCase
    {
        if ($testCase->attachment_path) {
            Storage::disk('public')->delete($testCase->attachment_path);
        }

        return $this->testCases->update($testCase, [
            'attachment_path' => null,
            'updated_by' => $actor->id,
        ]);
    }

    public function present(ProjectTestCase $testCase): array
    {
        return [
            'id' => $testCase->id,
            'project_id' => $testCase->project_id,
            'title' => $testCase->title,
            'steps' => $testCase->steps,
            'expected_result' => $testCase->expected_result,
            'actual_result' => $testCase->actual_result,
            'link_url' => $testCase->link_url,
            'attachment_path' => $testCase->attachment_path,
            'attachment_url' => $testCase->attachment_path ? Storage::disk('public')->url($testCase->attachment_path) : null,
            'check1' => [
                'status' => $testCase->check1_status,
                'by' => $this->presentUser($testCase->check1Reviewer),
                'at' => $testCase->check1_at?->toIso8601String(),
            ],
            'check2' => [
                'status' => $testCase->check2_status,
                'by' => $this->presentUser($testCase->check2Reviewer),
                'at' => $testCase->check2_at?->toIso8601String(),
            ],
            'assignee' => $this->presentUser($testCase->assignee),
            'phase' => $testCase->phase ? [
                'id' => $testCase->phase->id,
                'title' => $testCase->phase->title,
                'code' => $testCase->phase->code,
            ] : null,
            'creator' => $testCase->creator ? [
                'id' => $testCase->creator->id,
                'name' => $testCase->creator->name,
            ] : null,
            'created_at' => $testCase->created_at?->toIso8601String(),
            'updated_at' => $testCase->updated_at?->toIso8601String(),
        ];
    }

    private function presentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
