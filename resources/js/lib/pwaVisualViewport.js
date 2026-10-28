//
// Co khung AppShell theo visualViewport CHỈ khi bàn phím mở (PWA UI / BottomSheet:
// tránh double inset — shell full màn + footer giữ safe-bottom một lần).
//
import { isIosDevice } from './employeeDeviceOs';
import { syncEmployeeShellHeight } from './employeeMobileViewport';
import { isPwaStandalone } from './pwaStandalone';

export const PWA_VV_HEIGHT = '--pwa-vv-height';
export const PWA_VV_OFFSET_TOP = '--pwa-vv-offset-top';
export const PWA_VV_OFFSET_LEFT = '--pwa-vv-offset-left';
export const PWA_VV_WIDTH = '--pwa-vv-width';

export const PWA_VV_KEYBOARD_CLASS = 'pwa-vv-keyboard';

/** Giữ shell full màn khi hộp thư viện ảnh / tệp của hệ điều hành đang mở. */
export const PWA_NATIVE_PICKER_CLASS = 'pwa-native-picker';

/** @type {number} px — co khung khi layout cao hơn visual viewport (keyboard / zoom UI). */
const KEYBOARD_INSET_THRESHOLD = 48;

let nativePickerActive = false;
let nativePickerSawLeave = false;
let nativePickerSawShrink = false;
let nativePickerCloseTimer = 0;
let nativePickerHoldTimer = 0;

function viewportSettled() {
  const vv = window.visualViewport;
  if (!vv) return true;
  const inset = window.innerHeight - vv.height - Math.max(0, vv.offsetTop);
  return inset < KEYBOARD_INSET_THRESHOLD && Math.round(vv.offsetTop) < 8;
}

function setNativePickerClass(active) {
  document.documentElement.classList.toggle(PWA_NATIVE_PICKER_CLASS, active);
}

function holdNativePickerFrame() {
  clearPwaVisualViewport();
  setKeyboardFrame(false);
}

/**
 * Gọi đồng bộ trong lần chạm "thêm ảnh / tệp", trước input.click().
 * Thư viện ảnh thu visual viewport; nếu coi đó là bàn phím thì khung chat bị đẩy xuống
 * và picker nằm giữa phía trên màn hình.
 */
export function beginNativeFilePicker() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  nativePickerActive = true;
  nativePickerSawLeave = false;
  nativePickerSawShrink = false;
  window.clearTimeout(nativePickerCloseTimer);
  window.clearTimeout(nativePickerHoldTimer);
  setNativePickerClass(true);
  holdNativePickerFrame();
  window.scrollTo(0, 0);
  nativePickerHoldTimer = window.setTimeout(() => {
    if (!nativePickerActive || nativePickerSawShrink || !viewportSettled()) return;
    endNativeFilePicker();
  }, 1800);
}

export function endNativeFilePicker() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  const holding = nativePickerActive || document.documentElement.classList.contains(PWA_NATIVE_PICKER_CLASS);
  nativePickerActive = false;
  nativePickerSawLeave = false;
  nativePickerSawShrink = false;
  window.clearTimeout(nativePickerCloseTimer);
  window.clearTimeout(nativePickerHoldTimer);
  if (!holding) return;
  holdNativePickerFrame();
  nativePickerHoldTimer = window.setTimeout(() => {
    if (nativePickerActive) return;
    setNativePickerClass(false);
    syncPwaVisualViewport();
  }, 640);
}

function noteNativePickerLeave() {
  if (nativePickerActive) nativePickerSawLeave = true;
}

function noteNativePickerResize() {
  if (!nativePickerActive) return;
  if (!viewportSettled()) {
    nativePickerSawShrink = true;
    window.clearTimeout(nativePickerCloseTimer);
    return;
  }
  if (!nativePickerSawLeave && !nativePickerSawShrink) return;
  window.clearTimeout(nativePickerCloseTimer);
  nativePickerCloseTimer = window.setTimeout(() => {
    if (nativePickerActive && viewportSettled()) endNativeFilePicker();
  }, 220);
}

const CHAT_COMPOSER_INPUT_SELECTOR = 'textarea.chat-view__input';

function isEmployeeMobileShell() {
  if (typeof document === 'undefined') return false;
  const root = document.documentElement;
  return (
    root.classList.contains('employee-mobile-pwa')
    || root.classList.contains('employee-mobile-browser')
  );
}

function shouldSyncVisualViewportKeyboard() {
  return isPwaStandalone() || isEmployeeMobileShell();
}

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
  if (
    nativePickerActive
    || document.documentElement.classList.contains(PWA_NATIVE_PICKER_CLASS)
  ) {
    holdNativePickerFrame();
    return;
  }
  if (!shouldSyncVisualViewportKeyboard()) {
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
  const composerFocused =
    typeof document !== 'undefined'
    && document.activeElement?.matches?.(CHAT_COMPOSER_INPUT_SELECTOR);
  const keyboardOpen =
    keyboardInset >= KEYBOARD_INSET_THRESHOLD
    || (composerFocused && keyboardInset >= 20);

  if (!keyboardOpen) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    syncEmployeeShellHeight();
    return;
  }

  setKeyboardFrame(true);

  if (isIosDevice() && (offsetTop > 0 || composerFocused)) {
    window.scrollTo(0, 0);
  }

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

  // Gỡ --employee-shell-height (iOS PWA innerHeight) để #app không cao hơn vùng bàn phím.
  syncEmployeeShellHeight();
}

let bound = false;

export function bootstrapPwaVisualViewport() {
  if (typeof window === 'undefined') return;
  if (bound) {
    syncPwaVisualViewport();
    return;
  }
  bound = true;

  const schedule = () => {
    requestAnimationFrame(() => {
      noteNativePickerResize();
      syncPwaVisualViewport();
    });
  };

  syncPwaVisualViewport();
  window.visualViewport?.addEventListener('resize', schedule, { passive: true });
  window.visualViewport?.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule, { passive: true });
  window.addEventListener('orientationchange', schedule, { passive: true });
  window.addEventListener('blur', noteNativePickerLeave);
  window.addEventListener('focus', () => {
    noteNativePickerResize();
    schedule();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      noteNativePickerLeave();
      return;
    }
    noteNativePickerResize();
    schedule();
  });
}
