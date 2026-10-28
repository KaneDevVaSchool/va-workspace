//
// Dọn dữ liệu gắn với phiên đăng nhập khi đăng xuất / hết phiên (máy dùng chung):
// thông báo đẩy, realtime, cache trang đã mở.
//
// Các module tự đăng ký việc của mình (push, chat…) để auth store không phải
// import từng module — và guest không phải tải code chat chỉ để dọn.
//
//   onBeforeLogout(fn)    — chạy TRƯỚC khi gọi POST /logout (còn đăng nhập, gọi API được).
//   onSessionCleanup(fn)  — chạy SAU khi phiên đã kết thúc (chỉ dọn phía client).
//
const BEFORE_LOGOUT_TIMEOUT_MS = 3000;

const beforeLogoutHooks = new Set();
const cleanupHooks = new Set();

export function onBeforeLogout(fn) {
  beforeLogoutHooks.add(fn);
  return () => beforeLogoutHooks.delete(fn);
}

export function onSessionCleanup(fn) {
  cleanupHooks.add(fn);
  return () => cleanupHooks.delete(fn);
}

function withTimeout(promise, ms) {
  return Promise.race([
    promise,
    new Promise((resolve) => { window.setTimeout(resolve, ms); }),
  ]);
}

/** Best-effort: không bao giờ chặn việc đăng xuất quá vài giây hay vì lỗi một hook. */
export async function runBeforeLogout() {
  const run = Promise.allSettled([...beforeLogoutHooks].map((fn) => Promise.resolve().then(fn)));
  await withTimeout(run, BEFORE_LOGOUT_TIMEOUT_MS);
}

/** Xoá lịch sử trang đã mở trong cache của service worker (giữ shell "/" và trang offline). */
function clearServiceWorkerUserCaches() {
  try {
    const target = navigator.serviceWorker?.controller;
    target?.postMessage({ type: 'CLEAR_USER_CACHES' });
  } catch {
    // service worker không khả dụng — bỏ qua
  }
}

export async function runSessionCleanup() {
  clearServiceWorkerUserCaches();
  await Promise.allSettled([...cleanupHooks].map((fn) => Promise.resolve().then(fn)));
}
