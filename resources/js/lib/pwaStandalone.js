//
// PWA cài trên màn hình (display-mode: standalone / iOS navigator.standalone).
// Trình duyệt thường OK; standalone hay lộ nền body trắng dưới vùng fixed —
// cần class sớm trên <html> + màu nền body theo shell (guest / employee / app).
//

export function isPwaStandalone() {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true
  );
}

export function applyPwaStandaloneClass() {
  if (typeof document === 'undefined') return;
  if (isPwaStandalone()) {
    document.documentElement.classList.add('pwa-standalone');
  }
}

/**
 * Đăng ký SW sớm cho mọi phiên (không chỉ standalone) để app shell + asset
 * được cache ngay từ lần ghé đầu — mở lại khi mất mạng vẫn vào được.
 * Khi có bản SW mới chờ sẵn (waiting), tự kích hoạt rồi reload 1 lần —
 * tránh người dùng kẹt ở bản cache cũ vô thời hạn.
 */
export function bootstrapPwaServiceWorker() {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;

  // Nếu trang chưa có controller (chưa từng cài SW), lần "controllerchange"
  // đầu tiên chỉ là SW mới nhận quyền kiểm soát — không phải một bản cập
  // nhật — nên không cần reload.
  const hadControllerAtLoad = Boolean(navigator.serviceWorker.controller);

  navigator.serviceWorker.register('/sw.js', {
    scope: '/',
    updateViaCache: 'none',
  }).then((registration) => {
    function activateWaiting(worker) {
      worker.postMessage('skipWaiting');
    }

    if (registration.waiting && registration.active) {
      activateWaiting(registration.waiting);
    }

    registration.addEventListener('updatefound', () => {
      const worker = registration.installing;
      if (!worker) return;
      worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && registration.active) {
          activateWaiting(worker);
        }
      });
    });
  }).catch(() => {});

  let reloadedOnce = false;
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (!hadControllerAtLoad) return;
    if (reloadedOnce) return;
    reloadedOnce = true;
    window.location.reload();
  });
}

/** @param {'guest' | 'employee' | 'app' | null} kind */
export function setPwaShellSurface(kind) {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  root.classList.remove('pwa-surface-guest', 'pwa-surface-employee', 'pwa-surface-app');
  if (kind) {
    root.classList.add(`pwa-surface-${kind}`);
  }
}
