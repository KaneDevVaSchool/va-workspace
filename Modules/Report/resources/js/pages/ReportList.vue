<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { formatDate, formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  REPORT_LIST_COLUMNS,
  REPORT_LIST_COLUMN_KEY,
  REPORT_LIST_FILTERS,
  REPORT_LIST_FILTER_KEY,
  REPORT_LIST_WIDTH_KEY,
  REPORT_LIST_ZOOM_KEY,
  REPORT_STATUS_LABELS,
  REPORT_TYPE_LABELS,
  loadColumnWidths,
  loadVisibility,
  loadZoom,
  saveVisibility,
  saveZoom,
} from '../constants/report.js';

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const MIN_COL_PX = 72;
let measureCtx = null;
let wrapObserver = null;

const router = useRouter();
const auth = useAuthStore();

const reports = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);
const confirmTarget = ref(null);

const query = ref('');
const reportType = ref('');
const status = ref('');
const perPage = ref(20);

const visibleColumns = reactive(loadVisibility(REPORT_LIST_COLUMN_KEY, REPORT_LIST_COLUMNS));
const visibleFilters = reactive(loadVisibility(REPORT_LIST_FILTER_KEY, REPORT_LIST_FILTERS));
const columnWidths = reactive(loadColumnWidths(REPORT_LIST_WIDTH_KEY));
const tableZoom = ref(loadZoom(REPORT_LIST_ZOOM_KEY));

const tableWrap = ref(null);
const resizing = ref(false);

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const canCreate = computed(() => auth.can('report.manage_department'));

const shownColumns = computed(() => REPORT_LIST_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const hasActiveFilters = computed(
  () => Boolean(query.value.trim()) || Boolean(reportType.value) || Boolean(status.value),
);

const hasVisibleFilterFields = computed(() =>
  REPORT_LIST_FILTERS.some((item) => visibleFilters[item.key]),
);

const hiddenActiveFilterLabels = computed(() =>
  REPORT_LIST_FILTERS.filter((item) => !visibleFilters[item.key] && filterHasValue(item.key)).map(
    (item) => item.label,
  ),
);

const tableWidthPx = computed(() => {
  const sum = shownColumns.value.reduce(
    (total, col) => total + (Number(columnWidths[col.key]) || 0),
    0,
  );
  return sum > 0 ? `${sum}px` : '100%';
});

function filterHasValue(key) {
  if (key === 'q') return Boolean(query.value.trim());
  if (key === 'report_type') return Boolean(reportType.value);
  if (key === 'status') return Boolean(status.value);
  return false;
}

function periodText(row) {
  if (!row?.period_from || !row?.period_to) return '—';
  return `${formatDate(row.period_from)} — ${formatDate(row.period_to)}`;
}

function cellText(row, key) {
  if (key === 'period') return periodText(row);
  if (key === 'report_type') return REPORT_TYPE_LABELS[row.report_type] ?? row.report_type;
  if (key === 'status') return REPORT_STATUS_LABELS[row.status] ?? row.status;
  if (key === 'viewer_count') {
    return row.viewer_count === 0 ? 'Chưa chia sẻ' : `${row.viewer_count} người`;
  }
  if (key === 'created_at') return formatDateTime(row.created_at) || '—';
  return row[key] ?? '—';
}

async function loadReports(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/report', {
      params: {
        q: query.value.trim() || undefined,
        report_type: reportType.value || undefined,
        page,
        per_page: perPage.value,
      },
    });
    let rows = data.reports ?? [];
    // Tình trạng lọc tại chỗ: backend phân trang theo bộ lọc chung, còn
    // draft/saved chỉ là hai giá trị nên không cần thêm tham số máy chủ.
    if (status.value) {
      rows = rows.filter((row) => row.status === status.value);
    }
    reports.value = rows;
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: perPage.value };

    if (selected.value && !rows.some((row) => row.id === selected.value.id)) {
      selected.value = null;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không tải được danh sách báo cáo.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) {
    return;
  }
  loadReports(page);
}

function clearFilters() {
  query.value = '';
  reportType.value = '';
  status.value = '';
  loadReports(1);
}

function openReport(row) {
  router.push({ name: 'manager.reports.show', params: { id: row.id } });
}

async function removeReport() {
  if (!confirmTarget.value) return;

  try {
    await window.axios.delete(`/api/report/${confirmTarget.value.id}`);
    if (selected.value?.id === confirmTarget.value.id) selected.value = null;
    confirmTarget.value = null;
    showClientToast('success', 'Đã xoá báo cáo.');
    await loadReports(meta.value.current_page);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không xoá được báo cáo.');
  }
}

/* ---------- Cột: đo, kéo, bật/tắt ---------- */

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
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
  const table = tableWrap.value?.querySelector('.report-list__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = REPORT_LIST_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const row of reports.value) {
    maxW = Math.max(maxW, measureText(cellText(row, key), fonts.cell));
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

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = REPORT_LIST_COLUMNS.filter(
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
  if (confirmTarget.value) {
    confirmTarget.value = null;
    return;
  }
  if (selected.value) {
    selected.value = null;
  }
}

watch(visibleColumns, (value) => saveVisibility(REPORT_LIST_COLUMN_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(REPORT_LIST_FILTER_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(REPORT_LIST_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  saveZoom(REPORT_LIST_ZOOM_KEY, value);
  nextTick(fitColumnsToContent);
});
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch([reportType, status, perPage], () => loadReports(1));

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadReports(1);
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
  <section class="report-list">
    <PageHeader
      title="Báo cáo"
      icon="barChart"
      description="Quản lý, cấu hình và theo dõi các báo cáo của phòng ban."
    >
      <template #actions>
        <button
          v-if="canCreate"
          type="button"
          class="report-list__header-btn"
          @click="router.push({ name: 'manager.reports.personnel-evaluation.create' })"
        >
          <AppIcon name="plus" :size="16" />
          Tạo báo cáo
        </button>
      </template>
    </PageHeader>

    <div class="report-list__body">
      <div class="report-list__main">
        <div v-if="hasVisibleFilterFields" class="report-list__toolbar">
          <div class="report-list__filters">
            <div v-if="visibleFilters.q" class="report-list__field">
              <label class="report-list__label" for="report-q">Tìm kiếm</label>
              <input
                id="report-q"
                v-model="query"
                type="search"
                class="report-list__input"
                @keydown.enter="loadReports(1)"
              />
            </div>

            <div v-if="visibleFilters.report_type" class="report-list__field">
              <label class="report-list__label" for="report-type">Loại báo cáo</label>
              <select id="report-type" v-model="reportType" class="report-list__input">
                <option value="">Tất cả loại báo cáo</option>
                <option
                  v-for="(label, value) in REPORT_TYPE_LABELS"
                  :key="value"
                  :value="value"
                >
                  {{ label }}
                </option>
              </select>
            </div>

            <div v-if="visibleFilters.status" class="report-list__field">
              <label class="report-list__label" for="report-status">Tình trạng</label>
              <select id="report-status" v-model="status" class="report-list__input">
                <option value="">Tất cả tình trạng</option>
                <option
                  v-for="(label, value) in REPORT_STATUS_LABELS"
                  :key="value"
                  :value="value"
                >
                  {{ label }}
                </option>
              </select>
            </div>
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
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="loadReports(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in REPORT_LIST_FILTERS" :key="item.key" class="report-list__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in REPORT_LIST_COLUMNS" :key="col.key" class="report-list__check">
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <p v-if="hiddenActiveFilterLabels.length" class="report-list__note">
          Đang lọc thêm theo: {{ hiddenActiveFilterLabels.join(', ') }} (bộ lọc đang ẩn).
        </p>

        <div
          ref="tableWrap"
          class="report-list__table-wrap hide-scrollbar"
          :class="{ 'report-list__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="report-list__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col
                v-for="col in shownColumns"
                :key="col.key"
                :style="{ width: colWidthStyle(col.key) }"
              />
            </colgroup>
            <thead>
              <tr>
                <th v-for="col in shownColumns" :key="col.key">
                  <span>{{ col.label }}</span>
                  <button
                    type="button"
                    class="report-list__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="report-list__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="reports.length === 0">
                <td :colspan="colSpan" class="report-list__empty">
                  <span v-if="hasActiveFilters">
                    Không có báo cáo nào khớp với bộ lọc đang chọn.
                  </span>
                  <span v-else-if="canCreate">
                    Phòng ban chưa có báo cáo nào. Bấm "Tạo báo cáo" ở góc trên để bắt đầu.
                  </span>
                  <span v-else>Chưa có báo cáo nào được chia sẻ với bạn.</span>
                </td>
              </tr>
              <tr
                v-for="row in reports"
                v-else
                :key="row.id"
                :class="{ 'report-list__row--active': selected?.id === row.id }"
                @click="selected = row"
                @dblclick="openReport(row)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <span v-if="col.key === 'status'" class="report-list__status">
                    <span
                      class="report-list__dot"
                      :class="row.status === 'saved' ? 'report-list__dot--ok' : 'report-list__dot--draft'"
                    />
                    {{ REPORT_STATUS_LABELS[row.status] ?? row.status }}
                  </span>
                  <span v-else class="report-list__cell">{{ cellText(row, col.key) }}</span>
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
      </div>

      <aside v-if="selected" class="report-list__side" aria-label="Chi tiết báo cáo">
        <div class="report-list__side-head">
          <h2 class="report-list__side-title">Chi tiết báo cáo</h2>
          <button
            type="button"
            class="report-list__icon-btn"
            aria-label="Đóng"
            @click="selected = null"
          >
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="report-list__rows">
          <div class="report-list__row">
            <span class="report-list__row-label">Tên báo cáo</span>
            <span class="report-list__row-value">{{ selected.title }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Loại báo cáo</span>
            <span class="report-list__row-value">
              {{ REPORT_TYPE_LABELS[selected.report_type] ?? selected.report_type }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Kỳ báo cáo</span>
            <span class="report-list__row-value">{{ periodText(selected) }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Phòng ban</span>
            <span class="report-list__row-value">{{ selected.department_name ?? '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Người được xem</span>
            <span class="report-list__row-value">
              {{ selected.viewer_count === 0 ? 'Chưa chia sẻ' : `${selected.viewer_count} người` }}
            </span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Tình trạng</span>
            <span class="report-list__row-value">{{ REPORT_STATUS_LABELS[selected.status] }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Người tạo</span>
            <span class="report-list__row-value">{{ selected.created_by_name ?? '—' }}</span>
          </div>
          <div class="report-list__row">
            <span class="report-list__row-label">Tạo lúc</span>
            <span class="report-list__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
        </div>

        <div class="report-list__side-actions">
          <button type="button" class="report-list__btn" @click="openReport(selected)">
            Mở báo cáo
          </button>
          <button
            v-if="canCreate"
            type="button"
            class="report-list__btn report-list__btn--ghost"
            @click="confirmTarget = selected"
          >
            Xoá
          </button>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="confirmTarget"
        class="report-confirm"
        role="alertdialog"
        aria-modal="true"
        aria-label="Xác nhận xoá báo cáo"
      >
        <div class="report-confirm__backdrop" @click="confirmTarget = null" />
        <div class="report-confirm__panel">
          <h2 class="report-confirm__title">Xoá báo cáo</h2>
          <p class="report-confirm__text">
            Xoá báo cáo "{{ confirmTarget.title }}"? Thao tác này không hoàn tác được.
          </p>
          <div class="report-confirm__foot">
            <button type="button" class="report-list__btn report-list__btn--ghost" @click="confirmTarget = null">
              Huỷ
            </button>
            <button type="button" class="report-list__btn" @click="removeReport">Xoá</button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.report-list {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.report-list__header-btn {
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

.report-list__header-btn:hover {
  background: var(--color-surface-muted);
}

.report-list__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.report-list__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.report-list__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0;
}

.report-list__filters {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.report-list__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.report-list__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.report-list__input {
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

.report-list__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.report-list__note {
  flex-shrink: 0;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.report-list__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.report-list__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.report-list__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.report-list__table thead th {
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

.report-list__table th {
  position: relative;
}

.report-list__resize {
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

.report-list__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.report-list__resize:hover::after {
  background: var(--color-primary);
}

.report-list__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.report-list__table tbody tr {
  cursor: pointer;
}

.report-list__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.report-list__row--active td {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.report-list__cell {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.report-list__status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.report-list__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.report-list__dot--ok {
  background: var(--color-secondary);
}

.report-list__dot--draft {
  background: var(--color-primary-300);
}

.report-list__empty {
  padding: var(--space-6);
  color: var(--color-text-muted);
  text-align: center;
  white-space: normal;
}

.report-list__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.report-list__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

.report-list__side-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.report-list__icon-btn {
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

.report-list__icon-btn:hover {
  background: var(--color-surface);
}

.report-list__rows {
  display: flex;
  flex-direction: column;
}

.report-list__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.report-list__row:last-child {
  box-shadow: none;
}

.report-list__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.report-list__row-label::after {
  content: ':';
}

.report-list__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.report-list__side-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-4);
}

.report-list__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.report-list__btn:hover {
  background: var(--color-primary-hover);
}

.report-list__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.report-list__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.report-confirm {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.report-confirm__backdrop {
  position: absolute;
  inset: 0;
  background: color-mix(in srgb, var(--color-text) 45%, transparent);
}

.report-confirm__panel {
  position: relative;
  z-index: 1;
  width: min(26rem, 100%);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.report-confirm__title {
  margin: 0 0 var(--space-2);
  font-size: 1rem;
  font-weight: 600;
}

.report-confirm__text {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.report-confirm__foot {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 1024px) {
  .report-list__body {
    flex-direction: column;
  }

  .report-list__side {
    width: 100%;
    max-height: 42%;
  }

  .report-list__table-wrap {
    min-height: 16rem;
  }

  .report-list__filters {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .report-list {
    padding: var(--space-4);
  }

  .report-list__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .report-list {
    padding: var(--space-3);
  }

  .report-list__filters {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
