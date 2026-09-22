/**
 * Route Vue của module FeatureRequest — import/gộp vào resources/js/router/index.js.
 *
 * - /superadmin/feature-requests — bảng ghi nhận cho superadmin.
 * - /feature-requests/mine — bảng lịch sử ghi nhận của chính nhân viên
 *   (bổ sung cho drawer nhanh ở AppHeader HeaderFeatureRequestButton.vue).
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
