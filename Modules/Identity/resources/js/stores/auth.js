import { defineStore } from 'pinia';

/**
 * State đăng nhập (Sanctum SPA — session/cookie, không lưu token ở client).
 * fetchMe() gọi GET /api/me để lấy user hiện tại (cookie tự gửi kèm nhờ
 * axios withCredentials, xem resources/js/bootstrap.js).
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isLoading: false,
    isReady: false,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
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
  },
});
