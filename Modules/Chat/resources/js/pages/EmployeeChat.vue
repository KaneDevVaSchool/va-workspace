<script setup>
//
// Trò chuyện 1-1 trên app PWA nhân viên — tái sử dụng danh sách và khung chat
// desktop (ChatConversationList / ChatConversationView), state Pinia dùng chung.
//
import { inject, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChatConversationList from '../components/ChatConversationList.vue';
import ChatConversationView from '../components/ChatConversationView.vue';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const route = useRoute();
const router = useRouter();
const registerPageRefresh = inject('employeePageRefreshRegister', null);

let unregisterRefresh;

async function refreshConversations() {
  await store.fetchConversations();
}

async function openUserFromQuery(rawUserId) {
  const id = Number(rawUserId);
  if (!Number.isFinite(id) || id < 1) return;
  try {
    await store.fetchConversations();
    await store.openWithUser(id);
  } catch {
    store.panelOpen = true;
    store.activeView = 'list';
  }
}

async function openConversationFromQuery(rawConversationId) {
  const id = Number(rawConversationId);
  if (!Number.isFinite(id) || id < 1) return;
  try {
    await store.fetchConversations();
    await store.openConversationView(id);
  } catch {
    store.panelOpen = true;
    store.activeView = 'list';
  }
}

function clearDeepLinkQuery(keys) {
  const query = { ...route.query };
  let changed = false;
  for (const key of keys) {
    if (key in query) {
      delete query[key];
      changed = true;
    }
  }
  if (changed) {
    router.replace({ name: 'employee.chat', query });
  }
}

watch(
  () => route.query.user,
  (value) => {
    if (value == null || value === '') return;
    openUserFromQuery(value).finally(() => clearDeepLinkQuery(['user']));
  },
  { immediate: true },
);

watch(
  () => route.query.chat,
  (value) => {
    if (value == null || value === '') return;
    openConversationFromQuery(value).finally(() => clearDeepLinkQuery(['chat']));
  },
  { immediate: true },
);

// Mở một hội thoại thì thêm một mục lịch sử (`?thread=1`) để nút Back của hệ thống (Android Back, vuốt
// iOS) đóng hội thoại về danh sách thay vì rời cả trang Chat — PWA không có nút Back của trình duyệt.
let threadEntryPushed = false;

watch(
  () => store.activeView,
  (view) => {
    if (view === 'conversation' && !threadEntryPushed) {
      threadEntryPushed = true;
      router
        .push({ name: 'employee.chat', query: { ...route.query, thread: '1' } })
        .catch(() => {});
    } else if (view === 'list' && threadEntryPushed) {
      // Đóng bằng nút trong app: gỡ mục lịch sử đã thêm.
      threadEntryPushed = false;
      if (route.query.thread) router.back();
    }
  },
);

watch(
  () => route.query.thread,
  (thread) => {
    // Người dùng bấm Back của hệ thống: mục lịch sử bị gỡ → đóng hội thoại.
    if (thread || !threadEntryPushed) return;
    threadEntryPushed = false;
    if (store.activeView === 'conversation') store.backToList();
  },
);

onMounted(async () => {
  store.panelOpen = true;
  const hasDeepLink = Boolean(route.query.user || route.query.chat);
  if (!hasDeepLink && store.activeView !== 'conversation') {
    store.activeView = 'list';
  }
  await Promise.all([
    store.conversations.length === 0 ? store.fetchConversations() : Promise.resolve(),
    store.fetchUnreadTotal(),
  ]).catch(() => {});

  if (typeof registerPageRefresh === 'function') {
    unregisterRefresh = registerPageRefresh(refreshConversations);
  }
});

onBeforeUnmount(() => {
  unregisterRefresh?.();
  // Rời trang Chat: không được gọi router.back() từ watcher khi đóng hội thoại bên dưới.
  threadEntryPushed = false;
  store.backToList();
  store.panelOpen = false;
});
</script>

<template>
  <div class="employee-chat">
    <ChatConversationList v-if="store.activeView === 'list'" />
    <ChatConversationView v-else />
  </div>
</template>

<style scoped>
.employee-chat {
  display: flex;
  flex-direction: column;
  flex: 1;
  height: 100%;
  min-height: 0;
  margin: 0 calc(-1 * var(--space-3)) calc(-1 * var(--space-3));
  overflow: hidden;
}
</style>
