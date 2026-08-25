<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { formatRelativeTime } from '../lib/formatTime';
import { showClientToast } from '../lib/clientToast';
import { useHeaderPopover } from '../composables/useHeaderPopover';
import { useWebPush } from '../composables/useWebPush';
import AppIcon from './AppIcon.vue';

const POLL_MS = 25000;

const router = useRouter();
const rootRef = ref(null);
const { isOpen, toggle, close } = useHeaderPopover('notifications');
const {
  permission,
  pushReady,
  pushSupported,
  configured,
  vapidChecked,
  enabling,
  lastError,
  isBraveBrowser,
  enablePush,
  disablePush,
} = useWebPush();

const items = ref([]);
const unreadCount = ref(0);
const loading = ref(false);
let pollTimer = null;
let lastUnread = 0;

const badgeLabel = computed(() => {
  if (unreadCount.value <= 0) return '';
  return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});

const canTogglePush = computed(() => {
  if (!pushSupported.value || enabling.value) return false;
  if (pushReady.value) return true;
  return configured.value && permission.value !== 'denied';
});

const pushHint = computed(() => {
  if (!pushSupported?.value) return '';
  if (permission?.value === 'denied') return 'Trình duyệt đang chặn thông báo đẩy.';
  if (vapidChecked?.value && !configured?.value) return 'Máy chủ chưa cấu hình thông báo đẩy.';
  if (isBraveBrowser?.value && !pushReady.value) {
    return 'Brave chặn đẩy Google mặc định. Bật “Use Google services for push messaging” tại brave://settings/privacy.';
  }
  return '';
});

async function loadUnread() {
  try {
    const { data } = await window.axios.get('/api/notifications/unread-count');
    const next = data.unread_count ?? 0;
    if (next > lastUnread && lastUnread > 0 && document.hidden && Notification.permission === 'granted') {
      try {
        new Notification('VA Workspace', {
          body: next === 1 ? 'Bạn có 1 thông báo mới.' : `Bạn có ${next} thông báo chưa đọc.`,
          icon: '/images/favicon.png',
          tag: 'va-inbox',
        });
      } catch {
        // Trình duyệt có thể chặn Notification khi không có user gesture.
      }
    }
    lastUnread = next;
    unreadCount.value = next;
  } catch {
    // im lặng — chuông không được làm hỏng header
  }
}

async function loadList() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/notifications', { params: { per_page: 20 } });
    items.value = data.notifications ?? [];
    unreadCount.value = data.unread_count ?? 0;
    lastUnread = unreadCount.value;
  } catch {
    showClientToast('error', 'Không tải được thông báo.');
  } finally {
    loading.value = false;
  }
}

async function openItem(item) {
  close();
  if (!item.read_at) {
    try {
      const { data } = await window.axios.post(`/api/notifications/${item.id}/read`);
      unreadCount.value = data.unread_count ?? Math.max(0, unreadCount.value - 1);
      lastUnread = unreadCount.value;
      items.value = items.value.map((row) => (row.id === item.id ? { ...row, read_at: data.notification?.read_at ?? new Date().toISOString() } : row));
    } catch {
      // vẫn điều hướng
    }
  }
  if (item.url) {
    router.push(item.url);
  }
}

async function markAllRead() {
  try {
    await window.axios.post('/api/notifications/read-all');
    unreadCount.value = 0;
    lastUnread = 0;
    items.value = items.value.map((row) => ({ ...row, read_at: row.read_at ?? new Date().toISOString() }));
  } catch {
    showClientToast('error', 'Không đánh dấu được đã đọc.');
  }
}

async function onTogglePush() {
  if (!canTogglePush.value) return;
  if (pushReady.value) {
    const ok = await disablePush();
    if (ok) {
      showClientToast('success', 'Đã tắt thông báo đẩy trên trình duyệt này.');
      return;
    }
    showClientToast('error', lastError.value || 'Không tắt được thông báo đẩy.');
    return;
  }
  const ok = await enablePush();
  if (ok) {
    showClientToast('success', 'Đã bật thông báo đẩy trên trình duyệt này.');
    return;
  }
  showClientToast('error', lastError.value || 'Không bật được thông báo đẩy.');
}

function handleDocumentClick(event) {
  if (!isOpen.value || !rootRef.value) return;
  if (rootRef.value.contains(event.target)) return;
  close();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) close();
}

function startPoll() {
  stopPoll();
  pollTimer = window.setInterval(loadUnread, POLL_MS);
}

function stopPoll() {
  if (pollTimer) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  window.addEventListener('focus', loadUnread);
  loadUnread();
  startPoll();
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
  window.removeEventListener('focus', loadUnread);
  stopPoll();
});

function togglePanel() {
  toggle();
  if (!isOpen.value) return;
  loadList().catch(() => {});
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <div ref="rootRef" class="header-pop">
    <button
      type="button"
      class="header-pop__btn"
      :class="{ 'header-pop__btn--open': isOpen }"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      :aria-label="unreadCount > 0 ? `Thông báo, ${unreadCount} chưa đọc` : 'Thông báo'"
      @click="togglePanel"
    >
      <AppIcon name="bell" :size="20" :stroke-width="1.75" />
      <span v-if="badgeLabel" class="header-pop__badge">{{ badgeLabel }}</span>
    </button>

    <div v-if="isOpen" class="header-pop__panel" role="dialog" aria-label="Thông báo">
      <div class="header-pop__head">
        <div class="header-pop__title-row">
          <span class="header-pop__tab">Thông báo</span>
          <button
            v-if="pushSupported"
            type="button"
            class="header-pop__switch"
            :class="{ 'header-pop__switch--on': pushReady }"
            role="switch"
            :aria-checked="pushReady"
            :aria-label="pushReady ? 'Tắt thông báo đẩy trình duyệt' : 'Bật thông báo đẩy trình duyệt'"
            :disabled="!canTogglePush"
            :aria-busy="enabling"
            @click="onTogglePush"
          >
            <span class="header-pop__switch-thumb" />
          </button>
        </div>
        <button
          v-if="unreadCount > 0"
          type="button"
          class="header-pop__all"
          @click="markAllRead"
        >
          Đánh dấu đã đọc
        </button>
      </div>
      <p v-if="pushHint" class="header-pop__push-note">{{ pushHint }}</p>
      <p v-if="lastError && !pushReady && permission !== 'denied'" class="header-pop__push-error">{{ lastError }}</p>

      <div class="notice-list">
        <p v-if="loading" class="notice-list__empty">Đang tải…</p>
        <p v-else-if="items.length === 0" class="notice-list__empty">Chưa có thông báo nào.</p>
        <button
          v-for="item in items"
          v-else
          :key="item.id"
          type="button"
          class="notice-item"
          :class="{ 'notice-item--unread': !item.read_at }"
          @click="openItem(item)"
        >
          <img
            v-if="item.actor?.avatar_url"
            class="notice-item__avatar"
            :src="item.actor.avatar_url"
            :alt="`Ảnh đại diện của ${item.actor.name}`"
          />
          <span v-else class="notice-item__avatar notice-item__avatar--placeholder">
            {{ initial(item.actor?.name) }}
          </span>
          <span class="notice-item__body">
            <span class="notice-item__title">{{ item.title }}</span>
            <span v-if="item.body" class="notice-item__excerpt">{{ item.body }}</span>
            <span class="notice-item__time">{{ formatRelativeTime(item.created_at) }}</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.header-pop {
  position: relative;
}

.header-pop__btn {
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

.header-pop__btn:hover,
.header-pop__btn--open {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.header-pop__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-pop__badge {
  position: absolute;
  top: 0.2rem;
  right: 0.15rem;
  min-width: 1.05rem;
  height: 1.05rem;
  padding: 0 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
  color: #fff;
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1.05rem;
  text-align: center;
}

.header-pop__panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: min(28rem, calc(100vw - 1.5rem));
  max-height: min(36rem, calc(100vh - 5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.header-pop__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.header-pop__title-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.header-pop__tab {
  flex-shrink: 0;
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.header-pop__all {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.header-pop__switch {
  position: relative;
  flex-shrink: 0;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.header-pop__switch--on {
  background: var(--color-success);
}

.header-pop__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.header-pop__switch:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.header-pop__switch:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-pop__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.header-pop__switch--on .header-pop__switch-thumb {
  transform: translateX(1.25rem);
}

.header-pop__push-note,
.header-pop__push-error {
  flex-shrink: 0;
  margin: 0;
  padding: var(--space-2) var(--space-3);
  font-size: 0.75rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.header-pop__push-note {
  color: var(--color-text-muted);
}

.header-pop__push-error {
  color: var(--color-danger);
}

@media (prefers-reduced-motion: reduce) {
  .header-pop__switch-thumb {
    transition: none;
  }
}

.notice-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.notice-list__empty {
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.notice-item {
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

.notice-item:hover {
  background: var(--color-surface-muted);
}

.notice-item--unread {
  background: var(--color-primary-surface);
}

.notice-item__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.notice-item__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.notice-item__body {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.notice-item__title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.notice-item__excerpt {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.notice-item__time {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

@media (max-width: 480px) {
  .header-pop__panel {
    position: fixed;
    top: 3.5rem;
    right: var(--space-3);
    left: var(--space-3);
    width: auto;
  }
}
</style>
