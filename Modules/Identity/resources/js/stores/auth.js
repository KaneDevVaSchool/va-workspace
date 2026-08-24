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

    isImpersonating: (state) => state.user?.is_impersonating ?? false,
    activeRole: (state) => state.user?.active_role ?? null,
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
  },
});
