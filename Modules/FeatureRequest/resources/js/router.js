/**
 * Route Vue của module FeatureRequest — import/gộp vào resources/js/router/index.js.
 *
 * - /superadmin/feature-requests — danh sách ghi nhận theo phòng ban cho superadmin.
 * - /feature-requests/mine — trang chi tiết đầy đủ cho nhân viên tự xem lịch sử
 *   ghi nhận của mình (bổ sung cho drawer nhanh ở AppHeader
 *   HeaderFeatureRequestButton.vue, không thay thế).
 */
export default [
  {
    path: '/superadmin/feature-requests',
    name: 'superadmin.feature-requests.index',
    component: () => import('./pages/FeatureRequestSuperAdmin.vue'),
    meta: {
      requiresAuth: true,
      title: 'Ghi nhận yêu cầu tính năng',
      requiresPermission: 'feature_request.review',
    },
  },
  {
    path: '/feature-requests/mine',
    name: 'feature-requests.mine',
    component: () => import('./pages/FeatureRequestMine.vue'),
    meta: {
      requiresAuth: true,
      title: 'Ghi nhận yêu cầu tính năng của tôi',
    },
  },
];
