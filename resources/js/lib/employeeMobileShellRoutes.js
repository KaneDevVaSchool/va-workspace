//
// Phạm vi trải nghiệm mobile + PWA cài màn hình: chỉ các trang "của tôi".
// Mọi URL khác (kể cả từ thông báo đẩy) chuyển về shell này và nhắc mở desktop.
//
import { isPwaStandalone } from './pwaStandalone';

const MOBILE_MQ = '(max-width: 768px)';

function isMobileViewport() {
  return typeof window !== 'undefined' && window.matchMedia(MOBILE_MQ).matches;
}

export const EMPLOYEE_MOBILE_SHELL_PATHS = [
  '/dashboard/me',
  '/dashboard/me/feed',
  '/dashboard/me/attendance',
  '/dashboard/me/leave',
  '/dashboard/me/requests',
];

const ALLOWED_PATHS = new Set(EMPLOYEE_MOBILE_SHELL_PATHS);

/** Guest routes vẫn cần khi chưa đăng nhập trên mobile/PWA. */
export const EMPLOYEE_SHELL_GUEST_PATHS = ['/login', '/auth/callback'];

const GUEST_PATHS = new Set(EMPLOYEE_SHELL_GUEST_PATHS);

export const EMPLOYEE_SHELL_BLOCK_TOAST_KEY = 'va-employee-shell-block-toast';

export const EMPLOYEE_SHELL_BLOCK_MESSAGE =
  'Nội dung này chỉ xem được trên máy tính. Vui lòng mở Workspace trên trình duyệt desktop để xem chi tiết.';

export const EMPLOYEE_SHELL_DEFAULT_PATH = '/dashboard/me/feed';

export function normalizeShellPathname(pathname) {
  if (!pathname || typeof pathname !== 'string') return '/';
  const pathOnly = pathname.split('?')[0].split('#')[0];
  if (pathOnly.length > 1 && pathOnly.endsWith('/')) {
    return pathOnly.slice(0, -1);
  }
  return pathOnly || '/';
}

export function isEmployeeMobileShellPath(pathname) {
  return ALLOWED_PATHS.has(normalizeShellPathname(pathname));
}

export function isEmployeeShellGuestPath(pathname) {
  return GUEST_PATHS.has(normalizeShellPathname(pathname));
}

/** Mobile (≤768px) hoặc PWA standalone — áp giới hạn route. */
export function isEmployeeShellRestrictedContext() {
  return isPwaStandalone() || isMobileViewport();
}
