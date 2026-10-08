//
// Theo dõi vòng đời service worker để báo "có bản mới" mà KHÔNG tự ý reload
// ngay lập tức (tránh mất dữ liệu form đang nhập dở). Quy tắc áp dụng bản mới:
//
// - Có bản mới + tab đang ẩn (người dùng đã chuyển qua app khác) → áp dụng
//   ngầm ngay, không ai nhìn thấy gián đoạn.
// - Có bản mới + tab đang mở và đang nhìn → hiện banner nhỏ, để người dùng
//   tự bấm "Tải lại" khi họ rảnh tay (xong form đang làm).
// - Mỗi lần tab quay lại foreground, chủ động gọi registration.update() —
//   không phải đợi browser tự check (thường khá thưa).
//
import { ref } from 'vue';

const updateAvailable = ref(false);
let registrationRef = null;
let waitingWorker = null;
let reloaded = false;

function listenControllerChange() {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloaded) return;
    reloaded = true;
    window.location.reload();
  });
}

function activate(worker) {
  worker.postMessage('skipWaiting');
}

function trackWaiting(worker) {
  waitingWorker = worker;
  updateAvailable.value = true;

  if (document.visibilityState === 'hidden') {
    activate(worker);
  }
}

/**
 * Đăng ký SW sớm cho mọi phiên (không chỉ standalone) để app shell + asset
 * được cache ngay từ lần ghé đầu — mở lại khi mất mạng vẫn vào được.
 */
export function bootstrapPwaServiceWorker() {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;

  listenControllerChange();

  navigator.serviceWorker.register('/sw.js', {
    scope: '/',
    updateViaCache: 'none',
  }).then((registration) => {
    registrationRef = registration;

    if (registration.waiting && registration.active) {
      trackWaiting(registration.waiting);
    }

    registration.addEventListener('updatefound', () => {
      const worker = registration.installing;
      if (!worker) return;
      worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && registration.active) {
          trackWaiting(worker);
        }
      });
    });

    // Tab quay lại foreground → chủ động hỏi server có bản mới chưa, thay
    // vì phụ thuộc chu kỳ check mặc định (thưa) của trình duyệt.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') return;
      registration.update().catch(() => {});
      if (waitingWorker) {
        activate(waitingWorker);
      }
    });
  }).catch(() => {});
}

/** Dùng trong banner "Có bản cập nhật mới". */
export function usePwaUpdate() {
  function applyUpdate() {
    if (waitingWorker) {
      activate(waitingWorker);
      return;
    }
    // Không có SW mới đang chờ vì lý do nào đó — reload tay vẫn lấy bản mới
    // nhất từ server (route /sw.js không cache).
    window.location.reload();
  }

  return { updateAvailable, applyUpdate };
}
