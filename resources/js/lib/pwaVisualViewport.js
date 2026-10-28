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

const CHAT_COMPOSER_INPUT_SELECTOR = 'textarea.chat-view__input';

const SHELL_PIN_SELECTOR =
  '.employee-shell--pwa:not(.emp-shell-preview__shell), .employee-shell--browser:not(.emp-shell-preview__shell)';
const SHELL_PIN_PROPS = [
  'position',
  'inset',
  'top',
  'left',
  'right',
  'bottom',
  'width',
  'height',
  'max-height',
  'max-width',
  'transform',
  'margin',
];

let nativePickerActive = false;
let nativePickerSawLeave = false;
let nativePickerSawShrink = false;
let nativePickerCloseTimer = 0;
let nativePickerReleaseTimer = 0;

function viewportSettled() {
  const vv = window.visualViewport;
  if (!vv) return true;
  const inset = window.innerHeight - vv.height - Math.max(0, vv.offsetTop);
  return inset < KEYBOARD_INSET_THRESHOLD && Math.round(vv.offsetTop) < 8;
}

function setNativePickerClass(active) {
  document.documentElement.classList.toggle(PWA_NATIVE_PICKER_CLASS, active);
}

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

function composerInputFocused() {
  return document.activeElement?.matches?.(CHAT_COMPOSER_INPUT_SELECTOR) ?? false;
}

function keyboardInsetPx(vv) {
  if (!vv) return 0;
  return window.innerHeight - vv.height - Math.max(0, vv.offsetTop);
}

function isVisualViewportKeyboardOpen(vv, composerFocused = composerInputFocused()) {
  if (!vv) return false;
  const inset = keyboardInsetPx(vv);
  return inset >= KEYBOARD_INSET_THRESHOLD || (composerFocused && inset >= 20);
}

function writeVisualViewportFrame({ height, offsetTop, offsetLeft, width }) {
  const root = document.documentElement;
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

  syncEmployeeShellHeight();
}

function snapshotVisualViewport(vv) {
  return {
    height: Math.max(0, Math.round(vv.height)),
    offsetTop: Math.max(0, Math.round(vv.offsetTop)),
    offsetLeft: Math.max(0, Math.round(vv.offsetLeft)),
    width: Math.max(0, Math.round(vv.width)),
  };
}

function applyVisualViewportKeyboardFrame(vv, composerFocused = composerInputFocused()) {
  if (
    isIosDevice()
    && (vv.offsetTop > 0 || composerFocused)
    && !isNativePickerHolding()
  ) {
    window.scrollTo(0, 0);
  }

  writeVisualViewportFrame(snapshotVisualViewport(vv));
}

function shellPinTarget() {
  return document.querySelector(SHELL_PIN_SELECTOR);
}

/** Giữ đúng ô shell đang nhìn thấy — không đọc visualViewport (iOS báo full ngay khi blur). */
function pinShellNow() {
  const shell = shellPinTarget();
  if (!shell || shell.dataset.vaShellPin === '1') return;
  const rect = shell.getBoundingClientRect();
  const top = Math.max(0, Math.round(rect.top));
  const left = Math.max(0, Math.round(rect.left));
  const width = Math.max(1, Math.round(rect.width));
  const height = Math.max(1, Math.round(rect.height));
  shell.dataset.vaShellPin = '1';
  const set = (prop, value) => shell.style.setProperty(prop, value, 'important');
  set('position', 'fixed');
  set('inset', 'auto');
  set('top', `${top}px`);
  set('left', `${left}px`);
  set('right', 'auto');
  set('bottom', 'auto');
  set('width', `${width}px`);
  set('height', `${height}px`);
  set('max-height', `${height}px`);
  set('max-width', `${width}px`);
  set('transform', 'none');
  set('margin', '0');
}

function unpinShell() {
  const shell = shellPinTarget();
  if (!shell) return;
  delete shell.dataset.vaShellPin;
  for (const prop of SHELL_PIN_PROPS) {
    shell.style.removeProperty(prop);
  }
}

function isNativePickerHolding() {
  if (typeof document === 'undefined') return nativePickerActive;
  return (
    nativePickerActive
    || document.documentElement.classList.contains(PWA_NATIVE_PICKER_CLASS)
  );
}

/**
 * Gọi đồng bộ ngay sau input.click() trong cùng cú bấm của nút.
 * Thư viện ảnh thu visual viewport; nếu coi đó là bàn phím thì khung chat bị đẩy xuống
 * và picker nằm giữa phía trên màn hình. Không gọi trên pointerdown của input file —
 * đổi layout trước khi hộp thoại mở làm trình duyệt huỷ lần chọn.
 */
export function beginNativeFilePicker() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  if (nativePickerActive) return;
  nativePickerActive = true;
  nativePickerSawLeave = false;
  nativePickerSawShrink = false;
  window.clearTimeout(nativePickerCloseTimer);
  window.clearTimeout(nativePickerReleaseTimer);
  nativePickerReleaseTimer = 0;
  pinShellNow();
  setNativePickerClass(true);
}

export function endNativeFilePicker() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  const holding = nativePickerActive || document.documentElement.classList.contains(PWA_NATIVE_PICKER_CLASS);
  if (!holding) return;
  nativePickerActive = false;
  nativePickerSawLeave = false;
  nativePickerSawShrink = false;
  window.clearTimeout(nativePickerCloseTimer);
  if (nativePickerReleaseTimer) return;
  nativePickerReleaseTimer = window.setTimeout(() => {
    nativePickerReleaseTimer = 0;
    if (nativePickerActive) return;
    unpinShell();
    setNativePickerClass(false);
    syncPwaVisualViewport();
    window.dispatchEvent(new CustomEvent('va-native-file-picker-end'));
  }, 280);
}

function noteNativePickerLeave() {
  // iOS gửi window blur khi mở action sheet chọn ảnh/tệp — không phải lúc picker đóng.
  if (!nativePickerActive || document.visibilityState !== 'hidden') return;
  nativePickerSawLeave = true;
}

function scheduleNativePickerEndAfterSettle() {
  window.clearTimeout(nativePickerCloseTimer);
  nativePickerCloseTimer = window.setTimeout(() => {
    if (nativePickerActive && viewportSettled()) endNativeFilePicker();
  }, 220);
}

function noteNativePickerResize() {
  if (!nativePickerActive) return;
  if (!viewportSettled()) {
    nativePickerSawShrink = true;
    window.clearTimeout(nativePickerCloseTimer);
    return;
  }
  // Chỉ tự đóng sau khi viewport từng thu (album / camera) rồi trở lại bình thường.
  if (!nativePickerSawShrink) return;
  scheduleNativePickerEndAfterSettle();
}

export function syncPwaVisualViewport() {
  if (typeof document === 'undefined' || typeof window === 'undefined') return;
  if (isNativePickerHolding()) return;
  if (!shouldSyncVisualViewportKeyboard()) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    return;
  }

  const vv = window.visualViewport;

  if (!vv) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    return;
  }

  if (!isVisualViewportKeyboardOpen(vv)) {
    clearPwaVisualViewport();
    setKeyboardFrame(false);
    syncEmployeeShellHeight();
    return;
  }

  applyVisualViewportKeyboardFrame(vv);
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
      if (nativePickerActive) {
        nativePickerSawLeave = true;
        nativePickerSawShrink = true;
      }
      return;
    }
    if (nativePickerActive && nativePickerSawLeave) {
      scheduleNativePickerEndAfterSettle();
    }
    noteNativePickerResize();
    schedule();
  });
}
