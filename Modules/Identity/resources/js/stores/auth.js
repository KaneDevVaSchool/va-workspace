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

    /**
     * Menu sidebar bị superadmin ẩn Ở MỨC TOÀN HỆ THỐNG — thắng tuyệt đối
     * hiddenMenuKeys (per-department), áp dụng cho MỌI tài khoản kể cả
     * super_admin, không có ngoại lệ (xem AppSidebar.vue::itemPasses).
     */
    globallyHiddenMenuKeys: (state) => state.user?.globally_hidden_menu_keys ?? [],

    /** Tên hiển thị tuỳ chỉnh trên menu trái (menu_key => nhãn). */
    menuLabels: (state) => {
      const raw = state.user?.menu_labels;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Thứ tự tuỳ chỉnh (menu_key => sort_order). */
    menuOrder: (state) => {
      const raw = state.user?.menu_order;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Nhóm tuỳ chỉnh của từng mục (menu_key => section id). */
    menuItemSections: (state) => {
      const raw = state.user?.menu_item_sections;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Tên nhóm menu trái tuỳ chỉnh (section id => nhãn). */
    menuSectionLabels: (state) => {
      const raw = state.user?.menu_section_labels;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Tên tuỳ chỉnh TOÀN HỆ THỐNG cho từng item (menu_key => nhãn). */
    globalMenuLabels: (state) => {
      const raw = state.user?.global_menu_labels;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Thứ tự tuỳ chỉnh TOÀN HỆ THỐNG (menu_key => sort_order). */
    globalMenuOrder: (state) => {
      const raw = state.user?.global_menu_order;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Nhóm tuỳ chỉnh TOÀN HỆ THỐNG của từng item (menu_key => section id). */
    globalMenuItemSections: (state) => {
      const raw = state.user?.global_menu_item_sections;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },

    /** Tên nhóm menu tuỳ chỉnh TOÀN HỆ THỐNG (section id => nhãn). */
    globalMenuSectionLabels: (state) => {
      const raw = state.user?.global_menu_section_labels;
      if (!raw || Array.isArray(raw)) return {};
      return raw;
    },
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

    /**
     * Cập nhật tên hiển thị menu trái ngay trên client sau khi phòng ban
     * đổi tên — sidebar phản ánh tức thì.
     */
    setMenuLabel(menuKey, label) {
      if (!this.user || !menuKey) return;
      const raw = this.user.menu_labels;
      const labels = raw && !Array.isArray(raw) ? { ...raw } : {};
      const next = typeof label === 'string' ? label.trim() : '';
      if (next) labels[menuKey] = next;
      else delete labels[menuKey];
      this.user.menu_labels = labels;
    },

    /**
     * Áp layout (thứ tự + nhóm) sau kéo thả — sidebar trái đổi tức thì.
     * @param {{ order?: Record<string, number>, itemSections?: Record<string, string> }} layout
     */
    setMenuLayout(layout) {
      if (!this.user) return;
      if (layout?.order && !Array.isArray(layout.order)) {
        this.user.menu_order = { ...layout.order };
      }
      if (layout?.itemSections && !Array.isArray(layout.itemSections)) {
        this.user.menu_item_sections = { ...layout.itemSections };
      }
    },

    /**
     * Đổi tên item menu toàn hệ thống ngay trên client.
     */
    setGlobalMenuLabel(menuKey, label) {
      if (!this.user || !menuKey) return;
      const raw = this.user.global_menu_labels;
      const labels = raw && !Array.isArray(raw) ? { ...raw } : {};
      const next = typeof label === 'string' ? label.trim() : '';
      if (next) labels[menuKey] = next;
      else delete labels[menuKey];
      this.user.global_menu_labels = labels;
    },

    /**
     * Áp layout toàn hệ thống (thứ tự + nhóm) sau kéo thả.
     * @param {{ order?: Record<string, number>, itemSections?: Record<string, string> }} layout
     */
    setGlobalMenuLayout(layout) {
      if (!this.user) return;
      if (layout?.order && !Array.isArray(layout.order)) {
        this.user.global_menu_order = { ...layout.order };
      }
      if (layout?.itemSections && !Array.isArray(layout.itemSections)) {
        this.user.global_menu_item_sections = { ...layout.itemSections };
      }
    },

    /**
     * Đổi tên nhóm menu toàn hệ thống ngay trên client.
     */
    setGlobalMenuSectionLabel(sectionId, label) {
      if (!this.user || !sectionId) return;
      const raw = this.user.global_menu_section_labels;
      const labels = raw && !Array.isArray(raw) ? { ...raw } : {};
      const next = typeof label === 'string' ? label.trim() : '';
      if (next) labels[sectionId] = next;
      else delete labels[sectionId];
      this.user.global_menu_section_labels = labels;
    },

    /**
     * Cập nhật globally_hidden_menu_keys ngay trên client sau khi superadmin
     * bật/tắt 1 menu ở trang cấu hình toàn hệ thống.
     */
    setGlobalMenuKeyHidden(menuKey, isHidden) {
      if (!this.user || !menuKey) return;
      const hidden = new Set(
        Array.isArray(this.user.globally_hidden_menu_keys) ? this.user.globally_hidden_menu_keys : [],
      );
      if (isHidden) hidden.add(menuKey);
      else hidden.delete(menuKey);
      this.user.globally_hidden_menu_keys = [...hidden];
    },

    /**
     * Đổi tên nhóm menu trái ngay trên client.
     */
    setSectionLabel(sectionId, label) {
      if (!this.user || !sectionId) return;
      const raw = this.user.menu_section_labels;
      const labels = raw && !Array.isArray(raw) ? { ...raw } : {};
      const next = typeof label === 'string' ? label.trim() : '';
      if (next) labels[sectionId] = next;
      else delete labels[sectionId];
      this.user.menu_section_labels = labels;
    },
  },
});
