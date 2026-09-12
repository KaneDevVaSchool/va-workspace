<script setup>
//
// Tab Báo cáo — ma trận sheet nhân sự × số liệu việc, cùng kiểu trang
// Đánh giá (EvaluationSummary): bảng kín khung, 2 hàng tiêu đề nhóm cột,
// cột Nhân sự ghim trái, chân bảng tổng dự án, kéo chuột để cuộn, click
// dòng mở phiếu việc của người đó. Không dùng TablePagesBar.
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { showClientToast } from '@/lib/clientToast';
import { TASK_STATUS_LABELS, TASK_STATUS_TONES } from '../constants/task.js';

const UNASSIGNED_ID = 0;

const GROUPS = [
  {
    key: 'total',
    label: 'Tổng công việc',
    cols: [
      { key: 'total_count', label: 'Số lượng' },
      { key: 'total_on_time', label: 'Đúng hạn' },
      { key: 'total_overdue', label: 'Quá hạn' },
    ],
  },
  {
    key: 'in_progress',
    label: 'Đang thực hiện',
    cols: [
      { key: 'in_progress_count', label: 'Số lượng' },
      { key: 'in_progress_on_time', label: 'Trong hạn' },
      { key: 'in_progress_overdue', label: 'Quá hạn' },
    ],
  },
  {
    key: 'completed',
    label: 'Hoàn thành',
    cols: [
      { key: 'completed_count', label: 'Số lượng' },
      { key: 'completed_on_time', label: 'Đúng hạn' },
      { key: 'completed_late', label: 'Trễ hạn' },
    ],
  },
  {
    key: 'result',
    label: 'Kết quả',
    cols: [
      { key: 'progress', label: 'Tiến độ' },
      { key: 'est_hours', label: 'Giờ dự kiến' },
      { key: 'work_hours', label: 'Giờ thực tế' },
    ],
  },
];

const MIN_COL = {
  user: 220,
  total_count: 88,
  total_on_time: 88,
  total_overdue: 88,
  in_progress_count: 88,
  in_progress_on_time: 88,
  in_progress_overdue: 88,
  completed_count: 88,
  completed_on_time: 88,
  completed_late: 88,
  progress: 96,
  est_hours: 104,
  work_hours: 104,
};

const COL_KEYS = GROUPS.flatMap((group) => group.cols.map((col) => col.key));
const COL_SPAN = 1 + COL_KEYS.length;

const props = defineProps({
  project: { type: Object, required: true },
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const router = useRouter();

const tableWrap = ref(null);
const personTableWrap = ref(null);
const wrapWidth = ref(0);
const selectedId = ref(null);
const detailOpen = ref(false);
let wrapObserver = null;

useDragScroll(tableWrap);
useDragScroll(personTableWrap);

function flattenTasks(nodes) {
  const out = [];
  const walk = (list) => {
    for (const node of list || []) {
      out.push(node);
      if (node.children?.length) walk(node.children);
    }
  };
  walk(nodes);
  return out;
}

const realTasks = computed(() =>
  flattenTasks(props.tree).filter((task) => task.type === 'task'),
);

function isCompletedLate(task) {
  if (task?.status !== 'completed') return false;
  if (Number(task.overdue_days) > 0) return true;
  return Number(task.variance_days) > 0;
}

function isOpenOverdue(task) {
  return Boolean(task?.is_overdue);
}

function isOnTime(task) {
  if (!task || task.status === 'cancelled') return false;
  if (task.status === 'completed') return !isCompletedLate(task);
  return !isOpenOverdue(task);
}

function isOverdueAny(task) {
  return isOpenOverdue(task) || isCompletedLate(task);
}

function emptyStats() {
  return {
    total_count: 0,
    total_on_time: 0,
    total_overdue: 0,
    in_progress_count: 0,
    in_progress_on_time: 0,
    in_progress_overdue: 0,
    completed_count: 0,
    completed_on_time: 0,
    completed_late: 0,
    progress_sum: 0,
    est_hours: 0,
    work_hours: 0,
  };
}

function addTaskToStats(stats, task) {
  stats.total_count += 1;
  if (isOnTime(task)) stats.total_on_time += 1;
  if (isOverdueAny(task)) stats.total_overdue += 1;

  if (task.status === 'in_progress') {
    stats.in_progress_count += 1;
    if (!isOpenOverdue(task)) stats.in_progress_on_time += 1;
    if (isOpenOverdue(task)) stats.in_progress_overdue += 1;
  }

  if (task.status === 'completed') {
    stats.completed_count += 1;
    if (!isCompletedLate(task)) stats.completed_on_time += 1;
    if (isCompletedLate(task)) stats.completed_late += 1;
  }

  stats.progress_sum += Number(task.progress_percent) || 0;
  stats.est_hours += Number(task.estimated_hours) || 0;
  stats.work_hours += Number(task.worklog_hours) || 0;
}

function withProgress(stats) {
  return {
    ...stats,
    progress: stats.total_count
      ? Math.round(stats.progress_sum / stats.total_count)
      : 0,
  };
}

const sheetRows = computed(() => {
  const map = new Map();
  const addPerson = (person) => {
    if (!person?.id || map.has(person.id)) return;
    map.set(person.id, {
      id: person.id,
      name: person.name || '—',
      email: person.email || '',
      tasks: [],
      stats: emptyStats(),
    });
  };

  for (const member of props.project?.members || []) addPerson(member);
  for (const task of realTasks.value) {
    if (task.assignee) addPerson(task.assignee);
  }

  const unassigned = {
    id: UNASSIGNED_ID,
    name: 'Chưa giao',
    email: 'Công việc chưa có người thực hiện',
    tasks: [],
    stats: emptyStats(),
  };

  for (const task of realTasks.value) {
    const id = task.assignee?.id;
    const row = id && map.has(id) ? map.get(id) : unassigned;
    row.tasks.push(task);
    addTaskToStats(row.stats, task);
  }

  const rows = [...map.values()].map((row) => ({
    ...row,
    stats: withProgress(row.stats),
  }));
  rows.sort((a, b) => {
    if (b.stats.total_count !== a.stats.total_count) return b.stats.total_count - a.stats.total_count;
    return String(a.name).localeCompare(String(b.name), 'vi');
  });

  if (unassigned.tasks.length) {
    rows.push({ ...unassigned, stats: withProgress(unassigned.stats) });
  }

  return rows;
});

const foot = computed(() => {
  const stats = emptyStats();
  for (const task of realTasks.value) addTaskToStats(stats, task);
  return withProgress(stats);
});

const tableWidthPx = computed(() => {
  const sum = MIN_COL.user + COL_KEYS.reduce((total, key) => total + MIN_COL[key], 0);
  return `${Math.max(wrapWidth.value, sum)}px`;
});

function colWidthStyle(key) {
  const min = MIN_COL[key] ?? 80;
  const sum = MIN_COL.user + COL_KEYS.reduce((total, col) => total + (MIN_COL[col] ?? 80), 0);
  const extra = Math.max(0, wrapWidth.value - sum);
  if (key === 'user' && extra > 0) {
    return `${min + extra}px`;
  }
  return `${min}px`;
}

function formatNumber(value) {
  const num = Number(value) || 0;
  return Number.isInteger(num) ? String(num) : num.toFixed(1).replace(/\.0$/, '');
}

function formatPercent(value) {
  return `${formatNumber(value)}%`;
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('vi-VN');
}

function percentChipClass(value) {
  const num = Number(value) || 0;
  if (num >= 100) return 'sheet__chip sheet__chip--ok';
  if (num <= 0) return 'sheet__chip sheet__chip--muted';
  return 'sheet__chip sheet__chip--pct';
}

function countChipClass(value, tone) {
  const num = Number(value) || 0;
  if (tone === 'danger' && num > 0) return 'sheet__chip sheet__chip--minus';
  if (tone === 'ok' && num > 0) return 'sheet__chip sheet__chip--ok';
  return 'sheet__chip';
}

function statusTone(task) {
  if (isOpenOverdue(task) || isCompletedLate(task)) return 'danger';
  const mapped = TASK_STATUS_TONES[task.status];
  if (mapped === 'success') return 'ok';
  if (mapped === 'primary' || mapped === 'info') return 'info';
  if (mapped === 'gold' || mapped === 'warning') return 'warn';
  return 'muted';
}

function stripTone(row) {
  if (!row) return '';
  if (row.stats.total_overdue > 0) return 'danger';
  if (row.stats.completed_count > 0 && row.stats.completed_count === row.stats.total_count) return 'success';
  return 'info';
}

const selected = computed(() => sheetRows.value.find((row) => row.id === selectedId.value) || null);

const stepInfo = computed(() => {
  const list = sheetRows.value;
  const index = list.findIndex((row) => row.id === selectedId.value);
  return {
    index: Math.max(0, index),
    total: list.length,
    hasPrev: index > 0,
    hasNext: index >= 0 && index < list.length - 1,
  };
});

function openPerson(row) {
  selectedId.value = row.id;
  detailOpen.value = true;
}

function closeDetail() {
  detailOpen.value = false;
}

function step(delta) {
  const list = sheetRows.value;
  const index = list.findIndex((row) => row.id === selectedId.value);
  const next = list[index + delta];
  if (next) selectedId.value = next.id;
}

function openTask(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

function csvCell(value) {
  const text = String(value ?? '');
  if (/[",\n]/.test(text)) return `"${text.replace(/"/g, '""')}"`;
  return text;
}

function exportCsv() {
  const headers = ['Nhân sự', 'Email', ...GROUPS.flatMap((group) => group.cols.map((col) => `${group.label} · ${col.label}`))];
  const lines = [headers.join(',')];
  const cellsOf = (stats) => [
    stats.total_count,
    stats.total_on_time,
    stats.total_overdue,
    stats.in_progress_count,
    stats.in_progress_on_time,
    stats.in_progress_overdue,
    stats.completed_count,
    stats.completed_on_time,
    stats.completed_late,
    formatPercent(stats.progress),
    formatNumber(stats.est_hours),
    formatNumber(stats.work_hours),
  ];
  for (const row of sheetRows.value) {
    lines.push([csvCell(row.name), csvCell(row.email), ...cellsOf(row.stats)].join(','));
  }
  if (sheetRows.value.length) {
    lines.push([csvCell('Dự án'), '', ...cellsOf(foot.value)].join(','));
  }
  const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const stamp = new Date().toISOString().slice(0, 10);
  const code = String(props.project?.code || props.project?.id || 'du-an').replace(/[^\w.-]+/g, '_');
  link.href = url;
  link.download = `bao-cao-du-an_${code}_${stamp}.csv`;
  link.click();
  URL.revokeObjectURL(url);
  showClientToast('success', 'Đã xuất bảng CSV.');
}

function onKeydown(event) {
  if (event.key !== 'Escape') return;
  if (detailOpen.value) {
    event.preventDefault();
    closeDetail();
  }
}

watch(detailOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
  nextTick(() => {
    if (!tableWrap.value) return;
    wrapWidth.value = tableWrap.value.clientWidth;
    wrapObserver = new ResizeObserver((entries) => {
      const width = Math.round(entries[0]?.contentRect?.width || 0);
      if (width) wrapWidth.value = width;
    });
    wrapObserver.observe(tableWrap.value);
  });
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  wrapObserver?.disconnect();
  document.body.style.overflow = '';
});

defineExpose({ exportCsv });
</script>

<template>
  <section class="sheet">
    <div class="sheet__body">
      <div ref="tableWrap" class="sheet__wrap hide-scrollbar">
        <table class="sheet__table" :style="{ width: tableWidthPx }">
          <colgroup>
            <col :style="{ width: colWidthStyle('user') }" />
            <col v-for="key in COL_KEYS" :key="key" :style="{ width: colWidthStyle(key) }" />
          </colgroup>

          <thead>
            <tr class="sheet__groups">
              <th class="sheet__pin" rowspan="2">Nhân sự</th>
              <th
                v-for="group in GROUPS"
                :key="group.key"
                class="sheet__th-block"
                :colspan="group.cols.length"
              >
                {{ group.label }}
              </th>
            </tr>
            <tr class="sheet__leaves">
              <th v-for="col in GROUPS.flatMap((group) => group.cols)" :key="col.key">
                {{ col.label }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="loading">
              <td :colspan="COL_SPAN" class="sheet__empty">Đang tải…</td>
            </tr>
            <tr v-else-if="sheetRows.length === 0">
              <td :colspan="COL_SPAN" class="sheet__empty">
                Dự án chưa có người thực hiện hay công việc để tổng hợp.
              </td>
            </tr>
            <tr
              v-for="row in sheetRows"
              v-else
              :key="row.id"
              :class="{ 'sheet__row--on': selectedId === row.id }"
              @click="openPerson(row)"
            >
              <td class="sheet__pin sheet__td-user">
                <button type="button" class="sheet__who" @click.stop="openPerson(row)">
                  <span class="sheet__name">{{ row.name }}</span>
                  <span v-if="row.email" class="sheet__email">{{ row.email }}</span>
                </button>
              </td>
              <td>
                <span class="sheet__chip">{{ row.stats.total_count }}</span>
              </td>
              <td>
                <span :class="countChipClass(row.stats.total_on_time, 'ok')">{{ row.stats.total_on_time }}</span>
              </td>
              <td>
                <span :class="countChipClass(row.stats.total_overdue, 'danger')">{{ row.stats.total_overdue }}</span>
              </td>
              <td>
                <span class="sheet__chip">{{ row.stats.in_progress_count }}</span>
              </td>
              <td>
                <span class="sheet__chip">{{ row.stats.in_progress_on_time }}</span>
              </td>
              <td>
                <span :class="countChipClass(row.stats.in_progress_overdue, 'danger')">
                  {{ row.stats.in_progress_overdue }}
                </span>
              </td>
              <td>
                <span class="sheet__chip">{{ row.stats.completed_count }}</span>
              </td>
              <td>
                <span :class="countChipClass(row.stats.completed_on_time, 'ok')">{{ row.stats.completed_on_time }}</span>
              </td>
              <td>
                <span :class="countChipClass(row.stats.completed_late, 'danger')">{{ row.stats.completed_late }}</span>
              </td>
              <td>
                <span :class="percentChipClass(row.stats.progress)">{{ formatPercent(row.stats.progress) }}</span>
              </td>
              <td>
                <span class="sheet__chip">{{ formatNumber(row.stats.est_hours) }}</span>
              </td>
              <td>
                <span class="sheet__chip">{{ formatNumber(row.stats.work_hours) }}</span>
              </td>
            </tr>
          </tbody>

          <tfoot v-if="!loading && sheetRows.length">
            <tr>
              <th class="sheet__pin">Dự án</th>
              <td><span class="sheet__chip">{{ foot.total_count }}</span></td>
              <td><span :class="countChipClass(foot.total_on_time, 'ok')">{{ foot.total_on_time }}</span></td>
              <td><span :class="countChipClass(foot.total_overdue, 'danger')">{{ foot.total_overdue }}</span></td>
              <td><span class="sheet__chip">{{ foot.in_progress_count }}</span></td>
              <td><span class="sheet__chip">{{ foot.in_progress_on_time }}</span></td>
              <td>
                <span :class="countChipClass(foot.in_progress_overdue, 'danger')">{{ foot.in_progress_overdue }}</span>
              </td>
              <td><span class="sheet__chip">{{ foot.completed_count }}</span></td>
              <td><span :class="countChipClass(foot.completed_on_time, 'ok')">{{ foot.completed_on_time }}</span></td>
              <td><span :class="countChipClass(foot.completed_late, 'danger')">{{ foot.completed_late }}</span></td>
              <td><span :class="percentChipClass(foot.progress)">{{ formatPercent(foot.progress) }}</span></td>
              <td><span class="sheet__chip">{{ formatNumber(foot.est_hours) }}</span></td>
              <td><span class="sheet__chip">{{ formatNumber(foot.work_hours) }}</span></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="detailOpen && selected"
        class="sheet__dialog"
        role="presentation"
        @mousedown.self="closeDetail"
      >
        <div
          class="sheet__dialog-panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="project-report-person-title"
          @mousedown.stop
        >
          <div class="sheet__dialog-head">
            <div class="sheet__dialog-head-copy">
              <h2 id="project-report-person-title" class="sheet__dialog-title">{{ selected.name }}</h2>
              <p v-if="selected.email" class="sheet__dialog-email">{{ selected.email }}</p>
            </div>
            <div class="sheet__dialog-head-nav">
              <button
                type="button"
                class="sheet__icon-btn"
                aria-label="Nhân sự trước đó"
                :disabled="!stepInfo.hasPrev"
                @click="step(-1)"
              >
                <AppIcon name="chevronLeft" :size="16" />
              </button>
              <span class="sheet__dialog-pos">{{ stepInfo.index + 1 }}/{{ stepInfo.total }}</span>
              <button
                type="button"
                class="sheet__icon-btn"
                aria-label="Nhân sự kế tiếp"
                :disabled="!stepInfo.hasNext"
                @click="step(1)"
              >
                <AppIcon name="chevronRight" :size="16" />
              </button>
              <button type="button" class="sheet__dialog-close" aria-label="Đóng" @click="closeDetail">
                <AppIcon name="close" :size="16" />
              </button>
            </div>
          </div>

          <div class="sheet__strip" :class="`sheet__strip--${stripTone(selected)}`">
            <div class="sheet__strip-item">
              <span class="sheet__strip-label">Tiến độ</span>
              <span class="sheet__strip-value">{{ formatPercent(selected.stats.progress) }}</span>
            </div>
            <div class="sheet__strip-item">
              <span class="sheet__strip-label">Việc</span>
              <span class="sheet__strip-value">
                {{ selected.stats.completed_count }}/{{ selected.stats.total_count }}
              </span>
            </div>
            <div class="sheet__strip-meta">
              <span>
                <span class="sheet__strip-label">Đúng hạn</span>
                <span class="sheet__strip-value">{{ selected.stats.total_on_time }}</span>
              </span>
              <span v-if="selected.stats.total_overdue > 0" class="sheet__strip-warn">
                <span class="sheet__strip-label">Quá hạn</span>
                <span class="sheet__strip-value">{{ selected.stats.total_overdue }}</span>
              </span>
              <span>
                <span class="sheet__strip-label">Giờ thực tế</span>
                <span class="sheet__strip-value">{{ formatNumber(selected.stats.work_hours) }}</span>
              </span>
            </div>
          </div>

          <div class="sheet__dialog-body hide-scrollbar">
            <div v-if="selected.tasks.length === 0" class="sheet__empty-card">
              <AppIcon name="listChecks" :size="22" :stroke-width="1.75" />
              <p>Chưa được giao công việc trong dự án này.</p>
            </div>
            <div v-else class="sheet__person">
              <div class="sheet__person-head">
                <span class="sheet__person-title">Công việc trong dự án</span>
                <span class="sheet__person-count">{{ selected.tasks.length }} việc</span>
              </div>
              <div ref="personTableWrap" class="sheet__person-wrap hide-scrollbar">
                <table class="sheet__person-table">
                  <thead>
                    <tr>
                      <th>Việc</th>
                      <th>Trạng thái</th>
                      <th>Tiến độ</th>
                      <th>Hạn nộp</th>
                      <th>Ngày xong</th>
                      <th class="sheet__person-num">Giờ dự kiến</th>
                      <th class="sheet__person-num">Giờ thực tế</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="task in selected.tasks"
                      :key="task.id"
                      :class="{
                        'sheet__person-row--danger': isOpenOverdue(task) || isCompletedLate(task),
                      }"
                    >
                      <td class="sheet__person-title">
                        <button type="button" class="sheet__task-link" @click="openTask(task)">
                          {{ task.title }}
                        </button>
                        <span v-if="task.code" class="sheet__person-sub">{{ task.code }}</span>
                      </td>
                      <td>
                        <span class="sheet__pill" :class="`sheet__pill--${statusTone(task)}`">
                          {{ TASK_STATUS_LABELS[task.status] ?? task.status }}
                        </span>
                      </td>
                      <td>
                        <span :class="percentChipClass(task.progress_percent)">
                          {{ formatPercent(task.progress_percent) }}
                        </span>
                      </td>
                      <td>{{ formatDate(task.end_date) }}</td>
                      <td>{{ formatDate(task.actual_end_date) }}</td>
                      <td class="sheet__person-num">{{ formatNumber(task.estimated_hours) }}</td>
                      <td class="sheet__person-num">{{ formatNumber(task.worklog_hours) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.sheet {
  --sheet-group-h: 2rem;
  --sheet-leaf-h: 3rem;
  --sheet-vline: var(--color-border-strong);
  --sheet-hline: var(--color-border-strong);
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

.sheet__body {
  display: flex;
  flex: 1;
  min-height: 0;
}

.sheet__wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  background: var(--color-surface);
}

.sheet__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.sheet__table th,
.sheet__table td {
  position: relative;
  height: var(--sheet-leaf-h);
  padding: 0 0.75rem;
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: 400;
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
  box-shadow:
    inset -1px 0 0 var(--sheet-vline),
    inset 0 -1px 0 var(--sheet-hline);
}

.sheet__table thead th {
  z-index: 3;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
}

.sheet__groups th {
  top: 0;
  height: var(--sheet-group-h);
  text-transform: uppercase;
  box-shadow:
    inset -1px 0 0 var(--sheet-vline),
    inset 0 1px 0 var(--sheet-hline),
    inset 0 -1px 0 var(--sheet-hline);
}

.sheet__groups th[rowspan='2'] {
  height: calc(var(--sheet-group-h) + var(--sheet-leaf-h));
  text-transform: none;
  font-size: 0.75rem;
  letter-spacing: 0;
}

.sheet__th-block {
  text-align: center;
}

.sheet__leaves th {
  top: var(--sheet-group-h);
  white-space: normal;
  line-height: 1.25;
}

.sheet__table thead th,
.sheet__table tbody td.sheet__pin,
.sheet__table tfoot .sheet__pin {
  position: sticky;
}

.sheet__pin {
  left: 0;
  z-index: 2;
  text-align: left;
  box-shadow:
    inset 1px 0 0 var(--sheet-vline),
    inset -1px 0 0 var(--sheet-vline),
    inset 0 -1px 0 var(--sheet-hline);
}

.sheet__table thead .sheet__pin {
  z-index: 5;
  box-shadow:
    inset 1px 0 0 var(--sheet-vline),
    inset -1px 0 0 var(--sheet-vline),
    inset 0 1px 0 var(--sheet-hline),
    inset 0 -1px 0 var(--sheet-hline);
}

.sheet__table tfoot .sheet__pin {
  z-index: 4;
}

.sheet__table tbody tr {
  cursor: pointer;
}

.sheet__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.sheet__row--on td {
  background: var(--color-secondary-surface);
}

.sheet__td-user {
  text-align: left;
}

.sheet__who {
  display: flex;
  width: 100%;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 0.125rem;
  overflow: hidden;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.sheet__who:hover .sheet__name {
  color: var(--color-primary);
}

.sheet__name {
  display: block;
  max-width: 100%;
  overflow: hidden;
  font-weight: 600;
  text-align: left;
  text-overflow: ellipsis;
}

.sheet__email {
  display: block;
  max-width: 100%;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 400;
  text-align: left;
  text-overflow: ellipsis;
}

.sheet__chip {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 2.25rem;
  padding: 0.125rem 0.4375rem;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
  white-space: nowrap;
}

.sheet__chip--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.sheet__chip--minus {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.sheet__chip--muted {
  color: var(--color-text-muted);
}

.sheet__chip--pct {
  min-width: 3.25rem;
}

.sheet__empty {
  height: 6rem;
  color: var(--color-text-muted);
  white-space: normal;
}

.sheet__table tfoot th,
.sheet__table tfoot td {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: var(--color-surface-muted);
  font-weight: 600;
}

.sheet__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.sheet__dialog-panel {
  display: flex;
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  flex-direction: column;
  overflow: hidden;
  padding: 1.25rem 1.5rem 1rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.sheet__dialog-head {
  display: flex;
  flex-shrink: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}

.sheet__dialog-head-copy {
  min-width: 0;
}

.sheet__dialog-title {
  margin: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sheet__dialog-email {
  margin: 0.25rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.sheet__dialog-head-nav {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-1);
}

.sheet__icon-btn,
.sheet__dialog-close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.sheet__dialog-close {
  background: transparent;
  color: var(--color-text-muted);
  box-shadow: none;
}

.sheet__icon-btn:hover:not(:disabled),
.sheet__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.sheet__icon-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.sheet__dialog-pos {
  min-width: 2.5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-align: center;
}

.sheet__strip {
  position: relative;
  flex-shrink: 0;
  display: grid;
  grid-template-columns: minmax(7.5rem, auto) minmax(7.5rem, auto) minmax(0, 1fr);
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
}

.sheet__strip::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.sheet__strip--success::before {
  background: var(--color-success);
}

.sheet__strip--danger::before {
  background: var(--color-danger);
}

.sheet__strip--info::before {
  background: var(--color-info);
}

.sheet__strip-item,
.sheet__strip-meta > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  gap: 0.125rem;
}

.sheet__strip-item + .sheet__strip-item,
.sheet__strip-meta {
  padding-left: var(--space-4);
  box-shadow: -1px 0 0 var(--color-border);
}

.sheet__strip-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-4);
}

.sheet__strip-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.sheet__strip-value {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
}

.sheet__strip-item .sheet__strip-value {
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.sheet__strip-warn .sheet__strip-value {
  color: var(--color-danger);
}

.sheet__dialog-body {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  margin: var(--space-3) 0 0;
}

.sheet__empty-card {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  color: var(--color-text-muted);
}

.sheet__empty-card p {
  margin: 0;
  font-size: 0.875rem;
}

.sheet__person {
  display: flex;
  min-width: 0;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.sheet__person-head {
  display: flex;
  flex-shrink: 0;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.75rem 1rem 0.625rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.sheet__person-title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.sheet__person-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.sheet__person-wrap {
  min-width: 0;
  flex: 1;
  overflow: auto;
}

.sheet__person-table {
  width: 100%;
  min-width: max-content;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.sheet__person-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: 0.5625rem 0.875rem;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.sheet__person-table td {
  padding: 0.6875rem 0.875rem;
  color: var(--color-text);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.sheet__person-table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.sheet__person-row--danger td {
  background: color-mix(in srgb, var(--color-danger) 6%, var(--color-surface));
}

.sheet__person-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.sheet__person-table th.sheet__person-num {
  text-align: right;
}

.sheet__person-title {
  min-width: 11rem;
  max-width: 20rem;
}

.sheet__person-sub {
  display: block;
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.sheet__task-link {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.sheet__task-link:hover {
  color: var(--color-primary);
}

.sheet__pill {
  display: inline-flex;
  max-width: 100%;
  align-items: center;
  padding: 0.125rem 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sheet__pill--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.sheet__pill--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.sheet__pill--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.sheet__pill--warn {
  background: var(--color-gold-surface);
  color: var(--color-gold-800);
}

.sheet__pill--muted {
  color: var(--color-text-muted);
}

@media (max-width: 48rem) {
  .sheet__strip {
    grid-template-columns: 1fr;
  }

  .sheet__strip-item + .sheet__strip-item,
  .sheet__strip-meta {
    padding-left: 0;
    box-shadow: none;
  }
}
</style>
