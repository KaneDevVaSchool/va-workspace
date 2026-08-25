import { defineStore } from 'pinia';
import { ensureCsrfCookie } from '@/bootstrap';

function userCanViewAs(user) {
  if (!user) {
    return false;
  }
  if (user.can_view_as) {
    return true;
  }
  return Array.isArray(user.roles) && user.roles.includes('super_admin');
}

/**
 * Sanctum SPA — session cookie, CSRF qua bootstrap.js.
 * API session (web middleware): GET /api/me, POST|DELETE /api/view-as.
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoading: false,
    isReady: false,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,

    /** Hiện dropdown "Xem thử vai trò" (quyền thật super_admin). */
    canViewAs: (state) => userCanViewAs(state.user),

    /** @deprecated Dùng canViewAs — giữ alias tránh vỡ import cũ. */
    isSuperAdmin: (state) => userCanViewAs(state.user),

    /**
     * Menu quản trị (requiresSuperAdmin) — ẩn khi đang xem thử role khác
     * để UI phản ánh đúng vai trò hiệu lực.
     */
    showSuperAdminNav: (state) => {
      const user = state.user;
      if (!user || user.is_impersonating) {
        return false;
      }
      return userCanViewAs(user);
    },

    /**
     * Nhật ký hoạt động: super_admin / admin thật, hoặc đang xem thử đúng
     * vai trò admin (API cũng lọc theo role hiệu lực).
     */
    canViewActivityLog: (state) => {
      const user = state.user;
      if (!user) {
        return false;
      }

      if (user.is_impersonating) {
        return user.active_role === 'admin' || user.active_role === 'super_admin';
      }

      const roles = Array.isArray(user.roles) ? user.roles : [];
      return roles.includes('super_admin') || roles.includes('admin');
    },

    isImpersonating: (state) => state.user?.is_impersonating ?? false,
    activeRole: (state) => state.user?.active_role ?? null,

    /**
     * Kiểm tra permission key từ granted_permissions (cache từ /api/me).
     * Hỗ trợ hierarchy: '*', 'module.*', 'module.action'.
     *
     * Dùng trong component: authStore.can('task.delegate')
     * Dùng trong template: v-if="authStore.can('project.create')"
     *
     * @returns {(key: string) => boolean}
     */
    can: (state) => (key) => {
      const perms = state.user?.granted_permissions;
      if (!perms || perms.length === 0) return false;
      if (perms.includes('*')) return true;

      if (perms.includes(key)) return true;

      // module.* match
      const dotIdx = key.lastIndexOf('.');
      if (dotIdx > 0) {
        const moduleWild = key.slice(0, dotIdx) + '.*';
        if (perms.includes(moduleWild)) return true;
      }

      return false;
    },

    /** Toàn bộ permission keys hiệu lực. Dùng để debug hoặc render UI điều kiện. */
    grantedPermissions: (state) => state.user?.granted_permissions ?? [],

    /** Menu sidebar bị phòng ban của user tự tắt (xem AppSidebar.vue). */
    hiddenMenuKeys: (state) => state.user?.hidden_menu_keys ?? [],
  },

  actions: {
    resetSession() {
      this.user = null;
      this.isReady = false;
    },

    setUserFromApi(payload) {
      if (!payload?.user) {
        throw new Error('API response missing user payload');
      }
      this.user = payload.user;
      this.isReady = true;
    },

    async fetchMe({ force = false } = {}) {
      if (!force && this.isReady && this.user) {
        return;
      }

      this.isLoading = true;
      try {
        await ensureCsrfCookie();
        const { data } = await window.axios.get('/api/me');
        this.user = data;
      } catch (error) {
        this.user = null;
        if (error?.response?.status === 401) {
          this.isReady = true;
          return;
        }
        throw error;
      } finally {
        this.isLoading = false;
        this.isReady = true;
      }
    },

    async logout() {
      await ensureCsrfCookie();
      await window.axios.post('/logout');
      this.resetSession();
    },

    async viewAs(roleCode) {
      await ensureCsrfCookie();
      const { data } = await window.axios.post('/api/view-as', { role_code: roleCode });
      this.setUserFromApi(data);
    },

    async exitViewAs() {
      await ensureCsrfCookie();
      const { data } = await window.axios.delete('/api/view-as');
      this.setUserFromApi(data);
    },

    /**
     * Cập nhật hidden_menu_keys ngay trên client sau khi bật/tắt menu
     * phòng ban — sidebar trái phản ánh tức thì, không cần tải lại trang.
     */
    setMenuKeyVisible(menuKey, isVisible) {
      if (!this.user || !menuKey) return;
      const hidden = new Set(
        Array.isArray(this.user.hidden_menu_keys) ? this.user.hidden_menu_keys : [],
      );
      if (isVisible) hidden.delete(menuKey);
      else hidden.add(menuKey);
      this.user.hidden_menu_keys = [...hidden];
    },
  },
});
