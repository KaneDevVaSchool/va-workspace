import { DON_TU_SCREENS } from './nav.js';

/**
 * Hub xem trước. Mọi user đã đăng nhập đều vào được — cổng quyền don_tu.* gắn ở P1.
 */
export default [
  {
    path: '/manager/don-tu',
    component: () => import('./pages/DonTuLayout.vue'),
    redirect: { name: 'manager.don-tu.start' },
    meta: { requiresAuth: true, title: 'Đơn từ' },
    children: DON_TU_SCREENS.map((screen) => ({
      path: screen.path,
      name: screen.name,
      component: () => import('./pages/DonTuPlaceholder.vue'),
      meta: {
        requiresAuth: true,
        title: screen.title,
        screenTitle: screen.title,
        ui: screen.ui,
        phase: screen.phase,
      },
    })),
  },
];
