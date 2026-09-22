<?php

namespace Modules\Chat\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\App\Http\Requests\OpenChatConversationRequest;
use Modules\Chat\App\Services\ChatService;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;

class ChatConversationController extends Controller
{
    public function __construct(
        private readonly ChatService $service,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'conversations' => $this->service->listConversations($request->user()),
        ]);
    }

    public function store(OpenChatConversationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'conversation' => $this->service->openPrivateConversationWith($request->user(), (int) $validated['user_id']),
        ]);
    }

    public function markRead(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->markRead($request->user(), $conversationId);

        return response()->json(['unread_count' => 0]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json($this->service->unreadSummary($request->user()));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $actor = $request->user();

        return response()->json([
            'users' => $this->users->searchActiveByName($query, 8, $actor->id)
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'department' => $user->department?->name,
                ])
                ->values()
                ->all(),
        ]);
    }
}
