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

/** @param {'guest' | 'employee' | 'app' | null} kind */
export function setPwaShellSurface(kind) {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  root.classList.remove('pwa-surface-guest', 'pwa-surface-employee', 'pwa-surface-app');
  if (kind) {
    root.classList.add(`pwa-surface-${kind}`);
  }
}
