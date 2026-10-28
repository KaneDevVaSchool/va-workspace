<?php

namespace Modules\Chat\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\App\Http\Requests\ReactChatMessageRequest;
use Modules\Chat\App\Http\Requests\SendChatMessageRequest;
use Modules\Chat\App\Http\Requests\UpdateChatMessageRequest;
use Modules\Chat\App\Services\ChatService;

class ChatMessageController extends Controller
{
    public function __construct(
        private readonly ChatService $service,
    ) {}

    public function index(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $beforeId = $request->query('before_id');
        $query = trim((string) $request->query('q', ''));
        if ($query !== '') {
            return response()->json($this->service->searchMessages($request->user(), $conversationId, $query));
        }

        return response()->json(
            $this->service->listMessages($request->user(), $conversationId, $beforeId !== null ? (int) $beforeId : null)
        );
    }

    public function store(SendChatMessageRequest $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        return response()->json(
            $this->service->sendMessage($request->user(), $conversationId, $request->validated()),
            201,
        );
    }

    public function update(UpdateChatMessageRequest $request, int $conversationId, int $messageId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        return response()->json(
            $this->service->editMessage($request->user(), $conversationId, $messageId, $request->validated('message'))
        );
    }

    public function recall(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        return response()->json(
            $this->service->recallMessage($request->user(), $conversationId, $messageId)
        );
    }

    public function destroy(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $this->service->hideMessage($request->user(), $conversationId, $messageId);

        return response()->json(['ok' => true]);
    }

    public function media(Request $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        return response()->json($this->service->listMedia($request->user(), $conversationId));
    }

    public function vote(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $optionId = (int) $request->input('option_id');

        return response()->json(
            $this->service->votePoll($request->user(), $conversationId, $messageId, $optionId)
        );
    }

    public function react(ReactChatMessageRequest $request, int $conversationId, int $messageId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        return response()->json(
            $this->service->reactToMessage(
                $request->user(),
                $conversationId,
                $messageId,
                $request->validated('emoji'),
            )
        );
    }
}
