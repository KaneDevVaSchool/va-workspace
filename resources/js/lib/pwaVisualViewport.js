//
// Co khung AppShell theo visualViewport CHỈ khi bàn phím mở (PWA UI / BottomSheet:
// tránh double inset — shell full màn + footer giữ safe-bottom một lần).
//
import { syncEmployeeShellHeight } from './employeeMobileViewport';
import { isPwaStandalone } from './pwaStandalone';

export const PWA_VV_HEIGHT = '--pwa-vv-height';
export const PWA_VV_OFFSET_TOP = '--pwa-vv-offset-top';
export const PWA_VV_OFFSET_LEFT = '--pwa-vv-offset-left';
export const PWA_VV_WIDTH = '--pwa-vv-width';

export const PWA_VV_KEYBOARD_CLASS = 'pwa-vv-keyboard';

/** @type {number} px — co khung khi layout cao hơn visual viewport (keyboard / zoom UI). */
const KEYBOARD_INSET_THRESHOLD = 72;

const VV_PROPS = [PWA_VV_HEIGHT, PWA_VV_OFFSET_TOP, PWA_VV_OFFSET_LEFT, PWA_VV_WIDTH];

export function clearPwaVisualViewport() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  for (const prop of VV_PROPS) {
    root.style.removeProperty(prop);
  }
}

function setKeyboardFrame(active) {
  if (typeof document === 'undefined') return;
  document.documentElement.classList.toggle(PWA_VV_KEYBOARD_CLASS, active);
}

export function syncPwaVisualViewport() {
  if (typeof document === 'undefined' || typeof window === 'undefined') return;
  if (!isPwaStandalone()) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    return;
  }

  const vv = window.visualViewport;
  const root = document.documentElement;

  if (!vv) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    return;
  }

  const layoutHeight = window.innerHeight;
  const height = Math.max(0, Math.round(vv.height));
  const offsetTop = Math.max(0, Math.round(vv.offsetTop));
  const offsetLeft = Math.max(0, Math.round(vv.offsetLeft));
  const width = Math.max(0, Math.round(vv.width));
  const keyboardInset = layoutHeight - height - offsetTop;
  const keyboardOpen = keyboardInset >= KEYBOARD_INSET_THRESHOLD;

  if (!keyboardOpen) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    syncEmployeeShellHeight();
    return;
  }

  setKeyboardFrame(true);

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
