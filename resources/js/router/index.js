import { createRouter, createWebHistory } from 'vue-router';
import identityRoutes from '@modules/Identity/resources/js/router.js';

/**
 * Route Vue (SPA phía client) — KHÔNG nhầm với route Laravel
 * (routes/web.php, manager.php, superadmin.php, api.php).
 *
 * Quy ước:
 * - Mỗi module trong Modules/{Ten}/Resources/js đăng ký route riêng và
 *   được import/gộp vào đây, hoặc dùng auto-import theo glob.
 * - Tên route nên prefix theo module để tránh trùng, vd: "user.index".
 */
const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../pages/Home.vue'),
        meta: { requiresAuth: true },
    },
    ...identityRoutes,
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Guard đăng nhập: route có meta.requiresAuth cần auth.isAuthenticated,
// ngược lại route meta.guestOnly (vd. /login) tự chuyển vào app nếu đã
// đăng nhập. Store lazy-import để tránh phụ thuộc vòng lúc khởi tạo Pinia.
router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth && !to.meta.guestOnly) {
        return true;
    }

    const { useAuthStore } = await import('@modules/Identity/resources/js/stores/auth.js');
    const auth = useAuthStore();

    if (!auth.isReady) {
        await auth.fetchMe();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }

    return true;
});

export default router;
