import { onBeforeUnmount, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';
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
  useChatStore().$reset();
});

/**
 * Giữ kết nối Echo (inbox + từng hội thoại) khi user đã đăng nhập —
 * dùng trên shell mobile PWA (không có ChatWidget desktop).
 */
export function useChatRealtime() {
  const auth = useAuthStore();
  const chatStore = useChatStore();

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
}
