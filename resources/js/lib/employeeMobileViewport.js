//
// Chiều cao app shell mobile nhân viên — tách rõ Safari tab vs PWA standalone.
//
// · Safari tab: ghi --employee-shell-height = visualViewport.height (px).
// · PWA iOS: KHÔNG ghi --employee-shell-height — WebKit có thể trả clientHeight /
//   innerHeight thiếu vài chục px (#254868); shell PWA dùng CSS position:fixed; inset:0.
// · Safe-area: probe --employee-safe-* trên iOS PWA (employeeSafeArea.js); Safari tab dùng env().
//
import { isPwaStandalone } from '@/lib/pwaStandalone';

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

/** Chỉ để debug / so sánh trên thiết bị — không ghi lên CSS trong PWA. */
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

/**
 * Browser: set --employee-shell-height.
 * PWA: luôn xóa biến — tránh khóa shell theo px đo thiếu.
 */
export function syncEmployeeShellHeight() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
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
