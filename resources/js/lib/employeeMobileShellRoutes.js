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
  '/dashboard/me/contacts',
  '/dashboard/me/utilities',
  '/dashboard/me/profile',
  '/dashboard/me/chat',
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

export const EMPLOYEE_SHELL_MOBILE_MEDIA = MOBILE_MQ;

/**
 * Đồng bộ route khi chuyển desktop ↔ shell mobile (guard + resize viewport).
 * @param {import('vue-router').RouteLocationNormalized} route
 * @param {{ isAuthenticated?: boolean, can?: (key: string) => boolean }} auth
 * @returns {{ name: string, query?: object, hash?: string, desktopOnlyToast?: boolean } | null}
 */
export function resolveEmployeeShellViewportRouteSync(route, auth) {
  if (!auth?.isAuthenticated) return null;

  const restricted = isEmployeeShellRestrictedContext();
  const name = route.name;
  const path = route.path;

  if (!restricted) {
    if (name === 'employee.feed' && auth.can?.('dashboard.view')) {
      return {
        name: 'social.feed',
        query: route.query,
        hash: route.hash,
      };
    }
    return null;
  }

  if (!auth.can?.('dashboard.view')) return null;

  if (name === 'social.feed') {
    return {
      name: 'employee.feed',
      query: route.query,
      hash: route.hash,
    };
  }

  if (name === 'home') {
    return { name: 'employee.feed' };
  }

  if (!isEmployeeMobileShellPath(path) && !isEmployeeShellGuestPath(path)) {
    return { name: 'employee.feed', desktopOnlyToast: true };
  }

  return null;
}

function applyShellViewportRouteSync(router, auth) {
  const current = router.currentRoute.value;
  const target = resolveEmployeeShellViewportRouteSync(current, auth);
  if (!target || target.name === current.name) return;

  if (target.desktopOnlyToast) {
    try {
      sessionStorage.setItem(EMPLOYEE_SHELL_BLOCK_TOAST_KEY, '1');
    } catch {
      // sessionStorage có thể bị chặn
    }
  }

  const { desktopOnlyToast, ...nav } = target;
  router.replace(nav).catch(() => {});
}

/**
 * Khi co/giãn cửa sổ qua ngưỡng mobile, chuyển route tương ứng (vd. /social ↔ /me/feed).
 * @param {import('vue-router').Router} router
 * @param {() => Promise<{ isAuthenticated?: boolean, isReady?: boolean, can?: (key: string) => boolean, fetchMe?: (opts?: object) => Promise<void>, user?: object }>} getAuthStore
 */
export function installEmployeeShellViewportRouteSync(router, getAuthStore) {
  if (typeof window === 'undefined') return;

  const mq = window.matchMedia(EMPLOYEE_SHELL_MOBILE_MEDIA);
  let lastRestricted = isEmployeeShellRestrictedContext();

  async function onShellContextChange() {
    const restricted = isEmployeeShellRestrictedContext();
    if (restricted === lastRestricted) return;
    lastRestricted = restricted;

    let auth;
    try {
      auth = await getAuthStore();
    } catch {
      return;
    }

    if (!auth.isAuthenticated) return;

    if (!auth.isReady && !auth.user) {
      try {
        await auth.fetchMe?.();
      } catch {
        if (!auth.user) return;
      }
    }

    applyShellViewportRouteSync(router, auth);
  }

  mq.addEventListener('change', onShellContextChange);
}
