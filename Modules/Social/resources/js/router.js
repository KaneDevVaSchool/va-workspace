/**
 * Route Vue của module Social — import/gộp vào resources/js/router/index.js.
 */
export default [
  {
    path: '/social',
    name: 'social.feed',
    component: () => import('./pages/SocialFeed.vue'),
    meta: { requiresAuth: true, title: 'Bảng tin nội bộ' },
  },
];
