<script setup>
//
// superadmin/permissions — Ma trận phân quyền: xem quyền hiệu lực theo role,
// override (cấp/thu hồi) theo scope global/department/team, khôi phục mặc định.
// Backend (PermissionService::matrixFor()) là single source of truth — trang
// này không tự suy luận effective/default, chỉ đọc và gọi API khi toggle.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import PermissionScopeFilter from '../components/PermissionScopeFilter.vue';
import PermissionMatrixTable from '../components/PermissionMatrixTable.vue';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  FILTER_STORAGE_KEY,
  PERMISSION_FILTERS,
  PERMISSION_META_COLUMNS,
  PERMISSION_STATUS_OPTIONS,
  ZOOM_STORAGE_KEY,
  loadVisibility,
  permissionColumns,
  saveVisibility,
} from '../constants/permissions.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const MIN_COL_PX = 72;
let measureCtx = null;
let wrapObserver = null;

const scope = ref({ type: 'global', id: null });
const scopeLabel = ref('Toàn hệ thống');
const roles = ref([]);
const modules = ref([]);
const permissions = ref([]);
const matrix = ref({});
const isLoading = ref(false);
const pendingCells = reactive({});
const inspectPanel = ref(null); // { roleCode, permissionKey, cell }
const restoring = ref(false);
const pendingAction = ref(null); // { type: 'toggle'|'restore', roleCode, permissionKey, cell }
const confirmLoading = ref(false);

const query = ref('');
const moduleFilter = ref('all');
const statusFilter = ref('');
const page = ref(1);
const perPage = ref(20);

const tableWrap = ref(null);
const resizing = ref(false);
const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, PERMISSION_META_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, PERMISSION_FILTERS));
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const permissionByKey = computed(() => {
  const map = {};
  for (const perm of permissions.value) map[perm.key] = perm;
  return map;
});

const roleByCode = computed(() => {
  const map = {};
  for (const role of roles.value) map[role.code] = role;
  return map;
});

const tableColumns = computed(() => permissionColumns(roles.value));
const shownColumns = computed(() => tableColumns.value.filter((col) => visibleColumns[col.key]));

const activeCellKey = computed(() =>
  inspectPanel.value ? `${inspectPanel.value.roleCode}|${inspectPanel.value.permissionKey}` : null,
);

const blockedMessage = computed(() => {
  if (scope.value.type !== 'global' && !scope.value.id) {
    return `Vui lòng chọn ${scope.value.type === 'department' ? 'phòng ban' : 'nhóm'} để xem ma trận theo phạm vi này.`;
  }
  return null;
});

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    moduleFilter.value !== 'all' ||
    Boolean(statusFilter.value) ||
    scope.value.type !== 'global',
);

const hasVisibleFilterFields = computed(() => PERMISSION_FILTERS.some((item) => visibleFilters[item.key]));

const hiddenActiveFilterLabels = computed(() =>
  PERMISSION_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const filteredPermissions = computed(() => {
  const term = query.value.trim().toLowerCase();
  return permissions.value.filter((perm) => {
    if (moduleFilter.value !== 'all' && perm.module !== moduleFilter.value) return false;
    if (!permissionMatchesStatus(perm)) return false;
    if (!term) return true;
    return (
      perm.key.toLowerCase().includes(term) ||
      perm.label.toLowerCase().includes(term) ||
      (perm.description ?? '').toLowerCase().includes(term)
    );
  });
});

const totalCount = computed(() => filteredPermissions.value.length);
const lastPage = computed(() => Math.max(1, Math.ceil(totalCount.value / perPage.value) || 1));
const from = computed(() => (totalCount.value === 0 ? 0 : (page.value - 1) * perPage.value + 1));
const to = computed(() => Math.min(page.value * perPage.value, totalCount.value));

const pagedPermissions = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return filteredPermissions.value.slice(start, start + perPage.value);
});

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

function emptyCell() {
  return {
    default: false,
    effective: false,
    reserved: false,
    global_override: null,
    scoped_override: null,
    effective_source: 'config',
  };
}

function cellFor(roleCode, key) {
  return matrix.value?.[roleCode]?.[key] ?? emptyCell();
}

function permissionMatchesStatus(perm) {
  if (!statusFilter.value) return true;
  return roles.value.some((role) => {
    const cell = cellFor(role.code, perm.key);
    if (statusFilter.value === 'reserved') return cell.reserved;
    if (statusFilter.value === 'override') {
      return cell.global_override !== null || cell.scoped_override !== null;
    }
    if (cell.reserved) return false;
    return statusFilter.value === 'granted' ? cell.effective : !cell.effective;
  });
}

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'module') return moduleFilter.value !== 'all';
  if (key === 'status') return Boolean(statusFilter.value);
  if (key === 'scope') return scope.value.type !== 'global';
  return false;
}

function sourceExplanation(cell) {
  if (cell.effective_source === 'scoped') {
    return `Do có thiết lập riêng cho ${scopeLabel.value}`;
  }
  if (cell.effective_source === 'global') {
    return 'Do có thiết lập áp dụng cho toàn hệ thống';
  }
  return 'Theo thiết lập mặc định của hệ thống, chưa có thay đổi riêng';
}

function cellHasOverride(cell) {
  return cell.global_override !== null || cell.scoped_override !== null;
}

async function loadMatrix() {
  if (scope.value.type !== 'global' && !scope.value.id) {
    matrix.value = {};
    return;
  }

  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/permissions/matrix', {
      params: { scope_type: scope.value.type, scope_id: scope.value.id },
    });
    roles.value = data.roles ?? [];
    modules.value = data.modules ?? [];
    permissions.value = data.permissions ?? [];
    matrix.value = data.matrix ?? {};

    if (inspectPanel.value) {
      const { roleCode, permissionKey } = inspectPanel.value;
      const freshCell = matrix.value?.[roleCode]?.[permissionKey];
      if (freshCell) {
        inspectPanel.value = { roleCode, permissionKey, cell: freshCell };
      } else {
        inspectPanel.value = null;
      }
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được ma trận phân quyền.');
  } finally {
    isLoading.value = false;
  }
}

function onScopeChange(newScope) {
  scope.value = { type: newScope.type, id: newScope.id };
  scopeLabel.value = newScope.label ?? 'Toàn hệ thống';
  page.value = 1;
  loadMatrix();
}

function applyCellUpdate(roleCode, permissionKey, cell) {
  if (!matrix.value[roleCode]) matrix.value[roleCode] = {};
  matrix.value[roleCode][permissionKey] = cell;

  if (
    inspectPanel.value &&
    inspectPanel.value.roleCode === roleCode &&
    inspectPanel.value.permissionKey === permissionKey
  ) {
    inspectPanel.value = { roleCode, permissionKey, cell };
  }
}

function permissionLabel(key) {
  return permissionByKey.value[key]?.label ?? key;
}

function roleLabel(code) {
  return roleByCode.value[code]?.label ?? code;
}

const confirmOpen = computed({
  get: () => pendingAction.value !== null,
  set: (open) => {
    if (!open && !confirmLoading.value) pendingAction.value = null;
  },
});

const confirmCopy = computed(() => {
  const action = pendingAction.value;
  if (!action) {
    return { title: '', description: '', confirmLabel: 'Xác nhận', danger: false };
  }

  const perm = permissionLabel(action.permissionKey);
  const role = roleLabel(action.roleCode);

  if (action.type === 'restore') {
    return {
      title: 'Khôi phục mặc định?',
      description: `Bỏ thiết lập riêng của quyền “${perm}” cho vai trò ${role} trong ${scopeLabel.value}, quay về giá trị mặc định của hệ thống.`,
      confirmLabel: 'Khôi phục mặc định',
      danger: false,
    };
  }

  if (action.cell.effective) {
    return {
      title: 'Thu hồi quyền này?',
      description: `Thu hồi quyền “${perm}” của vai trò ${role} trong ${scopeLabel.value}. Người dùng với vai trò này sẽ không còn quyền đó tại phạm vi đang xem.`,
      confirmLabel: 'Thu hồi quyền',
      danger: true,
    };
  }

  return {
    title: 'Cấp quyền này?',
    description: `Cấp quyền “${perm}” cho vai trò ${role} trong ${scopeLabel.value}.`,
    confirmLabel: 'Cấp quyền',
    danger: false,
  };
});

function requestToggle({ roleCode, permissionKey, cell }) {
  if (cell.reserved) return;
  if (pendingCells[`${roleCode}|${permissionKey}`]) return;

  if (scope.value.type !== 'global' && !scope.value.id) {
    showClientToast('error', 'Vui lòng chọn phòng ban hoặc nhóm trước khi thay đổi quyền.');
    return;
  }

  inspectPanel.value = { roleCode, permissionKey, cell };
  pendingAction.value = { type: 'toggle', roleCode, permissionKey, cell };
}

function requestRestore() {
  if (!inspectPanel.value || restoring.value) return;
  pendingAction.value = {
    type: 'restore',
    roleCode: inspectPanel.value.roleCode,
    permissionKey: inspectPanel.value.permissionKey,
    cell: inspectPanel.value.cell,
  };
}

async function onConfirmAction() {
  const action = pendingAction.value;
  if (!action || confirmLoading.value) return;

  confirmLoading.value = true;
  try {
    if (action.type === 'restore') {
      await restoreDefault(action);
    } else {
      await applyToggle(action);
    }
    pendingAction.value = null;
  } catch {
    // Toast lỗi đã hiện; giữ hộp thoại để người dùng thử lại hoặc huỷ.
  } finally {
    confirmLoading.value = false;
  }
}

async function applyToggle({ roleCode, permissionKey, cell }) {
  const cellKey = `${roleCode}|${permissionKey}`;
  if (pendingCells[cellKey]) {
    throw new Error('pending');
  }

  const newValue = !cell.effective;
  const scopeType = scope.value.type;
  const scopeId = scope.value.id;

  if (scopeType !== 'global' && !scopeId) {
    showClientToast('error', 'Vui lòng chọn phòng ban hoặc nhóm trước khi thay đổi quyền.');
    throw new Error('missing-scope');
  }

  pendingCells[cellKey] = true;

  const overrideAtCurrentScope = scopeType === 'global' ? cell.global_override : cell.scoped_override;

  try {
    let cellResult;

    if (newValue === cell.default && overrideAtCurrentScope !== null) {
      const { data } = await window.axios.delete('/api/permissions/grants', {
        data: {
          role_code: roleCode,
          permission_key: permissionKey,
          scope_type: scopeType,
          scope_id: scopeId,
        },
      });
      cellResult = data.cell;
    } else {
      const { data } = await window.axios.put('/api/permissions/grants', {
        role_code: roleCode,
        permission_key: permissionKey,
        granted: newValue,
        scope_type: scopeType,
        scope_id: scopeId,
      });
      cellResult = data.cell;
    }

    applyCellUpdate(roleCode, permissionKey, cellResult);
    showClientToast(
      'success',
      newValue
        ? `Đã cấp quyền “${permissionLabel(permissionKey)}” cho vai trò ${roleLabel(roleCode)}.`
        : `Đã thu hồi quyền “${permissionLabel(permissionKey)}” của vai trò ${roleLabel(roleCode)}.`,
    );
  } catch (error) {
    const status = error?.response?.status;
    const message = error?.response?.data?.message;
    if (status === 422) {
      showClientToast('error', message || 'Dữ liệu không hợp lệ.');
    } else if (status === 403) {
      showClientToast('error', message || 'Bạn không có quyền thay đổi quyền hệ thống này.');
    } else {
      showClientToast('error', message || 'Không lưu được thay đổi. Vui lòng thử lại.');
    }
    throw error;
  } finally {
    delete pendingCells[cellKey];
  }
}

function onInspect({ roleCode, permissionKey, cell }) {
  if (!roleCode || !cell) return;
  inspectPanel.value = { roleCode, permissionKey, cell };
}

function inspectPermission(perm, roleCode = null) {
  if (!perm) return;
  const code =
    roleCode ||
    (inspectPanel.value?.permissionKey === perm.key ? inspectPanel.value.roleCode : null) ||
    shownColumns.value.find((col) => col.roleCode)?.roleCode ||
    roles.value[0]?.code;
  if (!code) return;
  inspectPanel.value = { roleCode: code, permissionKey: perm.key, cell: cellFor(code, perm.key) };
}

function closeInspect() {
  inspectPanel.value = null;
}

async function restoreDefault(action = inspectPanel.value) {
  if (!action || restoring.value) return;
  const { roleCode, permissionKey } = action;
  const scopeType = scope.value.type;
  const scopeId = scope.value.id;

  restoring.value = true;
  try {
    const { data } = await window.axios.delete('/api/permissions/grants', {
      data: {
        role_code: roleCode,
        permission_key: permissionKey,
        scope_type: scopeType,
        scope_id: scopeId,
      },
    });
    applyCellUpdate(roleCode, permissionKey, data.cell);
    showClientToast('success', `Đã khôi phục quyền “${permissionLabel(permissionKey)}” về mặc định.`);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không khôi phục được mặc định.');
    throw error;
  } finally {
    restoring.value = false;
  }
}

function goPage(next) {
  if (next < 1 || next > lastPage.value || next === page.value) return;
  page.value = next;
}

function applySearch() {
  page.value = 1;
}

function clearFilters() {
  query.value = '';
  moduleFilter.value = 'all';
  statusFilter.value = '';
  page.value = 1;
  onScopeChange({ type: 'global', id: null, label: 'Toàn hệ thống' });
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = tableColumns.value.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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
  const table = tableWrap.value?.querySelector('.perm-table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.perm-table__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function cellText(perm, key) {
  if (key === 'permission') return perm.label || '—';
  if (key === 'module') return perm.module || '—';
  if (key === 'key') return perm.key || '—';
  return '';
}

function columnContentWidth(key, fonts) {
  const col = shownColumns.value.find((item) => item.key === key);
  const label = col?.label ?? '';
  let maxW = measureText(label, fonts.header);
  if (col?.roleCode) {
    return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA));
  }
  for (const perm of pagedPermissions.value) {
    if (key === 'permission') {
      maxW = Math.max(maxW, measureText(cellText(perm, 'permission'), fonts.cell));
      const muted = perm.description || perm.key;
      if (muted) {
        maxW = Math.max(maxW, measureText(muted, fonts.muted));
      }
    } else {
      maxW = Math.max(maxW, measureText(cellText(perm, key), fonts.cell));
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

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
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

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (inspectPanel.value) {
    inspectPanel.value = null;
  }
}

function syncColumnVisibility(roleList) {
  const cols = permissionColumns(roleList);
  const loaded = loadVisibility(COLUMN_STORAGE_KEY, cols);
  const valid = new Set(cols.map((col) => col.key));
  for (const key of Object.keys(visibleColumns)) {
    if (!valid.has(key)) delete visibleColumns[key];
  }
  for (const col of cols) {
    if (typeof visibleColumns[col.key] !== 'boolean') {
      visibleColumns[col.key] = loaded[col.key];
    }
  }
}

watch(roles, (list) => syncColumnVisibility(list), { immediate: true });
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
watch(inspectPanel, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch(pagedPermissions, () => nextTick(fitColumnsToContent));
watch([query, moduleFilter, statusFilter, perPage], () => {
  page.value = 1;
});
watch(lastPage, (max) => {
  if (page.value > max) page.value = max;
});
watch(filteredPermissions, (list) => {
  if (inspectPanel.value && !list.some((perm) => perm.key === inspectPanel.value.permissionKey)) {
    inspectPanel.value = null;
  }
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadMatrix();
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
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="perm-page">
    <PageHeader
      title="Quản lý phân quyền"
      icon="shield"
      description="Xem và chỉnh quyền theo vai trò trong từng phạm vi: toàn hệ thống, phòng ban hoặc nhóm."
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Quản lý phân quyền' },
      ]"
    >
      <template #actions>
        <button type="button" class="perm-page__header-btn" :disabled="isLoading" @click="loadMatrix">
          <AppIcon name="refresh" :size="16" :class="{ 'perm-page__spin': isLoading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="perm-page__body">
      <div class="perm-page__main">
        <div v-if="hasVisibleFilterFields" class="perm-page__toolbar">
          <div class="perm-page__filters">
            <PermissionScopeFilter
              v-if="visibleFilters.scope"
              :model-value="scope"
              @update:model-value="onScopeChange"
            />

            <div v-if="visibleFilters.q" class="perm-page__field">
              <label class="perm-page__label" for="perm-q">Tìm quyền</label>
              <input
                id="perm-q"
                v-model="query"
                type="search"
                class="perm-page__input"
                placeholder="Ví dụ: quản lý nhóm"
                @keydown.enter="applySearch"
              />
            </div>

            <div v-if="visibleFilters.module" class="perm-page__field">
              <label class="perm-page__label" for="perm-module">Module</label>
              <select id="perm-module" v-model="moduleFilter" class="perm-page__input">
                <option value="all">Tất cả</option>
                <option v-for="item in modules" :key="item.key" :value="item.label">{{ item.label }}</option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="perm-page__field">
              <label class="perm-page__label" for="perm-status">Trạng thái</label>
              <select id="perm-status" v-model="statusFilter" class="perm-page__input">
                <option v-for="item in PERMISSION_STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="from"
          :to="to"
          :total="totalCount"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="applySearch"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in PERMISSION_FILTERS" :key="item.key" class="perm-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in tableColumns" :key="col.key" class="perm-page__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="perm-page__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="perm-page__table-wrap hide-scrollbar"
          :class="{ 'perm-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <PermissionMatrixTable
            :shown-columns="shownColumns"
            :permissions="pagedPermissions"
            :matrix="matrix"
            :pending-cells="pendingCells"
            :active-key="activeCellKey"
            :selected-key="inspectPanel?.permissionKey ?? null"
            :loading="isLoading"
            :blocked-message="blockedMessage"
            :column-widths="columnWidths"
            :table-width-px="tableWidthPx"
            @toggle="requestToggle"
            @inspect="onInspect"
            @inspect-row="inspectPermission"
            @resize-start="startResize"
          />
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="from"
          :to="to"
          :total="totalCount"
          :page="page"
          :last-page="lastPage"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />
      </div>

      <aside v-if="inspectPanel" class="perm-page__side" aria-label="Chi tiết quyền">
        <div class="perm-page__side-head">
          <h2 class="perm-page__side-title">Chi tiết quyền</h2>
          <button type="button" class="perm-page__icon-btn" aria-label="Đóng" @click="closeInspect">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <p class="perm-page__side-lead">
          {{ permissionByKey[inspectPanel.permissionKey]?.label ?? inspectPanel.permissionKey }}
        </p>
        <p v-if="permissionByKey[inspectPanel.permissionKey]?.description" class="perm-page__side-desc">
          {{ permissionByKey[inspectPanel.permissionKey].description }}
        </p>

        <div class="perm-page__rows">
          <div class="perm-page__row">
            <span class="perm-page__row-label">Module</span>
            <span class="perm-page__row-value">{{ permissionByKey[inspectPanel.permissionKey]?.module || '—' }}</span>
          </div>
          <div class="perm-page__row">
            <span class="perm-page__row-label">Mã quyền</span>
            <span class="perm-page__row-value">{{ inspectPanel.permissionKey }}</span>
          </div>
          <div class="perm-page__row">
            <span class="perm-page__row-label">Phạm vi đang xem</span>
            <span class="perm-page__row-value">{{ scopeLabel }}</span>
          </div>
        </div>

        <div class="perm-page__roles">
          <button
            v-for="role in roles"
            :key="role.code"
            type="button"
            class="perm-page__role"
            :class="{ 'perm-page__role--on': inspectPanel.roleCode === role.code }"
            @click="inspectPermission(permissionByKey[inspectPanel.permissionKey], role.code)"
          >
            <span>{{ role.label }}</span>
            <span
              class="perm-page__dot"
              :class="cellFor(role.code, inspectPanel.permissionKey).effective ? 'perm-page__dot--granted' : 'perm-page__dot--denied'"
            />
          </button>
        </div>

        <div class="perm-page__rows">
          <div class="perm-page__row">
            <span class="perm-page__row-label">Vai trò</span>
            <span class="perm-page__row-value">{{ roleByCode[inspectPanel.roleCode]?.label ?? inspectPanel.roleCode }}</span>
          </div>
          <div class="perm-page__row">
            <span class="perm-page__row-label">Hiện tại</span>
            <span class="perm-page__row-value">
              <span
                class="perm-page__dot"
                :class="inspectPanel.cell.effective ? 'perm-page__dot--granted' : 'perm-page__dot--denied'"
              />
              {{ inspectPanel.cell.effective ? 'Được cấp' : 'Không được cấp' }}
            </span>
          </div>
          <div class="perm-page__row">
            <span class="perm-page__row-label">Mặc định ban đầu</span>
            <span class="perm-page__row-value">
              <span
                class="perm-page__dot"
                :class="inspectPanel.cell.default ? 'perm-page__dot--granted' : 'perm-page__dot--denied'"
              />
              {{ inspectPanel.cell.default ? 'Được cấp' : 'Không được cấp' }}
            </span>
          </div>
        </div>

        <p class="perm-page__explain">{{ sourceExplanation(inspectPanel.cell) }}</p>

        <div v-if="inspectPanel.cell.reserved" class="perm-page__reserved">
          <AppIcon name="lock" :size="16" />
          Đây là quyền hệ thống, chỉ super_admin mới giữ được. Không thể đổi ở đây.
        </div>
        <template v-else>
          <button
            type="button"
            class="perm-page__toggle-btn"
            :disabled="pendingCells[`${inspectPanel.roleCode}|${inspectPanel.permissionKey}`]"
            @click="requestToggle({ roleCode: inspectPanel.roleCode, permissionKey: inspectPanel.permissionKey, cell: inspectPanel.cell })"
          >
            {{ inspectPanel.cell.effective ? 'Thu hồi quyền này' : 'Cấp quyền này' }}
          </button>

          <button
            v-if="cellHasOverride(inspectPanel.cell)"
            type="button"
            class="perm-page__restore-btn"
            :disabled="restoring"
            @click="requestRestore"
          >
            {{ restoring ? 'Đang khôi phục…' : 'Bỏ thiết lập riêng, quay về mặc định' }}
          </button>
        </template>
      </aside>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="confirmCopy.title"
      :description="confirmCopy.description"
      :confirm-label="confirmCopy.confirmLabel"
      :danger="confirmCopy.danger"
      :loading="confirmLoading"
      @confirm="onConfirmAction"
    />
  </section>
</template>

<style scoped>
.perm-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.perm-page__header-btn {
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

.perm-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.perm-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.perm-page__spin {
  animation: perm-page-spin 0.8s linear infinite;
}

@keyframes perm-page-spin {
  to {
    transform: rotate(360deg);
  }
}

.perm-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.perm-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.perm-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
}

.perm-page__filters {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.perm-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.perm-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.perm-page__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.perm-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.perm-page__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.perm-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.perm-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.perm-page__side {
  flex-shrink: 0;
  width: 20rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.perm-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.perm-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.perm-page__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.perm-page__icon-btn:hover {
  background: var(--color-surface-muted);
}

.perm-page__side-lead {
  margin: var(--space-3) 0 0.25rem;
  color: var(--color-text);
  font-weight: 700;
  font-size: 1rem;
}

.perm-page__side-desc {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.perm-page__rows {
  margin: 0 0 var(--space-3);
  display: flex;
  flex-direction: column;
}

.perm-page__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.perm-page__row:last-child {
  box-shadow: none;
}

.perm-page__row-label {
  color: var(--color-text-muted);
}

.perm-page__row-value {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-weight: 600;
  text-align: right;
}

.perm-page__roles {
  margin: 0 0 var(--space-3);
  display: flex;
  flex-direction: column;
}

.perm-page__role {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-2) 0;
  border: none;
  background: transparent;
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.perm-page__role:last-child {
  box-shadow: none;
}

.perm-page__role:hover {
  color: var(--color-primary);
}

.perm-page__role--on {
  font-weight: 700;
  color: var(--color-primary);
}

.perm-page__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.perm-page__dot--granted {
  background: var(--color-success);
}

.perm-page__dot--denied {
  background: var(--color-danger);
}

.perm-page__explain {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.perm-page__toggle-btn {
  width: 100%;
  padding: 0.625rem;
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-weight: 700;
  font-size: 0.875rem;
  cursor: pointer;
}

.perm-page__toggle-btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.perm-page__toggle-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.perm-page__restore-btn {
  width: 100%;
  margin-top: var(--space-2);
  padding: 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-weight: 600;
  font-size: 0.8125rem;
  cursor: pointer;
}

.perm-page__restore-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.perm-page__restore-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.perm-page__reserved {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

@media (max-width: 1024px) {
  .perm-page__body {
    flex-direction: column;
  }

  .perm-page__side {
    width: 100%;
    max-height: 42%;
  }

  .perm-page__table-wrap {
    min-height: 16rem;
  }

  .perm-page__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .perm-page {
    padding: var(--space-4);
  }

  .perm-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .perm-page {
    padding: var(--space-3);
  }

  .perm-page__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .perm-page__spin {
    animation: none;
  }
}
</style>
