<?php

namespace Modules\Chat\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\App\Http\Requests\CreateChatGroupRequest;
use Modules\Chat\App\Http\Requests\OpenChatConversationRequest;
use Modules\Chat\App\Services\ChatService;
use Modules\Identity\App\Services\WorkspaceColleagueSearchService;

class ChatConversationController extends Controller
{
    public function __construct(
        private readonly ChatService $service,
        private readonly WorkspaceColleagueSearchService $colleagueSearch,
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

    public function storeGroup(CreateChatGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'conversation' => $this->service->createGroup(
                $request->user(),
                (string) $validated['title'],
                $validated['user_ids'],
            ),
        ], 201);
    }

    public function markRead(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->markRead($request->user(), $conversationId);

        return response()->json(['unread_count' => 0]);
    }

    public function typing(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->broadcastTyping($request->user(), $conversationId, $request->boolean('active', true));

        return response()->json(['ok' => true]);
    }

    public function viewing(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->touchViewing($request->user(), $conversationId);

        return response()->json(['ok' => true]);
    }

    public function leaveViewing(Request $request, int $conversationId): JsonResponse
    {
        $this->service->clearViewing($request->user(), $conversationId);

        return response()->json(['ok' => true]);
    }

    public function clearHistory(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->clearHistory($request->user(), $conversationId);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $conversationId): JsonResponse
    {
        $this->service->disbandGroup($request->user(), $conversationId);

        return response()->json(['ok' => true]);
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
            'users' => $this->colleagueSearch->search($query, 8, $actor->id),
        ]);
    }

    public function presence(Request $request): JsonResponse
    {
        $this->service->touchPresence($request->user());

        return response()->json(['ok' => true]);
    }

    public function updateAvatar(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ], [
            'avatar.required' => 'Chọn ảnh đại diện nhóm.',
            'avatar.image' => 'Ảnh đại diện không hợp lệ.',
            'avatar.max' => 'Ảnh đại diện không được vượt quá 5MB.',
        ]);

        return response()->json([
            'conversation' => $this->service->updateGroupAvatar(
                $request->user(),
                $conversationId,
                $validated['avatar'],
            ),
        ]);
    }

    public function addMembers(Request $request, int $conversationId): JsonResponse
    {
        $ids = array_map('intval', (array) $request->input('user_ids', []));

        return response()->json([
            'conversation' => $this->service->addGroupMembers($request->user(), $conversationId, $ids),
        ]);
    }

    public function removeMember(Request $request, int $conversationId, int $userId): JsonResponse
    {
        return response()->json($this->service->removeGroupMember($request->user(), $conversationId, $userId));
    }

    public function transferOwner(Request $request, int $conversationId): JsonResponse
    {
        return response()->json([
            'conversation' => $this->service->transferGroupOwner(
                $request->user(),
                $conversationId,
                (int) $request->input('user_id'),
            ),
        ]);
    }

    public function pin(Request $request, int $conversationId): JsonResponse
    {
        return response()->json([
            'conversation' => $this->service->setPinned($request->user(), $conversationId, $request->boolean('pinned')),
        ]);
    }

    public function mute(Request $request, int $conversationId): JsonResponse
    {
        $hours = $request->input('hours');

        return response()->json([
            'conversation' => $this->service->setMuted(
                $request->user(),
                $conversationId,
                $hours === null || $hours === '' ? null : (int) $hours,
            ),
        ]);
    }

    public function disappear(Request $request, int $conversationId): JsonResponse
    {
        $seconds = $request->input('seconds');

        return response()->json([
            'conversation' => $this->service->setDisappear(
                $request->user(),
                $conversationId,
                $seconds === null || $seconds === '' || (int) $seconds === 0 ? null : (int) $seconds,
            ),
        ]);
    }

    public function pinMessage(Request $request, int $conversationId): JsonResponse
    {
        $messageId = $request->input('message_id');

        return response()->json([
            'conversation' => $this->service->pinMessage(
                $request->user(),
                $conversationId,
                $messageId === null || $messageId === '' ? null : (int) $messageId,
            ),
        ]);
    }
}
