<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { formatDate, formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import {
  DESCRIPTION_MAX,
  MINE_COLUMNS,
  MINE_COLUMN_KEY,
  MINE_FILTERS,
  MINE_FILTER_KEY,
  MINE_WIDTH_KEY,
  MINE_ZOOM_KEY,
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
const ACTION_COL_PX = 56;
const ACTION_MENU_WIDTH = 200;
const MIN_COL_PX = 72;

let measureCtx = null;
let wrapObserver = null;

const route = useRoute();
const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const acting = ref(false);
const selected = ref(null);
const page = ref(1);
const perPage = ref(20);

const query = ref('');
const appliedQuery = ref('');
const statusFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const visibleColumns = reactive(loadVisibility(MINE_COLUMN_KEY, MINE_COLUMNS));
const visibleFilters = reactive(loadVisibility(MINE_FILTER_KEY, MINE_FILTERS));
const columnWidths = reactive(loadColumnWidths());
const tableZoom = ref(loadZoom());

const tableWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const actionMenuId = ref(null);
const actionMenuPos = reactive({ top: 0, left: 0 });

const dialogOpen = ref(false);
const editingId = ref(null);
const form = reactive({
  description: '',
  page_title: '',
  page_url: '',
  attach_page: true,
});
const errors = ref({});
const withdrawTarget = ref(null);
const withdrawOpen = ref(false);

const shownColumns = computed(() => MINE_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length + 1, 1));

const filteredItems = computed(() => {
  const needle = appliedQuery.value.trim().toLowerCase();
  return items.value.filter((item) => {
    if (statusFilter.value && item.status !== statusFilter.value) return false;
    const day = localDateKey(item.created_at);
    if (dateFrom.value && day && day < dateFrom.value) return false;
    if (dateTo.value && day && day > dateTo.value) return false;
    if (!needle) return true;
    return (
      (item.description || '').toLowerCase().includes(needle) ||
      (item.page_title || '').toLowerCase().includes(needle)
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
    Boolean(dateFrom.value) ||
    Boolean(dateTo.value),
);

const hiddenActiveFilterLabels = computed(() =>
  MINE_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map((item) => item.label),
);

const hasVisibleFilterFields = computed(() => MINE_FILTERS.some((item) => visibleFilters[item.key]));

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  const total = sum + ACTION_COL_PX;
  return total > ACTION_COL_PX ? `${total}px` : '100%';
});

const currentPageTitle = computed(
  () => (typeof route.meta?.title === 'string' && route.meta.title) || document.title || route.fullPath,
);

function filterHasValue(key) {
  if (key === 'q') return Boolean(appliedQuery.value.trim());
  if (key === 'status') return Boolean(statusFilter.value);
  if (key === 'date_from') return Boolean(dateFrom.value);
  if (key === 'date_to') return Boolean(dateTo.value);
  return false;
}

function cellText(item, key) {
  if (key === 'created_at') return formatDateTime(item.created_at) || '—';
  if (key === 'description') return previewText(item.description);
  if (key === 'status') return STATUS_LABEL[item.status] || item.status || '—';
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
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/feature-requests/mine');
    items.value = data.items ?? [];
    if (selected.value && !items.value.some((row) => row.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch {
    showClientToast('error', 'Không tải được danh sách ghi nhận của bạn.');
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
  dateFrom.value = '';
  dateTo.value = '';
  page.value = 1;
}

function goPage(next) {
  if (next < 1 || next > meta.value.last_page || next === meta.value.current_page) return;
  page.value = next;
}

function openDetail(item) {
  closeActionMenu();
  selected.value = item;
}

function applyCurrentPage() {
  form.page_title = currentPageTitle.value;
  form.page_url = route.fullPath;
}

function openCreate() {
  closeActionMenu();
  editingId.value = null;
  form.description = '';
  form.page_title = '';
  form.page_url = '';
  form.attach_page = false;
  errors.value = {};
  dialogOpen.value = true;
}

function openEdit(item) {
  closeActionMenu();
  editingId.value = item.id;
  form.description = item.description || '';
  form.page_title = item.page_title || '';
  form.page_url = item.page_url || '';
  form.attach_page = Boolean(item.page_url);
  errors.value = {};
  dialogOpen.value = true;
}

function onAttachToggle() {
  if (form.attach_page) applyCurrentPage();
}

function closeDialog() {
  if (saving.value) return;
  dialogOpen.value = false;
}

function askWithdraw(item) {
  closeActionMenu();
  withdrawTarget.value = item;
  withdrawOpen.value = true;
}

function menuItemCount(item) {
  return item.status === 'pending' ? 3 : 1;
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

async function submit() {
  if (saving.value) return;
  errors.value = {};
  const description = form.description.trim();
  if (!description) {
    errors.value = { description: 'Vui lòng mô tả yêu cầu cụ thể.' };
    return;
  }

  const payload = {
    description,
    page_title: form.page_title.trim() || null,
    page_url: form.page_url.trim() || null,
  };

  saving.value = true;
  try {
    if (editingId.value) {
      const { data } = await window.axios.put(`/api/feature-requests/${editingId.value}`, payload);
      replaceItem(data.item);
      showClientToast('success', 'Đã cập nhật ghi nhận.');
    } else {
      const { data } = await window.axios.post('/api/feature-requests', payload);
      items.value = [data.item, ...items.value];
      page.value = 1;
      showClientToast('success', 'Đã gửi ghi nhận yêu cầu tính năng.');
    }
    dialogOpen.value = false;
    nextTick(fitColumnsToContent);
  } catch (error) {
    if (error?.response?.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.response.data.errors ?? {}).map(([key, value]) => [key, value[0]]),
      );
    } else {
      showClientToast('error', error?.response?.data?.message || 'Không gửi được ghi nhận.');
    }
  } finally {
    saving.value = false;
  }
}

async function confirmWithdraw() {
  const item = withdrawTarget.value;
  if (!item || acting.value) return;
  acting.value = true;
  try {
    await window.axios.delete(`/api/feature-requests/${item.id}`);
    items.value = items.value.filter((row) => row.id !== item.id);
    if (selected.value?.id === item.id) selected.value = null;
    withdrawOpen.value = false;
    showClientToast('success', 'Đã rút lại ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được ghi nhận.');
  } finally {
    acting.value = false;
  }
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(MINE_ZOOM_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(MINE_WIDTH_KEY);
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
  const table = tableWrap.value?.querySelector('.fr-mine__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = MINE_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const item of pageItems.value) {
    maxW = Math.max(maxW, measureText(cellText(item, key), fonts.cell));
    if (key === 'reviewer' && item.reviewed_by_email) {
      maxW = Math.max(maxW, measureText(item.reviewed_by_email, fonts.cell));
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
    const remaining = MINE_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
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
  if (event.target?.closest?.('.fr-mine__actions, .fr-mine__action-menu')) return;
  closeActionMenu();
}

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (dialogOpen.value) {
    closeDialog();
    return;
  }
  if (actionMenuId.value) {
    closeActionMenu();
    return;
  }
  if (selected.value && !withdrawOpen.value) selected.value = null;
}

watch(visibleColumns, (value) => saveVisibility(MINE_COLUMN_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(MINE_FILTER_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(MINE_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(MINE_ZOOM_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch([statusFilter, dateFrom, dateTo, perPage], () => {
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
  <section class="fr-mine">
    <PageHeader
      title="Ghi nhận của tôi"
      icon="alertTriangle"
      description="Theo dõi yêu cầu tính năng bạn đã gửi."
      :subtitle="`${items.length} ghi nhận`"
      :primary-action="{ label: 'Gửi ghi nhận', icon: 'plus', onClick: openCreate }"
    >
      <template #actions>
        <button type="button" class="fr-mine__header-btn" :disabled="loading" @click="load">
          <AppIcon name="refresh" :size="16" :class="{ 'fr-mine__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="fr-mine__body">
      <div class="fr-mine__main">
        <div v-if="hasVisibleFilterFields" class="fr-mine__toolbar">
          <div class="fr-mine__filters">
            <div v-if="visibleFilters.q" class="fr-mine__field">
              <label class="fr-mine__label" for="fr-mine-q">Tìm kiếm</label>
              <input
                id="fr-mine-q"
                v-model="query"
                type="search"
                class="fr-mine__input"
                placeholder="Nội dung hoặc trang đính kèm…"
                @keydown.enter="runSearch"
              />
            </div>
            <div v-if="visibleFilters.status" class="fr-mine__field">
              <label class="fr-mine__label" for="fr-mine-status">Trạng thái</label>
              <select id="fr-mine-status" v-model="statusFilter" class="fr-mine__input">
                <option v-for="item in STATUS_OPTIONS" :key="item.value || 'all'" :value="item.value">
                  {{ item.label }}
                </option>
              </select>
            </div>
            <div v-if="visibleFilters.date_from" class="fr-mine__field">
              <label class="fr-mine__label" for="fr-mine-from">Từ ngày</label>
              <input id="fr-mine-from" v-model="dateFrom" type="date" class="fr-mine__input" />
            </div>
            <div v-if="visibleFilters.date_to" class="fr-mine__field">
              <label class="fr-mine__label" for="fr-mine-to">Đến ngày</label>
              <input id="fr-mine-to" v-model="dateTo" type="date" class="fr-mine__input" />
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
            <label v-for="item in MINE_FILTERS" :key="item.key" class="fr-mine__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in MINE_COLUMNS" :key="col.key" class="fr-mine__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="fr-mine__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="fr-mine__table-wrap hide-scrollbar"
          :class="{ 'fr-mine__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="fr-mine__table" :style="{ width: tableWidthPx }">
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
                    class="fr-mine__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
                <th class="fr-mine__th-action">
                  <span class="fr-mine__sr">Thao tác</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="fr-mine__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="pageItems.length === 0">
                <td :colspan="colSpan" class="fr-mine__empty">
                  {{ items.length === 0 ? 'Bạn chưa gửi ghi nhận nào.' : 'Không có ghi nhận khớp bộ lọc.' }}
                </td>
              </tr>
              <tr
                v-for="item in pageItems"
                v-else
                :key="item.id"
                :class="{ 'fr-mine__row--active': selected?.id === item.id }"
                @click="openDetail(item)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <span v-if="col.key === 'status'" class="fr-mine__status">
                    <span class="fr-mine__dot" :class="`fr-mine__dot--${STATUS_TONE[item.status] || 'neutral'}`" />
                    {{ STATUS_LABEL[item.status] || item.status }}
                  </span>
                  <span v-else-if="col.key === 'reviewer'" class="fr-mine__stack">
                    <span>{{ cellText(item, 'reviewer') }}</span>
                    <span v-if="item.reviewed_by_email" class="fr-mine__muted">{{ item.reviewed_by_email }}</span>
                  </span>
                  <span v-else class="fr-mine__cell">{{ cellText(item, col.key) }}</span>
                </td>
                <td class="fr-mine__td-action" @click.stop>
                  <span class="fr-mine__actions">
                    <button
                      type="button"
                      class="fr-mine__action-trigger"
                      :class="{ 'fr-mine__action-trigger--open': actionMenuId === item.id }"
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
                        class="fr-mine__action-menu"
                        role="menu"
                        aria-label="Thao tác ghi nhận"
                        :style="{ top: `${actionMenuPos.top}px`, left: `${actionMenuPos.left}px` }"
                      >
                        <button type="button" role="menuitem" class="fr-mine__action-item" @click="openDetail(item)">
                          <AppIcon name="eye" :size="15" />
                          <span>Xem chi tiết</span>
                        </button>
                        <button
                          v-if="item.status === 'pending'"
                          type="button"
                          role="menuitem"
                          class="fr-mine__action-item"
                          @click="openEdit(item)"
                        >
                          <AppIcon name="pencil" :size="15" />
                          <span>Sửa ghi nhận</span>
                        </button>
                        <button
                          v-if="item.status === 'pending'"
                          type="button"
                          role="menuitem"
                          class="fr-mine__action-item fr-mine__action-item--danger"
                          @click="askWithdraw(item)"
                        >
                          <AppIcon name="trash" :size="15" />
                          <span>Rút lại</span>
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

      <aside v-if="selected" class="fr-mine__side" aria-label="Chi tiết ghi nhận">
        <div class="fr-mine__side-head">
          <h2 class="fr-mine__side-title">Chi tiết ghi nhận</h2>
          <button type="button" class="fr-mine__icon-btn" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="fr-mine__side-lead" :class="`fr-mine__side-lead--${STATUS_TONE[selected.status] || 'neutral'}`">
          <span class="fr-mine__dot" :class="`fr-mine__dot--${STATUS_TONE[selected.status] || 'neutral'}`" />
          <div>
            <span class="fr-mine__side-kicker">{{ STATUS_LABEL[selected.status] || selected.status }}</span>
            <p class="fr-mine__side-desc">{{ selected.description }}</p>
          </div>
        </div>

        <div class="fr-mine__rows">
          <div v-if="selected.page_title" class="fr-mine__row">
            <span class="fr-mine__row-label">Trang đính kèm</span>
            <a
              v-if="selected.page_url"
              class="fr-mine__row-value fr-mine__link"
              :href="selected.page_url"
              target="_blank"
              rel="noopener"
            >
              {{ selected.page_title }}
            </a>
            <span v-else class="fr-mine__row-value">{{ selected.page_title }}</span>
          </div>
          <div class="fr-mine__row">
            <span class="fr-mine__row-label">Thời gian gửi</span>
            <span class="fr-mine__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div v-if="selected.reviewed_by_name" class="fr-mine__row">
            <span class="fr-mine__row-label">Người xử lý</span>
            <span class="fr-mine__row-value">{{ selected.reviewed_by_name }}</span>
          </div>
          <div v-if="selected.expected_done_at" class="fr-mine__row">
            <span class="fr-mine__row-label">Ngày hoàn thành dự kiến</span>
            <span class="fr-mine__row-value">{{ formatDate(selected.expected_done_at) }}</span>
          </div>
          <div v-if="selected.progress_note" class="fr-mine__row">
            <span class="fr-mine__row-label">Ghi chú tiến độ</span>
            <span class="fr-mine__row-value">{{ selected.progress_note }}</span>
          </div>
          <div v-if="selected.reject_reason" class="fr-mine__row">
            <span class="fr-mine__row-label">Lý do từ chối</span>
            <span class="fr-mine__row-value">{{ selected.reject_reason }}</span>
          </div>
          <div v-if="selected.done_at" class="fr-mine__row">
            <span class="fr-mine__row-label">Hoàn thành lúc</span>
            <span class="fr-mine__row-value">{{ formatDateTime(selected.done_at) }}</span>
          </div>
          <div class="fr-mine__row">
            <span class="fr-mine__row-label">Tình trạng</span>
            <span class="fr-mine__row-value">{{ STATUS_HINT[selected.status] || '—' }}</span>
          </div>
          <div class="fr-mine__row">
            <span class="fr-mine__row-label">Mã bản ghi</span>
            <span class="fr-mine__row-value">{{ selected.id }}</span>
          </div>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <Transition name="fr-mine-fade">
        <div v-if="dialogOpen" class="fr-mine__dialog" role="presentation" @mousedown.self="closeDialog">
          <div
            class="fr-mine__dialog-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fr-mine-dialog-title"
          >
            <div class="fr-mine__dialog-head">
              <span class="fr-mine__dialog-icon" aria-hidden="true">
                <AppIcon :name="editingId ? 'pencil' : 'plus'" :size="22" :stroke-width="1.75" />
              </span>
              <div class="fr-mine__dialog-copy">
                <h2 id="fr-mine-dialog-title" class="fr-mine__dialog-title">
                  {{ editingId ? 'Sửa ghi nhận' : 'Gửi ghi nhận' }}
                </h2>
              </div>
              <button type="button" class="fr-mine__dialog-close" aria-label="Đóng" :disabled="saving" @click="closeDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="fr-mine__dialog-body hide-scrollbar">
              <div class="fr-mine__form">
                <div class="fr-mine__field fr-mine__field--full fr-mine__field--grow">
                  <label class="fr-mine__label" for="fr-mine-desc">
                    Yêu cầu cụ thể <span class="fr-mine__required" aria-hidden="true">*</span>
                  </label>
                  <textarea
                    id="fr-mine-desc"
                    v-model="form.description"
                    class="fr-mine__textarea"
                    :class="{ 'fr-mine__textarea--error': errors.description }"
                    :maxlength="DESCRIPTION_MAX"
                    placeholder="Vd. Muốn có nút xuất Excel cho bảng này, lọc theo tháng."
                    :disabled="saving"
                  />
                  <div class="fr-mine__field-foot">
                    <span v-if="errors.description" class="fr-mine__form-error">{{ errors.description }}</span>
                    <span class="fr-mine__count">{{ form.description.length }}/{{ DESCRIPTION_MAX }}</span>
                  </div>
                </div>

                <div class="fr-mine__field">
                  <label class="fr-mine__label" for="fr-mine-page-title">Tên trang</label>
                  <input
                    id="fr-mine-page-title"
                    v-model="form.page_title"
                    type="text"
                    class="fr-mine__input"
                    maxlength="255"
                    :disabled="saving"
                  />
                  <span v-if="errors.page_title" class="fr-mine__form-error">{{ errors.page_title }}</span>
                </div>
                <div class="fr-mine__field">
                  <label class="fr-mine__label" for="fr-mine-page-url">Đường dẫn</label>
                  <input
                    id="fr-mine-page-url"
                    v-model="form.page_url"
                    type="text"
                    class="fr-mine__input"
                    maxlength="500"
                    :disabled="saving"
                  />
                  <span v-if="errors.page_url" class="fr-mine__form-error">{{ errors.page_url }}</span>
                </div>
                <label class="fr-mine__attach">
                  <input v-model="form.attach_page" type="checkbox" :disabled="saving" @change="onAttachToggle" />
                  <span>Gắn trang đang mở</span>
                </label>
              </div>
            </div>

            <div class="fr-mine__dialog-actions">
              <button type="button" class="fr-mine__btn fr-mine__btn--ghost" :disabled="saving" @click="closeDialog">
                Huỷ
              </button>
              <button type="button" class="fr-mine__btn" :disabled="saving || !form.description.trim()" @click="submit">
                {{ saving ? 'Đang gửi…' : editingId ? 'Lưu thay đổi' : 'Gửi ghi nhận' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <ConfirmDialog
      v-model:open="withdrawOpen"
      title="Rút lại ghi nhận"
      description="Ghi nhận đang chờ sẽ bị xoá và không gửi tới superadmin nữa."
      confirm-label="Rút lại"
      danger
      :loading="acting"
      @confirm="confirmWithdraw"
    />
  </section>
</template>

<style scoped>
.fr-mine {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.fr-mine__header-btn {
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

.fr-mine__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.fr-mine__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-mine__spin {
  animation: fr-mine-spin 0.8s linear infinite;
}

@keyframes fr-mine-spin {
  to {
    transform: rotate(360deg);
  }
}

.fr-mine__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.fr-mine__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.fr-mine__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0;
}

.fr-mine__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
}

.fr-mine__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.fr-mine__field--full {
  grid-column: 1 / -1;
}

.fr-mine__field--grow {
  min-height: 0;
}

.fr-mine__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-mine__input,
.fr-mine__textarea {
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

.fr-mine__textarea {
  flex: 1;
  min-height: min(36rem, 60vh);
  resize: none;
}

.fr-mine__textarea--error {
  border-color: var(--color-danger);
}

.fr-mine__check,
.fr-mine__attach {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.fr-mine__check {
  padding: 0.375rem 0;
}

.fr-mine__attach {
  grid-column: 1 / -1;
}

.fr-mine__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-mine__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.fr-mine__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.fr-mine__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.fr-mine__table thead th {
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

.fr-mine__resize {
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

.fr-mine__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.fr-mine__resize:hover::after {
  background: var(--color-primary);
}

.fr-mine__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-mine__table tbody tr {
  cursor: pointer;
}

.fr-mine__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.fr-mine__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.fr-mine__cell,
.fr-mine__stack span {
  display: block;
  white-space: nowrap;
}

.fr-mine__stack {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.fr-mine__muted {
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-mine__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  white-space: nowrap;
}

.fr-mine__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.fr-mine__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.fr-mine__th-action,
.fr-mine__td-action {
  padding-left: var(--space-2);
  padding-right: var(--space-2);
  text-align: center;
}

.fr-mine__actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.fr-mine__action-trigger {
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

.fr-mine__action-trigger:hover,
.fr-mine__action-trigger--open {
  background: var(--color-surface);
  color: var(--color-text);
}

.fr-mine__action-menu {
  position: fixed;
  z-index: 1200;
  width: 12.5rem;
  display: flex;
  flex-direction: column;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
  text-align: left;
}

.fr-mine__action-item {
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

.fr-mine__action-item:hover {
  background: var(--color-surface-muted);
}

.fr-mine__action-item--danger {
  color: var(--color-danger);
}

.fr-mine__action-item--danger:hover {
  background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface));
}

.fr-mine__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.fr-mine__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.fr-mine__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.fr-mine__icon-btn,
.fr-mine__dialog-close {
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

.fr-mine__icon-btn:hover,
.fr-mine__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.fr-mine__side-lead {
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

.fr-mine__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--color-border);
}

.fr-mine__side-lead--neutral::before {
  background: var(--color-text-muted);
}

.fr-mine__side-lead--warning::before {
  background: var(--color-warning);
}

.fr-mine__side-lead--info::before {
  background: var(--color-info);
}

.fr-mine__side-lead--success::before {
  background: var(--color-success);
}

.fr-mine__side-lead--danger::before {
  background: var(--color-danger);
}

.fr-mine__side-kicker {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-mine__side-desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.45;
  white-space: pre-wrap;
}

.fr-mine__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.fr-mine__side-lead .fr-mine__dot {
  margin-top: 0.35rem;
}

.fr-mine__dot--warning {
  background: var(--color-warning);
}

.fr-mine__dot--info {
  background: var(--color-info);
}

.fr-mine__dot--success {
  background: var(--color-success);
}

.fr-mine__dot--danger {
  background: var(--color-danger);
}

.fr-mine__rows {
  display: flex;
  flex-direction: column;
}

.fr-mine__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.fr-mine__row:last-child {
  box-shadow: none;
}

.fr-mine__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.fr-mine__row-label::after {
  content: ':';
}

.fr-mine__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.fr-mine__link {
  color: var(--color-primary);
}

.fr-mine__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.fr-mine__dialog-panel {
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

.fr-mine__dialog-head,
.fr-mine__dialog-actions {
  flex-shrink: 0;
}

.fr-mine__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.fr-mine__dialog-icon {
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

.fr-mine__dialog-copy {
  flex: 1;
  min-width: 0;
}

.fr-mine__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
}

.fr-mine__dialog-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.fr-mine__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
  min-height: 100%;
}

.fr-mine__field-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.fr-mine__count {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-mine__required,
.fr-mine__form-error {
  color: var(--color-danger);
}

.fr-mine__form-error {
  font-size: 0.8125rem;
}

.fr-mine__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.fr-mine__btn {
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

.fr-mine__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.fr-mine__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-mine__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.fr-mine__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.fr-mine-fade-enter-active,
.fr-mine-fade-leave-active {
  transition: opacity 0.15s ease;
}

.fr-mine-fade-enter-from,
.fr-mine-fade-leave-to {
  opacity: 0;
}

@media (max-width: 1024px) {
  .fr-mine__body {
    flex-direction: column;
  }

  .fr-mine__side {
    width: 100%;
    max-height: 42%;
  }

  .fr-mine__table-wrap {
    min-height: 16rem;
  }

  .fr-mine__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .fr-mine {
    padding: var(--space-3);
  }

  .fr-mine__filters,
  .fr-mine__form {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .fr-mine__spin,
  .fr-mine-fade-enter-active,
  .fr-mine-fade-leave-active {
    animation: none;
    transition: none;
  }
}
</style>
