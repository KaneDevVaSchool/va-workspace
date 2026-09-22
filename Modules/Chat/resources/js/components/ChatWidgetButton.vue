<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();

const badgeLabel = computed(() => {
  if (store.unreadTotal <= 0) return '';
  return store.unreadTotal > 99 ? '99+' : String(store.unreadTotal);
});

const ariaLabel = computed(() =>
  store.unreadTotal > 0 ? `Trò chuyện, ${store.unreadTotal} tin nhắn chưa đọc` : 'Trò chuyện',
);
</script>

<template>
  <button
    type="button"
    class="chat-widget-btn"
    :class="{ 'chat-widget-btn--open': store.panelOpen }"
    aria-haspopup="dialog"
    :aria-expanded="store.panelOpen"
    :aria-label="ariaLabel"
    @click="store.togglePanel()"
  >
    <AppIcon name="messageCircle" :size="20" :stroke-width="1.75" />
    <span v-if="badgeLabel" class="chat-widget-btn__badge">{{ badgeLabel }}</span>
  </button>
</template>

<style scoped>
.chat-widget-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.chat-widget-btn:hover,
.chat-widget-btn--open {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.chat-widget-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.chat-widget-btn__badge {
  position: absolute;
  top: 0.2rem;
  right: 0.15rem;
  min-width: 1.05rem;
  height: 1.05rem;
  padding: 0 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
  color: var(--color-on-primary);
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1.05rem;
  text-align: center;
}
</style>
