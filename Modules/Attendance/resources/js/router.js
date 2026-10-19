/**
 * Route Vue của module Attendance — import/gộp vào resources/js/router/index.js.
 *
 * Trang chủ "của tôi" (/dashboard/me) KHÔNG đăng ký ở đây — nó vẫn thuộc
 * Modules/Dashboard/resources/js/router.js (route dashboard.me), vì desktop
 * (viewport >768px) vẫn cần xem bảng KPI gốc. Modules/Dashboard/resources/js/
 * pages/DashboardMe.vue tự chọn render EmployeeHome.vue (component của module
 * này) khi useEmployeeMobileView() = true (bất kỳ ai, kể cả quản lý, đang ở
 * viewport ≤768px). Ở đây đăng ký 4 trang hoàn toàn mới — chấm công, nghỉ
 * phép, danh sách đơn, bảng tin — chỉ dùng trên trải nghiệm mobile
 * (meta.employeeMobile). 3 trang đầu UI mock, chưa nối API thật; bảng tin gọi
 * API thật /api/social/posts (dữ liệu bài đăng đã có sẵn, không có rủi ro
 * logic nghiệp vụ mới).
 */
export default [
  {
    // Trưởng đơn vị duyệt bảng chấm công VA-HRM (desktop + mobile, mở từ thông báo
    // ?confirmation=<uuid>). Quyền thật do HRM kiểm (quản lý trực tiếp của phòng).
    path: '/attendance/timesheet-approvals',
    name: 'attendance.timesheet-approvals',
    component: () => import('./pages/TimesheetApprovals.vue'),
    meta: {
      requiresAuth: true,
      title: 'Duyệt bảng công',
    },
  },
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
      title: 'Quản lý Nghỉ phép',
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
    path: '/dashboard/me/utilities',
    name: 'employee.utilities',
    component: () => import('./pages/EmployeeUtilitiesAll.vue'),
    meta: {
      requiresAuth: true,
      title: 'Tiện ích nhanh',
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
