<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { formatDate, formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import {
  ADMIN_COLUMNS,
  ADMIN_COLUMN_KEY,
  ADMIN_FILTERS,
  ADMIN_FILTER_KEY,
  ADMIN_WIDTH_KEY,
  ADMIN_ZOOM_KEY,
  PROGRESS_NOTE_MAX,
  REJECT_REASON_MAX,
  STATUS_HINT,
  STATUS_LABEL,
  STATUS_OPTIONS,
  STATUS_TONE,
  loadVisibility,
  localDateKey,
  previewText,
  saveVisibility,
} from '../constants/featureRequest.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const SENDER_AVATAR_EXTRA = 42;
const ACTION_COL_PX = 56;
const ACTION_MENU_WIDTH = 240;
const MIN_COL_PX = 72;

let measureCtx = null;
let wrapObserver = null;

const items = ref([]);
const loading = ref(false);
const acting = ref(false);
const selected = ref(null);
const page = ref(1);
const perPage = ref(20);

const query = ref('');
const appliedQuery = ref('');
const statusFilter = ref('');
const departmentFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const visibleColumns = reactive(loadVisibility(ADMIN_COLUMN_KEY, ADMIN_COLUMNS));
const visibleFilters = reactive(loadVisibility(ADMIN_FILTER_KEY, ADMIN_FILTERS));
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const tableWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const actionMenuId = ref(null);
const actionMenuPos = reactive({ top: 0, left: 0 });

const dialog = ref(null);
const actionItem = ref(null);
const approveForm = reactive({ expected_done_at: '', progress_note: '' });
const rejectReason = ref('');
const formError = ref('');
const doneConfirmOpen = ref(false);

const shownColumns = computed(() => ADMIN_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length + 1, 1));

const departmentOptions = computed(() => {
  const map = new Map();
  for (const item of items.value) {
    const key = item.department_id ? String(item.department_id) : 'none';
    if (!map.has(key)) {
      map.set(key, item.department_name || 'Chưa xác định');
    }
  }
  return [...map.entries()]
    .map(([value, label]) => ({ value, label }))
    .sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

const filteredItems = computed(() => {
  const needle = appliedQuery.value.trim().toLowerCase();
  return items.value.filter((item) => {
    if (statusFilter.value && item.status !== statusFilter.value) return false;
    if (departmentFilter.value) {
      const key = item.department_id ? String(item.department_id) : 'none';
      if (key !== departmentFilter.value) return false;
    }
    const day = localDateKey(item.created_at);
    if (dateFrom.value && day && day < dateFrom.value) return false;
    if (dateTo.value && day && day > dateTo.value) return false;
    if (!needle) return true;
    return (
      (item.description || '').toLowerCase().includes(needle) ||
      (item.created_by_name || '').toLowerCase().includes(needle) ||
      (item.created_by_email || '').toLowerCase().includes(needle) ||
      (item.page_title || '').toLowerCase().includes(needle) ||
      (item.department_name || '').toLowerCase().includes(needle)
    );
  });
});

const meta = computed(() => {
  const total = filteredItems.value.length;
  const last = Math.max(1, Math.ceil(total / perPage.value) || 1);
  const current = Math.min(page.value, last);
  const from = total === 0 ? 0 : (current - 1) * perPage.value + 1;
  const to = Math.min(current * perPage.value, total);
  return { current_page: current, last_page: last, total, from, to };
});

const pageItems = computed(() => {
  const start = (meta.value.current_page - 1) * perPage.value;
  return filteredItems.value.slice(start, start + perPage.value);
});

const hasActiveFilters = computed(
  () =>
    Boolean(appliedQuery.value.trim()) ||
    Boolean(statusFilter.value) ||
    Boolean(departmentFilter.value) ||
    Boolean(dateFrom.value) ||
    Boolean(dateTo.value),
);

const hiddenActiveFilterLabels = computed(() =>
  ADMIN_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map((item) => item.label),
);

const hasVisibleFilterFields = computed(() => ADMIN_FILTERS.some((item) => visibleFilters[item.key]));

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  const total = sum + ACTION_COL_PX;
  return total > ACTION_COL_PX ? `${total}px` : '100%';
});

const dialogTitle = computed(() => {
  if (dialog.value === 'reject') return 'Từ chối ghi nhận';
  if (dialog.value === 'progress') return 'Cập nhật tiến độ';
  if (dialog.value === 'approve') return 'Duyệt ghi nhận';
  return '';
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(appliedQuery.value.trim());
  if (key === 'status') return Boolean(statusFilter.value);
  if (key === 'department') return Boolean(departmentFilter.value);
  if (key === 'date_from') return Boolean(dateFrom.value);
  if (key === 'date_to') return Boolean(dateTo.value);
  return false;
}

function senderUser(item) {
  if (!item) return null;
  return {
    id: item.created_by,
    name: item.created_by_name || '—',
    email: item.created_by_email || null,
    avatar_url: item.created_by_avatar_url || null,
  };
}

function cellText(item, key) {
  if (key === 'created_at') return formatDateTime(item.created_at) || '—';
  if (key === 'sender') return item.created_by_name || '—';
  if (key === 'description') return previewText(item.description);
  if (key === 'status') return STATUS_LABEL[item.status] || item.status || '—';
  if (key === 'department') return item.department_name || 'Chưa xác định';
  if (key === 'page') return item.page_title || '—';
  if (key === 'reviewer') return item.reviewed_by_name || '—';
  if (key === 'expected_done_at') return item.expected_done_at ? formatDate(item.expected_done_at) : '—';
  if (key === 'id') return String(item.id ?? '—');
  return '—';
}

function replaceItem(item) {
  const index = items.value.findIndex((row) => row.id === item.id);
  if (index !== -1) items.value.splice(index, 1, item);
  if (selected.value?.id === item.id) selected.value = item;
  if (actionItem.value?.id === item.id) actionItem.value = item;
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/superadmin/feature-requests');
    const rows = [];
    for (const group of data.groups ?? []) {
      for (const item of group.items ?? []) {
        rows.push({
          ...item,
          department_name: item.department_name || group.department_name || 'Chưa xác định',
        });
      }
    }
    rows.sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)));
    items.value = rows;
    if (selected.value && !rows.some((row) => row.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch {
    showClientToast('error', 'Không tải được danh sách ghi nhận.');
  } finally {
    loading.value = false;
  }
}

function runSearch() {
  appliedQuery.value = query.value.trim();
  page.value = 1;
}

function clearFilters() {
  query.value = '';
  appliedQuery.value = '';
  statusFilter.value = '';
  departmentFilter.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  page.value = 1;
}

function goPage(next) {
  if (next < 1 || next > meta.value.last_page || next === meta.value.current_page) return;
  page.value = next;
}

async function openDetail(item) {
  closeActionMenu();
  try {
    const { data } = await window.axios.get(`/api/superadmin/feature-requests/${item.id}`);
    replaceItem(data.item);
    selected.value = data.item;
  } catch {
    showClientToast('error', 'Không mở được chi tiết ghi nhận.');
  }
}

function menuItemCount(item) {
  let count = 1;
  if (item.status === 'approved') count += 2;
  else if (item.status === 'pending' || item.status === 'reviewing') count += 1;
  if (['pending', 'reviewing', 'approved'].includes(item.status)) count += 1;
  return count;
}

function toggleActionMenu(item, event) {
  event.stopPropagation();
  if (actionMenuId.value === item.id) {
    actionMenuId.value = null;
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const menuHeight = 8 + menuItemCount(item) * 36;
  const spaceBelow = window.innerHeight - rect.bottom;
  actionMenuPos.top = spaceBelow < menuHeight ? Math.max(8, rect.top - menuHeight) : rect.bottom + 4;
  actionMenuPos.left = Math.max(8, Math.min(rect.right - ACTION_MENU_WIDTH, window.innerWidth - ACTION_MENU_WIDTH - 8));
  actionMenuId.value = item.id;
}

function closeActionMenu() {
  actionMenuId.value = null;
}

function openApprove(item, kind) {
  closeActionMenu();
  actionItem.value = item;
  approveForm.expected_done_at = item.expected_done_at || '';
  approveForm.progress_note = item.progress_note || '';
  formError.value = '';
  dialog.value = kind;
}

function openReject(item) {
  closeActionMenu();
  actionItem.value = item;
  rejectReason.value = '';
  formError.value = '';
  dialog.value = 'reject';
}

function askDone(item) {
  closeActionMenu();
  actionItem.value = item;
  doneConfirmOpen.value = true;
}

function closeDialog() {
  if (acting.value) return;
  dialog.value = null;
  formError.value = '';
}

async function submitApprove() {
  if (!actionItem.value || acting.value) return;
  acting.value = true;
  formError.value = '';
  const savingProgress = dialog.value === 'progress';
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${actionItem.value.id}/approve`, {
      expected_done_at: approveForm.expected_done_at || null,
      progress_note: approveForm.progress_note || null,
    });
    replaceItem(data.item);
    dialog.value = null;
    showClientToast('success', savingProgress ? 'Đã lưu tiến độ.' : 'Đã duyệt ghi nhận.');
  } catch (error) {
    formError.value = error?.response?.data?.message || 'Không duyệt được ghi nhận.';
  } finally {
    acting.value = false;
  }
}

async function submitReject() {
  if (!actionItem.value || acting.value) return;
  if (!rejectReason.value.trim()) {
    formError.value = 'Cần nêu lý do từ chối.';
    return;
  }
  acting.value = true;
  formError.value = '';
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${actionItem.value.id}/reject`, {
      reject_reason: rejectReason.value.trim(),
    });
    replaceItem(data.item);
    dialog.value = null;
    showClientToast('success', 'Đã từ chối ghi nhận.');
  } catch (error) {
    formError.value = error?.response?.data?.message || 'Không từ chối được ghi nhận.';
  } finally {
    acting.value = false;
  }
}

async function confirmDone() {
  if (!actionItem.value || acting.value) return;
  acting.value = true;
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${actionItem.value.id}/done`);
    replaceItem(data.item);
    doneConfirmOpen.value = false;
    showClientToast('success', 'Đã đánh dấu hoàn thành.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được.');
  } finally {
    acting.value = false;
  }
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ADMIN_ZOOM_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(ADMIN_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua.
  }
  return {};
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function measureText(text, font) {
  if (!measureCtx) measureCtx = document.createElement('canvas').getContext('2d');
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.fr-admin__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    muted: fontOf(table?.querySelector('.fr-admin__muted'), '400 12px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = ADMIN_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const item of pageItems.value) {
    maxW = Math.max(maxW, measureText(cellText(item, key), fonts.cell));
    if (key === 'sender' && item.created_by_email) {
      maxW = Math.max(maxW, measureText(item.created_by_email, fonts.muted));
    }
    if (key === 'reviewer' && item.reviewed_by_email) {
      maxW = Math.max(maxW, measureText(item.reviewed_by_email, fonts.muted));
    }
  }
  const extra = key === 'sender' ? SENDER_AVATAR_EXTRA : 0;
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA + extra));
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
  for (const key of keys) measured[key] = columnContentWidth(key, fonts);
  const next = distributeExtraWidth(measured, keys, wrap.clientWidth - ACTION_COL_PX);
  for (const key of keys) columnWidths[key] = next[key];
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
    const remaining = ADMIN_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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

function handleDocumentClick(event) {
  if (!actionMenuId.value) return;
  if (event.target?.closest?.('.fr-admin__actions, .fr-admin__action-menu')) return;
  closeActionMenu();
}

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (dialog.value) {
    closeDialog();
    return;
  }
  if (actionMenuId.value) {
    closeActionMenu();
    return;
  }
  if (selected.value && !doneConfirmOpen.value) selected.value = null;
}

watch(visibleColumns, (value) => saveVisibility(ADMIN_COLUMN_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(ADMIN_FILTER_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(ADMIN_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ADMIN_ZOOM_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch([statusFilter, departmentFilter, dateFrom, dateTo, perPage], () => {
  page.value = 1;
});
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch(pageItems, () => nextTick(fitColumnsToContent));

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  document.addEventListener('mousedown', handleDocumentClick);
  load();
  nextTick(() => {
    fitColumnsToContent();
    if (!tableWrap.value) return;
    let lastWrapWidth = tableWrap.value.clientWidth;
    wrapObserver = new ResizeObserver((entries) => {
      const width = Math.round(entries[0]?.contentRect?.width || 0);
      if (!width || width === lastWrapWidth || resizing.value) return;
      lastWrapWidth = width;
      fitColumnsToContent();
    });
    wrapObserver.observe(tableWrap.value);
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.removeEventListener('mousedown', handleDocumentClick);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="fr-admin">
    <PageHeader
      title="Ghi nhận yêu cầu tính năng"
      icon="alertTriangle"
      description="Xem và xử lý yêu cầu tính năng từ các phòng ban."
      :subtitle="`${items.length} ghi nhận`"
    >
      <template #actions>
        <button type="button" class="fr-admin__header-btn" :disabled="loading" @click="load">
          <AppIcon name="refresh" :size="16" :class="{ 'fr-admin__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="fr-admin__body">
      <div class="fr-admin__main">
        <div v-if="hasVisibleFilterFields" class="fr-admin__toolbar">
          <div class="fr-admin__filters">
            <div v-if="visibleFilters.q" class="fr-admin__field">
              <label class="fr-admin__label" for="fr-admin-q">Tìm kiếm</label>
              <input
                id="fr-admin-q"
                v-model="query"
                type="search"
                class="fr-admin__input"
                placeholder="Nội dung, người gửi, trang…"
                @keydown.enter="runSearch"
              />
            </div>
            <div v-if="visibleFilters.status" class="fr-admin__field">
              <label class="fr-admin__label" for="fr-admin-status">Trạng thái</label>
              <select id="fr-admin-status" v-model="statusFilter" class="fr-admin__input">
                <option v-for="item in STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
            <div v-if="visibleFilters.department" class="fr-admin__field">
              <label class="fr-admin__label" for="fr-admin-dept">Phòng ban</label>
              <select id="fr-admin-dept" v-model="departmentFilter" class="fr-admin__input">
                <option value="">Tất cả phòng ban</option>
                <option v-for="item in departmentOptions" :key="item.value" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
            <div v-if="visibleFilters.date_from" class="fr-admin__field">
              <label class="fr-admin__label" for="fr-admin-from">Từ ngày</label>
              <input id="fr-admin-from" v-model="dateFrom" type="date" class="fr-admin__input" />
            </div>
            <div v-if="visibleFilters.date_to" class="fr-admin__field">
              <label class="fr-admin__label" for="fr-admin-to">Đến ngày</label>
              <input id="fr-admin-to" v-model="dateTo" type="date" class="fr-admin__input" />
            </div>
          </div>
        </div>

        <TablePagesBar
          placement="top"
          :from="meta.from"
          :to="meta.to"
          :total="meta.total"
          :page="meta.current_page"
          :last-page="meta.last_page"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="runSearch"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in ADMIN_FILTERS" :key="item.key" class="fr-admin__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in ADMIN_COLUMNS" :key="col.key" class="fr-admin__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="fr-admin__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="fr-admin__table-wrap hide-scrollbar"
          :class="{ 'fr-admin__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="fr-admin__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
              <col :style="{ width: `${ACTION_COL_PX}px` }" />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="fr-admin__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th class="fr-admin__th-action">
                  <span class="fr-admin__sr">Thao tác</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="fr-admin__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="pageItems.length === 0">
                <td :colspan="colSpan" class="fr-admin__empty">
                  {{ items.length === 0 ? 'Chưa có ghi nhận nào.' : 'Không có ghi nhận khớp bộ lọc.' }}
                </td>
              </tr>
              <tr
                v-for="item in pageItems"
                v-else
                :key="item.id"
                :class="{ 'fr-admin__row--active': selected?.id === item.id }"
                @click="openDetail(item)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <span v-if="col.key === 'sender'" class="fr-admin__person">
                    <UserAvatarTip :user="senderUser(item)" label="Người gửi" />
                    <span class="fr-admin__person-text">
                      <span>{{ cellText(item, 'sender') }}</span>
                      <span v-if="item.created_by_email" class="fr-admin__muted">{{ item.created_by_email }}</span>
                    </span>
                  </span>
                  <span v-else-if="col.key === 'reviewer'" class="fr-admin__person-text">
                    <span>{{ cellText(item, 'reviewer') }}</span>
                    <span v-if="item.reviewed_by_email" class="fr-admin__muted">{{ item.reviewed_by_email }}</span>
                  </span>
                  <span v-else-if="col.key === 'status'" class="fr-admin__status">
                    <span class="fr-admin__dot" :class="`fr-admin__dot--${STATUS_TONE[item.status] || 'neutral'}`" />
                    {{ STATUS_LABEL[item.status] || item.status }}
                  </span>
                  <span v-else class="fr-admin__cell">{{ cellText(item, col.key) }}</span>
                </td>
                <td class="fr-admin__td-action" @click.stop>
                  <span class="fr-admin__actions">
                    <button
                      type="button"
                      class="fr-admin__action-trigger"
                      :class="{ 'fr-admin__action-trigger--open': actionMenuId === item.id }"
                      aria-haspopup="menu"
                      :aria-expanded="actionMenuId === item.id"
                      aria-label="Thao tác"
                      @click="toggleActionMenu(item, $event)"
                    >
                      <AppIcon name="moreVertical" :size="16" />
                    </button>
                    <Teleport to="body">
                      <div
                        v-if="actionMenuId === item.id"
                        class="fr-admin__action-menu"
                        role="menu"
                        aria-label="Thao tác ghi nhận"
                        :style="{ top: `${actionMenuPos.top}px`, left: `${actionMenuPos.left}px` }"
                      >
                        <button type="button" role="menuitem" class="fr-admin__action-item" @click="openDetail(item)">
                          <AppIcon name="eye" :size="15" />
                          <span>Xem chi tiết</span>
                        </button>
                        <button
                          v-if="item.status === 'pending' || item.status === 'reviewing'"
                          type="button"
                          role="menuitem"
                          class="fr-admin__action-item"
                          @click="openApprove(item, 'approve')"
                        >
                          <AppIcon name="check" :size="15" />
                          <span>Duyệt</span>
                        </button>
                        <button
                          v-if="item.status === 'approved'"
                          type="button"
                          role="menuitem"
                          class="fr-admin__action-item"
                          @click="openApprove(item, 'progress')"
                        >
                          <AppIcon name="pencil" :size="15" />
                          <span>Cập nhật tiến độ</span>
                        </button>
                        <button
                          v-if="item.status === 'approved'"
                          type="button"
                          role="menuitem"
                          class="fr-admin__action-item"
                          @click="askDone(item)"
                        >
                          <AppIcon name="clipboardCheck" :size="15" />
                          <span>Đánh dấu hoàn thành</span>
                        </button>
                        <button
                          v-if="item.status === 'pending' || item.status === 'reviewing' || item.status === 'approved'"
                          type="button"
                          role="menuitem"
                          class="fr-admin__action-item fr-admin__action-item--danger"
                          @click="openReject(item)"
                        >
                          <AppIcon name="close" :size="15" />
                          <span>Từ chối</span>
                        </button>
                      </div>
                    </Teleport>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          placement="bottom"
          paging-only
          :from="meta.from"
          :to="meta.to"
          :total="meta.total"
          :page="meta.current_page"
          :last-page="meta.last_page"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />
      </div>

      <aside v-if="selected" class="fr-admin__side" aria-label="Chi tiết ghi nhận">
        <div class="fr-admin__side-head">
          <h2 class="fr-admin__side-title">Chi tiết ghi nhận</h2>
          <button type="button" class="fr-admin__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="fr-admin__side-lead" :class="`fr-admin__side-lead--${STATUS_TONE[selected.status] || 'neutral'}`">
          <span class="fr-admin__dot" :class="`fr-admin__dot--${STATUS_TONE[selected.status] || 'neutral'}`" />
          <div>
            <span class="fr-admin__side-kicker">{{ STATUS_LABEL[selected.status] || selected.status }}</span>
            <p class="fr-admin__side-desc">{{ selected.description }}</p>
          </div>
        </div>

        <div class="fr-admin__rows">
          <div class="fr-admin__row">
            <span class="fr-admin__row-label">Người gửi</span>
            <span class="fr-admin__row-value fr-admin__row-actor">
              <UserAvatarTip :user="senderUser(selected)" label="Người gửi" />
              <span>{{ selected.created_by_name || '—' }}</span>
            </span>
          </div>
          <div v-if="selected.created_by_email" class="fr-admin__row">
            <span class="fr-admin__row-label">Email</span>
            <span class="fr-admin__row-value">{{ selected.created_by_email }}</span>
          </div>
          <div class="fr-admin__row">
            <span class="fr-admin__row-label">Phòng ban</span>
            <span class="fr-admin__row-value">{{ selected.department_name || 'Chưa xác định' }}</span>
          </div>
          <div v-if="selected.page_title" class="fr-admin__row">
            <span class="fr-admin__row-label">Trang đính kèm</span>
            <a
              v-if="selected.page_url"
              class="fr-admin__row-value fr-admin__link"
              :href="selected.page_url"
              target="_blank"
              rel="noopener"
            >
              {{ selected.page_title }}
            </a>
            <span v-else class="fr-admin__row-value">{{ selected.page_title }}</span>
          </div>
          <div class="fr-admin__row">
            <span class="fr-admin__row-label">Thời gian gửi</span>
            <span class="fr-admin__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div v-if="selected.reviewed_by_name" class="fr-admin__row">
            <span class="fr-admin__row-label">Người xử lý</span>
            <span class="fr-admin__row-value">{{ selected.reviewed_by_name }}</span>
          </div>
          <div v-if="selected.expected_done_at" class="fr-admin__row">
            <span class="fr-admin__row-label">Ngày hoàn thành dự kiến</span>
            <span class="fr-admin__row-value">{{ formatDate(selected.expected_done_at) }}</span>
          </div>
          <div v-if="selected.progress_note" class="fr-admin__row">
            <span class="fr-admin__row-label">Ghi chú tiến độ</span>
            <span class="fr-admin__row-value">{{ selected.progress_note }}</span>
          </div>
          <div v-if="selected.reject_reason" class="fr-admin__row">
            <span class="fr-admin__row-label">Lý do từ chối</span>
            <span class="fr-admin__row-value">{{ selected.reject_reason }}</span>
          </div>
          <div v-if="selected.done_at" class="fr-admin__row">
            <span class="fr-admin__row-label">Hoàn thành lúc</span>
            <span class="fr-admin__row-value">{{ formatDateTime(selected.done_at) }}</span>
          </div>
          <div class="fr-admin__row">
            <span class="fr-admin__row-label">Tình trạng</span>
            <span class="fr-admin__row-value">{{ STATUS_HINT[selected.status] || '—' }}</span>
          </div>
          <div class="fr-admin__row">
            <span class="fr-admin__row-label">Mã bản ghi</span>
            <span class="fr-admin__row-value">{{ selected.id }}</span>
          </div>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <Transition name="fr-admin-fade">
        <div v-if="dialog" class="fr-admin__dialog" role="presentation" @mousedown.self="closeDialog">
          <div
            class="fr-admin__dialog-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fr-admin-dialog-title"
          >
            <div class="fr-admin__dialog-head">
              <span class="fr-admin__dialog-icon" aria-hidden="true">
                <AppIcon :name="dialog === 'reject' ? 'close' : 'check'" :size="22" :stroke-width="1.75" />
              </span>
              <div class="fr-admin__dialog-copy">
                <h2 id="fr-admin-dialog-title" class="fr-admin__dialog-title">{{ dialogTitle }}</h2>
              </div>
              <button type="button" class="fr-admin__dialog-close" aria-label="Đóng" :disabled="acting" @click="closeDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="fr-admin__dialog-body hide-scrollbar">
              <div class="fr-admin__form">
                <div class="fr-admin__field fr-admin__field--full">
                  <span class="fr-admin__label">Yêu cầu</span>
                  <p class="fr-admin__quote">{{ actionItem?.description }}</p>
                </div>

                <template v-if="dialog === 'approve' || dialog === 'progress'">
                  <div class="fr-admin__field">
                    <label class="fr-admin__label" for="fr-admin-done-at">Ngày hoàn thành dự kiến</label>
                    <input id="fr-admin-done-at" v-model="approveForm.expected_done_at" type="date" class="fr-admin__input" />
                  </div>
                  <div class="fr-admin__field fr-admin__field--full fr-admin__field--grow">
                    <label class="fr-admin__label" for="fr-admin-note">Ghi chú tiến độ</label>
                    <textarea
                      id="fr-admin-note"
                      v-model="approveForm.progress_note"
                      class="fr-admin__textarea"
                      :maxlength="PROGRESS_NOTE_MAX"
                      placeholder="Vd. Sẽ triển khai trong đợt cập nhật tháng sau."
                    />
                    <span class="fr-admin__count">{{ approveForm.progress_note.length }}/{{ PROGRESS_NOTE_MAX }}</span>
                  </div>
                </template>

                <div v-else class="fr-admin__field fr-admin__field--full fr-admin__field--grow">
                  <label class="fr-admin__label" for="fr-admin-reason">
                    Lý do từ chối <span class="fr-admin__required" aria-hidden="true">*</span>
                  </label>
                  <textarea
                    id="fr-admin-reason"
                    v-model="rejectReason"
                    class="fr-admin__textarea"
                    :maxlength="REJECT_REASON_MAX"
                    placeholder="Vd. Tính năng đã có sẵn ở mục Báo cáo."
                  />
                  <span class="fr-admin__count">{{ rejectReason.length }}/{{ REJECT_REASON_MAX }}</span>
                </div>

                <p v-if="formError" class="fr-admin__form-error">{{ formError }}</p>
              </div>
            </div>

            <div class="fr-admin__dialog-actions">
              <button type="button" class="fr-admin__btn fr-admin__btn--ghost" :disabled="acting" @click="closeDialog">
                Huỷ
              </button>
              <button
                v-if="dialog === 'reject'"
                type="button"
                class="fr-admin__btn fr-admin__btn--danger"
                :disabled="acting || !rejectReason.trim()"
                @click="submitReject"
              >
                {{ acting ? 'Đang lưu…' : 'Xác nhận từ chối' }}
              </button>
              <button v-else type="button" class="fr-admin__btn" :disabled="acting" @click="submitApprove">
                {{ acting ? 'Đang lưu…' : dialog === 'progress' ? 'Lưu tiến độ' : 'Duyệt ghi nhận' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ConfirmDialog
      v-model:open="doneConfirmOpen"
      title="Đánh dấu hoàn thành"
      description="Ghi nhận sẽ chuyển sang đã hoàn thành và người gửi nhận được thông báo."
      confirm-label="Hoàn thành"
      :loading="acting"
      @confirm="confirmDone"
    />
  </section>
</template>

<style scoped>
.fr-admin {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.fr-admin__header-btn {
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

.fr-admin__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.fr-admin__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-admin__spin {
  animation: fr-admin-spin 0.8s linear infinite;
}

@keyframes fr-admin-spin {
  to {
    transform: rotate(360deg);
  }
}

.fr-admin__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.fr-admin__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.fr-admin__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0;
}

.fr-admin__filters {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
}

.fr-admin__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.fr-admin__field--full {
  grid-column: 1 / -1;
}

.fr-admin__field--grow {
  flex: 1;
  min-height: 0;
}

.fr-admin__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-admin__input,
.fr-admin__textarea {
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

.fr-admin__textarea {
  flex: 1;
  min-height: min(32rem, 55vh);
  resize: none;
}

.fr-admin__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.fr-admin__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-admin__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.fr-admin__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.fr-admin__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.fr-admin__table thead th {
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
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-admin__resize {
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

.fr-admin__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.fr-admin__resize:hover::after {
  background: var(--color-primary);
}

.fr-admin__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-admin__table tbody tr {
  cursor: pointer;
}

.fr-admin__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.fr-admin__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.fr-admin__cell {
  display: block;
  white-space: nowrap;
}

.fr-admin__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.fr-admin__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.fr-admin__person-text span {
  display: block;
  white-space: nowrap;
}

.fr-admin__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-admin__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
}

.fr-admin__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.fr-admin__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.fr-admin__th-action,
.fr-admin__td-action {
  padding-left: var(--space-2);
  padding-right: var(--space-2);
  text-align: center;
}

.fr-admin__actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.fr-admin__action-trigger {
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

.fr-admin__action-trigger:hover,
.fr-admin__action-trigger--open {
  background: var(--color-surface);
  color: var(--color-text);
}

.fr-admin__action-menu {
  position: fixed;
  z-index: 1200;
  width: 15rem;
  display: flex;
  flex-direction: column;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
  text-align: left;
}

.fr-admin__action-item {
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

.fr-admin__action-item:hover {
  background: var(--color-surface-muted);
}

.fr-admin__action-item--danger {
  color: var(--color-danger);
}

.fr-admin__action-item--danger:hover {
  background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface));
}

.fr-admin__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.fr-admin__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.fr-admin__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.fr-admin__icon-btn {
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

.fr-admin__icon-btn:hover {
  background: var(--color-surface);
}

.fr-admin__side-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: var(--space-3) 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.fr-admin__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--color-border);
}

.fr-admin__side-lead--neutral::before {
  background: var(--color-text-muted);
}

.fr-admin__side-lead--warning::before {
  background: var(--color-warning);
}

.fr-admin__side-lead--info::before {
  background: var(--color-info);
}

.fr-admin__side-lead--success::before {
  background: var(--color-success);
}

.fr-admin__side-lead--danger::before {
  background: var(--color-danger);
}

.fr-admin__side-kicker {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-admin__side-desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.45;
  white-space: pre-wrap;
}

.fr-admin__dot {
  flex-shrink: 0;
  margin-top: 0.35rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.fr-admin__status .fr-admin__dot {
  margin-top: 0;
}

.fr-admin__dot--neutral {
  background: var(--color-text-muted);
}

.fr-admin__dot--warning {
  background: var(--color-warning);
}

.fr-admin__dot--info {
  background: var(--color-info);
}

.fr-admin__dot--success {
  background: var(--color-success);
}

.fr-admin__dot--danger {
  background: var(--color-danger);
}

.fr-admin__rows {
  display: flex;
  flex-direction: column;
}

.fr-admin__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.fr-admin__row:last-child {
  box-shadow: none;
}

.fr-admin__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.fr-admin__row-label::after {
  content: ':';
}

.fr-admin__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.fr-admin__row-actor {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.fr-admin__link {
  color: var(--color-primary);
}

.fr-admin__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.fr-admin__dialog-panel {
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: 1.5rem 1.75rem 1.25rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.fr-admin__dialog-head,
.fr-admin__dialog-actions {
  flex-shrink: 0;
}

.fr-admin__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.fr-admin__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.fr-admin__dialog-copy {
  flex: 1;
  min-width: 0;
}

.fr-admin__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
}

.fr-admin__dialog-close {
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

.fr-admin__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.fr-admin__dialog-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.fr-admin__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: stretch;
  min-height: 100%;
}

.fr-admin__quote {
  margin: 0;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.9375rem;
  line-height: 1.5;
  white-space: pre-wrap;
}

.fr-admin__count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-align: right;
}

.fr-admin__required,
.fr-admin__form-error {
  color: var(--color-danger);
}

.fr-admin__form-error {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 0.8125rem;
}

.fr-admin__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.fr-admin__btn {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.fr-admin__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.fr-admin__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-admin__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.fr-admin__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.fr-admin__btn--danger {
  background: var(--color-danger);
  color: var(--color-on-primary);
}

.fr-admin-fade-enter-active,
.fr-admin-fade-leave-active {
  transition: opacity 0.15s ease;
}

.fr-admin-fade-enter-from,
.fr-admin-fade-leave-to {
  opacity: 0;
}

@media (max-width: 1024px) {
  .fr-admin__body {
    flex-direction: column;
  }

  .fr-admin__side {
    width: 100%;
    max-height: 42%;
  }

  .fr-admin__table-wrap {
    min-height: 16rem;
  }

  .fr-admin__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .fr-admin {
    padding: var(--space-3);
  }

  .fr-admin__filters,
  .fr-admin__form {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .fr-admin__spin,
  .fr-admin-fade-enter-active,
  .fr-admin-fade-leave-active {
    animation: none;
    transition: none;
  }
}
</style>
