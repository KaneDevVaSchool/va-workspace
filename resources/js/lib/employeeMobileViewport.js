//
// Chiều cao app shell mobile nhân viên — tách rõ Safari tab vs PWA standalone.
//
// · Safari tab: ghi --employee-shell-height = visualViewport.height (px).
// · PWA iOS: ghi --employee-shell-height = innerHeight (px) — WebKit fill-available
//   thường ngắn hơn viewport thật → lộ dải trắng dưới tab bar (iPhone Plus).
// · PWA Android: không ghi biến — dùng inset:0.
// · Safe-area iOS: không dùng. syncEmployeeSafeAreaInsets() chỉ xóa biến inline cũ.
//
import { isIosDevice } from './employeeDeviceOs';
import { isPwaStandalone } from './pwaStandalone';

export const EMPLOYEE_SHELL_HEIGHT_VAR = '--employee-shell-height';

const DEBUG_KEY = 'va-employee-viewport-debug';

/** @returns {'pwa' | 'browser'} */
export function employeeShellViewportMode() {
  return isPwaStandalone() ? 'pwa' : 'browser';
}

/** Safari tab — vùng nhìn thấy khi thanh URL co giãn. */
export function measureBrowserShellHeightPx() {
  if (typeof window === 'undefined') return 0;
  const vv = window.visualViewport;
  const h = vv?.height ?? window.innerHeight;
  return Math.max(0, Math.round(h));
}

/** iOS PWA — khung layout phải khớp innerHeight (không clientHeight / fill-available). */
export function measureIosPwaShellHeightPx() {
  if (typeof window === 'undefined') return 0;
  const vv = window.visualViewport;
  const layoutFromVv = vv
    ? Math.max(0, Math.round(vv.height + vv.offsetTop))
    : 0;
  return Math.max(
    window.innerHeight,
    document.documentElement.clientHeight,
    layoutFromVv,
  );
}

/** Chỉ để debug / so sánh trên thiết bị — không ghi lên CSS trong PWA Android. */
export function readPwaLayoutHeightCandidatesPx() {
  if (typeof document === 'undefined' || typeof window === 'undefined') {
    return { clientHeight: 0, innerHeight: 0, visualViewportLayout: 0 };
  }
  const vv = window.visualViewport;
  const visualViewportLayout = vv
    ? Math.max(0, Math.round(vv.height + vv.offsetTop))
    : 0;
  return {
    clientHeight: document.documentElement.clientHeight,
    innerHeight: window.innerHeight,
    visualViewportLayout,
  };
}

export function readEmployeeViewportDiagnostics() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return null;
  const vv = window.visualViewport;
  return {
    mode: employeeShellViewportMode(),
    browserShellHeight: measureBrowserShellHeightPx(),
    iosPwaShellHeight: measureIosPwaShellHeightPx(),
    pwaCandidates: readPwaLayoutHeightCandidatesPx(),
    visualViewportHeight: vv?.height ?? null,
    visualViewportOffsetTop: vv?.offsetTop ?? null,
    shellHeightVarApplied: document.documentElement.style.getPropertyValue(
      EMPLOYEE_SHELL_HEIGHT_VAR,
    ),
  };
}

function maybeLogDiagnostics() {
  if (typeof localStorage === 'undefined') return;
  if (localStorage.getItem(DEBUG_KEY) !== '1') return;
  console.info('[employee-shell viewport]', readEmployeeViewportDiagnostics());
}

function shouldApplyIosPwaShellHeightPx() {
  if (typeof document === 'undefined') return false;
  if (!isPwaStandalone() || !isIosDevice()) return false;
  return !document.documentElement.classList.contains('pwa-vv-keyboard');
}

/**
 * Browser + PWA iOS: set --employee-shell-height (px).
 * PWA Android: xóa biến.
 */
export function syncEmployeeShellHeight() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;

  if (shouldApplyIosPwaShellHeightPx()) {
    const height = measureIosPwaShellHeightPx();
    if (height > 0) {
      root.style.setProperty(EMPLOYEE_SHELL_HEIGHT_VAR, `${height}px`);
    } else {
      root.style.removeProperty(EMPLOYEE_SHELL_HEIGHT_VAR);
    }
    maybeLogDiagnostics();
    return;
  }

  if (employeeShellViewportMode() === 'pwa') {
    root.style.removeProperty(EMPLOYEE_SHELL_HEIGHT_VAR);
    maybeLogDiagnostics();
    return;
  }

  const height = measureBrowserShellHeightPx();
  if (height > 0) {
    root.style.setProperty(EMPLOYEE_SHELL_HEIGHT_VAR, `${height}px`);
  } else {
    root.style.removeProperty(EMPLOYEE_SHELL_HEIGHT_VAR);
  }
  maybeLogDiagnostics();
}

export function clearEmployeeShellHeight() {
  if (typeof document === 'undefined') return;
  document.documentElement.style.removeProperty(EMPLOYEE_SHELL_HEIGHT_VAR);
}

/** Gọi sớm từ app.blade.php / app.js trước Vue — iOS PWA shortcut. */
export function bootstrapIosPwaShellHeight() {
  if (typeof window === 'undefined') return;
  syncEmployeeShellHeight();
  const onResize = () => syncEmployeeShellHeight();
  window.addEventListener('resize', onResize, { passive: true });
  window.visualViewport?.addEventListener('resize', onResize, { passive: true });
}
