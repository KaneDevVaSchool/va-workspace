//
// Chiều cao app shell mobile nhân viên — tách rõ Safari tab vs PWA standalone.
//
// Safari (Case browser): vùng nhìn thấy = visualViewport.height (thanh URL co giãn).
// PWA iOS (WebKit #254868): không dùng visualViewport cho chiều cao shell;
//   một nguồn duy nhất = document.documentElement.clientHeight (layout viewport).
//
// Biến CSS: --employee-shell-height (px), chỉ ghi khi đo được > 0.
// Tabbar: safe-area qua env() + probe JS (--employee-safe-*), không bù height shell.
//
import { isPwaStandalone } from '@/lib/pwaStandalone';

export const EMPLOYEE_SHELL_HEIGHT_VAR = '--employee-shell-height';

const DEBUG_KEY = 'va-employee-viewport-debug';

/** @returns {'pwa' | 'browser'} */
export function employeeShellViewportMode() {
  return isPwaStandalone() ? 'pwa' : 'browser';
}

/** Safari tab — chiều cao vùng layout trừ thanh trình duyệt. */
export function measureBrowserShellHeightPx() {
  if (typeof window === 'undefined') return 0;
  const vv = window.visualViewport;
  const h = vv?.height ?? window.innerHeight;
  return Math.max(0, Math.round(h));
}

/**
 * PWA standalone — layout viewport (không bù URL bar vì không có).
 * clientHeight: nguồn ổn định trên iOS A2HS; không cộng safe-area (tabbar tự xử lý).
 */
export function measurePwaShellHeightPx() {
  if (typeof document === 'undefined' || typeof window === 'undefined') return 0;
  const layout = document.documentElement.clientHeight;
  if (layout > 0) return Math.round(layout);
  return Math.max(0, Math.round(window.innerHeight));
}

export function measureEmployeeShellHeightPx(mode = employeeShellViewportMode()) {
  return mode === 'pwa' ? measurePwaShellHeightPx() : measureBrowserShellHeightPx();
}

function readDiagnostics() {
  if (typeof window === 'undefined' || typeof document === 'undefined') return null;
  const vv = window.visualViewport;
  return {
    mode: employeeShellViewportMode(),
    clientHeight: document.documentElement.clientHeight,
    innerHeight: window.innerHeight,
    visualViewportHeight: vv?.height ?? null,
    visualViewportOffsetTop: vv?.offsetTop ?? null,
    shellHeightApplied: document.documentElement.style.getPropertyValue(
      EMPLOYEE_SHELL_HEIGHT_VAR,
    ),
  };
}

function maybeLogDiagnostics() {
  if (typeof localStorage === 'undefined') return;
  if (localStorage.getItem(DEBUG_KEY) !== '1') return;
  console.info('[employee-shell viewport]', readDiagnostics());
}

/** Ghi hoặc xóa --employee-shell-height trên <html>. */
export function syncEmployeeShellHeight() {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  const height = measureEmployeeShellHeightPx();
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
