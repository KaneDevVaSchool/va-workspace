<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import {
  REPORT_VIEW_SORT_KEY,
  REPORT_VIEW_WIDTH_KEY,
  REPORT_VIEW_ZOOM_KEY,
  loadColumnWidths,
  loadSort,
  loadZoom,
  saveSort,
  saveVisibility,
  saveZoom,
} from '../constants/report.js';

/** Xếp mặc định: điểm cao nhất lên đầu — báo cáo đánh giá là để xếp hạng. */
const DEFAULT_SORT = { key: 'final_score', dir: 'desc' };

const DEFAULT_WIDTHS = {
  user_name: 220,
  start_score: 150,
  task_adjustment: 160,
  bonus: 130,
  penalty: 130,
  final_score: 140,
  classification: 160,
};

const MIN_COL_PX = 72;
let wrapObserver = null;

const route = useRoute();
const router = useRouter();

const report = ref(null);
const summary = ref(null);
const rows = ref([]);
const loading = ref(false);
const saving = ref(false);

const detail = ref(null);
const detailLoading = ref(false);

const tableWrap = ref(null);
const resizing = ref(false);
const tableZoom = ref(loadZoom(REPORT_VIEW_ZOOM_KEY));
const sort = reactive(loadSort(REPORT_VIEW_SORT_KEY, DEFAULT_SORT));

useDragScroll(tableWrap, { isBlocked: () => resizing.value });

const columnWidths = reactive({ ...DEFAULT_WIDTHS, ...loadColumnWidths(REPORT_VIEW_WIDTH_KEY) });

const shownColumns = computed(() => {
  const keys = report.value?.columns ?? [];
  const labels = report.value?.column_labels ?? {};

  return [
    { key: 'user_name', label: 'Nhân sự' },
    ...keys.map((key) => ({ key, label: labels[key] ?? key })),
  ];
});

/** Cột nào xếp được: tên xếp theo chữ, còn lại theo số. */
const SORTABLE_KEYS = new Set([
  'user_name',
  'start_score',
  'task_adjustment',
  'bonus',
  'penalty',
  'final_score',
  'classification',
]);

const sortedRows = computed(() => {
  const list = [...rows.value];
  const { key, dir } = sort;
  const factor = dir === 'asc' ? 1 : -1;

  return list.sort((a, b) => {
    if (key === 'user_name') {
      return factor * String(a.user_name ?? '').localeCompare(String(b.user_name ?? ''), 'vi');
    }
    if (key === 'classification') {
      return (
        factor *
        String(a.classification_label ?? '').localeCompare(
          String(b.classification_label ?? ''),
          'vi',
        )
      );
    }
    return factor * ((Number(a[key]) || 0) - (Number(b[key]) || 0));
  });
});

const periodText = computed(() => {
  if (!report.value) return '';
  return `${formatDate(report.value.period_from)} — ${formatDate(report.value.period_to)}`;
});

const isDraft = computed(() => report.value?.status === 'draft');

/**
 * Số việc engine chưa tính được — không hiện ra thì người xem tưởng mọi thứ
 * đã tính đủ và ra quyết định nhân sự trên số liệu thiếu.
 */
const missingNotes = computed(() => {
  const missing = summary.value?.missing;
  if (!missing) return [];

  const notes = [];
  if (missing.difficulty > 0) {
    notes.push(`${missing.difficulty} việc chưa rõ độ khó`);
  }
  if (missing.progress > 0) {
    notes.push(`${missing.progress} việc chưa có ngày hoàn thành thực tế`);
  }
  if (missing.quality > 0) {
    notes.push(`${missing.quality} việc chưa chấm chất lượng`);
  }
  return notes;
});

const tableWidthPx = computed(() => {
  const sum = shownColumns.value.reduce(
    (acc, col) => acc + (Number(columnWidths[col.key]) || 140),
    0,
  );
  return sum > 0 ? `${sum}px` : '100%';
});

/**
 * Mỗi mức xếp loại một màu riêng, lấy theo thứ tự trong thang (cao xuống
 * thấp) chứ không gán cứng theo tên — phòng ban tự đặt tên mức.
 */
const classificationTones = computed(() => {
  const levels = summary.value?.distribution ?? [];
  const map = {};
  levels.forEach((level, index) => {
    const key = level.code || level.label;
    if (!key) return;
    if (levels.length <= 1) {
      map[key] = 'high';
      return;
    }
    const ratio = index / (levels.length - 1);
    map[key] = ratio <= 0.25 ? 'high' : ratio <= 0.5 ? 'good' : ratio <= 0.75 ? 'fair' : 'low';
  });
  return map;
});

function classificationTone(row) {
  const key = row?.classification_code || row?.classification_label;
  return classificationTones.value[key] ?? 'neutral';
}

function colWidthStyle(key) {
  return `${Number(columnWidths[key]) || 140}px`;
}

function toggleSort(key) {
  if (!SORTABLE_KEYS.has(key)) return;

  if (sort.key === key) {
    sort.dir = sort.dir === 'asc' ? 'desc' : 'asc';
    return;
  }
  sort.key = key;
  // Tên người xếp A→Z là tự nhiên, còn điểm thì cao nhất lên đầu.
  sort.dir = key === 'user_name' ? 'asc' : 'desc';
}

function sortIndicator(key) {
  if (sort.key !== key) return '';
  return sort.dir === 'asc' ? '▲' : '▼';
}

function startResize(event, key) {
  resizing.value = true;
  const startX = event.clientX;
  const startWidth = Number(columnWidths[key]) || 140;

  function onMove(moveEvent) {
    columnWidths[key] = Math.max(MIN_COL_PX, startWidth + (moveEvent.clientX - startX));
  }

  function onUp() {
    resizing.value = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function signed(value) {
  const num = Number(value) || 0;
  return num > 0 ? `+${num}` : String(num);
}

function cellText(row, key) {
  if (key === 'user_name') return row.user_name;
  if (key === 'classification') return row.classification_label ?? '—';
  if (key === 'task_adjustment' || key === 'bonus') return signed(row[key]);
  if (key === 'penalty') return row.penalty > 0 ? `-${row.penalty}` : '0';
  if (row[key] === undefined || row[key] === null) return '—';
  return String(row[key]);
}

async function loadReport() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(`/api/report/${route.params.id}`);
    report.value = data.report;
    summary.value = data.summary;
    rows.value = data.rows ?? [];
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không tải được báo cáo.');
    router.push({ name: 'manager.reports.index' });
  } finally {
    loading.value = false;
  }
}

async function openDetail(row) {
  detailLoading.value = true;
  detail.value = null;
  try {
    const { data } = await window.axios.get(
      `/api/report/${route.params.id}/employees/${row.user_id}`,
    );
    detail.value = data.detail;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không tải được chi tiết nhân sự.');
  } finally {
    detailLoading.value = false;
  }
}

async function saveReport() {
  if (saving.value) return;

  saving.value = true;
  try {
    const { data } = await window.axios.patch(`/api/report/${route.params.id}/save`);
    report.value = { ...report.value, ...data.report };
    showClientToast('success', 'Đã lưu báo cáo.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không lưu được báo cáo.');
  } finally {
    saving.value = false;
  }
}

function goToEvents() {
  router.push({ name: 'manager.evaluation-events.index' });
}

/** In trang — đường thoát nhanh khi chưa có xuất file. */
function printReport() {
  window.print();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && detail.value) {
    detail.value = null;
  }
}

watch(columnWidths, (value) => saveVisibility(REPORT_VIEW_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => saveZoom(REPORT_VIEW_ZOOM_KEY, value));
watch(sort, (value) => saveSort(REPORT_VIEW_SORT_KEY, { key: value.key, dir: value.dir }), {
  deep: true,
});

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  loadReport();
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  wrapObserver?.disconnect();
});
</script>

<template>
  <section class="report-view">
    <PageHeader
      :title="report?.title ?? 'Báo cáo'"
      icon="barChart"
      :description="report ? `Kỳ ${periodText} · Phiên bản khung chấm điểm số ${report.version_no ?? '—'}` : ''"
    >
      <template #actions>
        <button
          type="button"
          class="report-view__header-btn"
          @click="router.push({ name: 'manager.reports.index' })"
        >
          <AppIcon name="chevronLeft" :size="16" />
          Danh sách báo cáo
        </button>
        <button type="button" class="report-view__header-btn" @click="printReport">
          <AppIcon name="fileText" :size="16" />
          In báo cáo
        </button>
        <button
          v-if="isDraft"
          type="button"
          class="report-view__header-btn"
          :disabled="saving"
          @click="saveReport"
        >
          <AppIcon name="check" :size="16" />
          {{ saving ? 'Đang lưu…' : 'Lưu báo cáo' }}
        </button>
      </template>
    </PageHeader>

    <p v-if="loading" class="report-view__loading">Đang tính điểm…</p>

    <div v-else class="report-view__body">
      <div class="report-view__main">
        <div v-if="summary" class="report-view__summary">
          <div class="report-view__stat">
            <span class="report-view__stat-label">Tổng nhân sự</span>
            <span class="report-view__stat-value">{{ summary.total_people }}</span>
          </div>
          <div class="report-view__stat">
            <span class="report-view__stat-label">Điểm trung bình</span>
            <span class="report-view__stat-value">{{ summary.average_score }}</span>
          </div>
          <div class="report-view__stat">
            <span class="report-view__stat-label">Điểm cao nhất</span>
            <span class="report-view__stat-value">{{ summary.highest_score }}</span>
          </div>
          <div class="report-view__stat">
            <span class="report-view__stat-label">Điểm thấp nhất</span>
            <span class="report-view__stat-value">{{ summary.lowest_score }}</span>
          </div>
        </div>

        <div v-if="summary?.distribution?.length" class="report-view__distribution">
          <span
            v-for="level in summary.distribution"
            :key="level.code || level.label"
            class="report-view__level"
          >
            <span
              class="report-view__dot"
              :class="`report-view__dot--${classificationTones[level.code || level.label] ?? 'neutral'}`"
            />
            {{ level.label }}: {{ level.count }} người
          </span>
        </div>

        <p v-if="missingNotes.length" class="report-view__warning">
          Một số công việc chưa đủ dữ liệu nên được tính như mức trung bình:
          {{ missingNotes.join(', ') }}. Bổ sung dữ liệu rồi mở lại báo cáo để có số chính xác hơn.
        </p>

        <div
          ref="tableWrap"
          class="report-view__table-wrap hide-scrollbar"
          :class="{ 'report-view__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="report-view__table" :style="{ width: tableWidthPx }">
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
                  <button
                    type="button"
                    class="report-view__sort"
                    :aria-label="`Sắp xếp theo ${col.label}`"
                    @click="toggleSort(col.key)"
                  >
                    <span>{{ col.label }}</span>
                    <span v-if="sortIndicator(col.key)" class="report-view__sort-mark">
                      {{ sortIndicator(col.key) }}
                    </span>
                  </button>
                  <button
                    type="button"
                    class="report-view__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td :colspan="shownColumns.length" class="report-view__empty">
                  <p class="report-view__empty-title">
                    Chưa có nhân sự nào trong phạm vi báo cáo này.
                  </p>
                  <p class="report-view__empty-text">
                    Kiểm tra lại kỳ báo cáo và danh sách nhân sự đã chọn. Nếu phòng ban chưa có
                    ghi nhận đánh giá nào, hãy thêm ghi nhận trước rồi mở lại báo cáo.
                  </p>
                  <button type="button" class="report-view__empty-btn" @click="goToEvents">
                    Sang trang Ghi nhận đánh giá
                  </button>
                </td>
              </tr>
              <tr
                v-for="row in sortedRows"
                v-else
                :key="row.user_id"
                :class="{ 'report-view__row--active': detail?.user_id === row.user_id }"
                @click="openDetail(row)"
              >
                <td v-for="col in shownColumns" :key="col.key">
                  <span v-if="col.key === 'classification'" class="report-view__classification">
                    <span
                      class="report-view__dot"
                      :class="`report-view__dot--${classificationTone(row)}`"
                    />
                    {{ row.classification_label ?? '—' }}
                  </span>
                  <span v-else class="report-view__cell">{{ cellText(row, col.key) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <aside v-if="detailLoading || detail" class="report-view__side" aria-label="Chi tiết nhân sự">
        <div class="report-view__side-head">
          <h2 class="report-view__side-title">
            {{ detail ? detail.user_name : 'Đang tải…' }}
          </h2>
          <button
            type="button"
            class="report-view__icon-btn"
            aria-label="Đóng"
            @click="detail = null"
          >
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <template v-if="detail">
          <div class="report-view__rows">
            <div class="report-view__row">
              <span class="report-view__row-label">Điểm khởi đầu</span>
              <span class="report-view__row-value">{{ detail.start_score }}</span>
            </div>
            <div class="report-view__row">
              <span class="report-view__row-label">Điểm từ công việc</span>
              <span class="report-view__row-value">{{ signed(detail.task_adjustment) }}</span>
            </div>
            <div class="report-view__row">
              <span class="report-view__row-label">Điểm cộng hành vi</span>
              <span class="report-view__row-value">{{ signed(detail.bonus) }}</span>
            </div>
            <div class="report-view__row">
              <span class="report-view__row-label">Điểm trừ hành vi</span>
              <span class="report-view__row-value">
                {{ detail.penalty > 0 ? `-${detail.penalty}` : '0' }}
              </span>
            </div>
            <div class="report-view__row">
              <span class="report-view__row-label">Điểm cuối</span>
              <span class="report-view__row-value">{{ detail.final_score }}</span>
            </div>
            <div class="report-view__row">
              <span class="report-view__row-label">Xếp loại</span>
              <span class="report-view__row-value">{{ detail.classification_label ?? '—' }}</span>
            </div>
          </div>

          <div v-if="detail.task_breakdown?.length" class="report-view__block">
            <h3 class="report-view__block-title">Đóng góp từ công việc</h3>
            <div class="report-view__rows">
              <div v-for="task in detail.task_breakdown" :key="task.task_id" class="report-view__row">
                <span class="report-view__row-label">{{ task.title }}</span>
                <span class="report-view__row-value">{{ signed(task.contribution) }}</span>
              </div>
            </div>
          </div>

          <div v-if="detail.event_breakdown?.length" class="report-view__block">
            <h3 class="report-view__block-title">Ghi nhận theo hành vi</h3>
            <div class="report-view__rows">
              <div
                v-for="event in detail.event_breakdown"
                :key="event.event_id"
                class="report-view__row"
              >
                <span class="report-view__row-label">
                  {{ event.level_label }} · {{ formatDate(event.occurred_at) }}
                </span>
                <span class="report-view__row-value">{{ signed(event.score) }}</span>
              </div>
            </div>
          </div>
        </template>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.report-view {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.report-view__header-btn {
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

.report-view__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.report-view__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.report-view__loading {
  margin: var(--space-5) 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.report-view__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.report-view__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.report-view__summary {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-4);
  margin: var(--space-3) 0;
}

.report-view__stat {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.report-view__stat-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.report-view__stat-value {
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.report-view__distribution {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-bottom: var(--space-3);
}

.report-view__level {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
}

/*
 * Mỗi mức xếp loại một màu riêng — cùng màu hết thì bảng phân bổ không nói
 * lên điều gì. Vẫn là chấm nhỏ + chữ thường, không badge (CLAUDE.md §14).
 */
.report-view__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.report-view__dot--high {
  background: var(--color-success);
}

.report-view__dot--good {
  background: var(--color-secondary);
}

.report-view__dot--fair {
  background: var(--color-warning);
}

.report-view__dot--low {
  background: var(--color-danger);
}

.report-view__dot--neutral {
  background: var(--color-text-muted);
}

.report-view__classification {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  white-space: nowrap;
}

.report-view__warning {
  flex-shrink: 0;
  margin: 0 0 var(--space-3);
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-warning-tint-border);
  border-radius: var(--radius-md);
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.report-view__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.report-view__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.report-view__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.report-view__table thead th {
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

.report-view__table th {
  position: relative;
}

/* Bấm tiêu đề cột để đổi cách xếp — báo cáo đánh giá cần xếp hạng được. */
.report-view__sort {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  letter-spacing: inherit;
  cursor: pointer;
}

.report-view__sort:hover {
  color: var(--color-text);
}

.report-view__sort-mark {
  color: var(--color-primary);
  font-size: 0.625rem;
}

.report-view__resize {
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

.report-view__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.report-view__resize:hover::after {
  background: var(--color-primary);
}

.report-view__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  box-shadow: 0 1px 0 var(--color-border);
}

.report-view__table tbody tr {
  cursor: pointer;
}

.report-view__table tbody tr:hover {
  background: var(--color-surface-muted);
}

.report-view__row--active {
  background: var(--color-primary-50);
}

.report-view__cell {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.report-view__empty {
  padding: var(--space-6);
  color: var(--color-text-muted);
  text-align: center;
  white-space: normal;
}

.report-view__empty-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.report-view__empty-text {
  margin: 0 auto var(--space-4);
  max-width: 32rem;
  font-size: 0.8125rem;
  line-height: 1.6;
}

.report-view__empty-btn {
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

.report-view__empty-btn:hover {
  background: var(--color-primary-hover);
}

.report-view__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.report-view__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

.report-view__side-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.report-view__icon-btn {
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

.report-view__icon-btn:hover {
  background: var(--color-surface);
}

.report-view__block {
  margin-top: var(--space-4);
}

.report-view__block-title {
  margin: 0 0 var(--space-2);
  font-size: 0.8125rem;
  font-weight: 600;
}

.report-view__rows {
  display: flex;
  flex-direction: column;
}

.report-view__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.report-view__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.report-view__row-label::after {
  content: ':';
}

.report-view__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

@media (max-width: 768px) {
  .report-view__summary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .report-view__body {
    flex-direction: column;
  }

  .report-view__side {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .report-view {
    padding: var(--space-3);
  }

  .report-view__summary {
    grid-template-columns: 1fr;
  }
}

/*
 * In báo cáo — đường thoát nhanh khi chưa có xuất file. Bảng phải bung hết
 * chiều cao (bình thường bị giới hạn trong vùng cuộn) và bỏ mọi nút bấm.
 */
@media print {
  .report-view {
    height: auto;
    padding: 0;
    overflow: visible;
  }

  .report-view__body,
  .report-view__main {
    display: block;
    height: auto;
    overflow: visible;
  }

  .report-view__table-wrap {
    height: auto;
    max-height: none;
    overflow: visible;
    border: 1px solid var(--color-border);
  }

  .report-view__table {
    width: 100% !important;
    table-layout: auto;
    font-size: 11pt;
  }

  .report-view__table thead th {
    position: static;
    background: transparent;
  }

  .report-view__resize,
  .report-view__side,
  .report-view__empty-btn,
  .report-view__header-btn {
    display: none !important;
  }

  .report-view__sort {
    cursor: default;
  }

  .report-view__sort-mark {
    display: none;
  }

  .report-view__table tbody tr {
    break-inside: avoid;
  }
}
</style>

<style>
/*
 * Khi in, vỏ ứng dụng (sidebar, thanh trên, vùng cuộn) không được xuất hiện
 * trên giấy — phần này phải là style toàn cục vì các phần tử đó nằm ngoài
 * component. Chỉ áp dụng lúc in, không ảnh hưởng màn hình.
 */
@media print {
  body:has(.report-view) .sidebar,
  body:has(.report-view) .app-layout__header,
  body:has(.report-view) .app-layout__header-reveal {
    display: none !important;
  }

  body:has(.report-view) .app-layout,
  body:has(.report-view) .app-layout__main,
  body:has(.report-view) .app-layout__content {
    display: block !important;
    height: auto !important;
    overflow: visible !important;
  }
}
</style>
