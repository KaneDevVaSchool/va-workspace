<?php

namespace Modules\Chat\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Chat\App\Http\Requests\SendChatMessageRequest;
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

        return response()->json(
            $this->service->listMessages($request->user(), $conversationId, $beforeId !== null ? (int) $beforeId : null)
        );
    }

    public function store(SendChatMessageRequest $request, int $conversationId): JsonResponse
    {
        if (! $this->service->isMember($request->user(), $conversationId)) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $validated = $request->validated();

        return response()->json(
            $this->service->sendMessage($request->user(), $conversationId, $validated['message']),
            201,
        );
    }
}
