<script setup>
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import {
  EMPLOYEE_TABLE_COLUMNS,
  EMPLOYEE_TABLE_COLUMN_STORAGE_KEY,
  EMPLOYEE_TABLE_COLUMN_WIDTH_KEY,
} from '../constants/departmentEmployeesTable';
import { loadColumnWidths, loadVisibility, saveColumnWidths, saveVisibility } from '../utils/tableStorage';
import EmployeeProjectsModal from './EmployeeProjectsModal.vue';

const props = defineProps({
  rows: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 20 },
  total: { type: Number, default: 0 },
  filtersActive: { type: Boolean, default: false },
});

const emit = defineEmits(['update:page', 'update:perPage', 'row-click', 'search', 'clear-filters']);

const MIN_COL_PX = 72;
const CELL_PAD_X = 24;
const COL_EXTRA = 8;

const taskStatusLabels = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  under_review: 'Đang đánh giá',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

const tableWrap = ref(null);
const resizing = ref(false);
useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const visibleColumns = reactive(loadVisibility(EMPLOYEE_TABLE_COLUMN_STORAGE_KEY, EMPLOYEE_TABLE_COLUMNS));
const columnWidths = reactive(loadColumnWidths(EMPLOYEE_TABLE_COLUMN_WIDTH_KEY));
watch(columnWidths, (value) => saveColumnWidths(EMPLOYEE_TABLE_COLUMN_WIDTH_KEY, value), { deep: true });
watch(visibleColumns, (value) => saveVisibility(EMPLOYEE_TABLE_COLUMN_STORAGE_KEY, value), { deep: true });

const shownColumns = computed(() => EMPLOYEE_TABLE_COLUMNS.filter((col) => visibleColumns[col.key]));
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

const projectsModalOpen = ref(false);
const projectsModalTitle = ref('');
const projectsModalList = ref([]);

function openProjectsModal(row, key) {
  projectsModalTitle.value = key === 'projects_leading' ? `Dự án đang phụ trách — ${row.name}` : `Dự án đang phối hợp — ${row.name}`;
  projectsModalList.value = row[key] ?? [];
  projectsModalOpen.value = true;
}

function closeProjectsModal() {
  projectsModalOpen.value = false;
}

function projectsCountLabel(row, key) {
  const count = (row[key] ?? []).length;
  return count === 0 ? 'Chưa có' : `${count} dự án`;
}

function taskStatusSummary(row) {
  const entries = Object.entries(row.tasks_by_status ?? {}).filter(([, count]) => count > 0);
  if (entries.length === 0) return 'Chưa có việc';
  return entries.map(([status, count]) => `${count} ${taskStatusLabels[status] ?? status}`).join(' · ');
}

function cellText(row, key) {
  if (key === 'projects_leading' || key === 'projects_collaborating') return projectsCountLabel(row, key);
  if (key === 'tasks_status') return taskStatusSummary(row);
  if (key === 'average_progress_percent') return row.average_progress_percent === null ? 'Chưa có dữ liệu' : `${row.average_progress_percent}%`;
  if (key === 'team_name') return row.team_name ?? '—';
  if (key === 'name') return row.name;
  if (key === 'tasks_total') return String(row.tasks_total ?? 0);
  if (key === 'user_id') return String(row.user_id ?? '—');
  return row[key] ?? '—';
}

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
  const table = tableWrap.value?.querySelector('.employee-table__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = EMPLOYEE_TABLE_COLUMNS.find((col) => col.key === key)?.label ?? '';
  let maxW = measureText(label, fonts.header);
  for (const row of props.rows) {
    const text = cellText(row, key);
    maxW = Math.max(maxW, measureText(text, fonts.cell));
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
    const remaining = EMPLOYEE_TABLE_COLUMNS.filter((col) => visibleColumns[col.key] && col.key !== key).length;
    if (remaining < 1) return;
  }
  visibleColumns[key] = checked;
}

watch(() => props.rows, () => nextTick(fitColumnsToContent), { deep: true });
watch(shownColumns, () => nextTick(fitColumnsToContent));

if (typeof document !== 'undefined' && document.fonts?.ready) {
  document.fonts.ready.then(() => nextTick(fitColumnsToContent));
}
</script>

<template>
  <div class="employee-table">
    <TablePagesBar
      placement="top"
      :from="rows.length ? (page - 1) * perPage + 1 : 0"
      :to="Math.min(page * perPage, total)"
      :total="total"
      :page="page"
      :last-page="lastPage"
      :per-page="perPage"
      show-search
      :show-clear-filters="filtersActive"
      :filters-active="filtersActive"
      @search="() => emit('search', searchTerm)"
      @clear-filters="() => { searchTerm = ''; emit('clear-filters'); }"
      @update:page="emit('update:page', $event)"
      @update:perPage="emit('update:perPage', $event)"
    >
      <template #filters>
        <div class="employee-table__search">
          <AppIcon name="search" :size="15" />
          <input
            v-model="searchTerm"
            type="search"
            placeholder="Tìm theo tên nhân viên..."
            aria-label="Tìm nhân viên"
            @input="onSearchInput"
          />
        </div>
      </template>
      <template #settings>
        <label v-for="col in EMPLOYEE_TABLE_COLUMNS" :key="col.key" class="employee-table__col-toggle">
          <input
            type="checkbox"
            :checked="visibleColumns[col.key]"
            @change="onColumnToggle(col.key, $event.target.checked)"
          />
          <span>{{ col.label }}</span>
        </label>
      </template>
    </TablePagesBar>

    <div ref="tableWrap" class="employee-table__wrap hide-scrollbar" :class="{ 'employee-table__wrap--resizing': resizing }">
      <table class="employee-table__table" :style="{ width: tableWidthPx }">
        <colgroup>
          <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="col in shownColumns" :key="col.key">
              <span>{{ col.label }}</span>
              <button
                type="button"
                class="employee-table__resize"
                aria-label="Kéo để đổi độ rộng cột"
                @click.stop
                @mousedown.stop.prevent="startResize($event, col.key)"
              />
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="colSpan" class="employee-table__empty">Đang tải…</td>
          </tr>
          <tr v-else-if="rows.length === 0">
            <td :colspan="colSpan" class="employee-table__empty">Không có nhân viên nào khớp bộ lọc hiện tại.</td>
          </tr>
          <tr
            v-for="row in rows"
            v-else
            :key="row.user_id"
            tabindex="0"
            @click="emit('row-click', row.user_id)"
            @keydown.enter="emit('row-click', row.user_id)"
          >
            <td v-for="col in shownColumns" :key="col.key">
              <template v-if="col.key === 'name'">
                <span class="employee-table__person">
                  <img v-if="row.avatar_url" :src="row.avatar_url" alt="" class="employee-table__avatar" />
                  <span v-else class="employee-table__avatar employee-table__avatar--placeholder">{{ row.name?.charAt(0) }}</span>
                  <span>{{ row.name }}</span>
                </span>
              </template>
              <template v-else-if="col.key === 'projects_leading' || col.key === 'projects_collaborating'">
                <button
                  v-if="(row[col.key] ?? []).length"
                  type="button"
                  class="employee-table__projects-chip"
                  @click.stop="openProjectsModal(row, col.key)"
                >
                  <span class="employee-table__dot" aria-hidden="true"></span>
                  <span>{{ cellText(row, col.key) }}</span>
                </button>
                <span v-else class="employee-table__muted">{{ cellText(row, col.key) }}</span>
              </template>
              <span v-else>{{ cellText(row, col.key) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <TablePagesBar
      placement="bottom"
      paging-only
      :from="rows.length ? (page - 1) * perPage + 1 : 0"
      :to="Math.min(page * perPage, total)"
      :total="total"
      :page="page"
      :last-page="lastPage"
      :per-page="perPage"
      @update:page="emit('update:page', $event)"
      @update:perPage="emit('update:perPage', $event)"
    />

    <EmployeeProjectsModal
      :open="projectsModalOpen"
      :title="projectsModalTitle"
      :projects="projectsModalList"
      @close="closeProjectsModal"
    />
  </div>
</template>

<style scoped>
.employee-table {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.employee-table__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-2);
  color: var(--color-text-muted);
}

.employee-table__search input {
  border: none;
  outline: none;
  font-size: 0.8125rem;
  color: var(--color-text);
  background: transparent;
  width: 12rem;
}

.employee-table__col-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-2);
  font-size: 0.8125rem;
  cursor: pointer;
  white-space: nowrap;
}

.employee-table__wrap {
  overflow-x: auto;
}

.employee-table__wrap--resizing {
  cursor: col-resize;
}

.employee-table__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.employee-table__table th {
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

.employee-table__table td {
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.employee-table__table tbody tr {
  cursor: pointer;
  transition: background-color 0.1s ease;
}

.employee-table__table tbody tr:hover,
.employee-table__table tbody tr:focus-visible {
  background: var(--color-surface-muted);
  outline: none;
}

.employee-table__empty {
  text-align: center;
  color: var(--color-text-muted);
  padding: var(--space-5);
  white-space: normal;
}

.employee-table__resize {
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

.employee-table__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.employee-table__person {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.employee-table__avatar {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  object-fit: cover;
}

.employee-table__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
  font-size: 0.75rem;
}

.employee-table__projects-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: inherit;
  cursor: pointer;
}

.employee-table__projects-chip:hover span:last-child {
  text-decoration: underline;
}

.employee-table__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}

.employee-table__muted {
  color: var(--color-text-muted);
}
</style>
