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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import AppIcon from './AppIcon.vue';
import SidebarLogo from './sidebar/SidebarLogo.vue';
import SidebarNavSection from './sidebar/SidebarNavSection.vue';
import { loadExpandedSections, persistExpandedSections } from '../composables/useSidebarSectionExpand.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

// Số bài chờ duyệt — hiện cạnh mục "Duyệt bài" (manager.social.moderation).
// Poll cùng nhịp với chuông thông báo (HeaderNotifications) để không lệch
// quá lâu so với thực tế khi có người khác vừa duyệt/đăng bài.
const MODERATION_POLL_MS = 25000;
const pendingModerationCount = ref(0);
let moderationPollTimer = null;

async function loadPendingModerationCount() {
  if (!auth.can('social.review')) {
    pendingModerationCount.value = 0;
    return;
  }
  try {
    const { data } = await window.axios.get('/api/social/moderation/pending-count');
    pendingModerationCount.value = data.count ?? 0;
  } catch {
    // im lặng — sidebar không được vỡ vì lỗi đếm badge
  }
}

function startModerationPoll() {
  stopModerationPoll();
  moderationPollTimer = window.setInterval(loadBadgeCounts, MODERATION_POLL_MS);
}

function stopModerationPoll() {
  if (moderationPollTimer) {
    window.clearInterval(moderationPollTimer);
    moderationPollTimer = null;
  }
}

// Số bảng chấm công HRM chờ Trưởng đơn vị duyệt — mục "Duyệt bảng công" chỉ hiện khi có
// bảng chờ (hoặc đang đứng ở trang đó); backend cache 60s nên poll chung nhịp không tốn HRM.
const pendingTimesheetCount = ref(0);

async function loadPendingTimesheetCount() {
  try {
    const { data } = await window.axios.get('/api/attendance/timesheet-approvals/count');
    pendingTimesheetCount.value = data.count ?? 0;
  } catch {
    // im lặng — sidebar không được vỡ vì lỗi đếm badge
  }
}

function loadBadgeCounts() {
  loadPendingModerationCount();
  loadPendingTimesheetCount();
}

const badgeCounts = computed(() => ({
  'manager.social.moderation': pendingModerationCount.value,
  'attendance.timesheet-approvals': pendingTimesheetCount.value,
}));

function badgeLabel(item) {
  const count = badgeCounts.value[item.name] || 0;
  if (count <= 0) return '';
  return count > 99 ? '99+' : String(count);
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

function handleKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    emit('close');
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown);
  loadBadgeCounts();
  startModerationPoll();
  window.addEventListener('focus', loadBadgeCounts);
});
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
  stopModerationPoll();
  window.removeEventListener('focus', loadBadgeCounts);
});

// Nhóm theo mạch "kể chuyện" khi dùng workspace: bắt đầu từ tổng quan → theo
// dõi tin tức → đánh giá → điều hành công việc → quản lý/kiểm soát → quản trị
// hệ thống → cấu hình. Cùng 1 danh sách section dùng chung cho mọi role, mỗi
// mục tự ẩn/hiện theo quyền (itemPasses) nên super-admin và trưởng phòng thấy
// bố cục khác nhau tự nhiên mà không cần nhánh riêng theo role.
/** Icon tiêu đề nhóm — đồng bộ phong cách accordion va-hrm */
const SECTION_ICONS = {
  general: 'target',
  news: 'megaphone',
  evaluation: 'clipboardCheck',
  operations: 'layoutList',
  manager: 'settings',
  control: 'shield',
  admin: 'settings',
  'superadmin-workspace-config': 'settings',
};

const DEFAULT_SECTION_OPEN = {
  general: true,
};

const MENU_SECTIONS = [
  {
    id: 'general',
    label: 'Tổng quan',
    items: [
      // dashboard.view gán cho hầu hết mọi role kể cả viewer — điểm chạm
      // đầu tiên sau đăng nhập, luôn hiện đầu mục Tổng quan.
      { name: 'dashboard.me', label: 'Của tôi', icon: 'target', requiresPermission: 'dashboard.view' },
      // Trưởng đơn vị duyệt bảng chấm công HRM — chỉ hiện khi có bảng chờ duyệt
      // (requiresTimesheetApprovals), vào lại qua thông báo / link email bất cứ lúc nào.
      {
        name: 'attendance.timesheet-approvals',
        label: 'Duyệt bảng công',
        icon: 'clipboardCheck',
        requiresTimesheetApprovals: true,
      },
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
      {
        // Nhà cung cấp & Hợp đồng — MVP1 mở trước phần Nhà cung cấp
        // (danh sách, hồ sơ, liên hệ, tài khoản ngân hàng). Scope thật
        // chặn ở API Contract: trưởng/phó phòng theo phòng ban, cấp giám
        // đốc trở lên xem toàn bộ.
        name: 'manager.contract.suppliers.index',
        label: 'Nhà cung cấp',
        icon: 'building',
        requiresAnyPermission: ['contract.view', 'contract.manage_department'],
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
      {
        // Gán phòng ban cho tài khoản mới đăng nhập Google — bước chặn
        // trước khi trưởng phòng thấy được nút "Gán vai trò" (chưa có
        // phòng ban thì WorkspaceConfigMemberController::departmentIdOrFail()
        // trả lỗi và Members.vue ẩn hẳn nút "Thêm").
        name: 'superadmin.workspace-config.unassigned',
        label: 'Nhân sự workspace',
        icon: 'users',
        requiresSuperAdmin: true,
      },
      {
        name: 'superadmin.workspace-config.quick-utilities',
        label: 'App PWA nhân viên',
        icon: 'layoutGrid',
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
    (!item.requiresTimesheetApprovals || pendingTimesheetCount.value > 0 || route.name === item.name) &&
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
  if (routeName === 'manager.contract.suppliers.index') {
    return route.path === '/manager/contract/suppliers' || route.path.startsWith('/manager/contract/suppliers/');
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

function sectionIcon(sectionId) {
  return SECTION_ICONS[sectionId] || 'layoutGrid';
}

const expanded = ref({});
let expandedInitialized = false;

const activeSectionIds = computed(() =>
  visibleSections.value
    .filter((section) => section.items.some((item) => isActive(item.name)))
    .map((section) => section.id),
);

function ensureExpandedState() {
  const sections = visibleSections.value;
  if (!sections.length) {
    return;
  }
  const defaults = {};
  for (const section of sections) {
    defaults[section.id] = Boolean(DEFAULT_SECTION_OPEN[section.id]);
  }
  expanded.value = loadExpandedSections(
    sections.map((s) => s.id),
    defaults,
    activeSectionIds.value,
  );
  expandedInitialized = true;
}

watch(
  visibleSections,
  (sections) => {
    if (!expandedInitialized && sections.length > 0) {
      ensureExpandedState();
    }
  },
  { immediate: true },
);

watch(activeSectionIds, (ids) => {
  if (!ids.length) {
    return;
  }
  let changed = false;
  const next = { ...expanded.value };
  for (const id of ids) {
    if (!next[id]) {
      next[id] = true;
      changed = true;
    }
  }
  if (changed) {
    expanded.value = next;
    persistExpandedSections(next);
  }
});

function toggleSection(id) {
  expanded.value = {
    ...expanded.value,
    [id]: !expanded.value[id],
  };
  persistExpandedSections(expanded.value);
}

const anyExpanded = computed(() =>
  visibleSections.value.some(
    (section) => expanded.value[section.id] ?? Boolean(DEFAULT_SECTION_OPEN[section.id]),
  ),
);

function setAllSections(open) {
  const next = { ...expanded.value };
  for (const section of visibleSections.value) {
    next[section.id] = open;
  }
  expanded.value = next;
  persistExpandedSections(next);
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
      <div class="sidebar__body">
        <SidebarLogo
          :collapsed="collapsed"
          show-close
          @close="closeDrawer"
        />

        <div
          v-if="!collapsed && visibleSections.length > 1"
          class="sidebar-nav-tools"
        >
          <span class="sidebar-nav-tools__label">Menu chức năng</span>
          <button
            type="button"
            class="sidebar-nav-tools__btn"
            data-tour="sidebar-toggle-all"
            @click="setAllSections(!anyExpanded)"
          >
            <AppIcon :name="anyExpanded ? 'chevronsUp' : 'chevronsDown'" :size="14" />
            <span>{{ anyExpanded ? 'Thu gọn tất cả' : 'Mở tất cả' }}</span>
          </button>
        </div>

        <nav
          class="sidebar-scroll sidebar__nav sidebar__nav--flow"
          aria-label="Điều hướng chính"
        >
          <div class="sidebar-flow" role="presentation">
            <SidebarNavSection
              v-for="section in visibleSections"
              :key="section.id"
              :section-id="section.id"
              :label="section.label"
              :section-icon="sectionIcon(section.id)"
              :items="section.items"
              :expanded="expanded[section.id] ?? Boolean(DEFAULT_SECTION_OPEN[section.id])"
              :collapsed="collapsed"
              :is-active="isActive"
              :item-label="itemLabel"
              :badge-label="badgeLabel"
              @toggle="toggleSection(section.id)"
              @navigate="closeDrawer"
            />
          </div>
        </nav>
      </div>
    </aside>
  </div>
</template>
