//
// Đích mặc định sau đăng nhập / khi guest đã có session. Mobile (≤768px)
// mở shell EmployeeMobileLayout tại bảng tin; desktop giữ trang Quy trình (/).
//
import {
  isEmployeeMobileShellPath,
  isEmployeeShellRestrictedContext,
} from './employeeMobileShellRoutes';

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
    if (
      isEmployeeShellRestrictedContext()
      && !isEmployeeMobileShellPath(explicitRedirect)
    ) {
      // redirect từ thông báo / deep link ngoài phạm vi mobile — bỏ qua
    } else {
      return explicitRedirect;
    }
  }

  if (isEmployeeShellRestrictedContext() && canDashboardView) {
    return { name: 'employee.feed' };
  }

  return { name: 'home' };
}
