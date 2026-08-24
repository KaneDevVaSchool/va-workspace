/**
 * Route Vue của module WorkspaceConfig — import/gộp vào resources/js/router/index.js.
 *
 * Hub 1 entry point sidebar (manager.workspace-config.hub), Members/Sidebar
 * là route CON (children) render qua <router-view> trong WorkspaceConfigHub.vue
 * — không phải mục sidebar riêng, điều hướng bằng tab nội bộ trang hub.
 */
export default [
  {
    path: '/manager/workspace-config',
    name: 'manager.workspace-config.hub',
    component: () => import('./pages/WorkspaceConfigHub.vue'),
    meta: { requiresAuth: true, title: 'Cấu hình phòng ban' },
    redirect: { name: 'manager.workspace-config.members' },
    children: [
      {
        path: 'members',
        name: 'manager.workspace-config.members',
        component: () => import('./pages/WorkspaceConfigMembers.vue'),
        meta: { requiresAuth: true, title: 'Thành viên phòng ban' },
      },
      {
        path: 'sidebar',
        name: 'manager.workspace-config.sidebar',
        component: () => import('./pages/WorkspaceConfigSidebar.vue'),
        meta: { requiresAuth: true, title: 'Cấu hình menu' },
      },
    ],
  },
  {
    path: '/superadmin/workspace-config',
    name: 'superadmin.workspace-config.overview',
    component: () => import('./pages/WorkspaceConfigOverviewSuperadmin.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Cấu hình Workspace' },
  },
  {
    path: '/superadmin/workspace-config/departments/:departmentId',
    name: 'superadmin.workspace-config.department-detail',
    component: () => import('./pages/WorkspaceConfigDepartmentDetailSuperadmin.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Chi tiết phòng ban' },
  },
];
