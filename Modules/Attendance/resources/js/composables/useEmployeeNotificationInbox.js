//
// Hộp thư thông báo của shell mobile / PWA. State nằm ở module để chuông
// (header) và badge icon app (layout) dùng chung một lần poll — header biến
// mất khi đang trong hội thoại chat nhưng số chưa đọc vẫn được cập nhật.
//
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { showSystemNotification, useWebPush } from '@/composables/useWebPush';

const POLL_MS = 25000;

const items = ref([]);
const unreadCount = ref(0);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref('');
const panelOpen = ref(false);
const page = ref(1);
const lastPage = ref(1);

let consumers = 0;
let pollTimer = null;
let lastUnread = 0;
let hasUnreadBaseline = false;
let pushReadyRef = null;

function onVisible() {
  if (document.visibilityState === 'visible') {
    loadUnread();
  }
}

async function fetchPage(pageNumber, append) {
  const { data } = await window.axios.get('/api/notifications', {
    params: { per_page: 20, page: pageNumber },
  });
  const rows = Array.isArray(data.notifications) ? data.notifications : [];
  if (append) {
    const seen = new Set(items.value.map((row) => row.id));
    items.value = [...items.value, ...rows.filter((row) => !seen.has(row.id))];
  } else {
    items.value = rows;
  }
  const nextUnread = data.unread_count ?? unreadCount.value;
  unreadCount.value = nextUnread;
  lastUnread = nextUnread;
  hasUnreadBaseline = true;
  page.value = data.meta?.current_page ?? pageNumber;
  lastPage.value = data.meta?.last_page ?? 1;
}

async function loadUnread() {
  try {
    const { data } = await window.axios.get('/api/notifications/unread-count');
    const next = data.unread_count ?? 0;
    const pushOn = Boolean(pushReadyRef?.value);
    if (
      hasUnreadBaseline
      && next > lastUnread
      && document.hidden
      && !pushOn
      && typeof Notification !== 'undefined'
      && Notification.permission === 'granted'
    ) {
      try {
        const latest = data.latest_unread;
        const isChat = latest?.type === 'chat_message';
        const line = latest?.push_line || latest?.body || latest?.title;
        await showSystemNotification({
          title: isChat ? (line || '1 lời nhắn từ VA') : 'VA',
          body: isChat ? '' : (line || (next === 1 ? 'Bạn có 1 thông báo mới.' : `Bạn có ${next} thông báo chưa đọc.`)),
          tag: latest?.id ? `va-n-${latest.id}` : 'va-inbox',
          url: latest?.url || '/dashboard/me',
          icon: latest?.actor?.avatar_url || '/images/pwa/icon-192.png',
        });
      } catch {
        // Trình duyệt có thể chặn Notification khi không có user gesture.
      }
    }
    const increased = hasUnreadBaseline && next > lastUnread;
    lastUnread = next;
    unreadCount.value = next;
    hasUnreadBaseline = true;
    if (increased && panelOpen.value && !loadingMore.value) {
      fetchPage(1, false).catch(() => {});
    }
  } catch {
    // Im lặng — chuông không được làm hỏng shell.
  }
}

async function refreshList() {
  const showSpinner = items.value.length === 0;
  if (showSpinner) loading.value = true;
  error.value = '';
  try {
    await fetchPage(1, false);
  } catch {
    error.value = 'Không tải được thông báo.';
  } finally {
    loading.value = false;
  }
}

async function loadMore() {
  if (loadingMore.value || loading.value || page.value >= lastPage.value) return;
  loadingMore.value = true;
  try {
    await fetchPage(page.value + 1, true);
  } catch {
    // Giữ danh sách đang có.
  } finally {
    loadingMore.value = false;
  }
}

async function markOneRead(id) {
  const { data } = await window.axios.post(`/api/notifications/${id}/read`);
  const readAt = data.notification?.read_at ?? new Date().toISOString();
  items.value = items.value.map((row) => (row.id === id ? { ...row, read_at: readAt } : row));
  unreadCount.value = data.unread_count ?? Math.max(0, unreadCount.value - 1);
  lastUnread = unreadCount.value;
}

async function markAllRead() {
  await window.axios.post('/api/notifications/read-all');
  const now = new Date().toISOString();
  items.value = items.value.map((row) => ({ ...row, read_at: row.read_at ?? now }));
  unreadCount.value = 0;
  lastUnread = 0;
}

function openPanel() {
  panelOpen.value = true;
  refreshList();
}

function closePanel() {
  panelOpen.value = false;
}

function startPolling() {
  if (pollTimer != null || typeof window === 'undefined') return;
  document.addEventListener('visibilitychange', onVisible);
  window.addEventListener('focus', loadUnread);
  loadUnread();
  pollTimer = window.setInterval(loadUnread, POLL_MS);
}

function stopPolling() {
  if (pollTimer != null) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
  document.removeEventListener('visibilitychange', onVisible);
  window.removeEventListener('focus', loadUnread);
}

export function useEmployeeNotificationInbox() {
  const { pushReady } = useWebPush();
  pushReadyRef = pushReady;

  onMounted(() => {
    consumers += 1;
    if (consumers === 1) startPolling();
  });

  onBeforeUnmount(() => {
    consumers -= 1;
    if (consumers <= 0) {
      consumers = 0;
      stopPolling();
    }
  });

  return {
    items,
    unreadCount,
    loading,
    loadingMore,
    error,
    panelOpen,
    page,
    lastPage,
    openPanel,
    closePanel,
    refreshList,
    loadMore,
    markOneRead,
    markAllRead,
  };
}
