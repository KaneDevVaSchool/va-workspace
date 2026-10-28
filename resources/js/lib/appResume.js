//
// Một nơi duy nhất phát sự kiện "app vừa quay lại" để các màn tự làm mới dữ liệu:
//   - 'visible': tab/PWA hiện lại sau khi bị ẩn đủ lâu (iOS đóng băng PWA nền → websocket
//                đứt và Reverb không phát lại tin đã lỡ);
//   - 'online' : mạng vừa có lại sau khi mất kết nối.
//
const RESUME_AFTER_HIDDEN_MS = 30_000;

const listeners = new Set();
let attached = false;
let hiddenAt = null;

function emit(reason) {
  listeners.forEach((listener) => {
    try {
      listener(reason);
    } catch {
      // một màn lỗi không được chặn các màn khác làm mới
    }
  });
}

function attach() {
  if (attached || typeof document === 'undefined' || typeof window === 'undefined') return;
  attached = true;

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
      return;
    }
    const awayMs = hiddenAt === null ? 0 : Date.now() - hiddenAt;
    hiddenAt = null;
    if (awayMs >= RESUME_AFTER_HIDDEN_MS) emit('visible');
  });

  // Khôi phục từ bfcache (Safari) không bắn visibilitychange đáng tin.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) emit('visible');
  });

  window.addEventListener('online', () => emit('online'));
}

/** @param {(reason: 'visible' | 'online') => void} listener  @returns {() => void} hàm huỷ đăng ký */
export function onAppResume(listener) {
  attach();
  listeners.add(listener);
  return () => listeners.delete(listener);
}
