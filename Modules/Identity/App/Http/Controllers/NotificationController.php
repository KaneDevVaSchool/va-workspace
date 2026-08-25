<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Http\Requests\SubscribePushRequest;
use Modules\Identity\App\Services\NotificationService;
use Modules\Identity\App\Services\WebPushService;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WebPushService $webPush,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $paginator = $this->notifications->paginate($request->user(), $perPage);

        return response()->json([
            'notifications' => collect($paginator->items())
                ->map(fn ($item) => $this->notifications->present($item))
                ->values(),
            'unread_count' => $this->notifications->unreadCount($request->user()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = $this->notifications->markRead($request->user(), $notificationId);
        if ($notification === null) {
            return response()->json(['message' => 'Không tìm thấy thông báo.'], 404);
        }

        return response()->json([
            'notification' => $this->notifications->present($notification),
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return response()->json(['unread_count' => 0]);
    }

    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => $this->webPush->publicKey(),
            'configured' => $this->webPush->isConfigured(),
        ]);
    }

    public function subscribe(SubscribePushRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->webPush->subscribe(
            $request->user()->id,
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');
        if ($endpoint === '') {
            return response()->json(['message' => 'Thiếu endpoint.'], 422);
        }

        $this->webPush->unsubscribe($request->user()->id, $endpoint);

        return response()->json(['ok' => true]);
    }
}
