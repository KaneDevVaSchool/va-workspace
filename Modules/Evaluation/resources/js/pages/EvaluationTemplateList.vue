<script setup>
//
// manager/evaluation-templates — Mẫu đánh giá (Evaluation Giai đoạn C).
// Mục sidebar RIÊNG (khác tiêu chí đánh giá — vẫn là tab trong
// WorkspaceConfigHub). department_director/deputy trở lên quản lý mẫu
// phòng ban; superadmin xem tất cả và tạo mẫu dùng chung toàn hệ thống.
// Xem plans/2026-08-26-mau-danh-gia.md.
//
// PR2 — CRUD mẫu cơ bản: tên, mô tả, danh sách tiêu chí kèm trọng số.
// PR3 — Vị trí đánh giá: chọn nhiều vị trí (chức danh) áp dụng cho mẫu,
// danh mục dùng chung toàn hệ thống (không thuộc riêng phòng ban).
// Chưa có: dùng chung toàn hệ thống (PR4), trường tùy biến (PR5),
// Import/Export (PR6).
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import EvaluationTemplateCreateDialog from '../components/EvaluationTemplateCreateDialog.vue';
import EvaluationTemplateEditDialog from '../components/EvaluationTemplateEditDialog.vue';

// ─── constants ────────────────────────────────────────────────────────────

const COLUMNS = [
  { key: 'name', label: 'Tên mẫu đánh giá', defaultOn: true },
  { key: 'code', label: 'Mã mẫu', defaultOn: true },
  { key: 'criteria_count', label: 'Số tiêu chí', defaultOn: true, align: 'center' },
  { key: 'positions', label: 'Vị trí đánh giá', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'is_global', label: 'Dùng chung toàn hệ thống', defaultOn: true },
  { key: 'department', label: 'Phòng ban', defaultOn: true },
  { key: 'created_at', label: 'Ngày tạo', defaultOn: false },
  { key: 'creator', label: 'Người tạo', defaultOn: true, align: 'center' },
];

const FILTERS = [
  { key: 'q', label: 'Tìm kiếm', defaultOn: true },
  { key: 'status', label: 'Trạng thái', defaultOn: true },
  { key: 'is_global', label: 'Dùng chung toàn hệ thống', defaultOn: false },
  { key: 'position_id', label: 'Vị trí đánh giá', defaultOn: false },
  { key: 'creator_id', label: 'Người tạo', defaultOn: false },
];

// Chỉ dùng để hiện tên loại field trong panel chi tiết (form nhập nằm ở
// EvaluationCustomFieldsEditor.vue, dùng chung cho 2 dialog Tạo/Sửa).
const CUSTOM_FIELD_TYPES = [
  { value: 'text', label: 'Chữ' },
  { value: 'bonus', label: 'Điểm phụ thêm' },
];

const COL_KEY = 'va-eval-templates-columns-v3';
const FILTER_KEY = 'va-eval-templates-filters-v2';
const WIDTH_KEY = 'va-eval-templates-widths-v2';
const ZOOM_KEY = 'va-eval-templates-zoom';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const AVATAR_CELL_PX = 40;
const MIN_COL_PX = 90;
let measureCtx = null;
let wrapObserver = null;

// ─── stores ─────────────────────────────────────────────────────────────────

const auth = useAuthStore();

// ─── state ──────────────────────────────────────────────────────────────────

const allTemplates = ref([]);
const allCriteria = ref([]);
const allPositions = ref([]);
// Tiêu chí active MỌI phòng ban — chỉ load khi cần sửa 1 mẫu đang is_global
// (PR4, quyền evaluation.manage_global_template). Rỗng nếu chưa từng cần.
const globalCriteriaPool = ref([]);
const loadingGlobalPool = ref(false);
const loading = ref(false);
const exporting = ref(false);
const selected = ref(null);
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingTemplate = ref(null);
const confirmDelete = ref(null);
const deletingId = ref(null);
const togglingId = ref(null);
const duplicatingId = ref(null);
const openActionMenuId = ref(null); // id mẫu đang mở dropdown Thao tác trên dòng
const actionMenuPos = reactive({ top: 0, left: 0 });
const deptGroupCollapsed = reactive({}); // key nhóm phòng ban đang thu gọn

const query = ref('');
const statusFilter = ref('');
const isGlobalFilter = ref('');
const positionFilter = ref('');
const creatorFilter = ref('');
const page = ref(1);
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(COL_KEY, COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_KEY, FILTERS));

const tableWrap = ref(null);
const resizing = ref(false);

useDragScroll(tableWrap, { isBlocked: () => resizing.value });
const colWidths = reactive(loadWidths());
const tableZoom = ref(loadZoom());

// ─── computed ─────────────────────────────────────────────────────────────

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));
const canViewAll = computed(() => auth.can('workspace_config.view_all'));
const canManage = computed(() => auth.can('evaluation.manage_department'));
/** Chỉ department_director trở lên + superadmin — đánh dấu mẫu dùng chung toàn hệ thống. */
const canManageGlobal = computed(() => auth.can('evaluation.manage_global_template'));
/** Superadmin không gắn phòng ban chỉ tạo được mẫu dùng chung. */
const forceGlobalCreate = computed(() => canViewAll.value && !hasDepartment.value);
const canCreate = computed(() => (hasDepartment.value && canManage.value) || forceGlobalCreate.value);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return allTemplates.value.filter((t) => {
    if (
      q &&
      !t.name.toLowerCase().includes(q) &&
      !(t.code ?? '').toLowerCase().includes(q)
    ) {
      return false;
    }
    if (statusFilter.value === 'active' && !t.is_active) return false;
    if (statusFilter.value === 'inactive' && t.is_active) return false;
    if (isGlobalFilter.value === 'yes' && !t.is_global) return false;
    if (isGlobalFilter.value === 'no' && t.is_global) return false;
    if (positionFilter.value && !(t.positions ?? []).some((p) => String(p.id) === String(positionFilter.value))) {
      return false;
    }
    if (creatorFilter.value && String(t.creator?.id ?? '') !== String(creatorFilter.value)) {
      return false;
    }
    return true;
  });
});

/** Danh sách người tạo distinct từ các mẫu đã có — không gọi API riêng. */
const creatorOptions = computed(() => {
  const seen = new Map();
  for (const t of allTemplates.value) {
    if (t.creator?.id != null && !seen.has(t.creator.id)) {
      seen.set(t.creator.id, t.creator);
    }
  }
  return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name, 'vi'));
});

const lastPage = computed(() => 1);

const pagerMeta = computed(() => {
  const total = filtered.value.length;
  const visible = templateGroups.value.reduce(
    (n, g) => n + (isDeptGroupCollapsed(g.key) ? 0 : g.templates.length),
    0,
  );
  return { from: visible ? 1 : 0, to: visible, total };
});

const tableColspan = computed(() => shownColumns.value.length + (canManage.value ? 1 : 0));

const hasActiveFilters = computed(() =>
  Boolean(
    query.value.trim() ||
      statusFilter.value ||
      isGlobalFilter.value ||
      positionFilter.value ||
      creatorFilter.value,
  ),
);

const hasVisibleFilterFields = computed(() => FILTERS.some((item) => visibleFilters[item.key]));

const hiddenActiveFilterLabels = computed(() =>
  FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map((item) => item.label),
);

const shownColumns = computed(() => COLUMNS.filter((col) => visibleColumns[col.key]));

/** Tên vị trí đánh giá, kèm hậu tố phân biệt khi là cả 1 phòng ban (không phải chức danh). */
function positionNames(template) {
  return (template.positions ?? []).map((p) => (p.kind === 'department' ? `${p.name} (phòng ban)` : p.name));
}

function departmentText(template) {
  return template.department?.name || (template.is_global ? 'Toàn hệ thống' : '—');
}

function departmentGroupKey(template) {
  if (template.department?.id != null) return `dept-${template.department.id}`;
  if (template.is_global) return 'global';
  return 'none';
}

function departmentGroupTitle(template) {
  return template.department?.name || (template.is_global ? 'Toàn hệ thống' : 'Chưa gắn phòng ban');
}

function buildDepartmentGroups(list) {
  const groups = [];
  const map = new Map();
  for (const template of list) {
    const key = departmentGroupKey(template);
    if (!map.has(key)) {
      map.set(key, { key, title: departmentGroupTitle(template), templates: [] });
      groups.push(map.get(key));
    }
    map.get(key).templates.push(template);
  }
  groups.sort((a, b) => {
    const tail = { global: 1, none: 2 };
    const rankA = tail[a.key] ?? 0;
    const rankB = tail[b.key] ?? 0;
    if (rankA !== rankB) return rankA - rankB;
    return a.title.localeCompare(b.title, 'vi');
  });
  return groups;
}

function isDeptGroupCollapsed(key) {
  return Boolean(deptGroupCollapsed[key]);
}

function toggleDeptGroup(key) {
  closeActionMenu();
  deptGroupCollapsed[key] = !deptGroupCollapsed[key];
}

const templateGroups = computed(() => buildDepartmentGroups(filtered.value));

const tableBodyRows = computed(() => {
  const rows = [];
  for (const group of templateGroups.value) {
    rows.push({
      kind: 'group',
      key: `group-${group.key}`,
      groupKey: group.key,
      title: group.title,
      count: group.templates.length,
      collapsed: isDeptGroupCollapsed(group.key),
    });
    if (!isDeptGroupCollapsed(group.key)) {
      for (const template of group.templates) {
        rows.push({ kind: 'template', key: template.id, template });
      }
    }
  }
  return rows;
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'status') return Boolean(statusFilter.value);
  if (key === 'is_global') return Boolean(isGlobalFilter.value);
  if (key === 'position_id') return Boolean(positionFilter.value);
  if (key === 'creator_id') return Boolean(creatorFilter.value);
  return false;
}

function clearFilters() {
  query.value = '';
  statusFilter.value = '';
  isGlobalFilter.value = '';
  positionFilter.value = '';
  creatorFilter.value = '';
  page.value = 1;
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

// ─── persistence helpers ────────────────────────────────────────────────────

function loadVisibility(key, items) {
  const defaults = {};
  for (const item of items) defaults[item.key] = item.defaultOn;
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return defaults;
    const parsed = JSON.parse(raw);
    const next = { ...defaults };
    for (const item of items) {
      if (typeof parsed[item.key] === 'boolean') next[item.key] = parsed[item.key];
    }
    return next;
  } catch {
    return defaults;
  }
}

function saveVisibility(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {}
}

function loadWidths() {
  try {
    const raw = localStorage.getItem(WIDTH_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function saveWidths() {
  try {
    localStorage.setItem(WIDTH_KEY, JSON.stringify({ ...colWidths }));
  } catch {}
}

function loadZoom() {
  try {
    const v = parseFloat(localStorage.getItem(ZOOM_KEY));
    return isNaN(v) ? 1 : v;
  } catch {
    return 1;
  }
}

function saveZoom(v) {
  try {
    localStorage.setItem(ZOOM_KEY, String(v));
  } catch {}
}

// ─── column widths / resize ────────────────────────────────────────────────

function measureText(text) {
  if (!measureCtx) {
    const canvas = document.createElement('canvas');
    measureCtx = canvas.getContext('2d');
    measureCtx.font = '600 0.75rem var(--font-family-base, sans-serif)';
  }
  return measureCtx.measureText(String(text ?? '')).width;
}

function computeDefaultWidths() {
  const wrapWidth = tableWrap.value?.clientWidth ?? 800;
  const rawWidths = {};

  for (const col of shownColumns.value) {
    let max = measureText(col.label) + COL_EXTRA;
    if (col.key === 'creator') {
      max = Math.max(max, AVATAR_CELL_PX + CELL_PAD_X);
    } else {
      for (const row of allTemplates.value) {
        let val = '';
        if (col.key === 'name') val = row.name ?? '';
        if (col.key === 'code') val = row.code ?? '';
        if (col.key === 'criteria_count') val = String(row.criteria_count ?? 0);
        if (col.key === 'status') val = row.is_active ? 'Hoạt động' : 'Không hoạt động';
        if (col.key === 'positions') {
          const names = positionNames(row);
          val = names.length ? names.reduce((longest, name) => (name.length > longest.length ? name : longest), '') : '—';
        }
        if (col.key === 'is_global') val = row.is_global ? 'Có' : 'Không';
        if (col.key === 'department') val = departmentText(row);
        if (col.key === 'created_at') val = formatDateTime(row.created_at) || '—';
        const w = measureText(val) + CELL_PAD_X;
        if (w > max) max = w;
      }
    }
    rawWidths[col.key] = Math.max(Math.ceil(max), MIN_COL_PX);
  }

  const totalRaw = Object.values(rawWidths).reduce((a, b) => a + b, 0);
  if (totalRaw < wrapWidth && shownColumns.value.length > 0) {
    const factor = wrapWidth / totalRaw;
    for (const k in rawWidths) rawWidths[k] = Math.floor(rawWidths[k] * factor);
  }

  for (const col of shownColumns.value) {
    if (!colWidths[col.key]) colWidths[col.key] = rawWidths[col.key];
  }
}

let resizeState = null;

function startResize(event, colKey) {
  event.preventDefault();
  const cols = shownColumns.value;
  const idx = cols.findIndex((c) => c.key === colKey);
  const nextCol = cols[idx + 1] ?? null;

  resizeState = {
    startX: event.clientX,
    startW: colWidths[colKey] ?? MIN_COL_PX,
    nextKey: nextCol?.key ?? null,
    startNextW: nextCol ? (colWidths[nextCol.key] ?? MIN_COL_PX) : null,
  };
  resizing.value = true;

  function onMove(e) {
    if (!resizeState) return;
    const dx = e.clientX - resizeState.startX;
    colWidths[colKey] = Math.max(MIN_COL_PX, resizeState.startW + dx);
    if (resizeState.nextKey) {
      colWidths[resizeState.nextKey] = Math.max(MIN_COL_PX, resizeState.startNextW - dx);
    }
  }

  function onUp() {
    resizing.value = false;
    resizeState = null;
    saveWidths();
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }

  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// ─── API ────────────────────────────────────────────────────────────────────

async function load() {
  if (!hasDepartment.value && !canViewAll.value) {
    allTemplates.value = [];
    allCriteria.value = [];
    allPositions.value = [];
    return;
  }
  loading.value = true;
  try {
    const templatesReq = canViewAll.value
      ? window.axios.get('/api/evaluation/templates', { params: { department_id: 'all' } })
      : window.axios.get('/api/evaluation/templates');
    const criteriaReq = hasDepartment.value
      ? window.axios.get('/api/evaluation/criteria')
      : Promise.resolve({ data: { criteria: [] } });
    const [templatesRes, criteriaRes, positionsRes] = await Promise.all([
      templatesReq,
      criteriaReq,
      window.axios.get('/api/evaluation/positions'),
    ]);
    allTemplates.value = templatesRes.data.templates ?? [];
    allCriteria.value = criteriaRes.data.criteria ?? [];
    allPositions.value = positionsRes.data.positions ?? [];
    if (forceGlobalCreate.value) {
      loadGlobalCriteriaPool();
    }
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được danh sách mẫu đánh giá.');
  } finally {
    loading.value = false;
  }
}

/**
 * Xuất Excel theo bộ lọc hiện tại (PR6) — CHỈ xuất, không có chiều nhập
 * ngược lại cho Mẫu đánh giá (khác Tiêu chí đánh giá). Ai xem được trang
 * đều xuất được, không cần quyền quản lý riêng.
 */
async function exportExcel() {
  exporting.value = true;
  try {
    const response = await window.axios.get('/api/evaluation/templates/export', {
      params: { q: query.value.trim(), status: statusFilter.value },
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được file.');
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || 'Mau_danh_gia.xlsx');

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showClientToast('success', 'Đã tải file Excel.');
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = 'Không xuất được file Excel.';
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || 'Không xuất được file Excel.');
  } finally {
    exporting.value = false;
  }
}

const exportOptions = computed(() => [
  {
    key: 'excel',
    label: 'Xuất Excel',
    description: 'Theo bộ lọc hiện tại trên trang.',
    onSelect: exportExcel,
  },
]);

async function loadGlobalCriteriaPool() {
  if (globalCriteriaPool.value.length > 0) return; // đã tải, không gọi lại mỗi lần mở form
  loadingGlobalPool.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/templates/global-criteria-pool');
    globalCriteriaPool.value = data.criteria ?? [];
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được danh sách tiêu chí toàn hệ thống.');
  } finally {
    loadingGlobalPool.value = false;
  }
}

function canMutateTemplate(template) {
  if (!template) return false;
  if (canViewAll.value) return Boolean(template.is_global);
  const deptId = auth.user?.department?.id;
  return Boolean(canManage.value && deptId && template.department?.id === deptId);
}

function canToggleGlobal(template) {
  return Boolean(canManageGlobal.value && canMutateTemplate(template) && template.department?.id);
}

function openCreate() {
  createDialogOpen.value = true;
}

function openEdit(template) {
  editingTemplate.value = template;
  editDialogOpen.value = true;
  if (template.is_global) {
    loadGlobalCriteriaPool();
  }
}

function openView(template) {
  selected.value = template;
}

function closePanel() {
  selected.value = null;
}

function onTemplateCreated(template) {
  allTemplates.value.unshift(template);
  nextTick(() => computeDefaultWidths());
}

function onTemplateUpdated(template) {
  applyTemplateUpdate(template);
}

function applyTemplateUpdate(template) {
  const idx = allTemplates.value.findIndex((t) => t.id === template.id);
  if (idx !== -1) allTemplates.value[idx] = template;
  if (selected.value?.id === template.id) selected.value = template;
}

async function doDelete() {
  if (!confirmDelete.value) return;
  const id = confirmDelete.value.id;
  const name = confirmDelete.value.name;
  deletingId.value = id;
  confirmDelete.value = null;

  try {
    await window.axios.delete(`/api/evaluation/templates/${id}`);
    allTemplates.value = allTemplates.value.filter((t) => t.id !== id);
    if (selected.value?.id === id) closePanel();
    showClientToast('success', `Đã xoá mẫu đánh giá "${name}".`);
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được mẫu đánh giá.');
  } finally {
    deletingId.value = null;
  }
}

async function toggleActive(template) {
  togglingId.value = template.id;
  try {
    const { data } = await window.axios.patch(`/api/evaluation/templates/${template.id}/toggle`);
    applyTemplateUpdate(data.template);
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được trạng thái.');
  } finally {
    togglingId.value = null;
  }
}

/**
 * Bật/tắt dùng chung toàn hệ thống — chỉ department_director trở lên
 * (evaluation.manage_global_template). Đây là thao tác đổi dữ liệu quan
 * trọng (mở mẫu cho mọi phòng ban thấy/dùng), nên chỉ gọi từ nút hành động
 * rõ ràng trong panel chi tiết, không phải click đơn trên dòng bảng (§14).
 */
async function toggleGlobalTemplate(template) {
  togglingId.value = template.id;
  try {
    const { data } = await window.axios.patch(`/api/evaluation/templates/${template.id}/toggle-global`);
    applyTemplateUpdate(data.template);
    showClientToast(
      'success',
      data.template.is_global
        ? `Đã đánh dấu "${data.template.name}" dùng chung toàn hệ thống.`
        : `Đã bỏ dùng chung toàn hệ thống cho "${data.template.name}".`,
    );
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được trạng thái dùng chung.');
  } finally {
    togglingId.value = null;
  }
}

async function duplicateTemplate(template) {
  duplicatingId.value = template.id;
  try {
    const { data } = await window.axios.post(`/api/evaluation/templates/${template.id}/duplicate`);
    allTemplates.value.unshift(data.template);
    showClientToast('success', `Đã nhân bản thành "${data.template.name}".`);
    await nextTick();
    computeDefaultWidths();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không nhân bản được mẫu đánh giá.');
  } finally {
    duplicatingId.value = null;
  }
}

function handleKeydown(e) {
  if (e.key !== 'Escape') return;
  if (confirmDelete.value) {
    confirmDelete.value = null;
    return;
  }
  if (openActionMenuId.value) {
    openActionMenuId.value = null;
    return;
  }
  // Dialog Tạo/Sửa (EvaluationTemplateCreateDialog/EditDialog) tự bắt Escape nội bộ.
  if (selected.value) closePanel();
}

// Dropdown Thao tác theo dòng (data-table.mdc §"Thao tác theo dòng = 1 nút dropdown").
// Teleport ra <body> + position:fixed vì bảng nằm trong wrap overflow:auto,
// nếu để menu absolute trong <td> sẽ bị cắt mất khi cuộn dọc/ngang.
const ACTION_MENU_WIDTH = 176; // khớp width .evtpl-page__action-menu (11rem)

function toggleActionMenu(id, event) {
  if (openActionMenuId.value === id) {
    openActionMenuId.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  actionMenuPos.top = rect.bottom + 4;
  actionMenuPos.left = Math.max(8, rect.right - ACTION_MENU_WIDTH);
  openActionMenuId.value = id;
}

function closeActionMenu() {
  openActionMenuId.value = null;
}

function runRowAction(fn, template) {
  closeActionMenu();
  fn(template);
}

function handleDocumentClick(event) {
  if (!openActionMenuId.value) return;
  // Menu teleport ra <body> nên không còn nằm trong .evtpl-page__actions (td) —
  // loại trừ cả 2 để click trigger/click item trong menu không tự đóng nhầm.
  if (event.target?.closest?.('.evtpl-page__actions, .evtpl-page__action-menu')) return;
  closeActionMenu();
}

function handleTableScroll() {
  if (openActionMenuId.value) closeActionMenu();
}

watch([query, statusFilter, isGlobalFilter, positionFilter, creatorFilter], () => {
  page.value = 1;
});
watch(visibleColumns, (value) => saveVisibility(COL_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_KEY, value), { deep: true });

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown);
  document.addEventListener('click', handleDocumentClick);
  await load();
  wrapObserver = new ResizeObserver(() => computeDefaultWidths());
  if (tableWrap.value) {
    wrapObserver.observe(tableWrap.value);
    tableWrap.value.addEventListener('scroll', handleTableScroll, { passive: true });
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
  document.removeEventListener('click', handleDocumentClick);
  tableWrap.value?.removeEventListener('scroll', handleTableScroll);
  wrapObserver?.disconnect();
});
</script>

<template>
  <div class="evtpl-page__wrap">
    <div class="evtpl-page" :class="{ 'evtpl-page--with-panel': selected }">
      <PageHeader
        title="Mẫu đánh giá"
        icon="clipboardCheck"
        :primary-action="canCreate ? { label: 'Tạo mẫu', onClick: openCreate } : null"
        export-label="Dữ liệu"
        :export-options="hasDepartment || canViewAll ? exportOptions : []"
        :export-busy-key="exporting ? 'excel' : undefined"
      />

      <div class="evtpl-page__main">
        <div v-if="hasVisibleFilterFields" class="evtpl-page__toolbar">
          <div class="evtpl-page__filters">
            <div v-if="visibleFilters.q" class="evtpl-page__field">
              <label class="evtpl-page__label" for="evtpl-q">Tìm kiếm</label>
              <input
                id="evtpl-q"
                v-model="query"
                type="search"
                class="evtpl-page__input"
                placeholder="Tên mẫu, mã mẫu…"
                @keydown.enter="page = 1"
              />
            </div>
            <div v-if="visibleFilters.status" class="evtpl-page__field">
              <label class="evtpl-page__label" for="evtpl-status">Trạng thái</label>
              <select id="evtpl-status" v-model="statusFilter" class="evtpl-page__input">
                <option value="">Tất cả trạng thái</option>
                <option value="active">Hoạt động</option>
                <option value="inactive">Không hoạt động</option>
              </select>
            </div>
            <div v-if="visibleFilters.is_global" class="evtpl-page__field">
              <label class="evtpl-page__label" for="evtpl-is-global">Dùng chung toàn hệ thống</label>
              <select id="evtpl-is-global" v-model="isGlobalFilter" class="evtpl-page__input">
                <option value="">Tất cả</option>
                <option value="yes">Có</option>
                <option value="no">Không</option>
              </select>
            </div>
            <div v-if="visibleFilters.position_id" class="evtpl-page__field">
              <label class="evtpl-page__label" for="evtpl-position-filter">Vị trí đánh giá</label>
              <select id="evtpl-position-filter" v-model="positionFilter" class="evtpl-page__input">
                <option value="">Tất cả vị trí</option>
                <option v-for="position in allPositions" :key="position.id" :value="String(position.id)">
                  {{ position.name }}
                </option>
              </select>
            </div>
            <div v-if="visibleFilters.creator_id" class="evtpl-page__field">
              <label class="evtpl-page__label" for="evtpl-creator-filter">Người tạo</label>
              <select id="evtpl-creator-filter" v-model="creatorFilter" class="evtpl-page__input">
                <option value="">Tất cả</option>
                <option v-for="creator in creatorOptions" :key="creator.id" :value="String(creator.id)">
                  {{ creator.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="pagerMeta.from"
          :to="pagerMeta.to"
          :total="pagerMeta.total"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="page = 1"
          @clear-filters="clearFilters"
          @update:page="page = $event"
          @update:per-page="perPage = $event; page = 1"
          @update:zoom="tableZoom = $event; saveZoom($event)"
        >
          <template #filters>
            <label v-for="item in FILTERS" :key="item.key" class="evtpl-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in COLUMNS" :key="col.key" class="evtpl-page__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="evtpl-page__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="evtpl-page__table-wrap hide-scrollbar"
          :class="{ 'evtpl-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <p v-if="!hasDepartment && !canViewAll" class="evtpl-page__empty">Tài khoản chưa gắn với phòng ban nào.</p>
          <p v-else-if="loading" class="evtpl-page__empty">Đang tải…</p>
          <p v-else-if="allTemplates.length === 0" class="evtpl-page__empty">
            Chưa có mẫu đánh giá nào.
            <template v-if="canCreate">Bấm <strong>Tạo mẫu</strong> để bắt đầu.</template>
          </p>
          <p v-else-if="filtered.length === 0" class="evtpl-page__empty">Không tìm thấy mẫu phù hợp.</p>

          <table v-else class="evtpl-page__table">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: (colWidths[col.key] ?? MIN_COL_PX) + 'px' }"
              />
              <col v-if="canManage" style="width:120px" />
            </colgroup>
            <thead>
              <tr>
                <th
                  v-for="col in shownColumns"
                  :key="col.key"
                  class="evtpl-page__th"
                  :class="{ 'evtpl-page__th--center': col.align === 'center' }"
                >
                  {{ col.label }}
                  <button
                    type="button"
                    class="evtpl-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @mousedown.stop="startResize($event, col.key)"
                  />
                </th>
                <th v-if="canManage" class="evtpl-page__th evtpl-page__th--center">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="entry in tableBodyRows" :key="entry.key">
                <tr
                  v-if="entry.kind === 'group'"
                  class="evtpl-page__tr evtpl-page__tr--group"
                  :aria-expanded="entry.collapsed ? 'false' : 'true'"
                  @click.stop="toggleDeptGroup(entry.groupKey)"
                >
                  <td :colspan="tableColspan" class="evtpl-page__td evtpl-page__td--group">
                    <div class="evtpl-page__group-inner">
                      <span class="evtpl-page__group-toggle" aria-hidden="true">
                        <AppIcon
                          :name="entry.collapsed ? 'chevronRight' : 'chevronDown'"
                          :size="16"
                          :stroke-width="1.75"
                        />
                      </span>
                      <span class="evtpl-page__group-copy">
                        <span class="evtpl-page__group-title">{{ entry.title }}</span>
                      </span>
                      <span class="evtpl-page__group-count">{{ entry.count }} mẫu</span>
                    </div>
                  </td>
                </tr>
                <tr
                  v-else
                  class="evtpl-page__tr"
                  :class="{ 'evtpl-page__tr--active': selected?.id === entry.template.id }"
                  @click="openView(entry.template)"
                >
                  <td v-if="visibleColumns.name" class="evtpl-page__td">
                    <span class="evtpl-page__name">{{ entry.template.name }}</span>
                  </td>
                  <td v-if="visibleColumns.code" class="evtpl-page__td">{{ entry.template.code }}</td>
                  <td v-if="visibleColumns.criteria_count" class="evtpl-page__td evtpl-page__td--center">
                    {{ entry.template.criteria_count }}
                  </td>
                  <td v-if="visibleColumns.positions" class="evtpl-page__td evtpl-page__td--positions">
                    <span v-if="positionNames(entry.template).length" class="evtpl-page__positions">
                      <span v-for="(name, idx) in positionNames(entry.template)" :key="idx">{{ name }}</span>
                    </span>
                    <template v-else>—</template>
                  </td>
                  <td v-if="visibleColumns.status" class="evtpl-page__td">
                    <span class="evtpl-page__status">
                      <span
                        class="evtpl-page__dot"
                        :class="entry.template.is_active ? 'evtpl-page__dot--active' : 'evtpl-page__dot--inactive'"
                      />
                      {{ entry.template.is_active ? 'Hoạt động' : 'Không hoạt động' }}
                    </span>
                  </td>
                  <td v-if="visibleColumns.is_global" class="evtpl-page__td">
                    <span class="evtpl-page__status">
                      <span
                        class="evtpl-page__dot"
                        :class="entry.template.is_global ? 'evtpl-page__dot--active' : 'evtpl-page__dot--inactive'"
                      />
                      {{ entry.template.is_global ? 'Có' : 'Không' }}
                    </span>
                  </td>
                  <td v-if="visibleColumns.department" class="evtpl-page__td">{{ departmentText(entry.template) }}</td>
                  <td v-if="visibleColumns.created_at" class="evtpl-page__td">
                    {{ formatDateTime(entry.template.created_at) || '—' }}
                  </td>
                  <td v-if="visibleColumns.creator" class="evtpl-page__td evtpl-page__td--center">
                    <UserAvatarTip :user="entry.template.creator" label="Người tạo" />
                  </td>
                  <td v-if="canManage" class="evtpl-page__td evtpl-page__td--center evtpl-page__td--action" @click.stop>
                    <span class="evtpl-page__actions">
                      <button
                        type="button"
                        class="evtpl-page__action-trigger"
                        :class="{ 'evtpl-page__action-trigger--open': openActionMenuId === entry.template.id }"
                        aria-haspopup="menu"
                        :aria-expanded="openActionMenuId === entry.template.id"
                        aria-label="Thao tác"
                        @click="toggleActionMenu(entry.template.id, $event)"
                      >
                        <AppIcon name="moreVertical" :size="16" />
                      </button>
                      <Teleport to="body">
                        <div
                          v-if="openActionMenuId === entry.template.id"
                          class="evtpl-page__action-menu"
                          role="menu"
                          aria-label="Thao tác mẫu đánh giá"
                          :style="{ top: actionMenuPos.top + 'px', left: actionMenuPos.left + 'px' }"
                        >
                          <button
                            v-if="canMutateTemplate(entry.template)"
                            type="button"
                            role="menuitem"
                            class="evtpl-page__action-item"
                            @click="runRowAction(openEdit, entry.template)"
                          >
                            <AppIcon name="pencil" :size="15" />
                            <span>Sửa</span>
                          </button>
                          <button
                            type="button"
                            role="menuitem"
                            class="evtpl-page__action-item"
                            :disabled="duplicatingId === entry.template.id"
                            @click="runRowAction(duplicateTemplate, entry.template)"
                          >
                            <AppIcon name="layers" :size="15" />
                            <span>Nhân bản</span>
                          </button>
                          <button
                            v-if="canMutateTemplate(entry.template)"
                            type="button"
                            role="menuitem"
                            class="evtpl-page__action-item"
                            :disabled="togglingId === entry.template.id"
                            @click="runRowAction(toggleActive, entry.template)"
                          >
                            <AppIcon :name="entry.template.is_active ? 'eyeOff' : 'eye'" :size="15" />
                            <span>{{ entry.template.is_active ? 'Ẩn' : 'Hiện' }}</span>
                          </button>
                          <button
                            v-if="canMutateTemplate(entry.template)"
                            type="button"
                            role="menuitem"
                            class="evtpl-page__action-item evtpl-page__action-item--danger"
                            :disabled="deletingId === entry.template.id"
                            @click="closeActionMenu(); confirmDelete = entry.template"
                          >
                            <AppIcon name="trash2" :size="15" />
                            <span>Xoá</span>
                          </button>
                        </div>
                      </Teleport>
                    </span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="pagerMeta.from"
          :to="pagerMeta.to"
          :total="pagerMeta.total"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          @update:page="page = $event"
          @update:per-page="perPage = $event; page = 1"
        />
      </div>

      <!-- ── panel chi tiết đẩy ngang ─────────────────────────────────────── -->
      <aside
        v-if="selected"
        class="evtpl-page__side"
        role="complementary"
        aria-label="Chi tiết mẫu đánh giá"
      >
        <div class="evtpl-page__side-head">
          <span class="evtpl-page__side-title">Chi tiết</span>
          <div class="evtpl-page__side-actions">
            <button v-if="canMutateTemplate(selected)" type="button" class="evtpl-page__edit-btn" @click="openEdit(selected)">
              <AppIcon name="pencil" :size="14" />
              Sửa
            </button>
            <button type="button" class="evtpl-page__panel-btn" aria-label="Đóng" @click="closePanel">
              <AppIcon name="close" :size="14" />
            </button>
          </div>
        </div>

        <div class="evtpl-page__side-body hide-scrollbar">
          <div class="evtpl-page__rows">
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Tên mẫu đánh giá</span>
              <span class="evtpl-page__row-value">{{ selected.name }}</span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Mã mẫu</span>
              <span class="evtpl-page__row-value">{{ selected.code }}</span>
            </div>
            <div v-if="selected.description" class="evtpl-page__row">
              <span class="evtpl-page__row-label">Mô tả</span>
              <span class="evtpl-page__row-value">{{ selected.description }}</span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Trạng thái</span>
              <span class="evtpl-page__row-value">
                <span class="evtpl-page__status">
                  <span
                    class="evtpl-page__dot"
                    :class="selected.is_active ? 'evtpl-page__dot--active' : 'evtpl-page__dot--inactive'"
                  />
                  {{ selected.is_active ? 'Hoạt động' : 'Không hoạt động' }}
                </span>
              </span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Dùng chung toàn hệ thống</span>
              <span class="evtpl-page__row-value">
                <span class="evtpl-page__status">
                  <span
                    class="evtpl-page__dot"
                    :class="selected.is_global ? 'evtpl-page__dot--active' : 'evtpl-page__dot--inactive'"
                  />
                  {{ selected.is_global ? 'Có' : 'Không' }}
                </span>
                <button
                  v-if="canToggleGlobal(selected)"
                  type="button"
                  class="evtpl-page__edit-btn"
                  :disabled="togglingId === selected.id"
                  @click="toggleGlobalTemplate(selected)"
                >
                  {{ selected.is_global ? 'Bỏ dùng chung' : 'Đánh dấu dùng chung' }}
                </button>
              </span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Phòng ban</span>
              <span class="evtpl-page__row-value">{{ departmentText(selected) }}</span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Điểm tối đa</span>
              <span class="evtpl-page__row-value">{{ selected.max_score }}</span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Vị trí đánh giá</span>
              <span class="evtpl-page__row-value evtpl-page__row-value--stack">
                <template v-if="positionNames(selected).length">
                  <span v-for="(name, idx) in positionNames(selected)" :key="idx">{{ name }}</span>
                </template>
                <template v-else>—</template>
              </span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Ngày tạo</span>
              <span class="evtpl-page__row-value">{{ formatDateTime(selected.created_at) || '—' }}</span>
            </div>
            <div class="evtpl-page__row">
              <span class="evtpl-page__row-label">Người tạo</span>
              <span class="evtpl-page__row-value">
                <UserAvatarTip :user="selected.creator" label="Người tạo" />
              </span>
            </div>
          </div>

          <h4 class="evtpl-page__side-subtitle">Tiêu chí đánh giá ({{ selected.criteria?.length ?? 0 }})</h4>
          <ul class="evtpl-page__criteria-list">
            <li v-for="c in selected.criteria" :key="c.id" class="evtpl-page__criteria-item">
              <span class="evtpl-page__criteria-copy">
                <span class="evtpl-page__criteria-name">{{ c.name }}</span>
                <span class="evtpl-page__criteria-meta">
                  <template v-if="c.count_in_total">
                    Trọng số: {{ c.weight_percent }}% · Tính vào tổng điểm: Có
                  </template>
                  <template v-else>Tính vào tổng điểm: Không</template>
                  <template v-if="c.required_score != null"> · Điểm yêu cầu: {{ c.required_score }}</template>
                </span>
              </span>
            </li>
          </ul>

          <template v-if="selected.custom_fields?.length">
            <h4 class="evtpl-page__side-subtitle">Trường tùy biến ({{ selected.custom_fields.length }})</h4>
            <ul class="evtpl-page__criteria-list">
              <li v-for="f in selected.custom_fields" :key="f.id" class="evtpl-page__criteria-item">
                <span class="evtpl-page__criteria-copy">
                  <span class="evtpl-page__criteria-name">{{ f.label }}</span>
                  <span class="evtpl-page__criteria-meta">
                    Loại: {{ CUSTOM_FIELD_TYPES.find((t) => t.value === f.field_type)?.label || f.field_type }}
                    <template v-if="f.is_required"> · Bắt buộc nhập</template>
                  </span>
                </span>
              </li>
            </ul>
          </template>
        </div>
      </aside>
    </div>

    <!-- ── dialog tạo mới / sửa mẫu — 2 component riêng biệt (không share state) ── -->
    <EvaluationTemplateCreateDialog
      v-model:open="createDialogOpen"
      :all-criteria="allCriteria"
      :global-criteria-pool="globalCriteriaPool"
      :loading-global-pool="loadingGlobalPool"
      :all-positions="allPositions"
      :can-manage-global="canManageGlobal"
      :force-global="forceGlobalCreate"
      @created="onTemplateCreated"
      @request-global-pool="loadGlobalCriteriaPool"
    />
    <EvaluationTemplateEditDialog
      v-model:open="editDialogOpen"
      :template="editingTemplate"
      :all-criteria="allCriteria"
      :global-criteria-pool="globalCriteriaPool"
      :loading-global-pool="loadingGlobalPool"
      :all-positions="allPositions"
      :can-manage-global="canManageGlobal"
      @updated="onTemplateUpdated"
      @request-global-pool="loadGlobalCriteriaPool"
    />

    <!-- ── confirm xoá ──────────────────────────────────────────────────── -->
    <div v-if="confirmDelete" class="evtpl-confirm-overlay" @click.self="confirmDelete = null">
      <div class="evtpl-confirm" role="alertdialog" aria-modal="true">
        <h3 class="evtpl-confirm__title">Xoá mẫu đánh giá</h3>
        <p class="evtpl-confirm__msg">
          Bạn có chắc muốn xoá mẫu đánh giá <strong>«{{ confirmDelete.name }}»</strong>? Hành động này không thể hoàn tác.
        </p>
        <div class="evtpl-confirm__actions">
          <button type="button" class="evtpl-page__btn evtpl-page__btn--danger" @click="doDelete">Xoá</button>
          <button type="button" class="evtpl-page__btn evtpl-page__btn--ghost" @click="confirmDelete = null">Huỷ</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ─── layout — khớp EvaluationView.vue ────────────────────────────────────── */
.evtpl-page__wrap {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

.evtpl-page {
  flex: 1;
  min-height: 0;
  display: flex;
  overflow: hidden;
  padding: var(--space-4);
  gap: var(--space-3);
}

.evtpl-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.evtpl-page__side {
  flex: 0 0 28rem;
  width: 28rem;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

/* ─── filters ─────────────────────────────────────────────────────────────── */
.evtpl-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0 0 var(--space-3);
}

.evtpl-page__filters {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.evtpl-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.evtpl-page__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.evtpl-page__input {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.evtpl-page__input:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.evtpl-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.evtpl-page__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

/* ─── table ───────────────────────────────────────────────────────────────── */
.evtpl-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.evtpl-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.evtpl-page__table {
  width: 100%;
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.evtpl-page__th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  letter-spacing: 0.02em;
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-page__th--center {
  text-align: center;
}

.evtpl-page__resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.evtpl-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 1px;
  height: 50%;
  background: var(--color-border);
  opacity: 0;
  transition: opacity 0.15s;
}

.evtpl-page__th:hover .evtpl-page__resize::after {
  opacity: 1;
}

.evtpl-page__tr {
  cursor: pointer;
}

.evtpl-page__tr:hover td {
  background: var(--color-surface-muted);
}

.evtpl-page__tr--active td {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.evtpl-page__tr--group {
  cursor: pointer;
}

.evtpl-page__tr--group:hover td {
  background: transparent;
}

.evtpl-page__tr--group:hover .evtpl-page__group-inner {
  background: var(--color-surface-muted);
}

.evtpl-page__td--group {
  padding: 0;
  overflow: visible;
  white-space: normal;
  vertical-align: middle;
}

.evtpl-page__group-inner {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem var(--space-4);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-text) 4%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-page__group-toggle {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.evtpl-page__group-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: baseline;
  gap: 0.5rem;
}

.evtpl-page__group-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.evtpl-page__group-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.evtpl-page__td {
  padding: var(--space-3) var(--space-4);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-page__td--center {
  text-align: center;
}

.evtpl-page__td--positions {
  white-space: normal;
  overflow: visible;
}

.evtpl-page__positions {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.evtpl-page__positions > span {
  white-space: nowrap;
}

.evtpl-page__name {
  font-weight: 600;
}

.evtpl-page__empty {
  margin: 2rem auto;
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.evtpl-page__actions {
  position: relative;
  display: inline-flex;
}

.evtpl-page__action-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.evtpl-page__action-trigger:hover,
.evtpl-page__action-trigger--open {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.evtpl-page__action-menu {
  position: fixed;
  z-index: 1200;
  width: 11rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  text-align: left;
}

.evtpl-page__action-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.evtpl-page__action-item:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.evtpl-page__action-item:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-page__action-item--danger {
  color: var(--color-danger);
}

.evtpl-page__action-item--danger:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface));
}

.evtpl-page__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
}

.evtpl-page__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.evtpl-page__dot--active {
  background: var(--color-success);
}

.evtpl-page__dot--inactive {
  background: var(--color-text-muted);
}

/* ─── panel ───────────────────────────────────────────────────────────────── */
.evtpl-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
  flex-shrink: 0;
}

.evtpl-page__side-title {
  font-weight: 700;
  font-size: 0.9375rem;
}

.evtpl-page__side-actions {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.evtpl-page__panel-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.evtpl-page__panel-btn:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.evtpl-page__edit-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.3125rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.evtpl-page__edit-btn:hover:not(:disabled) {
  background: var(--color-surface);
  color: var(--color-primary);
}

.evtpl-page__edit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-page__side-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

.evtpl-page__rows {
  display: flex;
  flex-direction: column;
}

.evtpl-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.875rem;
}

.evtpl-page__row:last-child {
  box-shadow: none;
}

.evtpl-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.evtpl-page__row-label::after {
  content: ':';
}

.evtpl-page__row-value {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.evtpl-page__row-value--stack {
  flex-direction: column;
  align-items: flex-end;
  gap: 0.125rem;
}

.evtpl-page__side-subtitle {
  margin: var(--space-4) 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.evtpl-page__criteria-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}

.evtpl-page__criteria-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-page__criteria-item:last-child {
  box-shadow: none;
}

.evtpl-page__criteria-copy {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.evtpl-page__criteria-name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.evtpl-page__criteria-meta {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

/* ─── nút confirm ─────────────────────────────────────────────────────────── */
.evtpl-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.evtpl-page__btn:hover {
  background: var(--color-primary-hover);
}

.evtpl-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.evtpl-page__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.evtpl-page__btn--ghost:hover {
  background: var(--color-border);
}

.evtpl-page__btn--danger {
  background: var(--color-danger);
}

.evtpl-confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: var(--color-sidebar-overlay, rgba(0, 0, 0, 0.4));
}

.evtpl-confirm {
  width: min(24rem, 100%);
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.18);
}

.evtpl-confirm__title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.evtpl-confirm__msg {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.evtpl-confirm__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

/* ─── responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1279px) {
  .evtpl-page {
    flex-direction: column;
  }

  .evtpl-page__side {
    width: 100%;
    flex: 1 1 auto;
    max-height: 42%;
  }
}

@media (max-width: 768px) {
  .evtpl-page {
    padding: var(--space-3);
  }

  .evtpl-page__filters {
    grid-template-columns: 1fr;
  }
}
</style>
