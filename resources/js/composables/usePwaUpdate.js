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
/** Worker đã gắn listener statechange — tránh track trùng. */
const trackedWorkers = new WeakSet();

function listenControllerChange() {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (reloaded) return;
    reloaded = true;
    clearUpdatePrompt();
    window.location.reload();
  });
}

function clearUpdatePrompt() {
  waitingWorker = null;
  updateAvailable.value = false;
}

function activate(worker) {
  if (!worker || worker.state === 'redundant') return;
  worker.postMessage('skipWaiting');
}

function watchWaitingWorker(worker) {
  if (!worker || trackedWorkers.has(worker)) return;
  trackedWorkers.add(worker);
  worker.addEventListener('statechange', () => {
    if (worker.state === 'redundant' || worker.state === 'activated') {
      if (waitingWorker === worker) {
        clearUpdatePrompt();
      }
    }
  });
}

function refreshUpdateState(registration) {
  if (!registration?.active || !registration.waiting) {
    clearUpdatePrompt();
    return;
  }
  const waiting = registration.waiting;
  if (waiting.state === 'redundant') {
    clearUpdatePrompt();
    return;
  }
  trackWaiting(waiting);
}

function trackWaiting(worker) {
  if (!worker || worker.state === 'redundant') {
    clearUpdatePrompt();
    return;
  }
  waitingWorker = worker;
  updateAvailable.value = true;
  watchWaitingWorker(worker);

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
    refreshUpdateState(registration);

    registration.addEventListener('updatefound', () => {
      const worker = registration.installing;
      if (!worker) return;
      worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && registration.active) {
          trackWaiting(worker);
        }
        if (worker.state === 'redundant') {
          refreshUpdateState(registration);
        }
      });
    });

    // Tab quay lại foreground → chủ động hỏi server có bản mới chưa, thay
    // vì phụ thuộc chu kỳ check mặc định (thưa) của trình duyệt.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') return;
      registration.update()
        .then(() => refreshUpdateState(registration))
        .catch(() => {});
    });
  }).catch(() => {});
}

/** Dùng trong banner "Có bản cập nhật mới". */
export function usePwaUpdate() {
  function applyUpdate() {
    reloaded = true;
    const worker = waitingWorker || registrationRef?.waiting;
    if (worker) {
      activate(worker);
    }
    clearUpdatePrompt();
    // Người dùng đã bấm "Tải lại" — luôn reload; không chỉ dựa controllerchange
    // (Safari / tab khác giữ SW cũ có thể không bắn sự kiện).
    window.setTimeout(() => {
      window.location.reload();
    }, worker ? 120 : 0);
  }

  return { updateAvailable, applyUpdate };
}
