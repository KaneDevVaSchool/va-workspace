<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChatFloatingPanel from '@modules/Chat/resources/js/components/ChatFloatingPanel.vue';
import ChatWidgetButton from '@modules/Chat/resources/js/components/ChatWidgetButton.vue';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';

const store = useChatStore();
const route = useRoute();
const router = useRouter();
const rootRef = ref(null);

watch(
  () => route.query.chat,
  async (value) => {
    const id = Number(value);
    if (!Number.isFinite(id) || id < 1) return;
    try {
      if (store.conversations.length === 0) {
        await store.fetchConversations();
      }
      await store.openConversationView(id);
    } catch {
      store.panelOpen = true;
    }
    const query = { ...route.query };
    delete query.chat;
    router.replace({ path: route.path, query });
  },
  { immediate: true },
);

onMounted(() => {
  store.fetchConversations().catch(() => {});
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
