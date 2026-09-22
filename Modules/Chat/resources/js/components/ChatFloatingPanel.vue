<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ChatConversationList from './ChatConversationList.vue';
import ChatConversationView from './ChatConversationView.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();

function close() {
  store.closePanel();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && store.panelOpen) close();
}

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <div v-if="store.panelOpen" class="chat-panel" role="dialog" aria-label="Trò chuyện">
    <div v-if="store.activeView === 'list'" class="chat-panel__head">
      <span class="chat-panel__title">Trò chuyện</span>
      <button type="button" class="chat-panel__close" aria-label="Đóng khung trò chuyện" @click="close">
        <AppIcon name="close" :size="16" :stroke-width="2" />
      </button>
    </div>

    <ChatConversationList v-if="store.activeView === 'list'" />
    <ChatConversationView v-else />
  </div>
</template>

<style scoped>
.chat-panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: min(28rem, calc(100vw - 1.5rem));
  height: min(36rem, calc(100vh - 5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.chat-panel__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-panel__title {
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.chat-panel__close {
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
}

.chat-panel__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

@media (max-width: 480px) {
  .chat-panel {
    position: fixed;
    top: 3.5rem;
    right: var(--space-3);
    left: var(--space-3);
    width: auto;
    height: calc(100vh - 4.5rem);
  }
}
</style>
