//
// PWA cài trên màn hình (display-mode: standalone / iOS navigator.standalone).
// Trình duyệt thường OK; standalone hay lộ nền body trắng dưới vùng fixed —
// cần class sớm trên <html> + màu nền body theo shell (guest / employee / app).
//

/** Khớp media query gắn <link> PWA (mẫu Drupal / Stack Overflow). */
export const PWA_DISPLAY_MODE_MEDIA = '(display-mode: standalone), (display-mode: fullscreen)';

export function isPwaStandalone() {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true
  );
}

function syncPwaStandaloneClass() {
  if (typeof document === 'undefined') return;
  document.documentElement.classList.toggle('pwa-standalone', isPwaStandalone());
}

export function applyPwaStandaloneClass() {
  syncPwaStandaloneClass();
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return;

  const mq = window.matchMedia(PWA_DISPLAY_MODE_MEDIA);
  const onChange = () => syncPwaStandaloneClass();
  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', onChange);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(onChange);
  }
}

function pwaStandaloneStylesheetPresent() {
  if (typeof document === 'undefined') return false;
  try {
    return [...document.styleSheets].some(
      (sheet) => sheet.href && sheet.href.includes('pwa-standalone'),
    );
  } catch {
    return false;
  }
}

/** CSS (fallback iOS) + JS chỉ PWA — không gọi khi mở tab trình duyệt thường. */
export function loadPwaStandaloneAssets() {
  if (!isPwaStandalone()) return;
  if (!pwaStandaloneStylesheetPresent()) {
    void import('../../css/pwa-standalone.css');
  }
  void import('../pwa/bootstrap.js').then((m) => m.bootstrapPwaStandalone());
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
