/**
 * Route Vue của module FeatureRequest — import/gộp vào resources/js/router/index.js.
 *
 * Trang duy nhất: danh sách ghi nhận theo phòng ban cho superadmin. Phía
 * nhân viên không cần trang riêng — form ghi nhận mở qua nút ở AppHeader
 * (HeaderFeatureRequestButton.vue), xem lại "ghi nhận của tôi" ngay trong
 * cùng drawer đó.
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
];
