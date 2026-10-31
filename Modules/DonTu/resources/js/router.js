import { DON_TU_SCREENS } from './nav.js';

const ADMIN_ROUTES = {
  'manager.don-tu.statuses': () => import('./pages/admin/DonTuStatusSets.vue'),
  'manager.don-tu.forms': () => import('./pages/admin/DonTuFormTemplates.vue'),
  'manager.don-tu.form': () => import('./pages/admin/DonTuFormEditor.vue'),
  'manager.don-tu.workflows': () => import('./pages/admin/DonTuWorkflows.vue'),
  'manager.don-tu.workflow': () => import('./pages/admin/DonTuWorkflowEditor.vue'),
};

/**
 * Hub Đơn từ. Cấu hình (admin) có API + UI; màn vận hành P1 vẫn placeholder.
 */
export default [
  {
    path: '/manager/don-tu',
    component: () => import('./pages/DonTuLayout.vue'),
    redirect: { name: 'manager.don-tu.config' },
    meta: { requiresAuth: true, title: 'Đơn từ' },
    children: [
      {
        path: 'config',
        name: 'manager.don-tu.config',
        component: () => import('./pages/admin/DonTuConfigStatusHub.vue'),
        meta: {
          requiresAuth: true,
          title: 'Cấu hình đơn từ',
          screenTitle: 'Cấu hình đơn từ',
          ui: 'UI-11',
          phase: 'P0',
        },
      },
      {
        path: 'monitor-hub',
        name: 'manager.don-tu.monitor-hub',
        redirect: { name: 'manager.don-tu.monitor' },
      },
      {
        path: 'admin/forms/:id',
        name: 'manager.don-tu.form',
        component: ADMIN_ROUTES['manager.don-tu.form'],
        meta: {
          requiresAuth: true,
          title: 'Cấu hình mẫu biểu',
          screenTitle: 'Cấu hình mẫu biểu',
          ui: 'UI-08',
          phase: 'P2',
        },
      },
      {
        path: 'admin/workflows/:id',
        name: 'manager.don-tu.workflow',
        component: ADMIN_ROUTES['manager.don-tu.workflow'],
        meta: {
          requiresAuth: true,
          title: 'Cấu hình quy trình',
          screenTitle: 'Cấu hình quy trình',
          ui: 'UI-10',
          phase: 'P2',
        },
      },
      ...DON_TU_SCREENS.filter(
        (screen) => screen.name !== 'manager.don-tu.form' && screen.name !== 'manager.don-tu.workflow',
      ).map((screen) => {
        const adminLoader = ADMIN_ROUTES[screen.name];
        return {
          path: screen.path,
          name: screen.name,
          component: adminLoader
            ? adminLoader
            : () => import('./pages/DonTuPlaceholder.vue'),
          meta: {
            requiresAuth: true,
            title: screen.title,
            screenTitle: screen.title,
            ui: screen.ui,
            phase: screen.phase,
          },
        };
      }),
    ],
  },
];
