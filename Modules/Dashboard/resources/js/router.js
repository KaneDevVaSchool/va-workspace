/**
 * Route Vue của module Dashboard — import/gộp vào resources/js/router/index.js.
 *
 * Dashboard tổng công ty: chỉ super_admin/admin/director_officer
 * (permission dashboard.view_company — key riêng, không mượn report.*).
 * Dashboard phòng ban: department_director/deputy/section_head/team_lead
 * (+ cấp cao hơn) qua OR 2 permission có sẵn.
 * Dashboard cá nhân (me): permission dashboard.view — gán cho hầu hết mọi
 * role kể cả viewer, luôn scope theo chính người đăng nhập.
 */
export default [
  {
    path: '/dashboard',
    name: 'dashboard.company',
    component: () => import('./pages/DashboardCompany.vue'),
    meta: {
      requiresAuth: true,
      title: 'Dashboard tổng công ty',
      requiresPermission: 'dashboard.view_company',
    },
  },
  {
    path: '/dashboard/department',
    name: 'dashboard.department',
    component: () => import('./pages/DashboardDepartment.vue'),
    meta: {
      requiresAuth: true,
      title: 'Dashboard phòng ban',
      requiresAnyPermission: ['performance.view_department', 'project.view'],
    },
  },
  {
    path: '/dashboard/me',
    name: 'dashboard.me',
    component: () => import('./pages/DashboardMe.vue'),
    meta: {
      requiresAuth: true,
      title: 'Dashboard của tôi',
      requiresPermission: 'dashboard.view',
      // Nhân viên thường trên mobile thấy trang chủ "của tôi" (checklist +
      // chấm công/nghỉ phép, Modules/Attendance) thay vì bảng KPI bên dưới —
      // xem DashboardMe.vue + useEmployeeMobileView(). employeeMobile: true
      // báo App.vue bọc EmployeeMobileLayout (bottom nav) thay vì AppLayout
      // khi điều kiện đó đúng; quản lý/desktop vẫn dùng AppLayout như cũ.
      employeeMobile: true,
    },
  },
];
