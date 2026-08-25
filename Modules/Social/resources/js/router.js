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
  {
    path: '/social/groups',
    name: 'social.groups.index',
    component: () => import('./pages/SocialGroups.vue'),
    meta: { requiresAuth: true, title: 'Nhóm' },
  },
  {
    path: '/social/groups/:id',
    name: 'social.groups.show',
    component: () => import('./pages/SocialGroupWall.vue'),
    meta: { requiresAuth: true, title: 'Nhóm' },
    props: true,
  },
];
