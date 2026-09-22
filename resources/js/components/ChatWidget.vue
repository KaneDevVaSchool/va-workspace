<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ChatFloatingPanel from '@modules/Chat/resources/js/components/ChatFloatingPanel.vue';
import ChatWidgetButton from '@modules/Chat/resources/js/components/ChatWidgetButton.vue';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';

const store = useChatStore();
const rootRef = ref(null);

function handleDocumentClick(event) {
  if (!store.panelOpen || !rootRef.value) return;
  if (rootRef.value.contains(event.target)) return;
  store.closePanel();
}

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
  store.fetchConversations().catch(() => {});
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentClick);
});
</script>

<template>
  <div ref="rootRef" class="chat-widget">
    <ChatWidgetButton />
    <ChatFloatingPanel />
  </div>
</template>

<style scoped>
.chat-widget {
  position: relative;
}
</style>
