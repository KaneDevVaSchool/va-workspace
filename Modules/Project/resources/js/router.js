/**
 * Route Vue của module Project — import/gộp vào resources/js/router/index.js.
 *
 * "Dự án" là mục sidebar riêng trong nhóm "Quản lý" — cùng nhóm với "Mẫu
 * đánh giá". Route này CHỈ tồn tại phía Vue Router (SPA) — Laravel phục vụ
 * qua fallback catch-all trong routes/web.php gốc, không có route Laravel
 * "manager.project.index" thật (giống hệt cách Evaluation làm với
 * manager.evaluation-templates.index — xem Modules/Evaluation/resources/js/router.js).
 */
export default [
  {
    path: '/manager/project',
    name: 'manager.project.index',
    component: () => import('./pages/ProjectList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Dự án',
      requiresPermission: 'project.view',
    },
  },
  {
    path: '/manager/project/create',
    name: 'manager.project.create',
    component: () => import('./pages/ProjectCreate.vue'),
    meta: {
      requiresAuth: true,
      title: 'Thêm dự án',
      requiresPermission: 'project.view',
    },
  },
  {
    path: '/manager/project/:id/edit',
    name: 'manager.project.edit',
    component: () => import('./pages/ProjectEdit.vue'),
    meta: {
      requiresAuth: true,
      title: 'Sửa dự án',
      requiresPermission: 'project.view',
    },
  },
  {
    path: '/manager/project/settings',
    name: 'manager.project.settings',
    component: () => import('./pages/ProjectSettings.vue'),
    meta: {
      requiresAuth: true,
      title: 'Cài đặt dự án',
      requiresPermission: 'project.manage_settings',
    },
  },
  {
    path: '/manager/project/tasks/create',
    name: 'manager.project.tasks.create',
    component: () => import('./pages/TaskCreate.vue'),
    meta: {
      requiresAuth: true,
      title: 'Tạo công việc',
      requiresPermission: 'task.create',
    },
  },
  {
    // "Tất cả công việc" (Project Giai đoạn 2 — Task thật) — mục sidebar
    // riêng cạnh "Dự án". requiresAnyPermission vì role member chỉ có
    // task.view_assigned (không có task.view) — xem AppSidebar.vue.
    path: '/manager/project/tasks',
    name: 'manager.project.tasks',
    component: () => import('./pages/TaskList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Tất cả công việc',
      requiresAnyPermission: ['task.view', 'task.view_assigned'],
    },
  },
];
