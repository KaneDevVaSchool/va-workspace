import { watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { useChatStore } from '@modules/Chat/resources/js/store/chatStore';

let presenceTimer = null;

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
      presenceTimer = window.setInterval(() => chatStore.beatPresence(), 30000);
    },
    { immediate: true },
  );
}
