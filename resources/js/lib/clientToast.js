//
// Toast client-side qua CustomEvent — port từ va-hrm (resources/js/lib/clientToast.ts).
// ToastHost.vue lắng nghe event này để hiện toast từ bất kỳ đâu trong app
// (component, store, hoặc code ngoài Vue) mà không cần inject/provide.
//

const EVENT_NAME = 'va-client-toast';

/**
 * @param {'success'|'error'|'warning'|'info'} variant
 * @param {string} message
 * @param {{ duration?: number }} [options] Thời gian tự đóng (ms). Bỏ qua với
 *   variant 'error' (luôn cần đóng thủ công vì cần người dùng đọc/xử lý).
 */
export function showClientToast(variant, message, options = {}) {
  window.dispatchEvent(
    new CustomEvent(EVENT_NAME, {
      detail: { variant, message, duration: options.duration },
    }),
  );
}

/** @param {(detail: { variant: 'success'|'error'|'warning'|'info', message: string, duration?: number }) => void} handler */
export function subscribeClientToast(handler) {
  const listener = (e) => {
    if (e.detail?.message) handler(e.detail);
  };
  window.addEventListener(EVENT_NAME, listener);
  return () => window.removeEventListener(EVENT_NAME, listener);
}
