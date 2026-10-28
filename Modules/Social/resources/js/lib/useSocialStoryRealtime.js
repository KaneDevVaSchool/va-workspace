import { onBeforeUnmount, watch } from 'vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

/**
 * Tải lại vòng story khi đồng nghiệp đăng/xoá (Echo) hoặc khi quay lại tab PWA.
 */
export function useSocialStoryRealtime(onRefresh) {
  const auth = useAuthStore();
  let channelName = null;
  let visibilityBound = false;

  function onVisibility() {
    if (document.visibilityState === 'visible') {
      onRefresh();
    }
  }

  function bindVisibility() {
    if (visibilityBound || typeof document === 'undefined') return;
    visibilityBound = true;
    document.addEventListener('visibilitychange', onVisibility);
  }

  function unbindVisibility() {
    if (!visibilityBound) return;
    visibilityBound = false;
    document.removeEventListener('visibilitychange', onVisibility);
  }

  function subscribe(userId) {
    // Kiểm tra userId TRƯỚC: chạm vào window.Echo là khởi tạo (và mở websocket) lần đầu.
    if (!userId || !window.Echo) return;
    const nextChannel = `App.Models.User.${userId}`;
    if (channelName === nextChannel) return;
    if (channelName) {
      window.Echo.leave(channelName);
    }
    channelName = nextChannel;
    window.Echo.private(nextChannel).listen('.story.rings.updated', () => {
      onRefresh();
    });
  }

  function unsubscribe() {
    if (channelName && window.Echo) {
      window.Echo.leave(channelName);
    }
    channelName = null;
    unbindVisibility();
  }

  watch(
    () => auth.user?.id,
    (userId) => {
      unsubscribe();
      if (!userId) return;
      subscribe(userId);
      bindVisibility();
    },
    { immediate: true },
  );

  onBeforeUnmount(unsubscribe);
}
