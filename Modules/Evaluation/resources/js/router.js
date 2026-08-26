/**
 * Route Vue của module Evaluation — import/gộp vào resources/js/router/index.js.
 *
 * "Mẫu đánh giá" (Giai đoạn C) là mục SIDEBAR RIÊNG — khác "Tiêu chí đánh
 * giá" (Giai đoạn B, vẫn là tab trong WorkspaceConfigHub, đăng ký ở
 * Modules/WorkspaceConfig/resources/js/router.js). Đây là quyết định đổi
 * có chủ đích so với Giai đoạn B — xem plans/2026-08-26-mau-danh-gia.md §2.3.
 *
 * department_director/deputy trở lên (evaluation.manage_department) và
 * superadmin (tạo mẫu dùng chung) xem được — kiểm tra ở AppSidebar.vue
 * qua requiresPermission. section_head/team_lead/member không có mục này.
 */
export default [
  {
    path: '/manager/evaluation-templates',
    name: 'manager.evaluation-templates.index',
    component: () => import('./pages/EvaluationTemplateList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Mẫu đánh giá',
      requiresPermission: 'evaluation.manage_department',
    },
  },
];
