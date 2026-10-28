<?php

namespace Modules\Chat\App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Models\MessageHide;
use Modules\Chat\App\Models\MessageReceipt;
use Modules\Chat\App\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        $message = Message::create($data);

        return $message->loadMissing($this->relations());
    }

    public function findForConversation(int $conversationId, int $messageId): ?Message
    {
        return Message::query()
            ->with($this->relations())
            ->where('conversation_id', $conversationId)
            ->whereKey($messageId)
            ->first();
    }

    public function save(Message $message): Message
    {
        $message->save();

        return $message->load($this->relations());
    }

    public function hideForUser(int $messageId, int $userId): void
    {
        MessageHide::query()->firstOrCreate([
            'message_id' => $messageId,
            'user_id' => $userId,
        ]);
    }

    public function hideAllForUser(int $conversationId, int $userId): void
    {
        $now = now();
        Message::query()
            ->where('conversation_id', $conversationId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($messages) use ($userId, $now) {
                $rows = [];
                foreach ($messages as $message) {
                    $rows[] = [
                        'message_id' => $message->id,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows !== []) {
                    MessageHide::query()->insertOrIgnore($rows);
                }
            });
    }

    public function paginateBefore(int $conversationId, int $viewerId, ?int $beforeId, int $perPage = 30): Collection
    {
        $query = Message::query()
            ->with($this->relations())
            ->where('conversation_id', $conversationId)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $viewerId))
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->limit($perPage)->get();
    }

    public function unreadCountsForUser(int $userId): array
    {
        $rows = DB::table('messages as m')
            ->join('conversation_members as cm', function ($join) use ($userId) {
                $join->on('cm.conversation_id', '=', 'm.conversation_id')
                    ->where('cm.user_id', '=', $userId);
            })
            ->leftJoin('message_hides as h', function ($join) use ($userId) {
                $join->on('h.message_id', '=', 'm.id')
                    ->where('h.user_id', '=', $userId);
            })
            ->where('m.sender_id', '!=', $userId)
            ->whereNull('m.recalled_at')
            ->whereNull('h.id')
            ->where(function ($query) {
                $query->whereNull('cm.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'cm.last_read_at');
            })
            ->groupBy('m.conversation_id')
            ->select('m.conversation_id', DB::raw('COUNT(*) as aggregate'))
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $count = (int) $row->aggregate;
            if ($count > 0) {
                $counts[(int) $row->conversation_id] = $count;
            }
        }

        return $counts;
    }

    public function markVisibleRead(int $conversationId, int $userId): array
    {
        $ids = Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->whereNull('recalled_at')
            ->whereDoesntHave('hides', fn ($query) => $query->where('user_id', $userId))
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $now = now();
        MessageReceipt::query()->insertOrIgnore($ids->map(fn ($id) => [
            'message_id' => $id,
            'user_id' => $userId,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return $ids->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'sender',
            'replyTo.sender',
            'replyTo.attachments',
            'attachments',
            'reads.user',
            'poll.options.votes',
            'mentions.user',
        ];
    }

    public function latestVisibleForUser(int $conversationId, int $viewerId): ?Message
    {
        return Message::query()
            ->with(['attachments', 'sender'])
            ->where('conversation_id', $conversationId)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $viewerId))
            ->orderByDesc('id')
            ->first();
    }
}
