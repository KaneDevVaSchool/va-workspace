<?php

namespace Modules\Identity\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\App\Http\Requests\SubscribePushRequest;
use Modules\Identity\App\Http\Requests\UnsubscribePushRequest;
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
        $user = $request->user();
        $unreadCount = $this->notifications->unreadCount($user);

        return response()->json([
            'unread_count' => $unreadCount,
            'latest_unread' => $unreadCount > 0 ? $this->notifications->latestUnreadPresented($user) : null,
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

    public function unsubscribe(UnsubscribePushRequest $request): JsonResponse
    {
        $this->webPush->unsubscribe($request->user()->id, $request->validated()['endpoint']);

        return response()->json(['ok' => true]);
    }
}
