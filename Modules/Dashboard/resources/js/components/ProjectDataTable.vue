<script setup>
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import {
  PROJECT_TABLE_COLUMNS,
  PROJECT_TABLE_COLUMN_STORAGE_KEY,
  PROJECT_TABLE_COLUMN_WIDTH_KEY,
} from '../constants/companyProjectsTable';
import { loadColumnWidths, loadVisibility, saveColumnWidths, saveVisibility } from '../utils/tableStorage';

/**
 * Bảng dự án nhóm theo phòng ban sở hữu (group-collapse) — mỗi dự án chỉ
 * thuộc đúng 1 nhóm (owner_department_name), tránh trùng lặp dự án có nhiều
 * phòng ban thực hiện. Mỗi nhóm luôn hiện dòng tổng hợp (số dự án, số trễ,
 * tiến độ TB) kể cả khi thu gọn — để trưởng phòng/ban giám đốc thấy vấn đề
 * ngay không cần mở từng nhóm. Không phân trang thật (dữ liệu đã tải hết từ
 * cha với per_page lớn) — cuộn dọc trong khung có max-height.
 */
const props = defineProps({
  rows: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, default: 0 },
  sortBy: { type: String, default: 'end_date' },
  sortDir: { type: String, default: 'asc' },
  filtersActive: { type: Boolean, default: false },
});

const emit = defineEmits(['sort', 'row-click', 'search', 'clear-filters']);

const MIN_COL_PX = 72;
const CELL_PAD_X = 24;
const COL_EXTRA = 8;

const healthLabel = { good: 'Tốt', warning: 'Cần chú ý', risk: 'Nguy cơ' };

const tableWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const visibleColumns = reactive(loadVisibility(PROJECT_TABLE_COLUMN_STORAGE_KEY, PROJECT_TABLE_COLUMNS));
const columnWidths = reactive(loadColumnWidths(PROJECT_TABLE_COLUMN_WIDTH_KEY));
watch(columnWidths, (value) => saveColumnWidths(PROJECT_TABLE_COLUMN_WIDTH_KEY, value), { deep: true });
watch(visibleColumns, (value) => saveVisibility(PROJECT_TABLE_COLUMN_STORAGE_KEY, value), { deep: true });

const shownColumns = computed(() => PROJECT_TABLE_COLUMNS.filter((col) => visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));
const tableWidthPx = computed(() => {
  const sum = shownColumns.value.reduce((total, col) => total + (Number(columnWidths[col.key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const searchTerm = ref('');
let searchTimer = null;
function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => emit('search', searchTerm.value), 300);
}

function toggleSort(column) {
  const dir = props.sortBy === column && props.sortDir === 'asc' ? 'desc' : 'asc';
  emit('sort', { column, dir });
}

function cellText(row, key) {
  if (key === 'progress_percent') return row.progress_percent === null ? 'Chưa có dữ liệu' : `${row.progress_percent}%`;
  if (key === 'health') return healthLabel[row.health] ?? row.health;
  if (key === 'days_overdue') return row.days_overdue > 0 ? `${row.days_overdue} ngày` : '—';
  if (key === 'end_date') return row.end_date ?? '—';
  if (key === 'lead_name') return row.lead_name ?? '—';
  if (key === 'tasks_summary') return `${row.tasks_completed ?? 0}/${row.tasks_total ?? 0}`;
  return row[key] ?? '—';
}

// ---- Nhóm theo phòng ban sở hữu ----
// Mặc định: nhóm có dự án trễ hạn luôn mở sẵn (vấn đề phải thấy ngay, không
// cần click) — nhóm không có vấn đề gì tự thu gọn để tiết kiệm chỗ nhìn.
// Người dùng bấm để mở/đóng lại tuỳ ý sau đó, lựa chọn thủ công luôn thắng.
const collapsedGroups = reactive(new Set());
const manuallyToggled = reactive(new Set());
const autoCollapseSeen = reactive(new Set());

function toggleGroup(name) {
  manuallyToggled.add(name);
  if (collapsedGroups.has(name)) collapsedGroups.delete(name);
  else collapsedGroups.add(name);
}

const groups = computed(() => {
  const map = new Map();
  for (const row of props.rows) {
    const key = row.department_name || '—';
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(row);
  }

  return Array.from(map.entries())
    .map(([name, items]) => {
      const overdue = items.filter((r) => r.days_overdue > 0).length;
      const progressValues = items.map((r) => r.progress_percent).filter((v) => v !== null);
      const avgProgress = progressValues.length ? Math.round((progressValues.reduce((a, b) => a + b, 0) / progressValues.length) * 10) / 10 : null;
      return { name, items, total: items.length, overdue, avgProgress };
    })
    .sort((a, b) => b.overdue - a.overdue || a.name.localeCompare(b.name, 'vi'));
});

watch(groups, (list) => {
  if (list.length === 0) return;
  for (const group of list) {
    // Áp dụng auto-collapse cho từng nhóm CHỈ 1 lần (lần đầu nó xuất hiện) —
    // để không tự đóng lại nhóm người dùng vừa mở tay ở lượt filter trước.
    if (group.overdue === 0 && !manuallyToggled.has(group.name) && !autoCollapseSeen.has(group.name)) {
      collapsedGroups.add(group.name);
    }
    autoCollapseSeen.add(group.name);
  }
});

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

let measureCtx = null;
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
  const table = tableWrap.value?.querySelector('.project-table__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = PROJECT_TABLE_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const row of props.rows) {
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
  for (const key of keys) measured[key] = columnContentWidth(key, fonts);

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
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
    const remaining = PROJECT_TABLE_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
    if (remaining < 1) return;
  }
  visibleColumns[key] = checked;
}

watch(() => props.rows, () => nextTick(fitColumnsToContent), { deep: true });
watch(shownColumns, () => nextTick(fitColumnsToContent));

// Web font (Be Vietnam Pro) có thể chưa load xong lúc đo lần đầu — canvas đo
// bằng font fallback sẽ ra width hẹp hơn thật, khiến chữ tràn đè cột kế bên.
// Đo lại 1 lần khi font đã sẵn sàng.
if (typeof document !== 'undefined' && document.fonts?.ready) {
  document.fonts.ready.then(() => nextTick(fitColumnsToContent));
}
</script>

<template>
  <div class="project-table">
    <TablePagesBar
      placement="top"
      paging-only
      :from="rows.length ? 1 : 0"
      :to="rows.length"
      :total="total"
      :page="1"
      :last-page="1"
      show-search
      :show-clear-filters="filtersActive"
      :filters-active="filtersActive"
      @search="() => emit('search', searchTerm)"
      @clear-filters="() => { searchTerm = ''; emit('clear-filters'); }"
    >
      <template #filters>
        <div class="project-table__search">
          <AppIcon name="search" :size="15" />
          <input
            v-model="searchTerm"
            type="search"
            placeholder="Tìm theo tên hoặc mã dự án..."
            aria-label="Tìm dự án"
            @input="onSearchInput"
          />
        </div>
      </template>
      <template #settings>
        <label v-for="col in PROJECT_TABLE_COLUMNS" :key="col.key" class="project-table__col-toggle">
          <input
            type="checkbox"
            :checked="visibleColumns[col.key]"
            @change="onColumnToggle(col.key, $event.target.checked)"
          />
          <span>{{ col.label }}</span>
        </label>
      </template>
    </TablePagesBar>

    <div ref="tableWrap" class="project-table__wrap hide-scrollbar" :class="{ 'project-table__wrap--resizing': resizing }">
      <table class="project-table__table" :style="{ width: tableWidthPx }">
        <colgroup>
          <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
        </colgroup>
        <thead>
          <tr>
            <th
              v-for="col in shownColumns"
              :key="col.key"
              :class="{ 'project-table__sortable': ['name', 'status_label', 'end_date'].includes(col.key) }"
              @click="['name', 'status_label', 'end_date'].includes(col.key) && toggleSort(col.key === 'status_label' ? 'status' : col.key)"
            >
              <span>{{ col.label }}</span>
              <AppIcon
                v-if="(col.key === 'name' && sortBy === 'name') || (col.key === 'status_label' && sortBy === 'status') || (col.key === 'end_date' && sortBy === 'end_date')"
                :name="sortDir === 'asc' ? 'chevronsUp' : 'chevronsDown'"
                :size="12"
              />
              <button
                type="button"
                class="project-table__resize"
                aria-label="Kéo để đổi độ rộng cột"
                @click.stop
                @mousedown.stop.prevent="startResize($event, col.key)"
              />
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="colSpan" class="project-table__empty">Đang tải…</td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td :colspan="colSpan" class="project-table__empty">Không có dự án nào khớp bộ lọc hiện tại.</td>
          </tr>
          <template v-else v-for="group in groups" :key="group.name">
            <tr class="project-table__group-row" tabindex="0" @click="toggleGroup(group.name)" @keydown.enter="toggleGroup(group.name)">
              <td :colspan="colSpan">
                <span class="project-table__group-toggle">
                  <AppIcon :name="collapsedGroups.has(group.name) ? 'chevronRight' : 'chevronDown'" :size="14" />
                  <span class="project-table__group-name">{{ group.name }}</span>
                </span>
                <span class="project-table__group-stats">
                  <span>{{ group.total }} dự án</span>
                  <span v-if="group.overdue > 0" class="project-table__group-overdue">{{ group.overdue }} trễ hạn</span>
                  <span v-if="group.avgProgress !== null">Tiến độ TB {{ group.avgProgress }}%</span>
                </span>
              </td>
            </tr>
            <tr
              v-for="row in (collapsedGroups.has(group.name) ? [] : group.items)"
              :key="row.id"
              tabindex="0"
              @click="emit('row-click', row.id)"
              @keydown.enter="emit('row-click', row.id)"
            >
              <td v-for="col in shownColumns" :key="col.key">
                <template v-if="col.key === 'health'">
                  <span :class="`project-table__dot project-table__dot--${row.health}`" aria-hidden="true"></span>
                  {{ healthLabel[row.health] }}
                </template>
                <span v-else-if="col.key === 'days_overdue' && row.days_overdue > 0" class="project-table__overdue">
                  {{ row.days_overdue }} ngày
                </span>
                <span v-else>{{ cellText(row, col.key) }}</span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.project-table {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.project-table__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-2);
  color: var(--color-text-muted);
}

.project-table__search input {
  border: none;
  outline: none;
  font-size: 0.8125rem;
  color: var(--color-text);
  background: transparent;
  width: 12rem;
}

.project-table__col-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-2);
  font-size: 0.8125rem;
  cursor: pointer;
  white-space: nowrap;
}

.project-table__wrap {
  overflow: auto;
  max-height: 32rem;
}

.project-table__wrap--resizing {
  cursor: col-resize;
}

.project-table__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.project-table__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--color-surface);
}

.project-table__table th {
  position: relative;
  text-align: left;
  padding: var(--space-2) var(--space-3);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  box-shadow: 0 1px 0 var(--color-border);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.project-table__table th span {
  overflow: hidden;
  text-overflow: ellipsis;
}

.project-table__sortable {
  cursor: pointer;
  user-select: none;
}

.project-table__table td {
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.project-table__table tbody tr:not(.project-table__group-row) {
  cursor: pointer;
  transition: background-color 0.1s ease;
}

.project-table__table tbody tr:not(.project-table__group-row):hover,
.project-table__table tbody tr:not(.project-table__group-row):focus-visible {
  background: var(--color-surface-muted);
  outline: none;
}

.project-table__empty {
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-5);
  white-space: normal;
}

.project-table__resize {
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

.project-table__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.project-table__group-row {
  cursor: pointer;
  background: var(--color-surface-muted);
}

.project-table__group-row:hover {
  background: var(--color-primary-surface);
}

.project-table__group-row td {
  padding: var(--space-2) var(--space-3);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  white-space: nowrap;
  overflow: visible;
}

.project-table__group-toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-weight: 700;
  font-size: 0.875rem;
}

.project-table__group-stats {
  display: inline-flex;
  align-items: center;
  gap: var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 400;
}

.project-table__group-overdue {
  color: var(--color-danger);
  font-weight: 700;
}

.project-table__dot {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  margin-right: var(--space-1);
}

.project-table__dot--good { background: var(--color-success); }
.project-table__dot--warning { background: var(--color-warning); }
.project-table__dot--risk { background: var(--color-danger); }

.project-table__overdue {
  color: var(--color-danger);
  font-weight: 600;
}
</style>
