/**
 * Route Vue module Chat — trang trò chuyện full màn hình trên app PWA nhân viên.
 */
export default [
  {
    path: '/dashboard/me/chat',
    name: 'employee.chat',
    component: () => import('./pages/EmployeeChat.vue'),
    meta: {
      requiresAuth: true,
      title: 'Trò chuyện',
      requiresPermission: 'dashboard.view',
      employeeMobile: true,
    },
  },
];
