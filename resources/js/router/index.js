import { createRouter, createWebHistory } from 'vue-router';
import identityRoutes from '@modules/Identity/resources/js/router.js';
import workspaceConfigRoutes from '@modules/WorkspaceConfig/resources/js/router.js';
import socialRoutes from '@modules/Social/resources/js/router.js';
import evaluationRoutes from '@modules/Evaluation/resources/js/router.js';

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
        meta: { requiresAuth: true, title: 'Tổng quan' },
    },
    ...identityRoutes,
    ...workspaceConfigRoutes,
    ...socialRoutes,
    ...evaluationRoutes,
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

    if (!auth.isReady || (to.meta.requiresAuth && !auth.user)) {
        await auth.fetchMe({ force: !auth.user });
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresSuperAdmin && !auth.showSuperAdminNav) {
        return { name: 'home' };
    }

    // Hub /manager/workspace-config/* scope theo phòng ban của user.
    // super_admin (không view-as) dùng trang tổng hợp mọi phòng ban.
    if (
        auth.showSuperAdminNav &&
        to.matched.some((record) => record.name === 'manager.workspace-config.hub')
    ) {
        return { name: 'superadmin.workspace-config.overview' };
    }

    if (to.meta.requiresAdmin && !auth.canViewActivityLog) {
        return { name: 'home' };
    }

    // Route yêu cầu 1 permission cụ thể (vd. mục sidebar riêng ngoài phạm vi
    // requiresSuperAdmin/requiresAdmin) — vd. manager.evaluation-templates.index
    // chỉ department_director/deputy trở lên (evaluation.manage_department).
    if (to.meta.requiresPermission && !auth.can(to.meta.requiresPermission)) {
        return { name: 'home' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }

    return true;
});

export default router;
