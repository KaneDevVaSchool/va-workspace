<?php

namespace Modules\Chat\App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $conversation,
        public readonly array $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("chat.inbox.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'inbox.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => $this->conversation,
            'message' => $this->message,
        ];
    }
}
