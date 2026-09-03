/**
 * Route Vue của module Evaluation — import/gộp vào resources/js/router/index.js.
 *
 * "Khung chấm điểm" là mục SIDEBAR RIÊNG — khác "Tiêu chí đánh giá" (vẫn là
 * tab trong WorkspaceConfigHub, đăng ký ở
 * Modules/WorkspaceConfig/resources/js/router.js).
 *
 * department_director/deputy trở lên (evaluation.manage_department) xem
 * được — kiểm tra ở AppSidebar.vue qua requiresPermission.
 * section_head/team_lead/member không có mục này.
 */
export default [
  {
    // Khung chấm điểm — mục sidebar riêng của phòng ban. Gói từng thang
    // điểm tiêu chí để chấm điểm theo task.
    // URL cũ /manager/workspace-config/task-type redirect sang đây.
    path: '/manager/evaluation-score-kit',
    name: 'manager.evaluation-score-kit.index',
    component: () => import('./pages/EvaluationScoreKit.vue'),
    meta: {
      requiresAuth: true,
      title: 'Khung chấm điểm',
      requiresPermission: 'evaluation.manage_department',
    },
  },
  {
    // Đánh giá nhân sự — ma trận chấm điểm cả phòng ban trong kỳ của báo cáo,
    // kèm công việc và điểm theo từng tiêu chí; ghi nhận diễn ra ngay trên
    // bảng này.
    //
    // Giữ nguyên path và name cũ để sidebar, phân quyền và cấu hình sidebar
    // theo phòng ban không phải đổi theo. Không phải mục sidebar riêng —
    // chỉ vào từ tạo/sửa báo cáo Đánh giá nhân sự, kèm ?from=&to=.
    path: '/manager/evaluation-events',
    name: 'manager.evaluation-events.index',
    component: () => import('./pages/EvaluationSummary.vue'),
    meta: {
      requiresAuth: true,
      title: 'Đánh giá nhân sự',
      requiresPermission: 'evaluation.manage_department',
    },
  },
];
