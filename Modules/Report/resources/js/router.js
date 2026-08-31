/**
 * Route Vue của module Report — import/gộp vào resources/js/router/index.js.
 *
 * Danh sách báo cáo mở cho cả người được chia sẻ quyền xem
 * (report.view_assigned); tạo báo cáo cần report.manage_department, kiểm tra
 * thêm ở backend chứ không chỉ ẩn giao diện.
 */
export default [
  {
    path: '/manager/reports',
    name: 'manager.reports.index',
    component: () => import('./pages/ReportList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Báo cáo',
      requiresAnyPermission: ['report.manage_department', 'report.view_assigned'],
    },
  },
  {
    path: '/manager/reports/personnel-evaluation/new',
    name: 'manager.reports.personnel-evaluation.create',
    component: () => import('./pages/ReportCreatePersonnelEvaluation.vue'),
    meta: {
      requiresAuth: true,
      title: 'Tạo báo cáo đánh giá nhân sự',
      requiresPermission: 'report.manage_department',
    },
  },
  {
    path: '/manager/reports/:id',
    name: 'manager.reports.show',
    component: () => import('./pages/ReportView.vue'),
    meta: {
      requiresAuth: true,
      title: 'Xem báo cáo',
      requiresAnyPermission: ['report.manage_department', 'report.view_assigned'],
    },
  },
];
