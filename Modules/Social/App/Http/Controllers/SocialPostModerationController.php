<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Services\ActivityLogService;
use Modules\Social\App\Http\Requests\RejectSocialPostRequest;
use Modules\Social\App\Models\SocialPost;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialPostService;

/**
 * Trang duyệt bài viết (toàn trường) — người có quyền `social.review`
 * (mặc định admin/super_admin, hoặc được cấp thêm). Khác `social.moderate`
 * (xoá bài vi phạm theo phòng ban): đây là bước duyệt TRƯỚC khi bài hiện công khai.
 */
class SocialPostModerationController extends Controller
{
    public function __construct(
        private readonly SocialPostService $service,
        private readonly SocialPostRepositoryInterface $posts,
        private readonly ActivityLogService $activityLogs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = max((int) $request->query('page', 1), 1);

        return response()->json($this->service->pendingList($request->user(), $perPage, $page));
    }

    public function approve(Request $request, int $postId): JsonResponse
    {
        $post = $this->pendingPost($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết đang chờ duyệt.'], 404);
        }

        $post = $this->service->approve($post, $request->user());

        $this->activityLogs->record(
            'social_post.approve',
            'Duyệt bài viết của "'.($post->is_anonymous ? 'ẩn danh' : $post->user->name).'" trên bảng tin',
            $request->user(),
            'social_post',
            $post->id,
        );

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    public function reject(RejectSocialPostRequest $request, int $postId): JsonResponse
    {
        $post = $this->pendingPost($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết đang chờ duyệt.'], 404);
        }

        $reason = $request->validated()['reason'] ?? null;
        $post = $this->service->reject($post, $request->user(), $reason);

        $this->activityLogs->record(
            'social_post.reject',
            'Từ chối bài viết của "'.($post->is_anonymous ? 'ẩn danh' : $post->user->name).'" trên bảng tin',
            $request->user(),
            'social_post',
            $post->id,
        );

        return response()->json(['post' => $this->service->present($post, $request->user())]);
    }

    private function pendingPost(int $postId): ?SocialPost
    {
        $post = $this->posts->find($postId);

        return $post && $post->review_status === SocialPost::REVIEW_PENDING ? $post : null;
    }
}
