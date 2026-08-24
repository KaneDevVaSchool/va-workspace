<script setup>
//
// Sidebar điều hướng chính sau khi đăng nhập (layout dạng admin: logo trên
// cùng, menu theo nhóm, khối user + đăng xuất dưới cùng).
//
// - Desktop (>=1280px): sidebar cố định, thu gọn được (chỉ icon + flyout
//   nhãn) qua nút chevron, trạng thái lưu vào localStorage.
// - Tablet/mobile (<1280px): sidebar ẩn mặc định, mở dạng off-canvas qua
//   prop `open` (điều khiển từ AppLayout) + lớp phủ để đóng khi bấm ra ngoài.
//
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { roleLabel as formatRoleLabel } from '@modules/Identity/resources/js/constants/roles.js';
import { showClientToast } from '../lib/clientToast';
import AppIcon from './AppIcon.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import ViewAsSwitcher from '@modules/Identity/resources/js/components/ViewAsSwitcher.vue';

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

const userMenuRef = ref(null);
const userMenuOpen = ref(false);

function toggleUserMenu() {
  userMenuOpen.value = !userMenuOpen.value;
}

function isNativeSelectEvent(event) {
  const target = event.target;
  if (target instanceof HTMLSelectElement || target instanceof HTMLOptionElement) {
    return true;
  }
  if (typeof target?.closest === 'function' && target.closest('select')) {
    return true;
  }
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
  return path.some((node) => node instanceof HTMLSelectElement || node instanceof HTMLOptionElement);
}

function handleClickOutside(event) {
  if (!userMenuOpen.value || !userMenuRef.value) {
    return;
  }
  if (userMenuRef.value.contains(event.target) || isNativeSelectEvent(event)) {
    return;
  }
  // Dropdown native của <select> nằm ngoài DOM Vue. Đợi 1 tick để @change
  // kịp chạy trước khi đóng menu.
  window.setTimeout(() => {
    const active = document.activeElement;
    if (userMenuRef.value?.contains(active) && active?.tagName === 'SELECT') {
      return;
    }
    userMenuOpen.value = false;
  }, 0);
}

function handleKeydown(event) {
  if (event.key !== 'Escape') {
    return;
  }
  if (userMenuOpen.value) {
    userMenuOpen.value = false;
    return;
  }
  if (props.open) {
    emit('close');
  }
}

watch(
  () => props.open,
  (open) => {
    if (typeof window === 'undefined') {
      return;
    }
    const isDrawer = window.matchMedia('(max-width: 1279px)').matches;
    document.body.style.overflow = open && isDrawer ? 'hidden' : '';
  },
);

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeydown);
});
onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
});

const MENU_SECTIONS = [
  {
    id: 'general',
    label: 'Điều hướng',
    items: [
      { name: 'home', label: 'Tổng quan', icon: 'dashboard' },
      { name: 'settings', label: 'Cài đặt', icon: 'settings' },
    ],
  },
  {
    id: 'admin',
    label: 'Quản trị',
    items: [
      { name: 'users', label: 'Người dùng', icon: 'users', requiresSuperAdmin: true },
      { name: 'departments', label: 'Phòng ban', icon: 'building', requiresSuperAdmin: true },
    ],
  },
];

const registeredRouteNames = computed(() => new Set(router.getRoutes().map((r) => r.name)));

const visibleSections = computed(() =>
  MENU_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter(
      (item) =>
        registeredRouteNames.value.has(item.name) &&
        (!item.requiresSuperAdmin || auth.showSuperAdminNav),
    ),
  })).filter((section) => section.items.length > 0),
);

const userLabel = computed(() => auth.user?.name ?? auth.user?.email ?? 'Người dùng');
const userInitial = computed(() => userLabel.value.trim().charAt(0).toUpperCase() || '?');

const roleLabel = computed(() => formatRoleLabel(auth.activeRole));

async function confirmLogout() {
  loggingOut.value = true;
  try {
    await auth.logout();
    logoutConfirmOpen.value = false;
    userMenuOpen.value = false;
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

function closeDrawer() {
  emit('close');
}
</script>

<template>
  <div class="sidebar-wrap" :class="{ 'sidebar-wrap--open': open }">
    <button
      type="button"
      class="sidebar-overlay"
      aria-label="Đóng overlay menu"
      @click="closeDrawer"
    />

    <aside
      class="sidebar sidebar-surface"
      :class="{ 'sidebar--collapsed': collapsed }"
      data-tour="sidebar"
      :aria-label="collapsed ? 'Menu thu gọn' : 'Menu chính'"
    >
      <button
        type="button"
        class="sidebar__collapse-btn"
        :aria-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
        @click="collapsed = !collapsed"
      >
        <AppIcon :name="collapsed ? 'chevronRight' : 'chevronLeft'" :size="16" />
      </button>

      <div class="sidebar__header">
        <router-link :to="{ name: 'home' }" class="sidebar__brand" @click="closeDrawer">
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

        <button type="button" class="sidebar__close-btn" aria-label="Đóng menu" @click="closeDrawer">
          <AppIcon name="close" :size="18" />
        </button>
      </div>

      <nav class="sidebar__nav sidebar-scroll" aria-label="Điều hướng chính">
        <section
          v-for="section in visibleSections"
          :key="section.id"
          class="sidebar__section"
          :data-tour="`sidebar-section-${section.id}`"
        >
          <p v-if="!collapsed" class="sidebar__section-label">{{ section.label }}</p>

          <router-link
            v-for="item in section.items"
            :key="item.name"
            :to="{ name: item.name }"
            class="sidebar__link sidebar-nav-item"
            :class="{ 'sidebar__link--active sidebar-nav-item--active': isActive(item.name) }"
            :aria-label="collapsed ? item.label : null"
            :aria-current="isActive(item.name) ? 'page' : null"
            @click="closeDrawer"
          >
            <span class="sidebar__link-icon">
              <AppIcon :name="item.icon" :size="18" />
            </span>
            <span v-if="!collapsed" class="sidebar__link-text">{{ item.label }}</span>
            <span v-else class="sidebar__flyout">{{ item.label }}</span>
          </router-link>
        </section>
      </nav>

      <div class="sidebar__footer" ref="userMenuRef">
        <div
          v-show="userMenuOpen"
          class="sidebar__user-menu"
          role="menu"
          :aria-hidden="!userMenuOpen"
          @click.stop
          @mousedown.stop
        >
          <div class="sidebar__user-menu-header">
            <span class="sidebar__user-menu-name">{{ userLabel }}</span>
            <span v-if="auth.user?.email" class="sidebar__user-menu-email">{{ auth.user.email }}</span>
            <span v-if="roleLabel" class="sidebar__user-menu-role">Vai trò: {{ roleLabel }}</span>
          </div>
          <ViewAsSwitcher />
          <button
            type="button"
            class="sidebar__user-menu-logout"
            role="menuitem"
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
          aria-haspopup="menu"
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
  position: relative;
  z-index: 20;
  flex-shrink: 0;
}

.sidebar-overlay {
  display: none;
}

.sidebar {
  position: relative;
  width: var(--spacing-sidebar-expanded);
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: visible;
  transition: width 0.2s ease;
}

.sidebar--collapsed {
  width: var(--spacing-sidebar-rail);
}

/* ---------- Header / brand ---------- */
.sidebar__header {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  height: 4.5rem;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
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

.sidebar--collapsed .sidebar__brand {
  justify-content: center;
}

.sidebar--collapsed .sidebar__header {
  padding: var(--space-3) var(--space-2);
}

.sidebar__brand-mark {
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  object-fit: contain;
  filter: brightness(1.04);
}

.sidebar__brand-logo {
  display: block;
  width: auto;
  max-width: 100%;
  height: 2.125rem;
  object-fit: contain;
  filter: brightness(1.04);
}

.sidebar__collapse-btn {
  position: absolute;
  top: 1.375rem;
  right: 0;
  z-index: 6;
  display: none;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-md);
  cursor: pointer;
  transform: translateX(50%);
}

.sidebar__close-btn {
  flex-shrink: 0;
  display: none;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-sidebar-text-muted);
  cursor: pointer;
}

.sidebar__collapse-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary-800);
}

.sidebar__close-btn:hover {
  color: var(--color-on-primary);
  background: var(--color-sidebar-hover);
}

/* ---------- Menu ---------- */
.sidebar__nav {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4) var(--space-3);
}

.sidebar--collapsed .sidebar__nav {
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-2);
  overflow: visible;
}

.sidebar__section {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
}

.sidebar__section-label {
  margin: 0 0 var(--space-2);
  padding: 0 var(--space-3);
  color: var(--color-sidebar-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.sidebar__link {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-height: 2.5rem;
  padding: 0.375rem 0.625rem;
  border-radius: var(--radius-md);
  color: var(--color-sidebar-text);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: visible;
}

.sidebar--collapsed .sidebar__link {
  justify-content: center;
  width: 2.5rem;
  padding: 0.375rem;
}

.sidebar__link-icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-sidebar-well);
  color: var(--color-sidebar-text-muted);
}

.sidebar__link-text {
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__link:hover {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
}

.sidebar__link:hover .sidebar__link-icon {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
}

.sidebar__link--active {
  background: var(--color-sidebar-active);
  color: var(--color-on-primary);
  font-weight: 600;
}

.sidebar--collapsed .sidebar__link--active {
  background: var(--color-sidebar-active);
}

.sidebar__link--active .sidebar__link-icon {
  background: var(--color-sidebar-well-strong);
  color: var(--color-on-primary);
}

.sidebar__flyout {
  position: absolute;
  left: calc(100% + var(--space-2));
  top: 50%;
  transform: translateY(-50%);
  z-index: 20;
  display: block;
  min-width: 8.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-sidebar-flyout);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  box-shadow: var(--shadow-md);
  opacity: 0;
  pointer-events: none;
  white-space: nowrap;
}

.sidebar__link:hover .sidebar__flyout,
.sidebar__link:focus-visible .sidebar__flyout {
  opacity: 1;
}

/* ---------- Footer / user ---------- */
.sidebar__footer {
  position: relative;
  flex-shrink: 0;
  padding: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-sidebar-divider);
}

.sidebar__user {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1);
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  overflow: hidden;
  cursor: pointer;
  font-family: var(--font-family-base);
  text-align: left;
}

.sidebar--collapsed .sidebar__user {
  justify-content: center;
  padding: var(--space-1) 0;
}

.sidebar__user:hover,
.sidebar__user--open {
  background: var(--color-sidebar-hover);
}

.sidebar__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.125rem;
  height: 2.125rem;
  border-radius: var(--radius-full);
  background: var(--color-sidebar-well-strong);
  box-shadow: inset 0 0 0 1px var(--color-sidebar-ring);
  color: var(--color-on-primary);
  font-weight: 600;
  font-size: 0.8125rem;
  overflow: hidden;
}

.sidebar__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sidebar__user-info {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.sidebar__user-name {
  color: var(--color-on-primary);
  font-weight: 600;
  font-size: 0.8125rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__user-role {
  color: var(--color-sidebar-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__user-caret {
  flex-shrink: 0;
  color: var(--color-sidebar-text-muted);
}

.sidebar__user-menu {
  position: absolute;
  bottom: calc(100% + var(--space-2));
  left: var(--space-3);
  right: var(--space-3);
  display: flex;
  flex-direction: column;
  max-height: min(28rem, calc(100vh - 8rem));
  overflow-x: hidden;
  overflow-y: auto;
  border-radius: var(--radius-md);
  background: var(--color-sidebar);
  box-shadow: var(--shadow-lg);
}

.sidebar__user-menu-header {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
}

.sidebar__user-menu-name {
  color: var(--color-on-primary);
  font-weight: 700;
  font-size: 0.875rem;
  overflow-wrap: break-word;
}

.sidebar__user-menu-email,
.sidebar__user-menu-role {
  color: var(--color-sidebar-text-muted);
  font-size: 0.75rem;
  overflow-wrap: break-word;
}

.sidebar__user-menu-logout {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-sidebar-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.sidebar__user-menu-logout:hover {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
}

.sidebar--collapsed .sidebar__user-menu {
  left: calc(100% + var(--space-2));
  right: auto;
  bottom: var(--space-3);
  width: 16rem;
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
    border: none;
    padding: 0;
    background: var(--color-sidebar-overlay);
    backdrop-filter: blur(2px);
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
    cursor: pointer;
  }

  .sidebar {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: min(86vw, var(--spacing-sidebar-drawer));
    transform: translateX(-100%);
    transition: transform 0.2s ease;
    box-shadow: var(--shadow-lg);
  }

  .sidebar--collapsed {
    width: min(86vw, var(--spacing-sidebar-drawer));
  }

  .sidebar-wrap--open .sidebar {
    animation: drawer-in 260ms cubic-bezier(0.22, 1, 0.36, 1);
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

  .sidebar__flyout {
    display: none;
  }
}

@media (min-width: 1280px) {
  .sidebar__collapse-btn {
    display: flex;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sidebar,
  .sidebar-overlay,
  .sidebar-nav-item,
  .sidebar-wrap--open .sidebar {
    transition: none;
    animation: none;
  }
}
</style>
