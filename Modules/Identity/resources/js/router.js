/**
 * Route Vue của module Identity — import/gộp vào resources/js/router/index.js.
 */
export default [
  {
    path: '/login',
    name: 'login',
    component: () => import('./pages/Login.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/auth/callback',
    name: 'auth.callback',
    component: () => import('./pages/AuthCallback.vue'),
  },
];
