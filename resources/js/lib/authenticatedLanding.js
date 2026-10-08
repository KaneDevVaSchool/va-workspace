//
// Đích mặc định sau đăng nhập / khi guest đã có session. Mobile (≤768px)
// mở shell EmployeeMobileLayout tại bảng tin; desktop giữ trang Quy trình (/).
//
const MOBILE_MQ = '(max-width: 768px)';

export function isMobileLandingViewport() {
  return typeof window !== 'undefined' && window.matchMedia(MOBILE_MQ).matches;
}

/**
 * @param {unknown} explicitRedirect path nội bộ từ ?redirect= hoặc session (vd. /tasks/1)
 * @param {{ canDashboardView?: boolean }} [options]
 * @returns {string | { name: string }}
 */
export function resolveAuthenticatedLandingRoute(explicitRedirect, options = {}) {
  const { canDashboardView = true } = options;

  if (
    typeof explicitRedirect === 'string'
    && explicitRedirect.startsWith('/')
    && explicitRedirect !== '/'
    && !explicitRedirect.startsWith('//')
  ) {
    return explicitRedirect;
  }

  if (isMobileLandingViewport() && canDashboardView) {
    return { name: 'employee.feed' };
  }

  return { name: 'home' };
}
