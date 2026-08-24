import { defineStore } from 'pinia';

/**
 * State đăng nhập (Sanctum SPA — session/cookie, không lưu token ở client).
 * fetchMe() gọi GET /api/me để lấy user hiện tại (cookie tự gửi kèm nhờ
 * axios withCredentials, xem resources/js/bootstrap.js).
 *
 * RBAC tối giản: `user.roles` là vai trò thật, `user.active_role` là vai
 * trò hiệu lực (ưu tiên view-as override nếu super_admin đang "xem thử"
 * — xem Modules/Identity/App/Services/ViewAsService.php).
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoading: false,
    isReady: false,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    isSuperAdmin: (state) => state.user?.roles?.includes('super_admin') ?? false,
    isImpersonating: (state) => state.user?.is_impersonating ?? false,
    activeRole: (state) => state.user?.active_role ?? null,
  },

  actions: {
    async fetchMe() {
      this.isLoading = true;
      try {
        await window.axios.get('/sanctum/csrf-cookie');
        const { data } = await window.axios.get('/api/me');
        this.user = data;
      } catch (error) {
        this.user = null;
        if (error?.response?.status !== 401) {
          throw error;
        }
      } finally {
        this.isLoading = false;
        this.isReady = true;
      }
    },

    async logout() {
      await window.axios.post('/logout');
      this.user = null;
    },

    /** "Xem thử" 1 role khác — chỉ có tác dụng nếu user thật là super_admin. */
    async viewAs(roleCode) {
      await window.axios.post('/api/view-as', { role_code: roleCode });
      await this.fetchMe();
    },

    async exitViewAs() {
      await window.axios.delete('/api/view-as');
      await this.fetchMe();
    },
  },
});
