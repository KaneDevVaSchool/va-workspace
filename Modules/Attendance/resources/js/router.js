/**
 * Route Vue của module Attendance — import/gộp vào resources/js/router/index.js.
 *
 * Trang chủ "của tôi" (/dashboard/me) KHÔNG đăng ký ở đây — nó vẫn thuộc
 * Modules/Dashboard/resources/js/router.js (route dashboard.me), vì desktop
 * và quản lý vẫn cần xem bảng KPI gốc. Modules/Dashboard/resources/js/pages/
 * DashboardMe.vue tự chọn render EmployeeHome.vue (component của module này)
 * khi useEmployeeMobileView() = true. Ở đây chỉ đăng ký 3 trang hoàn toàn mới:
 * chấm công, nghỉ phép, danh sách đơn — chỉ dùng trên trải nghiệm mobile nhân
 * viên (meta.employeeMobile), UI mock, chưa nối API thật.
 */
export default [
  {
    path: '/dashboard/me/attendance',
    name: 'employee.attendance',
    component: () => import('./pages/EmployeeAttendance.vue'),
    meta: {
      requiresAuth: true,
      title: 'Chấm công',
      requiresPermission: 'dashboard.view',
      employeeMobile: true,
    },
  },
  {
    path: '/dashboard/me/leave',
    name: 'employee.leave',
    component: () => import('./pages/EmployeeLeave.vue'),
    meta: {
      requiresAuth: true,
      title: 'Nghỉ phép',
      requiresPermission: 'dashboard.view',
      employeeMobile: true,
    },
  },
  {
    path: '/dashboard/me/requests',
    name: 'employee.requests',
    component: () => import('./pages/EmployeeRequests.vue'),
    meta: {
      requiresAuth: true,
      title: 'Đơn của tôi',
      requiresPermission: 'dashboard.view',
      employeeMobile: true,
    },
  },
  {
    path: '/dashboard/me/feed',
    name: 'employee.feed',
    component: () => import('./pages/EmployeeFeed.vue'),
    meta: {
      requiresAuth: true,
      title: 'Bảng tin',
      requiresPermission: 'dashboard.view',
      employeeMobile: true,
    },
  },
];
