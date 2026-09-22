<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatTime } from '@/lib/formatTime';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const auth = useAuthStore();

const draft = ref('');
const sending = ref(false);
const listRef = ref(null);

function scrollToBottom() {
  nextTick(() => {
    if (listRef.value) {
      listRef.value.scrollTop = listRef.value.scrollHeight;
    }
  });
}

onMounted(scrollToBottom);

watch(
  () => store.activeMessages.length,
  () => scrollToBottom(),
);

async function loadOlder() {
  const messages = store.activeMessages;
  if (messages.length === 0 || !store.hasMoreByConversation[store.activeConversationId]) return;

  const el = listRef.value;
  const previousHeight = el?.scrollHeight ?? 0;

  await store.fetchMessages(store.activeConversationId, messages[0].id);

  nextTick(() => {
    if (el) {
      el.scrollTop = el.scrollHeight - previousHeight;
    }
  });
}

function onScroll() {
  if (listRef.value && listRef.value.scrollTop < 40) {
    loadOlder();
  }
}

async function send() {
  const text = draft.value.trim();
  if (text === '' || sending.value) return;

  sending.value = true;
  try {
    await store.sendMessage(store.activeConversationId, text);
    draft.value = '';
  } finally {
    sending.value = false;
  }
}

function onKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

function isMine(message) {
  return message.sender?.id === auth.user?.id;
}
</script>

<template>
  <div class="chat-view">
    <div class="chat-view__head">
      <button type="button" class="chat-view__back" aria-label="Quay lại danh sách" @click="store.backToList()">
        <AppIcon name="chevronLeft" :size="18" :stroke-width="1.75" />
      </button>
      <span class="chat-view__title">{{ store.activeConversation?.other_user?.name || 'Trò chuyện' }}</span>
    </div>

    <div ref="listRef" class="chat-view__messages" @scroll="onScroll">
      <p v-if="store.loadingMessages && store.activeMessages.length === 0" class="chat-view__empty">Đang tải tin nhắn…</p>
      <p v-else-if="store.activeMessages.length === 0" class="chat-view__empty">
        Chưa có tin nhắn. Hãy bắt đầu cuộc trò chuyện.
      </p>
      <div
        v-for="message in store.activeMessages"
        :key="message.id"
        class="chat-bubble-row"
        :class="{ 'chat-bubble-row--mine': isMine(message) }"
      >
        <div class="chat-bubble" :class="{ 'chat-bubble--mine': isMine(message) }">
          <span class="chat-bubble__text">{{ message.message }}</span>
          <span class="chat-bubble__time">{{ formatTime(message.created_at) }}</span>
        </div>
      </div>
    </div>

    <div class="chat-view__composer">
      <textarea
        v-model="draft"
        class="chat-view__input"
        rows="1"
        placeholder="Nhập tin nhắn..."
        :aria-label="`Nhập tin nhắn gửi ${store.activeConversation?.other_user?.name || ''}`"
        @keydown="onKeydown"
      />
      <button
        type="button"
        class="chat-view__send"
        :disabled="draft.trim() === '' || sending"
        aria-label="Gửi tin nhắn"
        @click="send"
      >
        <AppIcon name="arrowRight" :size="18" :stroke-width="2" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-view {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.chat-view__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-view__back {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  flex-shrink: 0;
}

.chat-view__back:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.chat-view__title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-view__messages {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
}

.chat-view__empty {
  margin: auto 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.chat-bubble-row {
  display: flex;
  justify-content: flex-start;
}

.chat-bubble-row--mine {
  justify-content: flex-end;
}

.chat-bubble {
  max-width: 78%;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.5rem 0.75rem;
  border-radius: 12px;
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.chat-bubble--mine {
  background: var(--color-primary-surface);
}

.chat-bubble__text {
  font-size: 0.8125rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.chat-bubble__time {
  align-self: flex-end;
  color: var(--color-text-muted);
  font-size: 0.625rem;
}

.chat-view__composer {
  flex-shrink: 0;
  display: flex;
  align-items: flex-end;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-border);
}

.chat-view__input {
  flex: 1;
  min-width: 0;
  max-height: 6rem;
  resize: none;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  padding: 0.5rem 0.75rem;
}

.chat-view__input:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.chat-view__send {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  cursor: pointer;
}

.chat-view__send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
