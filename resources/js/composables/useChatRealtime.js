import { onBeforeUnmount, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';
import { resetMessageFetchEpochs } from '@modules/Chat/resources/js/lib/chatMessageFetchEpoch';
import { leaveAllEchoChannels } from '@/echo';
import { onAppResume } from '@/lib/appResume';
import { onSessionCleanup } from '@/lib/sessionCleanup';

let presenceTimer = null;

// Đăng xuất / hết phiên: dừng nhịp "đang online", rời kênh realtime và xoá hội thoại
// trong store — máy dùng chung không được còn nhận tin hay hiện toast của người trước.
onSessionCleanup(() => {
  window.clearInterval(presenceTimer);
  presenceTimer = null;
  leaveAllEchoChannels();
  resetMessageFetchEpochs();
  useChatStore().$reset();
});

/**
 * Giữ kết nối Echo (inbox + từng hội thoại) khi user đã đăng nhập —
 * dùng trên shell mobile PWA (không có ChatWidget desktop).
 */
export function useChatRealtime() {
  const auth = useAuthStore();
  const chatStore = useChatStore();
  const route = useRoute();

  watch(
    () => auth.user?.id,
    (userId) => {
      if (!userId) return;
      chatStore.ensureRealtimeSubscriptions().catch(() => {});
      chatStore.beatPresence();
      window.clearInterval(presenceTimer);
      // Tab/PWA đang ẩn thì không báo "đang online" (tiết kiệm pin, và đúng trạng thái thật).
      presenceTimer = window.setInterval(() => {
        if (!document.hidden) chatStore.beatPresence();
      }, 30000);
    },
    { immediate: true },
  );

  // Quay lại app / có mạng lại: đồng bộ tin đã lỡ trong lúc websocket đứt.
  const stopResume = onAppResume(() => {
    chatStore.refreshAfterResume().catch(() => {});
  });
  onBeforeUnmount(stopResume);

  // Metadata hội thoại đổi (inbox, fetch list, push) trong khi đang xem thread — kéo tin mới.
  watch(
    () => chatStore.conversations.map(
      (item) => `${item.id}:${item.unread_count ?? 0}:${item.last_message?.id ?? 0}`,
    ).join('|'),
    () => {
      chatStore.syncActiveConversationIfStale().catch(() => {});
    },
  );

  // Deep link / tab chat (?chat, ?user) — không kích khi chỉ thêm ?thread= cho nút Back.
  watch(
    () => (
      route.name === 'employee.chat'
        ? `${route.query.chat ?? ''}|${route.query.user ?? ''}`
        : ''
    ),
    (key, previous) => {
      if (!key && !previous) return;
      chatStore.fetchConversations().catch(() => {});
    },
  );

  // Tab hiện lại < 30s (không kích appResume): vẫn sync thread đang mở.
  if (typeof document !== 'undefined') {
    const onVisible = () => {
      if (document.visibilityState !== 'visible') return;
      chatStore.syncActiveConversationIfStale().catch(() => {});
    };
    document.addEventListener('visibilitychange', onVisible);
    onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisible));
  }
}
