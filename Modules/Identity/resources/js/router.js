/**
 * Route Vue của module Identity — import/gộp vào resources/js/router/index.js.
 */
export default [
  {
    path: '/login',
    name: 'login',
    component: () => import('./pages/Login.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/auth/callback',
    name: 'auth.callback',
    component: () => import('./pages/AuthCallback.vue'),
  },
  {
    path: '/superadmin/permissions',
    name: 'superadmin.permissions',
    component: () => import('./pages/PermissionMatrix.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Quản lý phân quyền' },
  },
  {
    path: '/superadmin/activity',
    name: 'superadmin.activity',
    component: () => import('./pages/ActivityLog.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, title: 'Nhật ký hoạt động' },
  },
];
