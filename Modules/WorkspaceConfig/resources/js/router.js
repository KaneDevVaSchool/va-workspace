/**
 * Route Vue của module WorkspaceConfig — import/gộp vào resources/js/router/index.js.
 *
 * Hub 1 entry point sidebar (manager.workspace-config.hub), Members/Sidebar
 * là route CON (children) render qua <router-view> trong WorkspaceConfigHub.vue
 * — không phải mục sidebar riêng, điều hướng bằng tab nội bộ trang hub.
 *
 * Trang chi tiết phòng ban của superadmin (department-detail) theo đúng
 * pattern hub-tab tương tự: WorkspaceConfigDepartmentDetailHub.vue load 1 lần
 * dữ liệu phòng ban rồi cấp cho 3 tab con (Thành viên/Menu hiển thị/Tiêu chí
 * đánh giá), tất cả ở chế độ chỉ xem — super_admin không sửa thay
 * department_director.
 */
export default [
  {
    // manager/evaluation — trang riêng ngoài sidebar, mọi thành viên có
    // phòng ban xem tiêu chí đánh giá của phòng ban mình (chỉ xem). Khác
    // với tab "Tiêu chí đánh giá" trong hub bên dưới (dành cho trưởng
    // phòng, có quyền tạo/sửa/xoá) — mục sidebar riêng, ẩn/hiện được qua
    // Cấu hình menu (đồng bộ menu_key với DepartmentSidebarConfigService).
    path: '/manager/evaluation',
    name: 'manager.evaluation.view',
    component: () => import('@modules/Evaluation/resources/js/pages/EvaluationView.vue'),
    meta: { requiresAuth: true, title: 'Tiêu chí đánh giá' },
  },
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
      {
        path: 'evaluation',
        name: 'manager.workspace-config.evaluation',
        component: () => import('@modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue'),
        meta: { requiresAuth: true, title: 'Tiêu chí đánh giá' },
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
    component: () => import('./pages/WorkspaceConfigDepartmentDetailHub.vue'),
    meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Chi tiết phòng ban' },
    redirect: (to) => ({
      name: 'superadmin.workspace-config.department-detail.members',
      params: to.params,
    }),
    children: [
      {
        path: 'members',
        name: 'superadmin.workspace-config.department-detail.members',
        component: () => import('./pages/WorkspaceConfigDepartmentMembersSuperadmin.vue'),
        meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Thành viên phòng ban' },
      },
      {
        path: 'sidebar',
        name: 'superadmin.workspace-config.department-detail.sidebar',
        component: () => import('./pages/WorkspaceConfigDepartmentSidebarSuperadmin.vue'),
        meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Menu hiển thị phòng ban' },
      },
      {
        path: 'evaluation',
        name: 'superadmin.workspace-config.department-detail.evaluation',
        component: () =>
          import('@modules/Evaluation/resources/js/pages/WorkspaceConfigDepartmentEvaluationSuperadmin.vue'),
        meta: { requiresAuth: true, requiresSuperAdmin: true, title: 'Tiêu chí đánh giá phòng ban' },
      },
    ],
  },
];
