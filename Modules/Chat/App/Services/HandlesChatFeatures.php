<?php

namespace Modules\Chat\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Chat\App\Events\ConversationRemoved;
use Modules\Chat\App\Events\InboxUpdated;
use Modules\Chat\App\Events\MessageUpdated;
use Modules\Chat\App\Models\ChatPoll;
use Modules\Chat\App\Models\ChatPollOption;
use Modules\Chat\App\Models\ChatPollVote;
use Modules\Chat\App\Models\Conversation;
use Modules\Chat\App\Models\ConversationMember;
use Modules\Chat\App\Models\Message;
use Modules\Chat\App\Models\MessageAttachment;
use Modules\Chat\App\Models\MessageMention;
use Modules\Chat\App\Models\MessageReaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait HandlesChatFeatures
{
    /**
     * @param  list<int>  $userIds
     */
    public function updateGroupAvatar(User $actor, int $conversationId, UploadedFile $avatar): array
    {
        $conversation = $this->requireOwnedGroup($actor, $conversationId);

        if (! $conversation->created_by) {
            $conversation->update(['created_by' => $actor->id]);
            $conversation->refresh();
        }

        if ($conversation->avatar_path) {
            Storage::disk('public')->delete($conversation->avatar_path);
        }

        $conversation->update([
            'avatar_path' => $avatar->store('chat/groups', 'public'),
        ]);

        $this->refreshMemberInboxes($conversationId);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    /**
     * @param  list<int>  $userIds
     */
    public function addGroupMembers(User $actor, int $conversationId, array $userIds): array
    {
        $conversation = $this->requireOwnedGroup($actor, $conversationId);
        $existing = $conversation->members->pluck('user_id')->map(fn ($id) => (int) $id);
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && ! $existing->contains($id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Những người này đã ở trong nhóm.'],
            ]);
        }

        if ($existing->count() + $ids->count() > 50) {
            throw ValidationException::withMessages([
                'user_ids' => ['Nhóm chỉ có tối đa 50 thành viên.'],
            ]);
        }

        $users = User::query()->whereIn('id', $ids)->get()->filter(fn (User $user) => $user->isActive());
        if ($users->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'user_ids' => ['Có thành viên không tồn tại hoặc đã ngừng hoạt động.'],
            ]);
        }

        foreach ($ids as $id) {
            $conversation->members()->create([
                'user_id' => $id,
                'joined_at' => now(),
            ]);
        }

        $names = $users->pluck('name')->implode(', ');
        $this->postNotice($actor, $conversationId, $actor->name.' đã thêm '.$names.' vào nhóm');
        $this->refreshMemberInboxes($conversationId);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    public function removeGroupMember(User $actor, int $conversationId, int $userId): array
    {
        $conversation = $this->requireGroup($actor, $conversationId);
        $isOwner = (int) $conversation->created_by === (int) $actor->id;
        $leavingSelf = (int) $userId === (int) $actor->id;

        if (! $leavingSelf && ! $isOwner) {
            throw new AccessDeniedHttpException('Chỉ chủ nhóm mới mời thành viên ra.');
        }

        if ($leavingSelf && $isOwner) {
            throw ValidationException::withMessages([
                'user_id' => ['Chủ nhóm cần chuyển quyền hoặc giải tán nhóm trước khi rời.'],
            ]);
        }

        $member = $conversation->members->firstWhere('user_id', $userId);
        if (! $member || ! $member->user) {
            throw ValidationException::withMessages([
                'user_id' => ['Người này không còn trong nhóm.'],
            ]);
        }

        $name = $member->user->name;
        $member->delete();

        $text = $leavingSelf
            ? $actor->name.' đã rời nhóm'
            : $actor->name.' đã mời '.$name.' ra khỏi nhóm';
        $this->postNotice($actor, $conversationId, $text);
        ConversationRemoved::dispatch($userId, $conversationId);
        $this->refreshMemberInboxes($conversationId);

        if ($leavingSelf) {
            return ['left' => true];
        }

        return ['conversation' => $this->presentConversation($this->requireConversation($conversationId), $actor)];
    }

    public function transferGroupOwner(User $actor, int $conversationId, int $userId): array
    {
        $conversation = $this->requireOwnedGroup($actor, $conversationId);
        if ((int) $userId === (int) $actor->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Hãy chọn một thành viên khác.'],
            ]);
        }

        $member = $conversation->members->firstWhere('user_id', $userId);
        if (! $member || ! $member->user) {
            throw ValidationException::withMessages([
                'user_id' => ['Người này không còn trong nhóm.'],
            ]);
        }

        $conversation->created_by = $userId;
        $conversation->save();
        $this->postNotice($actor, $conversationId, $actor->name.' đã chuyển quyền chủ nhóm cho '.$member->user->name);
        $this->refreshMemberInboxes($conversationId);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    public function setPinned(User $actor, int $conversationId, bool $pinned): array
    {
        $this->requireMembership($actor, $conversationId);
        ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $actor->id)
            ->update(['pinned_at' => $pinned ? now() : null]);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    public function setMuted(User $actor, int $conversationId, ?int $hours): array
    {
        $this->requireMembership($actor, $conversationId);
        $until = null;
        if ($hours !== null && $hours > 0) {
            $until = $hours >= 87600 ? now()->addYears(10) : now()->addHours($hours);
        }

        ConversationMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $actor->id)
            ->update(['muted_until' => $until]);

        return $this->presentConversation($this->requireConversation($conversationId), $actor);
    }

    public function setDisappear(User $actor, int $conversationId, ?int $seconds): array
    {
        $this->requireMembership($actor, $conversationId);
        if (! in_array($seconds, [null, 86400, 604800], true)) {
            throw ValidationException::withMessages([
                'seconds' => ['Chỉ chọn tắt, 24 giờ hoặc 7 ngày.'],
            ]);
        }

        $conversation = $this->requireConversation($conversationId);
        $conversation->disappear_seconds = $seconds;
        $conversation->save();

        Message::query()
            ->where('conversation_id', $conversationId)
            ->whereNull('recalled_at')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use ($seconds) {
                foreach ($messages as $message) {
                    $message->expires_at = $seconds ? $message->created_at?->copy()->addSeconds($seconds) : null;
                    $message->save();
                }
            });

        return $this->presentConversation($conversation->fresh() ?? $conversation, $actor);
    }

    public function pinMessage(User $actor, int $conversationId, ?int $messageId): array
    {
        $this->requireMembership($actor, $conversationId);
        $conversation = $this->requireConversation($conversationId);

        if ($messageId !== null) {
            $message = $this->messages->findForConversation($conversationId, $messageId);
            if (! $message || $message->isRecalled()) {
                throw ValidationException::withMessages([
                    'message_id' => ['Không ghim được tin nhắn này.'],
                ]);
            }
        }

        $conversation->pinned_message_id = $messageId;
        $conversation->save();
        $this->refreshMemberInboxes($conversationId);

        return $this->presentConversation($conversation->fresh() ?? $conversation, $actor);
    }

    public function searchMessages(User $actor, int $conversationId, string $query): array
    {
        $this->requireMembership($actor, $conversationId);
        $term = trim($query);
        if (mb_strlen($term) < 2) {
            return ['messages' => []];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $messages = Message::query()
            ->with(['sender', 'attachments'])
            ->where('conversation_id', $conversationId)
            ->whereNull('recalled_at')
            ->where('message_type', Message::TYPE_TEXT)
            ->where('message', 'like', $like)
            ->whereDoesntHave('hides', fn ($q) => $q->where('user_id', $actor->id))
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return [
            'messages' => $messages->map(fn (Message $message) => $this->present($message))->values()->all(),
        ];
    }

    public function listMedia(User $actor, int $conversationId): array
    {
        $this->requireMembership($actor, $conversationId);

        $files = MessageAttachment::query()
            ->whereHas('message', function ($query) use ($conversationId, $actor) {
                $query->where('conversation_id', $conversationId)
                    ->whereNull('recalled_at')
                    ->whereDoesntHave('hides', fn ($hide) => $hide->where('user_id', $actor->id));
            })
            ->latest('id')
            ->limit(80)
            ->get();

        return [
            'files' => $files->map(fn (MessageAttachment $file) => [
                'id' => $file->id,
                'message_id' => $file->message_id,
                'type' => $file->type,
                'name' => $file->file_name,
                'size' => (int) $file->file_size,
                'url' => $this->attachmentUrl($file),
            ])->values()->all(),
        ];
    }

    public function votePoll(User $actor, int $conversationId, int $messageId, int $optionId): array
    {
        $this->requireMembership($actor, $conversationId);
        $message = $this->messages->findForConversation($conversationId, $messageId);
        if (! $message || $message->message_type !== Message::TYPE_POLL || $message->isRecalled() || ! $message->poll) {
            throw ValidationException::withMessages([
                'option_id' => ['Bình chọn không còn hợp lệ.'],
            ]);
        }

        $option = $message->poll->options->firstWhere('id', $optionId);
        if (! $option) {
            throw ValidationException::withMessages([
                'option_id' => ['Lựa chọn không thuộc bình chọn này.'],
            ]);
        }

        ChatPollVote::query()->where('poll_id', $message->poll->id)->where('user_id', $actor->id)->delete();
        ChatPollVote::query()->create([
            'poll_id' => $message->poll->id,
            'option_id' => $option->id,
            'user_id' => $actor->id,
        ]);

        $fresh = $this->messages->findForConversation($conversationId, $messageId) ?? $message;
        $presented = $this->present($fresh);
        MessageUpdated::dispatch($conversationId, $presented);

        return ['message' => $presented];
    }

    public function reactToMessage(User $actor, int $conversationId, int $messageId, string $emoji): array
    {
        $this->requireMembership($actor, $conversationId);
        $canonical = MessageReaction::canonical($emoji);
        if ($canonical === null) {
            throw ValidationException::withMessages([
                'emoji' => ['Cảm xúc không được hỗ trợ.'],
            ]);
        }

        $message = $this->messages->findForConversation($conversationId, $messageId);
        if (! $message || $message->isRecalled() || $message->message_type === Message::TYPE_SYSTEM) {
            throw ValidationException::withMessages([
                'emoji' => ['Không thả cảm xúc cho tin nhắn này.'],
            ]);
        }

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($existing && $existing->emoji === $canonical) {
            $existing->delete();
        } elseif ($existing) {
            $existing->emoji = $canonical;
            $existing->save();
        } else {
            MessageReaction::query()->create([
                'message_id' => $message->id,
                'user_id' => $actor->id,
                'emoji' => $canonical,
            ]);
        }

        $fresh = $this->messages->findForConversation($conversationId, $messageId) ?? $message;
        $presented = $this->present($fresh);
        MessageUpdated::dispatch($conversationId, $presented);

        return ['message' => $presented];
    }

    public function touchPresence(User $actor): void
    {
        DB::table('users')->where('id', $actor->id)->update(['last_seen_at' => now()]);
    }

    public function expireDueMessages(): int
    {
        $due = Message::query()
            ->whereNull('recalled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($due as $message) {
            $this->deleteAttachments($message);
            $message->message = '';
            $message->sticker_id = null;
            $message->recalled_at = now();
            $saved = $this->messages->save($message);
            MessageUpdated::dispatch($saved->conversation_id, $this->present($saved));
        }

        return $due->count();
    }

    /**
     * @param  list<int>  $mentionIds
     * @param  list<string>  $pollOptions
     */
    protected function storePoll(Message $message, string $question, array $pollOptions): void
    {
        $labels = collect($pollOptions)
            ->map(fn ($label) => trim((string) $label))
            ->filter(fn (string $label) => $label !== '')
            ->unique()
            ->values();

        if ($labels->count() < 2 || $labels->count() > 6) {
            throw ValidationException::withMessages([
                'poll_options' => ['Bình chọn cần từ 2 đến 6 lựa chọn.'],
            ]);
        }

        $poll = ChatPoll::query()->create([
            'message_id' => $message->id,
            'question' => mb_substr($question, 0, 200),
        ]);

        foreach ($labels as $index => $label) {
            ChatPollOption::query()->create([
                'poll_id' => $poll->id,
                'label' => mb_substr($label, 0, 80),
                'position' => $index,
            ]);
        }
    }

    /**
     * @param  list<int>  $mentionIds
     * @return list<int>
     */
    protected function storeMentions(User $actor, Conversation $conversation, Message $message, array $mentionIds): array
    {
        if ($conversation->type !== Conversation::TYPE_GROUP || $mentionIds === []) {
            return [];
        }

        $allowed = $conversation->members->pluck('user_id')->map(fn ($id) => (int) $id);
        $ids = collect($mentionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $actor->id && $allowed->contains($id))
            ->unique()
            ->values();

        foreach ($ids as $id) {
            MessageMention::query()->create([
                'message_id' => $message->id,
                'user_id' => $id,
            ]);
        }

        return $ids->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageExtras(Message $message): array
    {
        $reads = $message->relationLoaded('reads')
            ? $message->reads->map(fn ($read) => [
                'id' => $read->user_id,
                'name' => $read->user?->name,
                'avatar_url' => $read->user?->avatar_url,
            ])->values()->all()
            : [];

        return [
            'voice_duration' => $message->voice_duration,
            'expires_at' => $message->expires_at?->toIso8601String(),
            'reads' => $message->isRecalled() ? [] : $reads,
            'poll' => $message->isRecalled() ? null : $this->presentPoll($message),
            'reactions' => $message->isRecalled() ? [] : $this->presentReactions($message),
        ];
    }

    /**
     * @return list<array{emoji: string, count: int, user_ids: list<int>, users: list<array{id: int, name: string|null}>}>
     */
    protected function presentReactions(Message $message): array
    {
        if (! $message->relationLoaded('reactions')) {
            return [];
        }

        $order = array_flip(MessageReaction::EMOJIS);

        return $message->reactions
            ->groupBy('emoji')
            ->map(function ($rows, $emoji) {
                $users = $rows
                    ->map(fn (MessageReaction $row) => [
                        'id' => (int) $row->user_id,
                        'name' => $row->user?->name,
                    ])
                    ->values();

                return [
                    'emoji' => (string) $emoji,
                    'count' => $users->count(),
                    'user_ids' => $users->pluck('id')->all(),
                    'users' => $users->take(12)->all(),
                ];
            })
            ->sortBy(fn (array $row) => $order[$row['emoji']] ?? 99)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function conversationExtras(Conversation $conversation, User $actor): array
    {
        $mine = $conversation->members->firstWhere('user_id', $actor->id);
        $pinned = null;
        if ($conversation->pinned_message_id) {
            $pinnedMessage = $this->messages->findForConversation($conversation->id, (int) $conversation->pinned_message_id);
            if ($pinnedMessage && ! $pinnedMessage->isRecalled()) {
                $pinned = [
                    'id' => $pinnedMessage->id,
                    'message' => $this->previewText($pinnedMessage),
                    'sender_name' => $pinnedMessage->sender?->name,
                ];
            }
        }

        $ownerId = $this->groupOwnerUserId($conversation);

        return [
            'created_by' => $ownerId ?? $conversation->created_by,
            'is_owner' => $conversation->type === Conversation::TYPE_GROUP && $ownerId !== null && $ownerId === (int) $actor->id,
            'pinned_at' => $mine?->pinned_at?->toIso8601String(),
            'muted_until' => $mine?->muted_until?->toIso8601String(),
            'is_muted' => $mine?->muted_until !== null && $mine->muted_until->isFuture(),
            'disappear_seconds' => $conversation->disappear_seconds,
            'pinned_message' => $pinned,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, question: string, options: list<array<string, mixed>>}|null
     */
    protected function presentPoll(Message $message): ?array
    {
        if ($message->message_type !== Message::TYPE_POLL || ! $message->relationLoaded('poll') || ! $message->poll) {
            return null;
        }

        return [
            'id' => $message->poll->id,
            'question' => $message->poll->question,
            'options' => $message->poll->options->map(fn (ChatPollOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'votes' => $option->votes->count(),
                'voter_ids' => $option->votes->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            ])->values()->all(),
        ];
    }

    protected function memberIsMuted(ConversationMember $member): bool
    {
        return $member->muted_until !== null && $member->muted_until->isFuture();
    }

    private function requireMembership(User $actor, int $conversationId): void
    {
        if (! $this->isMember($actor, $conversationId)) {
            throw new AccessDeniedHttpException('Bạn không thuộc cuộc trò chuyện này.');
        }
    }

    private function requireGroup(User $actor, int $conversationId): Conversation
    {
        $this->requireMembership($actor, $conversationId);
        $conversation = $this->requireConversation($conversationId);
        $conversation->load(['members.user.department']);
        if ($conversation->type !== Conversation::TYPE_GROUP) {
            throw ValidationException::withMessages([
                'conversation' => ['Thao tác này chỉ dùng cho nhóm.'],
            ]);
        }

        return $conversation;
    }

    private function requireOwnedGroup(User $actor, int $conversationId): Conversation
    {
        $conversation = $this->requireGroup($actor, $conversationId);
        $ownerId = $this->groupOwnerUserId($conversation);
        if ($ownerId === null || $ownerId !== (int) $actor->id) {
            throw new AccessDeniedHttpException('Chỉ chủ nhóm mới làm được việc này.');
        }

        return $conversation;
    }

    private function groupOwnerUserId(Conversation $conversation): ?int
    {
        if ($conversation->created_by) {
            return (int) $conversation->created_by;
        }

        $conversation->loadMissing('members');
        $fallback = $conversation->members
            ->sortBy(fn (ConversationMember $member) => [$member->joined_at?->getTimestamp() ?? PHP_INT_MAX, $member->id])
            ->first()
            ?->user_id;

        return $fallback ? (int) $fallback : null;
    }

    private function refreshMemberInboxes(int $conversationId): void
    {
        $conversation = $this->requireConversation($conversationId);
        $conversation->load(['members.user.department']);
        foreach ($conversation->members as $member) {
            if (! $member->user) {
                continue;
            }
            $unread = $this->messages->unreadCountsForUser($member->user->id)[$conversationId] ?? 0;
            InboxUpdated::dispatch(
                $member->user->id,
                $this->presentConversation($conversation, $member->user, $unread),
                ['id' => 0, 'conversation_id' => $conversationId],
            );
        }
    }
}
