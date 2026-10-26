<script setup>
import { formatRelativeTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import ChatUserSearch from './ChatUserSearch.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function peerMeta(conversation) {
  return formatPeerMeta(conversation.other_user);
}

function excerpt(conversation) {
  const msg = conversation.last_message;
  if (!msg?.message) return 'Chưa có tin nhắn';
  const text = msg.message;
  if (auth.user?.id && msg.sender_id === auth.user.id && !msg.recalled) {
    return `Bạn: ${text}`;
  }
  return text;
}

function open(conversationId) {
  store.openConversationView(conversationId);
}
</script>

<template>
  <div class="chat-list">
    <ChatUserSearch />

    <div class="chat-list__items">
      <p v-if="store.loadingConversations" class="chat-list__empty chat-list__empty--loading">
        <span class="chat-list__shimmer" aria-hidden="true" />
        Đang tải hội thoại…
      </p>
      <p v-else-if="store.conversations.length === 0" class="chat-list__empty">
        Chưa có cuộc trò chuyện nào. Tìm tên đồng nghiệp ở trên để bắt đầu.
      </p>
      <button
        v-for="(conversation, index) in store.conversations"
        v-else
        :key="conversation.id"
        type="button"
        class="chat-item"
        :class="{
          'chat-item--unread': conversation.unread_count > 0,
          'chat-item--fresh': store.freshConversationId === conversation.id,
        }"
        :style="{ animationDelay: `${Math.min(index, 12) * 35}ms` }"
        @click="open(conversation.id)"
      >
        <span class="chat-item__avatar-wrap" aria-hidden="true">
          <img
            v-if="conversation.other_user?.avatar_url"
            class="chat-item__avatar"
            :src="conversation.other_user.avatar_url"
            :alt="`Ảnh đại diện của ${conversation.other_user.name}`"
          />
          <span v-else class="chat-item__avatar chat-item__avatar--placeholder">
            {{ initial(conversation.other_user?.name) }}
          </span>
        </span>
        <span class="chat-item__body">
          <span class="chat-item__row">
            <span class="chat-item__name">{{ conversation.other_user?.name || 'Người dùng' }}</span>
            <span v-if="conversation.last_message" class="chat-item__time">
              {{ formatRelativeTime(conversation.last_message.created_at) }}
            </span>
          </span>
          <span v-if="peerMeta(conversation)" class="chat-item__meta">{{ peerMeta(conversation) }}</span>
          <span
            class="chat-item__excerpt"
            :class="{
              'chat-item__excerpt--muted':
                !conversation.last_message
                || conversation.last_message.recalled
                || conversation.last_message.message_type === 'sticker',
              'chat-item__excerpt--bold': conversation.unread_count > 0,
            }"
          >
            {{ excerpt(conversation) }}
          </span>
        </span>
        <span v-if="conversation.unread_count > 0" class="chat-item__count">
          {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-list {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.chat-list__items {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.chat-list__empty {
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.chat-list__empty--loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}

.chat-list__shimmer {
  display: block;
  width: 2.5rem;
  height: 0.35rem;
  border-radius: var(--radius-full);
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    color-mix(in srgb, var(--color-primary) 12%, var(--color-surface-muted)) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: chat-shimmer 1.4s ease-in-out infinite;
}

.chat-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.8rem var(--space-3);
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 85%, transparent);
  cursor: pointer;
  font-family: inherit;
  animation: chat-list-item-in 320ms ease both;
  transition: background 140ms ease, transform 140ms ease;
}

.chat-item:active {
  transform: scale(0.995);
}

.chat-item:hover {
  background: color-mix(in srgb, var(--color-surface-muted) 70%, transparent);
}

.chat-item--unread {
  background: color-mix(in srgb, var(--color-primary-surface) 65%, var(--color-surface));
}

.chat-item--unread::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.65rem;
  bottom: 0.65rem;
  width: 3px;
  border-radius: 0 4px 4px 0;
  background: linear-gradient(
    180deg,
    var(--color-tertiary-500),
    var(--color-primary)
  );
}

.chat-item--fresh {
  animation: chat-item-fresh 2.4s ease, chat-list-item-in 320ms ease both;
}

@keyframes chat-item-fresh {
  0%,
  40% {
    background: var(--color-primary-surface);
  }
}

.chat-item__avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.chat-item__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  box-shadow:
    0 0 0 2px var(--color-surface),
    0 0 0 3px color-mix(in srgb, var(--color-tertiary-400) 35%, transparent);
}

.chat-item__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    145deg,
    var(--color-primary-surface),
    color-mix(in srgb, var(--color-tertiary-100) 80%, var(--color-primary-surface))
  );
  color: var(--color-primary);
  font-size: 0.875rem;
  font-weight: 700;
}

.chat-item__body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
  flex: 1;
}

.chat-item__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.chat-item__name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.chat-item--unread .chat-item__name {
  font-weight: 700;
}

.chat-item__meta {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-item__time {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.chat-item--unread .chat-item__time {
  color: var(--color-primary);
  font-weight: 600;
}

.chat-item__excerpt {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-item__excerpt--bold {
  color: var(--color-text);
  font-weight: 600;
}

.chat-item__excerpt--muted {
  font-style: italic;
}

.chat-item__count {
  flex-shrink: 0;
  min-width: 1.25rem;
  height: 1.25rem;
  margin-top: 0.15rem;
  padding: 0 0.35rem;
  border-radius: var(--radius-full);
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.25rem;
  text-align: center;
  box-shadow: 0 2px 8px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
</style>
