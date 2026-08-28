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
      // configurableByDepartment: true — đồng bộ thủ công với
      // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
      { name: 'home', label: 'Tổng quan', icon: 'dashboard', configurableByDepartment: true },
      { name: 'social.feed', label: 'Bảng tin nội bộ', icon: 'megaphone', configurableByDepartment: true },
      {
        name: 'manager.evaluation.view',
        label: 'Tiêu chí đánh giá',
        icon: 'clipboardCheck',
        configurableByDepartment: true,
      },
    ],
  },
  {
    id: 'admin',
    label: 'Quản trị',
    items: [
      { name: 'superadmin.permissions', label: 'Phân quyền', icon: 'settings', requiresSuperAdmin: true },
      { name: 'superadmin.activity', label: 'Nhật ký hoạt động', icon: 'clock', requiresAdmin: true },
    ],
  },
  {
    id: 'manager',
    label: 'Quản lý',
    items: [
      {
        name: 'manager.workspace-config.hub',
        label: 'Cấu hình phòng ban',
        icon: 'settings',
        requiresPermission: 'workspace_config.view_department',
        // super_admin (*) cũng có permission này nhưng dùng trang tổng hợp
        // /superadmin/workspace-config — API members lấy department_id của
        // chính user, super_admin thường không có phòng ban.
        hideWhenSuperAdmin: true,
        // KHÔNG configurableByDepartment — tránh trưởng phòng tự khoá lối
        // vào trang cấu hình của chính mình. Tab con của hub (Thành viên,
        // Menu, Tiêu chí) cũng không phải mục sidebar.
      },
      {
        // Mẫu đánh giá (Evaluation Giai đoạn C) — mục sidebar RIÊNG, khác
        // "Tiêu chí đánh giá" (Giai đoạn B, vẫn là tab trong Hub).
        // department_director/deputy trở lên (evaluation.manage_department)
        // và superadmin (tạo mẫu dùng chung toàn hệ thống).
        // Xem plans/2026-08-26-mau-danh-gia.md §2.3.
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        name: 'manager.evaluation-templates.index',
        label: 'Mẫu đánh giá',
        icon: 'clipboardCheck',
        requiresPermission: 'evaluation.manage_department',
        configurableByDepartment: true,
      },
      {
        // Dự án (Project module — giai đoạn 1: CRUD) — mục sidebar riêng,
        // cùng nhóm "Quản lý" với "Mẫu đánh giá". Ai xem được danh sách dự
        // án (project.view) đều thấy mục này — department_director trở lên
        // có thêm quyền tạo/sửa/xoá (project.create/update_department).
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        name: 'manager.project.index',
        label: 'Dự án',
        icon: 'layers',
        requiresPermission: 'project.view',
        configurableByDepartment: true,
      },
      {
        // Công việc (Project Giai đoạn 2 — Task thật) — trang "Tất cả công
        // việc" xuyên project, cạnh mục "Dự án". requiresAnyPermission vì
        // role member chỉ có task.view_assigned (không có task.view) —
        // TaskService::paginate() tự ép lọc theo assignee_id = chính mình
        // khi viewer không có task.view/task.*.
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        name: 'manager.project.tasks',
        label: 'Công việc',
        icon: 'layoutList',
        requiresAnyPermission: ['task.view', 'task.view_assigned'],
        configurableByDepartment: true,
      },
      {
        // Duyệt bài viết (toàn trường) — khác "social.moderate" (xoá bài
        // vi phạm theo phòng ban). Hiện với bất kỳ ai có social.review:
        // mặc định admin/super_admin (social.* / *), hoặc được cấp thêm
        // qua ma trận phân quyền. KHÔNG configurableByDepartment.
        name: 'manager.social.moderation',
        label: 'Duyệt bài viết',
        icon: 'listChecks',
        requiresPermission: 'social.review',
      },
    ],
  },
  {
    id: 'superadmin-workspace-config',
    label: 'Cấu hình Workspace',
    items: [
      {
        name: 'superadmin.workspace-config.overview',
        label: 'Cấu hình Workspace theo phòng ban',
        icon: 'settings',
        requiresSuperAdmin: true,
      },
      {
        // Ẩn/hiện menu sidebar TOÀN HỆ THỐNG — áp dụng cho MỌI tài khoản kể
        // cả super_admin, thắng tuyệt đối per-department override. Chính
        // mục này KHÔNG tự ẩn được: GlobalMenuVisibilityService::PROTECTED_MENU_KEYS
        // chặn ở backend, đảm bảo super_admin luôn vào lại được trang này.
        name: 'superadmin.workspace-config.global-menu',
        label: 'Ẩn/hiện menu toàn hệ thống',
        icon: 'eyeOff',
        requiresSuperAdmin: true,
      },
    ],
  },
];

const registeredRouteNames = computed(() => new Set(router.getRoutes().map((r) => r.name)));

function itemPasses(item) {
  return (
    registeredRouteNames.value.has(item.name) &&
    (!item.requiresSuperAdmin || auth.showSuperAdminNav) &&
    (!item.hideWhenSuperAdmin || !auth.showSuperAdminNav) &&
    (!item.requiresAdmin || auth.canViewActivityLog) &&
    (!item.requiresPermission || auth.can(item.requiresPermission)) &&
    // requiresAnyPermission: OR nhiều key — dùng khi 1 mục cần hiện với
    // nhiều permission khác nhau tuỳ vai trò (VD: "Công việc" hiện với
    // task.view HOẶC task.view_assigned — xem TaskService::paginate()).
    (!item.requiresAnyPermission || item.requiresAnyPermission.some((key) => auth.can(key))) &&
    !auth.hiddenMenuKeys.includes(item.name) &&
    // Ẩn toàn hệ thống (superadmin) thắng tuyệt đối per-department, áp dụng
    // cho MỌI tài khoản kể cả super_admin (không có ngoại lệ) — trang
    // "Ẩn/hiện menu toàn hệ thống" tự bảo vệ (PROTECTED_MENU_KEYS) nên
    // super_admin luôn còn đường vào lại để bật menu đã ẩn.
    !auth.globallyHiddenMenuKeys.includes(item.name)
  );
}

const visibleSections = computed(() => {
  const itemSections = auth.menuItemSections;           // per-department
  const menuOrder = auth.menuOrder;                      // per-department
  const sectionLabels = auth.menuSectionLabels;          // per-department
  const globalItemSections = auth.globalMenuItemSections; // global
  const globalMenuOrder = auth.globalMenuOrder;           // global
  const globalSectionLabels = auth.globalMenuSectionLabels; // global

  // Nhãn section: department > global > mặc định
  const sectionMap = new Map(
    MENU_SECTIONS.map((section) => {
      const deptLabel = typeof sectionLabels[section.id] === 'string' ? sectionLabels[section.id].trim() : '';
      const globalLabel = typeof globalSectionLabels[section.id] === 'string' ? globalSectionLabels[section.id].trim() : '';
      return [
        section.id,
        {
          ...section,
          label: deptLabel || globalLabel || section.label,
          items: [],
        },
      ];
    }),
  );

  MENU_SECTIONS.forEach((section, sectionIndex) => {
    section.items.forEach((item, itemIndex) => {
      const originalIndex = sectionIndex * 100 + itemIndex;

      // Nhóm section: department (chỉ configurable) > global > mặc định
      let targetId = section.id;
      if (item.configurableByDepartment) {
        const deptSection = itemSections[item.name];
        if (deptSection && sectionMap.has(deptSection)) {
          targetId = deptSection;
        } else {
          const globalSection = globalItemSections[item.name];
          if (globalSection && sectionMap.has(globalSection)) targetId = globalSection;
        }
      } else {
        const globalSection = globalItemSections[item.name];
        if (globalSection && sectionMap.has(globalSection)) targetId = globalSection;
      }

      sectionMap.get(targetId).items.push({ ...item, originalIndex });
    });
  });

  return [...sectionMap.values()]
    .map((section) => ({
      ...section,
      items: section.items
        .slice()
        .sort((a, b) => {
          // Thứ tự: department (chỉ configurable) > global > mặc định
          const getOrder = (item) => {
            if (item.configurableByDepartment && Object.hasOwn(menuOrder, item.name)) {
              const deptOrder = Number(menuOrder[item.name]);
              if (Number.isFinite(deptOrder)) return deptOrder;
            }
            if (Object.hasOwn(globalMenuOrder, item.name)) {
              const globalOrder = Number(globalMenuOrder[item.name]);
              if (Number.isFinite(globalOrder)) return globalOrder;
            }
            return 1000 + item.originalIndex;
          };
          return getOrder(a) - getOrder(b) || a.originalIndex - b.originalIndex;
        })
        .filter(itemPasses),
    }))
    .filter((section) => section.items.length > 0);
});

function isActive(routeName) {
  return route.name === routeName || route.matched.some((r) => r.name === routeName);
}

function itemLabel(item) {
  // Nhãn: department > global > mặc định
  const deptCustom = auth.menuLabels[item.name];
  if (typeof deptCustom === 'string' && deptCustom.trim()) return deptCustom.trim();
  const globalCustom = auth.globalMenuLabels[item.name];
  if (typeof globalCustom === 'string' && globalCustom.trim()) return globalCustom.trim();
  return item.label;
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
            :aria-label="collapsed ? itemLabel(item) : null"
            :aria-current="isActive(item.name) ? 'page' : null"
            @click="closeDrawer"
          >
            <span class="sidebar__link-icon">
              <AppIcon :name="item.icon" :size="18" />
            </span>
            <span v-if="!collapsed" class="sidebar__link-text">{{ itemLabel(item) }}</span>
            <span v-else class="sidebar__flyout">{{ itemLabel(item) }}</span>
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
  width: 2.5rem;
  height: 2.5rem;
  object-fit: contain;
  filter: brightness(1.04);
}

.sidebar__brand-logo {
  display: block;
  width: auto;
  max-width: 100%;
  height: 2.5rem;
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
