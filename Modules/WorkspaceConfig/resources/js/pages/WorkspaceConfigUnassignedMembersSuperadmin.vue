<script setup>
//
// superadmin/workspace-config/unassigned — trang QUẢN LÝ NHÂN SỰ workspace,
// dựng lại theo mẫu trang /employees của va-hrm nhưng bằng token + quy tắc UI
// của dự án này:
//   PageHeader → dải thẻ KPI bấm lọc nhanh → panel danh sách (tiêu đề +
//   thanh công cụ: số dòng/trang, tìm kiếm, menu Bộ lọc, menu Cột) → bảng có
//   lọc/sắp xếp ngay trên tiêu đề từng cột + chip bộ lọc đang áp dụng +
//   chọn nhiều dòng để gán phòng ban hàng loạt → panel chi tiết đẩy ngang.
// Không badge nền màu, không border theo hướng, không title/tooltip.
// Endpoint gán phòng ban trả về bản ghi vừa đổi để patch thẳng vào state.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { showClientToast } from '@/lib/clientToast';
import { isTransientClientNetworkError } from '@/lib/networkError';
import { useDragScroll } from '@/composables/useDragScroll';
import StatusBadge from '../components/StatusBadge.vue';
import MemberKpiStrip from '../components/MemberKpiStrip.vue';
import ColumnHeaderFilter from '../components/ColumnHeaderFilter.vue';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FALLBACK_AVATAR_SRC,
  FALLBACK_AVATAR_SRCSET,
  FILTER_STORAGE_KEY,
  MEMBER_STATUS_OPTIONS,
  SORT_OPTIONS,
  UNASSIGNED_COLUMNS,
  UNASSIGNED_FILTERS,
  ZOOM_STORAGE_KEY,
  departmentName,
  loadVisibility,
  memberRoles,
  memberRolesText,
  memberStatusLabel,
  saveVisibility,
  teamName,
} from '../constants/unassignedMembers.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const AVATAR_EXTRA = 44;
const SELECT_COL_PX = 44;
let measureCtx = null;
let wrapObserver = null;

const allMembers = ref([]);
const departmentOptions = ref([]);
const loading = ref(false);
const selected = ref(null);
const brokenAvatarIds = ref(new Set());

const departmentAssignId = ref('');
const departmentAssignSaving = ref(false);

const selectedIds = ref(new Set());
const bulkDepartmentId = ref('');
const bulkSaving = ref(false);

const query = ref('');
const departmentId = ref('');
const status = ref('');
const roleCode = ref('');
const sortKey = ref('name_asc');
const page = ref(1);
const perPage = ref(20);

const headerFilters = reactive({});
const headerSort = ref(null);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, UNASSIGNED_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, UNASSIGNED_FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);
const selectAllBox = ref(null);
const MIN_COL_PX = 72;

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const shownColumns = computed(() => UNASSIGNED_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1) + 1);

/* ── Thống kê + lọc nhanh ──────────────────────────────────────────────── */

const stats = computed(() => {
  const rows = allMembers.value;
  return {
    total: rows.length,
    assigned: rows.filter((member) => member.department).length,
    unassigned: rows.filter((member) => !member.department).length,
    inactive: rows.filter((member) => member.status !== 'active').length,
    departments: departmentOptions.value.length,
  };
});

const kpiCards = computed(() => [
  {
    key: 'total',
    label: 'Tổng nhân sự',
    value: fmtNumber(stats.value.total),
    tone: 'brand',
    icon: 'users',
    filter: 'all',
  },
  {
    key: 'assigned',
    label: 'Đã có phòng ban',
    value: fmtNumber(stats.value.assigned),
    tone: 'success',
    icon: 'userPlus',
    filter: 'assigned',
  },
  {
    key: 'unassigned',
    label: 'Chưa gán phòng ban',
    value: fmtNumber(stats.value.unassigned),
    tone: 'warning',
    icon: 'userX',
    filter: 'unassigned',
  },
  {
    key: 'inactive',
    label: 'Ngừng hoạt động',
    value: fmtNumber(stats.value.inactive),
    tone: 'info',
    icon: 'pauseCircle',
    filter: 'inactive',
  },
  {
    key: 'departments',
    label: 'Phòng ban',
    value: fmtNumber(stats.value.departments),
    tone: 'teal',
    icon: 'building',
    filter: '',
  },
]);

const activeQuickFilter = computed(() => {
  if (query.value.trim() || roleCode.value) return '';
  if (departmentId.value === 'none' && !status.value) return 'unassigned';
  if (departmentId.value === 'any' && !status.value) return 'assigned';
  if (status.value === 'inactive' && !departmentId.value) return 'inactive';
  if (!departmentId.value && !status.value) return 'all';
  return '';
});

function fmtNumber(value) {
  return Number(value || 0).toLocaleString('vi-VN');
}

function onQuickFilter(filter) {
  if (activeQuickFilter.value === filter) {
    clearFilters();
    return;
  }
  query.value = '';
  roleCode.value = '';
  status.value = filter === 'inactive' ? 'inactive' : '';
  if (filter === 'unassigned') departmentId.value = 'none';
  else if (filter === 'assigned') departmentId.value = 'any';
  else departmentId.value = '';
  page.value = 1;
}

/* ── Options cho bộ lọc ────────────────────────────────────────────────── */

const departmentFilterOptions = computed(() =>
  [...departmentOptions.value]
    .map((item) => ({ value: String(item.id), label: item.name }))
    .sort((a, b) => a.label.localeCompare(b.label, 'vi')),
);

const roleFilterOptions = computed(() => {
  const seen = new Map();
  for (const member of allMembers.value) {
    for (const role of memberRoles(member)) {
      if (role.code && !seen.has(role.code)) seen.set(role.code, role.name);
    }
  }
  return [...seen.entries()]
    .map(([value, label]) => ({ value, label }))
    .sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

/* ── Lọc + sắp xếp ─────────────────────────────────────────────────────── */

function headerFilterText(member, key) {
  if (key === 'person') return `${member.name ?? ''} ${member.email ?? ''}`;
  if (key === 'email') return member.email ?? '';
  if (key === 'department') return departmentName(member);
  if (key === 'team') return teamName(member);
  if (key === 'roles') return memberRolesText(member);
  return '';
}

const activeHeaderFilterEntries = computed(() =>
  Object.entries(headerFilters).filter(([, value]) => Boolean(value && value.trim())),
);

const filteredMembers = computed(() => {
  const q = query.value.trim().toLowerCase();
  const entries = activeHeaderFilterEntries.value;

  const rows = allMembers.value.filter((member) => {
    if (q) {
      const hay = `${member.name ?? ''} ${member.email ?? ''} ${departmentName(member)} ${teamName(member)}`
        .toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (departmentId.value === 'none') {
      if (member.department) return false;
    } else if (departmentId.value === 'any') {
      if (!member.department) return false;
    } else if (departmentId.value && String(member.department?.id) !== departmentId.value) {
      return false;
    }
    if (status.value === 'active' && member.status !== 'active') return false;
    if (status.value === 'inactive' && member.status === 'active') return false;
    if (roleCode.value && !memberRoles(member).some((role) => role.code === roleCode.value)) {
      return false;
    }
    for (const [key, needle] of entries) {
      if (!headerFilterText(member, key).toLowerCase().includes(needle.trim().toLowerCase())) {
        return false;
      }
    }
    return true;
  });

  return sortMembers(rows);
});

function compareBy(a, b, key) {
  if (key === 'person') return (a.name ?? '').localeCompare(b.name ?? '', 'vi');
  if (key === 'email') return (a.email ?? '').localeCompare(b.email ?? '', 'vi');
  if (key === 'department') return departmentName(a).localeCompare(departmentName(b), 'vi');
  if (key === 'team') return teamName(a).localeCompare(teamName(b), 'vi');
  if (key === 'status') return (a.status ?? '').localeCompare(b.status ?? '');
  if (key === 'id') return Number(a.id ?? 0) - Number(b.id ?? 0);
  return 0;
}

function sortMembers(rows) {
  const next = [...rows];

  if (headerSort.value) {
    const { key, dir } = headerSort.value;
    next.sort((a, b) => compareBy(a, b, key) * (dir === 'desc' ? -1 : 1));
    return next;
  }

  if (sortKey.value === 'name_desc') {
    next.sort((a, b) => compareBy(b, a, 'person'));
  } else if (sortKey.value === 'department_asc') {
    next.sort((a, b) => compareBy(a, b, 'department') || compareBy(a, b, 'person'));
  } else if (sortKey.value === 'unassigned_first') {
    next.sort(
      (a, b) => (a.department ? 1 : 0) - (b.department ? 1 : 0) || compareBy(a, b, 'person'),
    );
  } else if (sortKey.value === 'id_desc') {
    next.sort((a, b) => compareBy(b, a, 'id'));
  } else {
    next.sort((a, b) => compareBy(a, b, 'person'));
  }
  return next;
}

const lastPage = computed(() =>
  Math.max(1, Math.ceil(filteredMembers.value.length / perPage.value)),
);

const meta = computed(() => {
  const total = filteredMembers.value.length;
  const current = Math.min(Math.max(page.value, 1), lastPage.value);
  const from = total === 0 ? 0 : (current - 1) * perPage.value + 1;
  const to = Math.min(current * perPage.value, total);
  return {
    current_page: current,
    last_page: lastPage.value,
    total,
    from,
    to,
    per_page: perPage.value,
  };
});

const pageMembers = computed(() => {
  const start = (meta.value.current_page - 1) * perPage.value;
  return filteredMembers.value.slice(start, start + perPage.value);
});

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(departmentId.value) ||
    Boolean(status.value) ||
    Boolean(roleCode.value) ||
    activeHeaderFilterEntries.value.length > 0 ||
    Boolean(headerSort.value),
);

const hasVisibleFilterFields = computed(() =>
  UNASSIGNED_FILTERS.some((item) => visibleFilters[item.key]),
);

/* ── Chip bộ lọc đang áp dụng ──────────────────────────────────────────── */

const filterChips = computed(() => {
  const chips = [];

  if (query.value.trim()) {
    chips.push({ key: 'q', label: `Tìm: ${query.value.trim()}` });
  }
  if (departmentId.value === 'none') {
    chips.push({ key: 'department_id', label: 'Chưa gán phòng ban' });
  } else if (departmentId.value === 'any') {
    chips.push({ key: 'department_id', label: 'Đã có phòng ban' });
  } else if (departmentId.value) {
    const name = departmentFilterOptions.value.find(
      (item) => item.value === departmentId.value,
    )?.label;
    chips.push({ key: 'department_id', label: `Phòng ban: ${name ?? departmentId.value}` });
  }
  if (status.value) {
    chips.push({ key: 'status', label: `Trạng thái: ${memberStatusLabel(status.value)}` });
  }
  if (roleCode.value) {
    const name = roleFilterOptions.value.find((item) => item.value === roleCode.value)?.label;
    chips.push({ key: 'role', label: `Vai trò: ${name ?? roleCode.value}` });
  }
  for (const [key, value] of activeHeaderFilterEntries.value) {
    const label = UNASSIGNED_COLUMNS.find((col) => col.key === key)?.label ?? key;
    chips.push({ key: `header:${key}`, label: `${label} chứa: ${value.trim()}` });
  }
  if (headerSort.value) {
    const label = UNASSIGNED_COLUMNS.find((col) => col.key === headerSort.value.key)?.label ?? '';
    chips.push({
      key: 'sort',
      label: `Sắp xếp: ${label} ${headerSort.value.dir === 'desc' ? 'giảm dần' : 'tăng dần'}`,
    });
  }

  return chips;
});

function removeChip(key) {
  if (key === 'q') query.value = '';
  else if (key === 'department_id') departmentId.value = '';
  else if (key === 'status') status.value = '';
  else if (key === 'role') roleCode.value = '';
  else if (key === 'sort') headerSort.value = null;
  else if (key.startsWith('header:')) delete headerFilters[key.slice('header:'.length)];
}

function clearFilters() {
  query.value = '';
  departmentId.value = '';
  status.value = '';
  roleCode.value = '';
  headerSort.value = null;
  for (const key of Object.keys(headerFilters)) delete headerFilters[key];
  page.value = 1;
}

function applyHeaderFilter(key, value) {
  if (!value) delete headerFilters[key];
  else headerFilters[key] = value;
  page.value = 1;
}

function clearHeaderFilter(key) {
  delete headerFilters[key];
  page.value = 1;
}

function toggleHeaderSort(key) {
  const current = headerSort.value;
  if (!current || current.key !== key) {
    headerSort.value = { key, dir: 'asc' };
  } else if (current.dir === 'asc') {
    headerSort.value = { key, dir: 'desc' };
  } else {
    headerSort.value = null;
  }
}

function headerSortDir(key) {
  return headerSort.value?.key === key ? headerSort.value.dir : '';
}

/* ── Chọn nhiều dòng ───────────────────────────────────────────────────── */

const filteredIds = computed(() => filteredMembers.value.map((member) => member.id));

const selectedCount = computed(() => selectedIds.value.size);

const allFilteredSelected = computed(
  () => filteredIds.value.length > 0 && filteredIds.value.every((id) => selectedIds.value.has(id)),
);

const someFilteredSelected = computed(
  () => !allFilteredSelected.value && filteredIds.value.some((id) => selectedIds.value.has(id)),
);

const bulkDepartmentName = computed(
  () =>
    departmentOptions.value.find((item) => String(item.id) === bulkDepartmentId.value)?.name ?? '',
);

function isSelected(id) {
  return selectedIds.value.has(id);
}

function toggleSelect(id) {
  const next = new Set(selectedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  selectedIds.value = next;
}

function toggleSelectFiltered() {
  const next = new Set(selectedIds.value);
  if (allFilteredSelected.value) {
    for (const id of filteredIds.value) next.delete(id);
  } else {
    for (const id of filteredIds.value) next.add(id);
  }
  selectedIds.value = next;
}

function clearSelection() {
  selectedIds.value = new Set();
  bulkDepartmentId.value = '';
}

/* ── Tải dữ liệu ───────────────────────────────────────────────────────── */

async function load(options = {}) {
  const { silent = false } = options;
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/members/by-department');
    const grouped = (data.departments ?? []).flatMap((group) => group.members ?? []);
    allMembers.value = [...(data.unassigned ?? []), ...grouped];
    departmentOptions.value = data.department_options ?? [];
    if (selected.value && !allMembers.value.some((member) => member.id === selected.value.id)) {
      selected.value = null;
    }
    const live = new Set(allMembers.value.map((member) => member.id));
    selectedIds.value = new Set([...selectedIds.value].filter((id) => live.has(id)));
    nextTick(fitColumnsToContent);
  } catch (error) {
    if (isTransientClientNetworkError(error)) {
      return;
    }
    if (!silent) {
      showClientToast('error', 'Không tải được danh sách nhân sự theo phòng ban.');
    }
  } finally {
    loading.value = false;
  }
}

function retryLoadWhenOnline() {
  if (document.visibilityState !== 'visible' || loading.value) {
    return;
  }
  load({ silent: true });
}

function goPage(nextPage) {
  if (nextPage < 1 || nextPage > lastPage.value || nextPage === page.value) {
    return;
  }
  page.value = nextPage;
}

function inspect(member) {
  selected.value = member;
}

function usesPhoto(member) {
  return Boolean(member?.avatar_url) && !brokenAvatarIds.value.has(member.id);
}

function onAvatarError(id) {
  if (brokenAvatarIds.value.has(id)) return;
  const next = new Set(brokenAvatarIds.value);
  next.add(id);
  brokenAvatarIds.value = next;
}

/* ── Gán phòng ban ─────────────────────────────────────────────────────── */

const departmentAssignUnchanged = computed(() => {
  if (!selected.value) return true;
  const current = selected.value.department?.id ?? '';
  const next = departmentAssignId.value === '' ? '' : Number(departmentAssignId.value);
  return current === next;
});

function patchMember(member) {
  const index = allMembers.value.findIndex((item) => item.id === member.id);
  if (index >= 0) allMembers.value[index] = member;
  if (selected.value?.id === member.id) selected.value = member;
}

async function assignDepartment(memberId, nextDepartmentId) {
  const { data } = await window.axios.put(
    `/api/workspace-config/members/${memberId}/department`,
    { department_id: Number(nextDepartmentId) },
  );
  patchMember(data.member);
  return data.member;
}

async function saveMemberDepartment() {
  if (!selected.value || departmentAssignUnchanged.value || departmentAssignId.value === '') return;

  departmentAssignSaving.value = true;
  try {
    const member = await assignDepartment(selected.value.id, departmentAssignId.value);
    showClientToast(
      'success',
      `Đã gán ${member.name} vào phòng ban ${member.department?.name ?? ''}.`,
    );
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không gán được phòng ban. Vui lòng thử lại.');
  } finally {
    departmentAssignSaving.value = false;
  }
}

async function saveBulkDepartment() {
  if (bulkDepartmentId.value === '' || selectedCount.value === 0) return;

  bulkSaving.value = true;
  const ids = [...selectedIds.value];
  const name = bulkDepartmentName.value;
  let ok = 0;
  let failed = 0;

  try {
    for (const id of ids) {
      try {
        await assignDepartment(id, bulkDepartmentId.value);
        ok += 1;
      } catch {
        failed += 1;
      }
    }

    if (ok > 0) {
      showClientToast('success', `Đã gán ${ok} nhân sự vào phòng ban ${name}.`);
    }
    if (failed > 0) {
      showClientToast('error', `Có ${failed} nhân sự không gán được phòng ban.`);
    }
    if (failed === 0) clearSelection();
  } finally {
    bulkSaving.value = false;
  }
}

/* ── Bảng: đo cột, kéo đổi độ rộng ─────────────────────────────────────── */

function cellText(member, key) {
  if (key === 'person') return member.name || '—';
  if (key === 'email') return member.email || '—';
  if (key === 'department') return departmentName(member) || 'Chưa gán phòng ban';
  if (key === 'team') return teamName(member) || '—';
  if (key === 'roles') return memberRolesText(member);
  if (key === 'status') return memberStatusLabel(member.status);
  if (key === 'id') return String(member.id ?? '—');
  if (key === 'actions') return 'Xem chi tiết';
  return '—';
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ZOOM_STORAGE_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(COLUMN_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      return parsed;
    }
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum + SELECT_COL_PX}px` : '100%';
});

function measureText(text, font) {
  if (!measureCtx) {
    measureCtx = document.createElement('canvas').getContext('2d');
  }
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.wc-people__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(
      table?.querySelector('.wc-people__muted'),
      '400 12px "Be Vietnam Pro", sans-serif',
    ),
  };
}

function columnContentWidth(key, fonts) {
  const col = UNASSIGNED_COLUMNS.find((item) => item.key === key);
  const tools = (col?.sortable ? 22 : 0) + (col?.filterable ? 22 : 0);
  let maxW = measureText(col?.label ?? '', fonts.header) + tools;

  for (const member of pageMembers.value) {
    if (key === 'person') {
      maxW = Math.max(maxW, measureText(cellText(member, 'person'), fonts.cell) + AVATAR_EXTRA);
    } else {
      maxW = Math.max(maxW, measureText(cellText(member, key), fonts.cell));
    }
  }
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA));
}

function distributeExtraWidth(widths, keys, available) {
  const sum = keys.reduce((total, key) => total + widths[key], 0);
  if (sum <= 0 || available <= sum) return widths;

  const extra = available - sum;
  const next = { ...widths };
  let used = 0;
  keys.forEach((key, index) => {
    if (index === keys.length - 1) {
      next[key] = available - used;
      return;
    }
    next[key] = widths[key] + Math.floor((widths[key] / sum) * extra);
    used += next[key];
  });
  return next;
}

function fitColumnsToContent() {
  const wrap = tableWrap.value;
  const keys = shownColumns.value.map((col) => col.key);
  if (!wrap || keys.length === 0 || resizing.value) return;

  const fonts = readTableFonts();
  const measured = {};
  for (const key of keys) {
    measured[key] = columnContentWidth(key, fonts);
  }

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth - SELECT_COL_PX);
  for (const key of keys) {
    columnWidths[key] = next[key];
  }
}

function startResize(event, key) {
  const keys = shownColumns.value.map((col) => col.key);
  const index = keys.indexOf(key);
  if (index < 0) return;

  const neighbor = keys[index + 1] ?? keys[index - 1];
  if (!neighbor || neighbor === key) return;

  const towardNext = keys.indexOf(neighbor) > index;
  const startX = event.clientX;
  const startA = Number(columnWidths[key]) || MIN_COL_PX;
  const startB = Number(columnWidths[neighbor]) || MIN_COL_PX;
  const pair = startA + startB;

  resizing.value = true;

  function onMove(moveEvent) {
    const delta = (moveEvent.clientX - startX) * (towardNext ? 1 : -1);
    let nextA = Math.round(startA + delta);
    nextA = Math.min(Math.max(nextA, MIN_COL_PX), pair - MIN_COL_PX);
    columnWidths[key] = nextA;
    columnWidths[neighbor] = pair - nextA;
  }

  function onUp() {
    resizing.value = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = UNASSIGNED_COLUMNS.filter(
      (col) => visibleColumns[col.key] && col.key !== key,
    ).length;
    if (remaining < 1) {
      showClientToast('warning', 'Cần giữ ít nhất một cột trên bảng.');
      return;
    }
  }
  visibleColumns[key] = checked;
}

function onFilterToggle(key, checked) {
  visibleFilters[key] = checked;
}

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (selected.value) {
    selected.value = null;
  }
}

/* ── Watchers ──────────────────────────────────────────────────────────── */

watch(visibleColumns, (value) => saveVisibility(COLUMN_STORAGE_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_STORAGE_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(COLUMN_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ZOOM_STORAGE_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch(selected, (member) => {
  departmentAssignId.value = member?.department?.id != null ? String(member.department.id) : '';
  nextTick(fitColumnsToContent);
});
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch(pageMembers, () => nextTick(fitColumnsToContent));

watch(someFilteredSelected, (value) => {
  if (selectAllBox.value) selectAllBox.value.indeterminate = value;
});

watch([query, departmentId, status, roleCode, sortKey, perPage], () => {
  page.value = 1;
});

watch(filteredMembers, (rows) => {
  if (selected.value && !rows.some((member) => member.id === selected.value.id)) {
    selected.value = null;
  }
  if (page.value > lastPage.value) {
    page.value = lastPage.value;
  }
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  load();
  nextTick(() => {
    fitColumnsToContent();
    if (tableWrap.value) {
      let lastWrapWidth = tableWrap.value.clientWidth;
      wrapObserver = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect?.width || 0);
        if (!width || width === lastWrapWidth || resizing.value) return;
        lastWrapWidth = width;
        fitColumnsToContent();
      });
      wrapObserver.observe(tableWrap.value);
    }
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
  document.addEventListener('visibilitychange', retryLoadWhenOnline);
  window.addEventListener('online', retryLoadWhenOnline);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.removeEventListener('visibilitychange', retryLoadWhenOnline);
  window.removeEventListener('online', retryLoadWhenOnline);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="wc-people">
    <PageHeader
      title="Nhân sự"
      subtitle="Danh sách toàn bộ nhân sự workspace và phòng ban đang phụ trách"
      icon="users"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Nhân sự' },
      ]"
    >
      <template #actions>
        <button type="button" class="wc-people__tool-btn" :disabled="loading" @click="load">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-people__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <MemberKpiStrip
      :cards="kpiCards"
      :active-filter="activeQuickFilter"
      @quick-filter="onQuickFilter"
    />

    <div class="wc-people__body">
      <div class="wc-people__main">
        <section class="wc-people__panel" aria-label="Danh sách nhân sự">
          <header class="wc-people__panel-head">
            <div class="wc-people__panel-title">
              <h2 class="wc-people__panel-heading">Danh sách nhân sự</h2>
              <p class="wc-people__panel-count">{{ fmtNumber(meta.total) }} kết quả</p>
            </div>

            <div class="wc-people__panel-search">
              <label class="wc-people__label" for="wc-people-q">Tìm nhân sự</label>
              <div class="wc-people__search-box">
                <AppIcon name="search" :size="15" class="wc-people__search-icon" />
                <input
                  id="wc-people-q"
                  v-model="query"
                  type="search"
                  class="wc-people__input wc-people__input--search"
                  placeholder="Vd. Nguyễn Văn An, an.nguyen@vaschools.edu.vn"
                />
              </div>
            </div>
          </header>

          <div v-if="hasVisibleFilterFields" class="wc-people__filters">
            <div v-if="visibleFilters.department_id" class="wc-people__field">
              <label class="wc-people__label" for="wc-people-dept">Phòng ban</label>
              <select id="wc-people-dept" v-model="departmentId" class="wc-people__input">
                <option value="">Tất cả phòng ban</option>
                <option value="none">Chưa gán phòng ban</option>
                <option value="any">Đã có phòng ban</option>
                <option v-for="item in departmentFilterOptions" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="wc-people__field">
              <label class="wc-people__label" for="wc-people-status">Trạng thái</label>
              <select id="wc-people-status" v-model="status" class="wc-people__input">
                <option v-for="item in MEMBER_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.role" class="wc-people__field">
              <label class="wc-people__label" for="wc-people-role">Vai trò</label>
              <select id="wc-people-role" v-model="roleCode" class="wc-people__input">
                <option value="">Tất cả vai trò</option>
                <option v-for="item in roleFilterOptions" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.sort" class="wc-people__field">
              <label class="wc-people__label" for="wc-people-sort">Sắp xếp</label>
              <select id="wc-people-sort" v-model="sortKey" class="wc-people__input">
                <option v-for="item in SORT_OPTIONS" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
          </div>

          <TablePagesBar
            placement="top"
            :from="meta.from || 0"
            :to="meta.to || 0"
            :total="meta.total || 0"
            :page="meta.current_page || 1"
            :last-page="meta.last_page || 1"
            :per-page="perPage"
            :zoom="tableZoom"
            :show-clear-filters="hasActiveFilters"
            :filters-active="hasActiveFilters"
            @clear-filters="clearFilters"
            @update:page="goPage"
            @update:per-page="perPage = $event"
            @update:zoom="tableZoom = $event"
          >
            <template #filters>
              <label v-for="item in UNASSIGNED_FILTERS" :key="item.key" class="wc-people__check">
                <input
                  type="checkbox"
                  :checked="visibleFilters[item.key]"
                  @change="onFilterToggle(item.key, $event.target.checked)"
                />
                <span>{{ item.label }}</span>
              </label>
            </template>
            <template #settings>
              <label v-for="col in UNASSIGNED_COLUMNS" :key="col.key" class="wc-people__check">
                <input
                  type="checkbox"
                  :checked="visibleColumns[col.key]"
                  @change="onColumnToggle(col.key, $event.target.checked)"
                />
                <span>{{ col.label }}</span>
              </label>
            </template>
          </TablePagesBar>

          <div v-if="filterChips.length" class="wc-people__chips">
            <span class="wc-people__chips-label">Đang lọc</span>
            <button
              v-for="chip in filterChips"
              :key="chip.key"
              type="button"
              class="wc-people__chip"
              @click="removeChip(chip.key)"
            >
              <span>{{ chip.label }}</span>
              <AppIcon name="close" :size="12" />
            </button>
            <button type="button" class="wc-people__chips-clear" @click="clearFilters">
              Bỏ tất cả
            </button>
          </div>

          <div v-if="selectedCount > 0" class="wc-people__bulk">
            <p class="wc-people__bulk-text">
              Đã chọn <strong>{{ fmtNumber(selectedCount) }}</strong> nhân sự
            </p>
            <div class="wc-people__bulk-actions">
              <label class="wc-people__label" for="wc-people-bulk-dept">Gán vào phòng ban</label>
              <select
                id="wc-people-bulk-dept"
                v-model="bulkDepartmentId"
                class="wc-people__input wc-people__input--bulk"
                :disabled="bulkSaving"
              >
                <option value="">Chọn phòng ban</option>
                <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
                  {{ item.name }}
                </option>
              </select>
              <button
                type="button"
                class="wc-people__btn wc-people__btn--primary"
                :disabled="bulkSaving || bulkDepartmentId === ''"
                @click="saveBulkDepartment"
              >
                {{ bulkSaving ? 'Đang gán…' : 'Gán phòng ban' }}
              </button>
              <button
                type="button"
                class="wc-people__btn"
                :disabled="bulkSaving"
                @click="clearSelection"
              >
                Bỏ chọn
              </button>
            </div>
          </div>

          <div
            ref="tableWrap"
            class="wc-people__table-wrap hide-scrollbar"
            :class="{ 'wc-people__table-wrap--resizing': resizing }"
            :style="{ '--table-zoom': tableZoom }"
          >
            <table class="wc-people__table" :style="{ width: tableWidthPx }">
              <colgroup>
                <col :style="{ width: '44px' }" />
                <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
              </colgroup>
              <thead>
                <tr>
                  <th class="wc-people__th-select">
                    <input
                      ref="selectAllBox"
                      type="checkbox"
                      class="wc-people__checkbox"
                      :checked="allFilteredSelected"
                      :disabled="filteredIds.length === 0"
                      aria-label="Chọn tất cả nhân sự đang lọc"
                      @change="toggleSelectFiltered"
                    />
                  </th>
                  <th v-for="col in shownColumns" :key="col.key">
                    <ColumnHeaderFilter
                      :label="col.label"
                      :value="headerFilters[col.key] ?? ''"
                      :filterable="col.filterable"
                      :sortable="col.sortable"
                      :sort-dir="headerSortDir(col.key)"
                      @apply="applyHeaderFilter(col.key, $event)"
                      @clear="clearHeaderFilter(col.key)"
                      @toggle-sort="toggleHeaderSort(col.key)"
                    />
                    <button
                      type="button"
                      class="wc-people__resize"
                      aria-label="Kéo để đổi độ rộng cột"
                      @click.stop
                      @mousedown.stop.prevent="startResize($event, col.key)"
                    />
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td :colspan="colSpan" class="wc-people__empty">Đang tải…</td>
                </tr>
                <tr v-else-if="pageMembers.length === 0">
                  <td :colspan="colSpan" class="wc-people__empty">
                    <div class="wc-people__empty-box">
                      <span class="wc-people__empty-icon" aria-hidden="true">
                        <AppIcon name="users" :size="24" :stroke-width="1.5" />
                      </span>
                      <p class="wc-people__empty-title">
                        {{ hasActiveFilters ? 'Không có nhân sự phù hợp' : 'Workspace chưa có nhân sự nào' }}
                      </p>
                      <p class="wc-people__empty-text">
                        {{
                          hasActiveFilters
                            ? 'Thử xoá từ khoá tìm kiếm hoặc đặt lại các bộ lọc đang áp dụng.'
                            : 'Nhân sự được đồng bộ từ hệ thống nhân sự VA-HRM, bấm Làm mới để tải lại.'
                        }}
                      </p>
                      <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="wc-people__btn"
                        @click="clearFilters"
                      >
                        Đặt lại bộ lọc
                      </button>
                    </div>
                  </td>
                </tr>
                <tr
                  v-for="member in pageMembers"
                  v-else
                  :key="member.id"
                  :class="{ 'wc-people__row--active': selected?.id === member.id }"
                  @click="inspect(member)"
                >
                  <td class="wc-people__td-select" @click.stop>
                    <input
                      type="checkbox"
                      class="wc-people__checkbox"
                      :checked="isSelected(member.id)"
                      :aria-label="`Chọn ${member.name}`"
                      @change="toggleSelect(member.id)"
                    />
                  </td>
                  <td v-for="col in shownColumns" :key="col.key">
                    <template v-if="col.key === 'person'">
                      <span class="wc-people__person">
                        <span class="wc-people__avatar" aria-hidden="true">
                          <img
                            v-if="usesPhoto(member)"
                            :src="member.avatar_url"
                            alt=""
                            class="wc-people__avatar-img"
                            referrerpolicy="no-referrer"
                            @error="onAvatarError(member.id)"
                          />
                          <img
                            v-else
                            :src="FALLBACK_AVATAR_SRC"
                            :srcset="FALLBACK_AVATAR_SRCSET"
                            alt=""
                            class="wc-people__avatar-fallback"
                          />
                        </span>
                        <span class="wc-people__person-name">{{ member.name }}</span>
                      </span>
                    </template>
                    <template v-else-if="col.key === 'department'">
                      <span v-if="member.department">{{ member.department.name }}</span>
                      <span v-else class="wc-people__muted">Chưa gán phòng ban</span>
                    </template>
                    <template v-else-if="col.key === 'status'">
                      <StatusBadge :on="member.status === 'active'" :label="memberStatusLabel(member.status)" />
                    </template>
                    <template v-else-if="col.key === 'actions'">
                      <button
                        type="button"
                        class="wc-people__row-btn"
                        @click.stop="inspect(member)"
                      >
                        Xem chi tiết
                      </button>
                    </template>
                    <span v-else>{{ cellText(member, col.key) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <TablePagesBar
            placement="bottom"
            paging-only
            :from="meta.from || 0"
            :to="meta.to || 0"
            :total="meta.total || 0"
            :page="meta.current_page || 1"
            :last-page="meta.last_page || 1"
            :per-page="perPage"
            @update:page="goPage"
            @update:per-page="perPage = $event"
          />
        </section>
      </div>

      <Transition name="wc-people-side">
        <aside v-if="selected" class="wc-people__side" aria-label="Chi tiết nhân sự">
          <div class="wc-people__side-head">
            <h2 class="wc-people__side-title">Chi tiết nhân sự</h2>
            <button type="button" class="wc-people__icon-btn" aria-label="Đóng" @click="selected = null">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="wc-people__side-person">
            <span class="wc-people__avatar wc-people__avatar--lg" aria-hidden="true">
              <img
                v-if="usesPhoto(selected)"
                :src="selected.avatar_url"
                alt=""
                class="wc-people__avatar-img"
                referrerpolicy="no-referrer"
                @error="onAvatarError(selected.id)"
              />
              <img
                v-else
                :src="FALLBACK_AVATAR_SRC"
                :srcset="FALLBACK_AVATAR_SRCSET"
                alt=""
                class="wc-people__avatar-fallback"
              />
            </span>
            <span class="wc-people__side-person-text">
              <span class="wc-people__side-lead">{{ selected.name }}</span>
              <span v-if="selected.email" class="wc-people__muted">{{ selected.email }}</span>
            </span>
          </div>

          <div class="wc-people__rows">
            <div class="wc-people__row">
              <span class="wc-people__row-label">Email</span>
              <span class="wc-people__row-value">{{ selected.email || '—' }}</span>
            </div>
            <div class="wc-people__row">
              <span class="wc-people__row-label">Phòng ban hiện tại</span>
              <span class="wc-people__row-value">
                <span v-if="selected.department">{{ selected.department.name }}</span>
                <span v-else class="wc-people__muted">Chưa gán phòng ban</span>
              </span>
            </div>
            <div class="wc-people__row">
              <span class="wc-people__row-label">Nhóm</span>
              <span class="wc-people__row-value">{{ selected.team?.name || '—' }}</span>
            </div>
            <div class="wc-people__row">
              <span class="wc-people__row-label">Vai trò</span>
              <span class="wc-people__row-value">{{ memberRolesText(selected) }}</span>
            </div>
            <div class="wc-people__row">
              <span class="wc-people__row-label">Trạng thái</span>
              <span class="wc-people__row-value">
                <StatusBadge :on="selected.status === 'active'" :label="memberStatusLabel(selected.status)" />
              </span>
            </div>
            <div class="wc-people__row">
              <span class="wc-people__row-label">Mã thành viên</span>
              <span class="wc-people__row-value">{{ selected.id }}</span>
            </div>
          </div>

          <div class="wc-people__side-form">
            <label class="wc-people__label" for="wc-people-dept-assign">Đổi phòng ban</label>
            <select
              id="wc-people-dept-assign"
              v-model="departmentAssignId"
              class="wc-people__input"
              :disabled="departmentAssignSaving"
            >
              <option value="" disabled>Chọn phòng ban</option>
              <option v-for="item in departmentOptions" :key="item.id" :value="String(item.id)">
                {{ item.name }}
              </option>
            </select>
            <button
              type="button"
              class="wc-people__btn wc-people__btn--primary wc-people__btn--block"
              :disabled="departmentAssignSaving || departmentAssignUnchanged || departmentAssignId === ''"
              @click="saveMemberDepartment"
            >
              {{ departmentAssignSaving ? 'Đang lưu…' : 'Lưu phòng ban' }}
            </button>
          </div>
        </aside>
      </Transition>
    </div>
  </section>
</template>

<style scoped>
.wc-people {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-5);
  overflow: hidden;
}

.wc-people__tool-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.wc-people__tool-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-people__tool-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-people__spin {
  animation: wc-people-spin 0.8s linear infinite;
}

@keyframes wc-people-spin {
  to {
    transform: rotate(360deg);
  }
}

/* ── Bố cục thân trang ─────────────────────────────────────────────────── */

.wc-people__body {
  flex: 1;
  display: flex;
  gap: var(--space-3);
  min-height: 0;
  overflow: hidden;
}

.wc-people__main {
  flex: 1;
  display: flex;
  min-width: 0;
  min-height: 0;
}

/* ── Panel danh sách (thẻ trắng bọc bảng, mẫu ListDataPanel) ───────────── */

.wc-people__panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  overflow: hidden;
}

.wc-people__panel-head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__panel-title {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
  min-width: 0;
}

.wc-people__panel-heading {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.3;
}

.wc-people__panel-count {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-people__panel-search {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  width: 100%;
  max-width: 20rem;
}

.wc-people__search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.wc-people__search-icon {
  position: absolute;
  left: 0.5rem;
  color: var(--color-text-muted);
  pointer-events: none;
}

.wc-people__input--search {
  padding-left: 2.25rem;
  padding-right: 1.75rem;
  appearance: none;
  -webkit-appearance: none;
}

.wc-people__input--search::-webkit-search-decoration,
.wc-people__input--search::-webkit-search-results-button,
.wc-people__input--search::-webkit-search-results-decoration {
  -webkit-appearance: none;
  appearance: none;
  display: none;
}

.wc-people__input--search::-webkit-search-cancel-button {
  -webkit-appearance: none;
  appearance: none;
}

/* ── Bộ lọc ghim ───────────────────────────────────────────────────────── */

.wc-people__filters {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.wc-people__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
}

.wc-people__input {
  width: 100%;
  height: 2rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-people__input:focus {
  outline: none;
  box-shadow: inset 0 0 0 2px var(--color-primary-900);
}

.wc-people__input:disabled {
  background: var(--color-surface-muted);
  opacity: 0.7;
}

.wc-people__check {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.25rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  white-space: nowrap;
  cursor: pointer;
}

/* ── Chip bộ lọc đang áp dụng ──────────────────────────────────────────── */

.wc-people__chips {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__chips-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-people__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 1.625rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.wc-people__chip:hover {
  background: var(--color-primary-50);
  color: var(--color-primary-900);
}

.wc-people__chips-clear {
  border: none;
  background: transparent;
  color: var(--color-primary-900);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-people__chips-clear:hover {
  text-decoration: underline;
}

/* ── Thanh thao tác hàng loạt ──────────────────────────────────────────── */

.wc-people__bulk {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
  background: var(--color-primary-50);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__bulk-text {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
}

.wc-people__bulk-text strong {
  color: var(--color-primary-900);
  font-variant-numeric: tabular-nums;
}

.wc-people__bulk-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.wc-people__input--bulk {
  width: 12rem;
}

.wc-people__btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.wc-people__btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-people__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-people__btn--primary {
  background: var(--color-primary-900);
  color: var(--color-on-primary);
  box-shadow: none;
}

.wc-people__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.wc-people__btn--block {
  width: 100%;
}

/* ── Bảng ──────────────────────────────────────────────────────────────── */

.wc-people__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  zoom: var(--table-zoom, 1);
}

.wc-people__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.wc-people__table {
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
}

.wc-people__table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: 0.5rem 1rem;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__table thead th:not(.wc-people__th-select) {
  position: sticky;
  padding-right: 1.25rem;
}

.wc-people__th-select,
.wc-people__td-select {
  width: 44px;
  padding-left: 0.75rem !important;
  padding-right: 0.75rem !important;
  text-align: center;
}

.wc-people__checkbox {
  width: 0.875rem;
  height: 0.875rem;
  accent-color: var(--color-primary-900);
  cursor: pointer;
}

.wc-people__checkbox:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.wc-people__resize {
  position: absolute;
  top: 0;
  right: 0;
  width: 0.625rem;
  height: 100%;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.wc-people__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 0.25rem;
  width: 1px;
  height: 50%;
  background: var(--color-border-strong);
}

.wc-people__resize:hover::after {
  background: var(--color-primary-900);
}

.wc-people__table tbody td {
  padding: 0.5rem 1rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__table tbody tr {
  cursor: pointer;
}

.wc-people__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.wc-people__row--active td {
  background: var(--color-primary-50);
}

.wc-people__row-btn {
  height: 1.625rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-primary-900);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.wc-people__row-btn:hover {
  background: var(--color-primary-50);
}

.wc-people__person {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.wc-people__person-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wc-people__avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-900);
  overflow: hidden;
}

.wc-people__avatar--lg {
  width: 3rem;
  height: 3rem;
}

.wc-people__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wc-people__avatar-fallback {
  width: 60%;
  height: 60%;
  object-fit: contain;
}

.wc-people__muted {
  color: var(--color-text-muted);
}

/* ── Trạng thái rỗng ───────────────────────────────────────────────────── */

.wc-people__empty {
  padding: var(--space-6) var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
  white-space: normal;
}

.wc-people__empty-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  max-width: 24rem;
  margin: 0 auto;
}

.wc-people__empty-icon {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-people__empty-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.wc-people__empty-text {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

/* ── Panel chi tiết đẩy ngang ──────────────────────────────────────────── */

.wc-people__side {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  width: 28rem;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  overflow-y: auto;
}

.wc-people__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.wc-people__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.wc-people__icon-btn {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.wc-people__icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.wc-people__side-person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
}

.wc-people__side-person-text {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.wc-people__side-lead {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.3;
}

.wc-people__side-person-text .wc-people__muted {
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
}

.wc-people__rows {
  display: flex;
  flex-direction: column;
}

.wc-people__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.5rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-people__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-people__row-value {
  min-width: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  text-align: right;
  overflow-wrap: anywhere;
}

.wc-people__side-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-top: var(--space-2);
}

.wc-people-side-enter-active,
.wc-people-side-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.wc-people-side-enter-from,
.wc-people-side-leave-to {
  opacity: 0;
  transform: translateX(var(--space-4));
}

/* ── Responsive ────────────────────────────────────────────────────────── */

@media (max-width: 1280px) {
  .wc-people__side {
    width: 24rem;
  }
}

@media (max-width: 768px) {
  .wc-people {
    padding: var(--space-3);
  }

  .wc-people__body {
    flex-direction: column;
    overflow-y: auto;
  }

  .wc-people__panel-head {
    flex-direction: column;
    align-items: stretch;
  }

  .wc-people__panel-search {
    max-width: none;
  }

  .wc-people__side {
    width: 100%;
  }

  .wc-people__bulk {
    flex-direction: column;
    align-items: stretch;
  }

  .wc-people__input--bulk {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .wc-people {
    padding: var(--space-2);
  }

  .wc-people__filters {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-2);
  }

  .wc-people__bulk-actions {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
