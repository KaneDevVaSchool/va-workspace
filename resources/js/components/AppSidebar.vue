<script setup>
//
// Sidebar điều hướng chính sau khi đăng nhập (layout dạng admin: logo trên
// cùng, danh sách menu có icon, khối user + đăng xuất dưới cùng).
//
// - Desktop (>=1280px): sidebar cố định, thu gọn được (chỉ icon) qua nút
//   chevron, trạng thái lưu vào localStorage để giữ giữa các lần load.
// - Tablet/mobile (<1280px): sidebar ẩn mặc định, mở dạng off-canvas qua
//   prop `open` (điều khiển từ AppLayout) + lớp phủ để đóng khi bấm ra ngoài.
//
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { showClientToast } from '../lib/clientToast';
import AppIcon from './AppIcon.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const logoutConfirmOpen = ref(false);
const loggingOut = ref(false);

const STORAGE_KEY = 'va-sidebar-collapsed';
const collapsed = ref(localStorage.getItem(STORAGE_KEY) === '1');
watch(collapsed, (value) => {
  localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
});

// Dropdown khối user (avatar + tên + vai trò + đăng xuất) mở khi bấm vào
// avatar, đóng khi bấm ra ngoài — thay cho việc bày nút đăng xuất cố định.
const userMenuRef = ref(null);
const userMenuOpen = ref(false);

function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value;
}

function handleClickOutside(event) {
  if (userMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside));

// Danh sách menu tĩnh cho khu vực chung. Module có thể tự bổ sung mục menu
// sau này bằng cách mở rộng mảng này (hoặc gộp menu theo module tương tự
// cách gộp route ở resources/js/router/index.js). Mục có `name` chưa được
// đăng ký trong router sẽ tự ẩn (tránh router-link lỗi "no match") cho tới
// khi module tương ứng thêm route thật.
const MENU = [
  { name: 'home', label: 'Tổng quan', icon: 'dashboard' },
  { name: 'users', label: 'Người dùng', icon: 'users', requiresSuperAdmin: true },
  { name: 'departments', label: 'Phòng ban', icon: 'building', requiresSuperAdmin: true },
  { name: 'settings', label: 'Cài đặt', icon: 'settings' },
];

const registeredRouteNames = computed(() => new Set(router.getRoutes().map((r) => r.name)));

const menuItems = computed(() =>
  MENU.filter(
    (item) =>
      registeredRouteNames.value.has(item.name) && (!item.requiresSuperAdmin || auth.isSuperAdmin),
  ),
);

const userLabel = computed(() => auth.user?.name ?? auth.user?.email ?? 'Người dùng');
const userInitial = computed(() => userLabel.value.trim().charAt(0).toUpperCase() || '?');

const roleLabels = {
  super_admin: 'Super Admin',
  admin: 'Admin',
  director_officer: 'Giám đốc điều hành',
  department_director: 'Trưởng phòng ban',
  team_lead: 'Trưởng nhóm',
  member: 'Nhân viên',
  viewer: 'Người xem',
};
const roleLabel = computed(() => roleLabels[auth.activeRole] ?? auth.activeRole ?? '');

async function confirmLogout() {
  loggingOut.value = true;
  try {
    await auth.logout();
    logoutConfirmOpen.value = false;
    userMenuOpen.value = false;
    // Điều hướng qua router (thay vì full-page reload) để giữ SPA mượt —
    // route /login tự chạy được vì không nằm trong AppLayout (App.vue chỉ
    // bọc AppLayout cho route requiresAuth).
    await router.push({ name: 'login' });
    showClientToast('success', 'Đã đăng xuất.');
  } catch {
    showClientToast('error', 'Đăng xuất thất bại. Vui lòng thử lại.');
  } finally {
    loggingOut.value = false;
  }
}

function isActive(routeName) {
  return route.name === routeName || route.matched.some((r) => r.name === routeName);
}
</script>

<template>
  <div class="sidebar-wrap" :class="{ 'sidebar-wrap--open': open }">
    <div class="sidebar-overlay" @click="emit('close')" />

    <aside class="sidebar" :class="{ 'sidebar--collapsed': collapsed }">
      <div class="sidebar__header">
        <router-link :to="{ name: 'home' }" class="sidebar__brand" @click="emit('close')">
          <img
            v-if="collapsed"
            src="/images/congnghe/brand/vas-white-mark.png"
            srcset="/images/congnghe/brand/vas-white-mark.png 1x, /images/congnghe/brand/vas-white-mark@2x.png 2x"
            alt="Vietnam America Schools"
            class="sidebar__brand-mark"
          />
          <img
            v-else
            src="/images/congnghe/brand/vas-white.png"
            alt="Vietnam America Schools"
            class="sidebar__brand-logo"
          />
        </router-link>

        <button
          type="button"
          class="sidebar__collapse-btn"
          :aria-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
          @click="collapsed = !collapsed"
        >
          <AppIcon :name="collapsed ? 'chevronRight' : 'chevronLeft'" :size="16" />
        </button>

        <button type="button" class="sidebar__close-btn" aria-label="Đóng menu" @click="emit('close')">
          <AppIcon name="close" :size="18" />
        </button>
      </div>

      <nav class="sidebar__nav">
        <router-link
          v-for="item in menuItems"
          :key="item.name"
          :to="{ name: item.name }"
          class="sidebar__link"
          :class="{ 'sidebar__link--active': isActive(item.name) }"
          :aria-label="collapsed ? item.label : null"
          @click="emit('close')"
        >
          <AppIcon :name="item.icon" :size="20" />
          <span v-if="!collapsed" class="sidebar__link-text">{{ item.label }}</span>
        </router-link>
      </nav>

      <div class="sidebar__footer" ref="userMenuRef">
        <div v-if="userMenuOpen" class="sidebar__user-menu">
          <div class="sidebar__user-menu-header">
            <span class="sidebar__user-menu-name">{{ userLabel }}</span>
            <span v-if="auth.user?.email" class="sidebar__user-menu-email">{{ auth.user.email }}</span>
            <span v-if="roleLabel" class="sidebar__user-menu-role">Vai trò: {{ roleLabel }}</span>
          </div>
          <button
            type="button"
            class="sidebar__user-menu-logout"
            @click="logoutConfirmOpen = true"
          >
            <AppIcon name="logout" :size="18" />
            <span>Đăng xuất</span>
          </button>
        </div>

        <button
          type="button"
          class="sidebar__user"
          :class="{ 'sidebar__user--open': userMenuOpen }"
          aria-haspopup="true"
          :aria-expanded="userMenuOpen"
          @click="toggleUserMenu"
        >
          <span class="sidebar__avatar">
            <img
              v-if="auth.user?.avatar_url"
              :src="auth.user.avatar_url"
              :alt="userLabel"
              class="sidebar__avatar-img"
              referrerpolicy="no-referrer"
            />
            <template v-else>{{ userInitial }}</template>
          </span>
          <span v-if="!collapsed" class="sidebar__user-info">
            <span class="sidebar__user-name">{{ userLabel }}</span>
            <span v-if="roleLabel" class="sidebar__user-role">{{ roleLabel }}</span>
          </span>
          <AppIcon v-if="!collapsed" name="chevronDown" :size="14" class="sidebar__user-caret" />
        </button>
      </div>
    </aside>

    <ConfirmDialog
      v-model:open="logoutConfirmOpen"
      title="Xác nhận đăng xuất"
      description="Bạn có chắc muốn đăng xuất khỏi VA Workspace? Phiên làm việc hiện tại sẽ kết thúc."
      confirm-label="Đăng xuất"
      danger
      :loading="loggingOut"
      @confirm="confirmLogout"
    />
  </div>
</template>

<style scoped>
.sidebar-wrap {
  flex-shrink: 0;
}

.sidebar-overlay {
  display: none;
}

.sidebar {
  width: 15rem;
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: var(--color-primary-900);
  background-image: linear-gradient(
    180deg,
    rgba(255, 255, 255, 0.06) 0%,
    rgba(0, 0, 0, 0) 30%,
    rgba(0, 0, 0, 0.16) 100%
  );
  box-shadow: 1px 0 0 var(--color-primary-900);
  transition: width 0.2s ease;
}

.sidebar--collapsed {
  width: 4.5rem;
}

/* ---------- Header / brand ---------- */
.sidebar__header {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  height: 4.75rem;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.1);
}

.sidebar__brand {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  text-decoration: none;
  overflow: hidden;
}

.sidebar__brand-mark {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  object-fit: contain;
}

.sidebar__brand-logo {
  display: block;
  width: auto;
  max-width: 100%;
  height: 2.25rem;
  object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.25));
}

.sidebar__collapse-btn {
  position: absolute;
  right: var(--space-2);
  top: 50%;
  transform: translateY(-50%);
  flex-shrink: 0;
  display: none;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
}

.sidebar__collapse-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.16);
}

.sidebar__close-btn {
  position: absolute;
  right: var(--space-2);
  top: 50%;
  transform: translateY(-50%);
  flex-shrink: 0;
  display: none;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
}

/* ---------- Menu ---------- */
.sidebar__nav {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-3);
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  color: rgba(255, 255, 255, 0.72);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
}

.sidebar__link-text {
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__link:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}

.sidebar__link--active {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
  font-weight: 700;
}

/* ---------- Footer / user ---------- */
.sidebar__footer {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 -1px 0 rgba(255, 255, 255, 0.1);
}

.sidebar__user {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1);
  overflow: hidden;
}

.sidebar__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.16);
  color: #ffffff;
  font-weight: 700;
  font-size: 0.8125rem;
}

.sidebar__user-info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.sidebar__user-name {
  color: #ffffff;
  font-weight: 600;
  font-size: 0.8125rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__user-role {
  color: rgba(255, 255, 255, 0.64);
  font-size: 0.75rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__logout {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: rgba(255, 255, 255, 0.72);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.sidebar__logout:hover {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

/* ---------- Responsive: off-canvas dưới desktop ---------- */
@media (max-width: 1279px) {
  .sidebar-wrap {
    position: fixed;
    inset: 0;
    z-index: 40;
    pointer-events: none;
  }

  .sidebar-overlay {
    display: block;
    position: absolute;
    inset: 0;
    background: color-mix(in srgb, #000000 40%, transparent);
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
  }

  .sidebar {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 16rem;
    max-width: 85vw;
    transform: translateX(-100%);
    transition: transform 0.2s ease;
    box-shadow: var(--shadow-lg);
  }

  .sidebar--collapsed {
    width: 16rem;
  }

  .sidebar__collapse-btn {
    display: none !important;
  }

  .sidebar__close-btn {
    display: flex;
  }

  .sidebar-wrap--open {
    pointer-events: auto;
  }

  .sidebar-wrap--open .sidebar-overlay {
    opacity: 1;
    pointer-events: auto;
  }

  .sidebar-wrap--open .sidebar {
    transform: translateX(0);
  }
}

@media (min-width: 1280px) {
  .sidebar__collapse-btn {
    display: flex;
  }
}
</style>
