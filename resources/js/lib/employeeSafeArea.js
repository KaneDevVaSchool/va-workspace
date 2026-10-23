//
// iOS PWA đôi khi không áp env(safe-area-inset-*) đúng trên phần tử lồng nhau.
// Đo padding computed từ phần tử probe rồi gán --employee-safe-* (px) lên <html>.
//
import { isIosDevice } from './employeeDeviceOs';
import { isPwaStandalone } from './pwaStandalone';

const SIDES = ['top', 'bottom', 'left', 'right'];

/** Home indicator — fallback khi WebKit standalone probe env() = 0. */
const IOS_PWA_HOME_INDICATOR_FALLBACK_PX = 34;

/**
 * iOS Safari tab: env() ổn định — probe px trên html từng lệch với thanh URL.
 * iOS PWA standalone: env() / var(--*, env()) đôi khi = 0 trên WebKit → cần probe px.
 */
function isIosSafariTabCssOnlySafeArea() {
  if (typeof document === 'undefined' || !isIosDevice()) return false;
  const root = document.documentElement;
  const standalone =
    root.classList.contains('pwa-standalone')
    || (typeof navigator !== 'undefined' && navigator.standalone === true);
  if (standalone) return false;
  return (
    root.classList.contains('employee-mobile-pwa')
    || root.classList.contains('employee-mobile-browser')
  );
}

function measureSafeAreaInset(side) {
  if (typeof document === 'undefined') return 0;
  const el = document.createElement('div');
  el.setAttribute('aria-hidden', 'true');
  el.style.cssText = [
    'position:fixed',
    'top:0',
    'left:0',
    'width:0',
    'height:0',
    'visibility:hidden',
    'pointer-events:none',
    'padding:0',
    `padding-${side}:constant(safe-area-inset-${side})`,
    `padding-${side}:env(safe-area-inset-${side},0px)`,
  ].join(';');
  document.documentElement.appendChild(el);
  const raw = getComputedStyle(el).getPropertyValue(`padding-${side}`);
  el.remove();
  const px = parseFloat(raw);
  return Number.isFinite(px) && px > 0 ? px : 0;
}

export function isEmployeeMobileShellActive() {
  if (typeof document === 'undefined') return false;
  const root = document.documentElement;
  if (
    root.classList.contains('employee-mobile-pwa')
    || root.classList.contains('employee-mobile-browser')
  ) {
    return true;
  }
  return Boolean(document.querySelector('.employee-shell'));
}

function shouldSyncSafeAreaInsets() {
  if (typeof document === 'undefined') return false;
  if (isEmployeeMobileShellActive()) return true;
  const root = document.documentElement;
  return (
    root.classList.contains('pwa-standalone')
    || (typeof navigator !== 'undefined' && navigator.standalone === true)
  );
}

export function syncEmployeeSafeAreaInsets() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (!shouldSyncSafeAreaInsets()) {
    for (const side of SIDES) {
      root.style.removeProperty(`--employee-safe-${side}`);
    }
    return;
  }

  if (isIosSafariTabCssOnlySafeArea()) {
    for (const side of SIDES) {
      root.style.removeProperty(`--employee-safe-${side}`);
    }
    return;
  }

  for (const side of SIDES) {
    let px = measureSafeAreaInset(side);
    if (
      side === 'bottom'
      && px <= 0
      && isIosDevice()
      && isPwaStandalone()
      && typeof window !== 'undefined'
      && window.screen?.height >= 812
    ) {
      px = IOS_PWA_HOME_INDICATOR_FALLBACK_PX;
    }
    if (px > 0) {
      root.style.setProperty(`--employee-safe-${side}`, `${px}px`);
    } else {
      root.style.removeProperty(`--employee-safe-${side}`);
    }
  }
}
