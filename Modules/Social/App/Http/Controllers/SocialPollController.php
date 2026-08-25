<?php

namespace Modules\Social\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Social\App\Http\Requests\VoteSocialPollRequest;
use Modules\Social\App\Repositories\Contracts\SocialPostRepositoryInterface;
use Modules\Social\App\Services\SocialPollService;

class SocialPollController extends Controller
{
    public function __construct(
        private readonly SocialPollService $polls,
        private readonly SocialPostRepositoryInterface $posts,
    ) {}

    public function vote(VoteSocialPollRequest $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json(
            $this->polls->vote($post, $request->user(), (int) $request->validated()['option_id'])
        );
    }

    public function voters(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        $optionId = $request->query('option_id');
        $optionId = $optionId === null || $optionId === '' ? null : (int) $optionId;

        return response()->json($this->polls->voters($post, $request->user(), $optionId));
    }

    public function close(Request $request, int $postId): JsonResponse
    {
        $post = $this->posts->find($postId);
        if (! $post) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        return response()->json($this->polls->close($post, $request->user()));
    }
}
