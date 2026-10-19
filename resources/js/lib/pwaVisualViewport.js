//
// Khung AppShell PWA: gắn kích thước shell theo visualViewport (PWAProvider pattern).
// Tránh inset:0 + 100dvh — layout viewport iOS có thể cao hơn vùng nhìn → hở đáy.
//
import { isPwaStandalone } from './pwaStandalone';

export const PWA_VV_HEIGHT = '--pwa-vv-height';
export const PWA_VV_OFFSET_TOP = '--pwa-vv-offset-top';
export const PWA_VV_OFFSET_LEFT = '--pwa-vv-offset-left';
export const PWA_VV_WIDTH = '--pwa-vv-width';

const VV_PROPS = [PWA_VV_HEIGHT, PWA_VV_OFFSET_TOP, PWA_VV_OFFSET_LEFT, PWA_VV_WIDTH];

export function clearPwaVisualViewport() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  for (const prop of VV_PROPS) {
    root.style.removeProperty(prop);
  }
}

export function syncPwaVisualViewport() {
  if (typeof document === 'undefined' || typeof window === 'undefined') return;
  if (!isPwaStandalone()) {
    clearPwaVisualViewport();
    return;
  }

  const vv = window.visualViewport;
  const root = document.documentElement;

  if (!vv) {
    clearPwaVisualViewport();
    return;
  }

  const height = Math.max(0, Math.round(vv.height));
  const offsetTop = Math.max(0, Math.round(vv.offsetTop));
  const offsetLeft = Math.max(0, Math.round(vv.offsetLeft));
  const width = Math.max(0, Math.round(vv.width));

  if (height > 0) {
    root.style.setProperty(PWA_VV_HEIGHT, `${height}px`);
  } else {
    root.style.removeProperty(PWA_VV_HEIGHT);
  }

  root.style.setProperty(PWA_VV_OFFSET_TOP, `${offsetTop}px`);
  root.style.setProperty(PWA_VV_OFFSET_LEFT, `${offsetLeft}px`);

  if (width > 0) {
    root.style.setProperty(PWA_VV_WIDTH, `${width}px`);
  } else {
    root.style.removeProperty(PWA_VV_WIDTH);
  }
}

let bound = false;

export function bootstrapPwaVisualViewport() {
  if (typeof window === 'undefined' || !isPwaStandalone()) return;
  if (bound) {
    syncPwaVisualViewport();
    return;
  }
  bound = true;

  const schedule = () => {
    requestAnimationFrame(syncPwaVisualViewport);
  };

  syncPwaVisualViewport();
  window.visualViewport?.addEventListener('resize', schedule, { passive: true });
  window.visualViewport?.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule, { passive: true });
  window.addEventListener('orientationchange', schedule, { passive: true });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      schedule();
    }
  });
}
