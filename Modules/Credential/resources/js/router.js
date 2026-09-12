/**
 * Route Vue của module Credential — import/gộp vào resources/js/router/index.js.
 *
 * "Quản lý tài khoản" là mục sidebar riêng. Route này CHỈ tồn tại phía Vue
 * Router (SPA) — Laravel phục vụ qua fallback catch-all trong routes/web.php
 * gốc, không có route Laravel "manager.credential.index" thật (giống hệt
 * cách Project/Evaluation làm — xem Modules/Project/resources/js/router.js).
 *
 * Modal tạo/sửa render ngay trong CredentialList.vue (skill form-modal),
 * không dùng route riêng — chỉ trang danh sách + trang chi tiết.
 */
export default [
  {
    path: '/manager/credential',
    name: 'manager.credential.index',
    component: () => import('./pages/CredentialList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Quản lý tài khoản',
      requiresPermission: 'credential.view',
    },
  },
  {
    // PHẢI đăng ký SAU CÙNG (route tĩnh /manager/credential ở trên) — nếu
    // không :id (wildcard) sẽ nuốt mất path đó.
    path: '/manager/credential/:id',
    name: 'manager.credential.detail',
    component: () => import('./pages/CredentialDetail.vue'),
    meta: {
      requiresAuth: true,
      title: 'Chi tiết tài khoản',
      requiresPermission: 'credential.view',
    },
  },
];
