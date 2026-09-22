<script setup>
import { formatRelativeTime } from '@/lib/formatTime';
import ChatUserSearch from './ChatUserSearch.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function open(conversationId) {
  store.openConversationView(conversationId);
}
</script>

<template>
  <div class="chat-list">
    <ChatUserSearch />

    <div class="chat-list__items">
      <p v-if="store.loadingConversations" class="chat-list__empty">Đang tải…</p>
      <p v-else-if="store.conversations.length === 0" class="chat-list__empty">
        Chưa có cuộc trò chuyện nào. Tìm tên đồng nghiệp ở trên để bắt đầu.
      </p>
      <button
        v-for="conversation in store.conversations"
        v-else
        :key="conversation.id"
        type="button"
        class="chat-item"
        :class="{ 'chat-item--unread': conversation.unread_count > 0 }"
        @click="open(conversation.id)"
      >
        <img
          v-if="conversation.other_user?.avatar_url"
          class="chat-item__avatar"
          :src="conversation.other_user.avatar_url"
          :alt="`Ảnh đại diện của ${conversation.other_user.name}`"
        />
        <span v-else class="chat-item__avatar chat-item__avatar--placeholder">
          {{ initial(conversation.other_user?.name) }}
        </span>
        <span class="chat-item__body">
          <span class="chat-item__row">
            <span class="chat-item__name">{{ conversation.other_user?.name || 'Người dùng' }}</span>
            <span v-if="conversation.last_message" class="chat-item__time">
              {{ formatRelativeTime(conversation.last_message.created_at) }}
            </span>
          </span>
          <span v-if="conversation.last_message" class="chat-item__excerpt">
            {{ conversation.last_message.message }}
          </span>
          <span v-else class="chat-item__excerpt chat-item__excerpt--muted">Chưa có tin nhắn</span>
        </span>
        <span v-if="conversation.unread_count > 0" class="chat-item__unread-dot" aria-hidden="true" />
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

.chat-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.75rem var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  cursor: pointer;
  font-family: inherit;
}

.chat-item:hover {
  background: var(--color-surface-muted);
}

.chat-item--unread {
  background: var(--color-primary-surface);
}

.chat-item__avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.chat-item__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.chat-item__body {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
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
  font-size: 0.8125rem;
  font-weight: 600;
}

.chat-item__time {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.chat-item__excerpt {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-item__excerpt--muted {
  font-style: italic;
}

.chat-item__unread-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.4rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}
</style>
