<script setup>
//
// Sidebar điều hướng chính sau khi đăng nhập (layout dạng admin: logo trên
// cùng, menu theo nhóm). Tài khoản nằm trên header.
//
// - Desktop (>=1280px): sidebar cố định, thu gọn được (chỉ icon + flyout
//   nhãn) qua nút menu trên header, trạng thái lưu vào localStorage.
// - Tablet/mobile (<1280px): sidebar ẩn mặc định, mở dạng off-canvas qua
//   prop `open` (điều khiển từ AppLayout) + lớp phủ để đóng khi bấm ra ngoài.
//
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AppIcon from './AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

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

function handleKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    emit('close');
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
});
onBeforeUnmount(() => {
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
      { name: 'superadmin.permissions', label: 'Phân quyền', icon: 'settings', requiresSuperAdmin: true },
      { name: 'superadmin.activity', label: 'Nhật ký hoạt động', icon: 'clock', requiresAdmin: true },
    ],
  },
  {
    id: 'manager',
    label: 'Quản lý',
    items: [
      { name: 'manager.teams', label: 'Quản lý nhóm', icon: 'users' },
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
        (!item.requiresSuperAdmin || auth.showSuperAdminNav) &&
        (!item.requiresAdmin || auth.canViewActivityLog),
    ),
  })).filter((section) => section.items.length > 0),
);

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
    </aside>
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
