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

// Nhóm theo mạch "kể chuyện" khi dùng workspace: bắt đầu từ tổng quan → theo
// dõi tin tức → đánh giá → điều hành công việc → quản lý/kiểm soát → quản trị
// hệ thống → cấu hình. Cùng 1 danh sách section dùng chung cho mọi role, mỗi
// mục tự ẩn/hiện theo quyền (itemPasses) nên super-admin và trưởng phòng thấy
// bố cục khác nhau tự nhiên mà không cần nhánh riêng theo role.
const MENU_SECTIONS = [
  {
    id: 'general',
    label: 'Tổng quan',
    items: [
      // dashboard.view gán cho hầu hết mọi role kể cả viewer — điểm chạm
      // đầu tiên sau đăng nhập, luôn hiện đầu mục Tổng quan.
      { name: 'dashboard.me', label: 'Của tôi', icon: 'target', requiresPermission: 'dashboard.view' },
      // configurableByDepartment: true — đồng bộ thủ công với
      // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
      { name: 'home', label: 'Quy trình', icon: 'gitBranch', configurableByDepartment: true },
      { name: 'dashboard.company', label: 'Tổng công ty', icon: 'dashboard', requiresPermission: 'dashboard.view_company' },
      {
        name: 'dashboard.department',
        label: 'Phòng ban',
        icon: 'activity',
        requiresAnyPermission: ['performance.view_department', 'project.view'],
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        configurableByDepartment: true,
      },
    ],
  },
  {
    id: 'news',
    label: 'Thông tin',
    items: [
      { name: 'social.feed', label: 'Bảng tin', icon: 'megaphone', configurableByDepartment: true },
    ],
  },
  {
    id: 'evaluation',
    label: 'Đánh giá',
    items: [
      {
        name: 'manager.evaluation.view',
        label: 'Tiêu chí',
        icon: 'clipboardCheck',
        configurableByDepartment: true,
      },
      {
        // Khung chấm điểm — mục sidebar RIÊNG của phòng ban, khác tab
        // "Tiêu chí đánh giá" trong hub Cấu hình.
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        name: 'manager.evaluation-score-kit.index',
        label: 'Khung điểm',
        icon: 'layers',
        requiresPermission: 'evaluation.manage_department',
        hideWhenSuperAdmin: true,
        configurableByDepartment: true,
      },
    ],
  },
  {
    id: 'operations',
    label: 'Điều hành',
    items: [
      {
        // Dự án (Project module — giai đoạn 1: CRUD) — mục sidebar riêng,
        // cùng nhóm "Điều hành" với Công việc/Báo cáo. Ai xem được danh sách
        // dự án (project.view) đều thấy mục này — department_director trở
        // lên có thêm quyền tạo/sửa/xoá (project.create/update_department).
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
        // Báo cáo — trưởng phòng tạo và cấu hình; người được chia sẻ chỉ xem
        // (report.view_assigned), lọc thật ở backend theo report_viewers.
        // configurableByDepartment: true — đồng bộ thủ công với
        // CONFIGURABLE_MENUS trong DepartmentSidebarConfigService.
        name: 'manager.reports.index',
        label: 'Báo cáo',
        icon: 'barChart',
        requiresAnyPermission: ['report.manage_department', 'report.view_assigned'],
        configurableByDepartment: true,
      },
    ],
  },
  {
    // Vận hành phòng ban — trưởng phòng dùng hằng ngày. "Tài khoản"
    // (Credential) nằm ở section "control" (Kiểm soát) theo mặc định, nhưng
    // trưởng phòng vẫn tự đổi nhóm/ẩn-hiện được qua configurableByDepartment
    // — xem giải thích ở mục manager.credential.index bên dưới.
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
    ],
  },
  {
    // Kiểm soát toàn hệ thống — chủ yếu super_admin/admin. Trưởng phòng
    // (không có 2 mục đầu) vẫn thấy nhóm này nhưng chỉ còn "Tài khoản".
    id: 'control',
    label: 'Kiểm soát',
    items: [
      {
        // Duyệt bài viết (toàn trường) — khác "social.moderate" (xoá bài
        // vi phạm theo phòng ban). Hiện với bất kỳ ai có social.review:
        // mặc định admin/super_admin (social.* / *), hoặc được cấp thêm
        // qua ma trận phân quyền. KHÔNG configurableByDepartment.
        name: 'manager.social.moderation',
        label: 'Duyệt bài',
        icon: 'listChecks',
        requiresPermission: 'social.review',
      },
      { name: 'superadmin.activity', label: 'Nhật ký', icon: 'clock', requiresAdmin: true },
      {
        name: 'superadmin.feature-requests.index',
        label: 'Ghi nhận yêu cầu tính năng',
        icon: 'alertTriangle',
        requiresPermission: 'feature_request.review',
      },
      {
        // Trang đầy đủ cho MỌI nhân viên tự xem lịch sử ghi nhận yêu cầu
        // tính năng của chính mình — khác mục review ở trên (chỉ hiện với
        // người có quyền feature_request.review). Không cần permission gì
        // vì route chỉ requiresAuth (xem router.js module FeatureRequest).
        name: 'feature-requests.mine',
        label: 'Ghi nhận của tôi',
        icon: 'alertTriangle',
      },
      {
        // Quản lý tài khoản (Credential module) — chứa thông tin đăng nhập
        // dịch vụ dùng chung công ty (Google, Canva, Cursor, Claude, AWS,
        // VPS, database, IAM, domain...), không phải dữ liệu riêng của 1
        // phòng ban. Service tự ẩn password/username thật với ai không phải
        // người tạo/được cấp quyền xem — xem CredentialService::present().
        // Mặc định gán vào "Kiểm soát" (không phải "Quản lý") vì bản chất là
        // dữ liệu dùng chung công ty cần giám sát — nhưng vẫn
        // configurableByDepartment: true để trưởng phòng tự đổi nhóm/tên/
        // ẩn-hiện được như các mục khác. configurableByDepartment: true —
        // đồng bộ thủ công với CONFIGURABLE_MENUS trong
        // DepartmentSidebarConfigService.
        name: 'manager.credential.index',
        label: 'Tài khoản',
        icon: 'lock',
        requiresPermission: 'credential.view',
        configurableByDepartment: true,
      },
    ],
  },
  {
    id: 'admin',
    label: 'Quản trị',
    items: [
      { name: 'superadmin.permissions', label: 'Phân quyền', icon: 'settings', requiresSuperAdmin: true },
    ],
  },
  {
    id: 'superadmin-workspace-config',
    label: 'Cấu hình',
    items: [
      {
        name: 'superadmin.workspace-config.overview',
        label: 'Workspace',
        icon: 'settings',
        requiresSuperAdmin: true,
      },
      {
        // Ẩn/hiện menu sidebar TOÀN HỆ THỐNG — áp dụng cho MỌI tài khoản kể
        // cả super_admin, thắng tuyệt đối per-department override. Chính
        // mục này KHÔNG tự ẩn được: GlobalMenuVisibilityService::PROTECTED_MENU_KEYS
        // chặn ở backend, đảm bảo super_admin luôn vào lại được trang này.
        name: 'superadmin.workspace-config.global-menu',
        label: 'Menu hệ thống',
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
  if (route.name === routeName || route.matched.some((r) => r.name === routeName)) {
    return true;
  }
  if (routeName === 'manager.reports.index') {
    return route.path === '/manager/reports' || route.path.startsWith('/manager/reports/');
  }
  if (routeName === 'manager.project.tasks') {
    return route.path === '/manager/project/tasks' || route.path.startsWith('/manager/project/tasks/');
  }
  return false;
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

      <p v-if="!collapsed" class="sidebar__tagline">
        <AppIcon name="sparkles" :size="13" />
        Không gian làm việc
      </p>

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

      <div v-if="!collapsed" class="sidebar__footer">
        <router-link :to="{ name: 'home' }" class="sidebar__promo" @click="closeDrawer">
          <span class="sidebar__promo-icon">
            <AppIcon name="gitBranch" :size="18" />
          </span>
          <span class="sidebar__promo-copy">
            <strong>Xem quy trình làm việc</strong>
            <span>Bắt đầu từ đây nếu chưa rõ nên làm gì</span>
          </span>
        </router-link>
      </div>
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
  transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.sidebar__brand-logo {
  display: block;
  width: auto;
  max-width: 100%;
  height: clamp(2.5rem, 3.4vw, 3rem);
  object-fit: contain;
  filter: brightness(1.04) drop-shadow(var(--shadow-md));
  transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.sidebar__brand:hover .sidebar__brand-logo,
.sidebar__brand:hover .sidebar__brand-mark {
  transform: scale(1.05);
}

/* ---------- Tagline định vị dưới logo ---------- */
.sidebar__tagline {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  margin: 0;
  padding: 0 var(--space-4) var(--space-3);
  color: var(--color-sidebar-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  text-align: center;
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
  animation: sidebar-fade-up 420ms ease-out both;
}

.sidebar__tagline svg {
  flex-shrink: 0;
  color: var(--color-gold);
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
  animation: sidebar-fade-up 380ms ease-out both;
}

/* Vào trang: các mục hiện lên so le theo thứ tự section, không cần đánh số
   thủ công trong template — chọn theo nth-of-type của .sidebar__section. */
.sidebar__section:nth-of-type(1) { animation-delay: 40ms; }
.sidebar__section:nth-of-type(2) { animation-delay: 90ms; }
.sidebar__section:nth-of-type(3) { animation-delay: 140ms; }
.sidebar__section:nth-of-type(4) { animation-delay: 190ms; }
.sidebar__section:nth-of-type(n) .sidebar__link:nth-child(2) { animation-delay: 60ms; }
.sidebar__section:nth-of-type(n) .sidebar__link:nth-child(3) { animation-delay: 110ms; }

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
  animation: sidebar-fade-up 420ms ease-out both;
  transition:
    background-color 200ms ease,
    color 200ms ease,
    transform 200ms cubic-bezier(0.22, 1, 0.36, 1);
}

/* Vạch sáng bên trái - chỉ "nở" ra khi mục đang mở, thay cho việc chỉ đổi
   màu nền (dễ nhận ra hơn ở khoé mắt). */
.sidebar__link::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  width: 3px;
  height: 0;
  border-radius: 0 var(--radius-full) var(--radius-full) 0;
  background: var(--color-gold);
  transform: translateY(-50%);
  transition: height 240ms cubic-bezier(0.22, 1, 0.36, 1);
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
  transition:
    background-color 200ms ease,
    color 200ms ease,
    transform 260ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.sidebar__link-text {
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__link:hover {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
  transform: translateX(3px);
}

.sidebar__link:hover .sidebar__link-icon {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
  transform: scale(1.08) rotate(-4deg);
}

.sidebar--collapsed .sidebar__link:hover {
  transform: none;
}

.sidebar__link--active {
  background: var(--color-sidebar-active);
  color: var(--color-on-primary);
  font-weight: 600;
}

.sidebar--collapsed .sidebar__link--active {
  background: var(--color-sidebar-active);
}

.sidebar__link--active::before {
  height: 60%;
}

.sidebar__link--active .sidebar__link-icon {
  background: var(--color-gold);
  color: var(--color-umber-700);
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

/* ---------- Footer: thẻ dẫn sang trang quy trình ---------- */
.sidebar__footer {
  flex-shrink: 0;
  padding: var(--space-3);
}

.sidebar__promo {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-sidebar-well);
  color: var(--color-on-primary);
  text-decoration: none;
  overflow: hidden;
  transition:
    background-color 200ms ease,
    transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.sidebar__promo:hover {
  background: var(--color-sidebar-well-strong);
  transform: translateY(-2px);
}

.sidebar__promo:focus-visible {
  outline: 2px solid var(--color-sidebar-focus);
  outline-offset: 2px;
}

.sidebar__promo-icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
  background: var(--color-sidebar-well-strong);
  color: var(--color-gold);
}

.sidebar__promo-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.sidebar__promo-copy strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
}

.sidebar__promo-copy > span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-sidebar-text-muted);
  font-size: 0.6875rem;
  line-height: 1.3;
}

/* ---------- Keyframes ---------- */
@keyframes sidebar-fade-up {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
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
  .sidebar-wrap--open .sidebar,
  .sidebar__tagline,
  .sidebar__section-label,
  .sidebar__link,
  .sidebar__link-icon,
  .sidebar__link::before,
  .sidebar__brand-logo,
  .sidebar__brand-mark,
  .sidebar__promo,
  .sidebar__flyout {
    transition: none;
    animation: none;
  }
}
</style>
