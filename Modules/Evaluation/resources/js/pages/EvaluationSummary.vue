<script setup>
/**
 * Tổng hợp đánh giá — bảng làm việc chấm điểm cả phòng ban trong một kỳ.
 *
 * Không theo mẫu danh sách phẳng: mỗi người một dòng, ngăn chi tiết đẩy ngang
 * để xem việc / ghi nhận / chấm điểm. Giao diện góc vuông, không viên thuốc,
 * không dùng màu thương hiệu đỏ.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import {
  SUMMARY_PERIOD_KEY,
  TASK_STATUS_LABELS,
  TIMELINESS_LABELS,
  TIMELINESS_TONES,
  loadSort,
  saveSort,
} from '@modules/Report/resources/js/constants/report.js';

const SORT_STORAGE_KEY = 'va-evaluation-summary-sort-v2';

const rows = ref([]);
const criteria = ref([]);
const summary = ref(null);
const versionNo = ref(null);
const loading = ref(false);
const loadError = ref('');

function todayISO() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

function isoOf(date) {
  const offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

function lastDayOfMonth(month) {
  const [year, mon] = month.split('-').map(Number);
  return new Date(year, mon, 0).getDate();
}

function monthRange(month) {
  return {
    from: `${month}-01`,
    to: `${month}-${String(lastDayOfMonth(month)).padStart(2, '0')}`,
  };
}

function monthTitle(month) {
  const [year, mon] = month.split('-').map(Number);
  return `Tháng ${mon} năm ${year}`;
}

const periodFrom = ref('');
const periodTo = ref('');
const periodName = ref('');
const customOpen = ref(false);

const quickPeriods = computed(() => {
  const today = todayISO();
  const now = new Date();
  const thisMonth = today.slice(0, 7);

  const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const prevMonth = `${prev.getFullYear()}-${String(prev.getMonth() + 1).padStart(2, '0')}`;

  const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;
  const quarterFrom = isoOf(new Date(now.getFullYear(), quarterStartMonth, 1));
  const quarterTo = isoOf(new Date(now.getFullYear(), quarterStartMonth + 3, 0));

  const weekStart = new Date(now);
  weekStart.setDate(now.getDate() - ((now.getDay() + 6) % 7));
  const weekEnd = new Date(weekStart);
  weekEnd.setDate(weekStart.getDate() + 6);

  return [
    { key: 'this_month', label: 'Tháng này', ...monthRange(thisMonth) },
    { key: 'prev_month', label: 'Tháng trước', ...monthRange(prevMonth) },
    { key: 'this_week', label: 'Tuần này', from: isoOf(weekStart), to: isoOf(weekEnd) },
    { key: 'this_quarter', label: 'Quý này', from: quarterFrom, to: quarterTo },
  ];
});

function applyQuickPeriod(item) {
  periodFrom.value = item.from;
  periodTo.value = item.to;
  periodName.value = item.label;
  customOpen.value = false;
}

function applyMonth(month) {
  const range = monthRange(month);
  periodFrom.value = range.from;
  periodTo.value = range.to;
  periodName.value = monthTitle(month);
  customOpen.value = false;
}

function isFullMonthRange(from, to) {
  if (!from || !to) return false;
  const range = monthRange(from.slice(0, 7));
  return range.from === from && range.to === to;
}

function shiftPeriod(delta) {
  if (!periodFrom.value || !periodTo.value) return;

  if (isFullMonthRange(periodFrom.value, periodTo.value)) {
    const [year, month] = periodFrom.value.slice(0, 7).split('-').map(Number);
    const next = new Date(year, month - 1 + delta, 1);
    applyMonth(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`);
    return;
  }

  const fromDate = new Date(`${periodFrom.value}T00:00:00`);
  const toDate = new Date(`${periodTo.value}T00:00:00`);
  const span = Math.round((toDate - fromDate) / 86400000) + 1;
  fromDate.setDate(fromDate.getDate() + delta * span);
  toDate.setDate(toDate.getDate() + delta * span);
  periodFrom.value = isoOf(fromDate);
  periodTo.value = isoOf(toDate);
  periodName.value = '';
  customOpen.value = false;
}

const activeQuickKey = computed(
  () =>
    quickPeriods.value.find(
      (item) => item.from === periodFrom.value && item.to === periodTo.value,
    )?.key ?? '',
);

const period = computed(() => {
  if (!periodFrom.value || !periodTo.value) return null;
  if (periodFrom.value > periodTo.value) return null;
  return { from: periodFrom.value, to: periodTo.value };
});

const periodHeadline = computed(() => {
  if (!period.value) return 'Chưa chọn kỳ';
  if (isFullMonthRange(period.value.from, period.value.to)) {
    return monthTitle(period.value.from.slice(0, 7));
  }
  return periodName.value || 'Khoảng ngày';
});

const periodRangeText = computed(() => {
  if (!period.value) return '';
  return `${formatDate(period.value.from)} – ${formatDate(period.value.to)}`;
});

const periodInvalid = computed(
  () => Boolean(periodFrom.value && periodTo.value && periodFrom.value > periodTo.value),
);

const query = ref('');
const savedSort = loadSort(SORT_STORAGE_KEY, { key: 'final_score', dir: 'desc' });
const sortKey = ref(savedSort.key);
const sortDir = ref(savedSort.dir);
const focus = ref('all');
const rankFilter = ref('');

const FOCUS_OPTIONS = [
  { key: 'all', label: 'Tất cả' },
  { key: 'overdue', label: 'Có việc quá hạn' },
  { key: 'unrated', label: 'Chưa ghi nhận' },
  { key: 'no_task', label: 'Không có việc' },
  { key: 'below_avg', label: 'Dưới trung bình' },
];

function overdueOf(row) {
  return row.task_status_counts?.by_timeliness?.overdue ?? 0;
}

function doneOf(row) {
  return row.task_status_counts?.by_status?.completed ?? 0;
}

function totalTasksOf(row) {
  return row.task_status_counts?.total ?? 0;
}

function missingOf(row) {
  return row.missing_total ?? 0;
}

function matchesFocusKey(row, key) {
  if (key === 'overdue') return overdueOf(row) > 0;
  if (key === 'unrated') return (row.event_count ?? 0) === 0;
  if (key === 'no_task') return totalTasksOf(row) === 0;
  if (key === 'below_avg') {
    const avg = Number(summary.value?.average_score) || 0;
    return (Number(row.final_score) || 0) < avg;
  }
  return true;
}

function matchesFocus(row) {
  return matchesFocusKey(row, focus.value);
}

const focusCounts = computed(() => {
  const out = {};
  for (const option of FOCUS_OPTIONS) {
    out[option.key] = rows.value.filter((row) => matchesFocusKey(row, option.key)).length;
  }
  return out;
});

const filteredRows = computed(() => {
  const needle = query.value.trim().toLowerCase();

  const list = rows.value.filter((row) => {
    if (needle) {
      const hay = `${row.user_name ?? ''} ${row.classification_label ?? ''}`.toLowerCase();
      if (!hay.includes(needle)) return false;
    }
    if (rankFilter.value && row.classification_code !== rankFilter.value) return false;
    return matchesFocus(row);
  });

  const dir = sortDir.value === 'asc' ? 1 : -1;
  return [...list].sort((a, b) => {
    if (sortKey.value === 'user_name') {
      return String(a.user_name ?? '').localeCompare(String(b.user_name ?? ''), 'vi') * dir;
    }
    if (sortKey.value === 'tasks') {
      return (totalTasksOf(a) - totalTasksOf(b)) * dir;
    }
    if (sortKey.value === 'overdue') {
      return (overdueOf(a) - overdueOf(b)) * dir;
    }
    if (sortKey.value === 'rank') {
      return (rankOf(a) - rankOf(b)) * dir;
    }
    if (sortKey.value === 'classification') {
      return (
        String(a.classification_label ?? '').localeCompare(
          String(b.classification_label ?? ''),
          'vi',
        ) * dir
      );
    }
    return ((Number(a[sortKey.value]) || 0) - (Number(b[sortKey.value]) || 0)) * dir;
  });
});

const SORT_OPTIONS = [
  { key: 'rank', label: 'Hạng' },
  { key: 'user_name', label: 'Nhân sự' },
  { key: 'tasks', label: 'Công việc' },
  { key: 'final_score', label: 'Điểm cuối' },
  { key: 'classification', label: 'Xếp loại' },
];

watch([sortKey, sortDir], () => {
  saveSort(SORT_STORAGE_KEY, { key: sortKey.value, dir: sortDir.value });
});

const filtersActive = computed(
  () => Boolean(query.value) || focus.value !== 'all' || Boolean(rankFilter.value),
);

function clearFilters() {
  query.value = '';
  focus.value = 'all';
  rankFilter.value = '';
}

function toggleRankFilter(code) {
  rankFilter.value = rankFilter.value === code ? '' : code;
}

function formatNumber(value) {
  const num = Number(value) || 0;
  return Number.isInteger(num) ? String(num) : num.toFixed(2).replace(/\.?0+$/, '');
}

function signedText(value) {
  const num = Number(value) || 0;
  if (num === 0) return '0';
  return num > 0 ? `+${formatNumber(num)}` : formatNumber(num);
}

function donePercent(row) {
  const total = totalTasksOf(row);
  if (total === 0) return 0;
  return Math.round((doneOf(row) / total) * 100);
}

function deltaVsAvg(row) {
  const avg = Number(summary.value?.average_score) || 0;
  return (Number(row.final_score) || 0) - avg;
}

const rankMap = computed(() => {
  const sorted = [...rows.value].sort((a, b) => {
    const score = (Number(b.final_score) || 0) - (Number(a.final_score) || 0);
    if (score !== 0) return score;
    return String(a.user_name ?? '').localeCompare(String(b.user_name ?? ''), 'vi');
  });
  const map = {};
  sorted.forEach((row, index) => {
    map[row.user_id] = index + 1;
  });
  return map;
});

function rankOf(row) {
  return rankMap.value[row.user_id] ?? 0;
}

function padRank(row) {
  const rank = rankOf(row);
  const width = String(rows.value.length || 1).length;
  return String(rank).padStart(width, '0');
}

const distribution = computed(() =>
  (summary.value?.distribution ?? []).filter((item) => item.count > 0),
);

function distributionPercent(item) {
  const total = summary.value?.total_people ?? 0;
  if (!total) return 0;
  return Math.round((item.count / total) * 100);
}

function compositionParts(row) {
  const start = Math.max(0, Number(row.start_score) || 0);
  const task = Number(row.task_adjustment) || 0;
  const bonus = Number(row.bonus) || 0;
  const penalty = Number(row.penalty) || 0;
  const posTask = Math.max(0, task);
  const negTask = Math.max(0, -task);
  const total = start + posTask + bonus + penalty + negTask;
  if (total <= 0) return [];
  return [
    { key: 'start', tone: 'gold', width: (start / total) * 100 },
    { key: 'task', tone: 'teal', width: (posTask / total) * 100 },
    { key: 'bonus', tone: 'ok', width: (bonus / total) * 100 },
    { key: 'cut', tone: 'cut', width: ((penalty + negTask) / total) * 100 },
  ].filter((part) => part.width > 0.5);
}

async function loadSummary() {
  if (!period.value) {
    loadError.value = periodInvalid.value
      ? 'Ngày bắt đầu đang sau ngày kết thúc, chưa xem được.'
      : 'Chưa chọn kỳ đánh giá.';
    rows.value = [];
    summary.value = null;
    return;
  }

  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await window.axios.get('/api/evaluation/summary', {
      params: { from: period.value.from, to: period.value.to },
    });
    rows.value = data.rows ?? [];
    criteria.value = data.criteria ?? [];
    summary.value = data.summary ?? null;
    versionNo.value = data.version_no ?? null;

    if (selectedId.value && !rows.value.some((row) => row.user_id === selectedId.value)) {
      selectedId.value = null;
    }
  } catch (error) {
    loadError.value =
      error?.response?.data?.errors?.version?.[0] ??
      error?.response?.data?.message ??
      'Không tải được bảng tổng hợp.';
    rows.value = [];
    criteria.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
  }
}

const selectedId = ref(null);

const selected = computed(
  () => rows.value.find((row) => row.user_id === selectedId.value) ?? null,
);

function select(row) {
  if (selectedId.value === row.user_id) {
    closeDetail();
    return;
  }
  selectedId.value = row.user_id;
  detailTab.value = 'tasks';
  taskFocus.value = 'all';
  resetDraft();
}

function closeDetail() {
  selectedId.value = null;
  resetDraft();
}

function step(offset) {
  const list = filteredRows.value;
  const index = list.findIndex((row) => row.user_id === selectedId.value);
  const next = list[index + offset];
  if (next) {
    selectedId.value = next.user_id;
    detailTab.value = 'tasks';
    taskFocus.value = 'all';
    resetDraft();
  }
}

const stepInfo = computed(() => {
  const list = filteredRows.value;
  const index = list.findIndex((row) => row.user_id === selectedId.value);
  return {
    index,
    total: list.length,
    hasPrev: index > 0,
    hasNext: index >= 0 && index < list.length - 1,
  };
});

const detailTab = ref('tasks');
const taskFocus = ref('all');

const detailTasks = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.task_breakdown ?? [])].sort((a, b) => {
    const weight = (task) => (task.on_time_state === 'overdue' ? 0 : 1);
    if (weight(a) !== weight(b)) return weight(a) - weight(b);
    return String(a.end_date ?? '').localeCompare(String(b.end_date ?? ''));
  });
});

const visibleTasks = computed(() => {
  if (taskFocus.value === 'overdue') {
    return detailTasks.value.filter((task) => task.on_time_state === 'overdue');
  }
  if (taskFocus.value === 'open') {
    return detailTasks.value.filter((task) => task.status !== 'completed');
  }
  return detailTasks.value;
});

const taskFocusCounts = computed(() => ({
  all: detailTasks.value.length,
  overdue: detailTasks.value.filter((task) => task.on_time_state === 'overdue').length,
  open: detailTasks.value.filter((task) => task.status !== 'completed').length,
}));

const detailEvents = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.event_breakdown ?? [])].sort((a, b) =>
    String(b.occurred_at ?? '').localeCompare(String(a.occurred_at ?? '')),
  );
});

const selectedCriteria = computed(() => selected.value?.criterion_totals ?? []);

function taskTitleOf(taskId) {
  if (!taskId) return '';
  return (
    (selected.value?.task_breakdown ?? []).find((task) => task.task_id === taskId)?.title ?? ''
  );
}

const draft = reactive({
  criterion_id: '',
  level_code: '',
  task_id: '',
  reason: '',
  occurred_at: '',
});
const saving = ref(false);
const removingId = ref(null);
const confirmRemoveId = ref(null);

function resetDraft() {
  draft.criterion_id = '';
  draft.level_code = '';
  draft.task_id = '';
  draft.reason = '';
  draft.occurred_at = period.value?.to ?? '';
}

const selectedCriterion = computed(
  () => criteria.value.find((item) => String(item.id) === String(draft.criterion_id)) ?? null,
);

const draftLevels = computed(() => selectedCriterion.value?.levels ?? []);

const draftLevel = computed(
  () => draftLevels.value.find((level) => level.code === draft.level_code) ?? null,
);

const canRecord = computed(
  () => Boolean(draft.criterion_id && draft.level_code && draft.occurred_at) && !saving.value,
);

function onCriterionChange() {
  draft.level_code = '';
}

const criteriaGroups = computed(() => {
  const map = new Map();
  for (const item of criteria.value) {
    const label = String(item.criterion_type_name ?? '').trim() || 'Tiêu chí khác';
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(item);
  }
  return [...map.entries()].map(([label, items]) => ({ label, items }));
});

function recordForTask(task) {
  detailTab.value = 'record';
  draft.task_id = String(task.task_id);
  nextTick(() => criterionField.value?.focus());
}

const criterionField = ref(null);

async function record() {
  const row = selected.value;
  if (!row || !canRecord.value || !period.value) return;

  saving.value = true;
  try {
    const { data } = await window.axios.post('/api/evaluation/events', {
      user_id: row.user_id,
      criterion_id: draft.criterion_id,
      level_code: draft.level_code,
      occurred_at: draft.occurred_at || period.value.to,
      task_id: draft.task_id || null,
      reason: draft.reason.trim() || null,
      period_from: period.value.from,
      period_to: period.value.to,
    });

    if (data.row) applyRow(data.row);
    resetDraft();
    detailTab.value = 'events';

    if (data.duplicate_warning) {
      showClientToast(
        'warning',
        'Đã ghi nhận, nhưng nội dung này trùng với một ghi nhận cùng ngày đã có.',
      );
    } else {
      showClientToast('success', 'Đã ghi nhận đánh giá.');
    }
  } catch (error) {
    const errors = error?.response?.data?.errors ?? {};
    const first = Object.values(errors)[0]?.[0];
    showClientToast('error', first ?? error?.response?.data?.message ?? 'Không ghi nhận được.');
  } finally {
    saving.value = false;
  }
}

function applyRow(fresh) {
  const index = rows.value.findIndex((row) => row.user_id === fresh.user_id);
  if (index >= 0) rows.value[index] = fresh;
}

async function removeEvent(event) {
  if (removingId.value || !period.value) return;

  removingId.value = event.event_id;
  try {
    const { data } = await window.axios.delete(`/api/evaluation/events/${event.event_id}`, {
      params: { period_from: period.value.from, period_to: period.value.to },
    });
    if (data.row) applyRow(data.row);
    confirmRemoveId.value = null;
    showClientToast('success', 'Đã xoá ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không xoá được ghi nhận.');
  } finally {
    removingId.value = null;
  }
}

function confirmRemove() {
  const event = detailEvents.value.find((item) => item.event_id === confirmRemoveId.value);
  if (event) removeEvent(event);
}

function onConfirmOpen(open) {
  if (!open) confirmRemoveId.value = null;
}

function csvCell(value) {
  const text = String(value ?? '');
  if (/[",\n]/.test(text)) return `"${text.replace(/"/g, '""')}"`;
  return text;
}

const exportOptions = computed(() => [
  {
    key: 'csv',
    label: 'Xuất bảng CSV',
    description: 'Danh sách nhân sự đang lọc, theo kỳ đang xem.',
    icon: 'fileSpreadsheet',
    onSelect: exportCsv,
  },
]);

function exportCsv() {
  const headers = [
    'Hạng',
    'Nhân sự',
    'Số việc',
    'Hoàn thành',
    'Quá hạn',
    'Điểm khởi đầu',
    'Từ công việc',
    'Điểm cộng',
    'Điểm trừ',
    'Điểm cuối',
    'Xếp loại',
    'Số ghi nhận',
  ];
  const lines = [headers.join(',')];
  for (const row of filteredRows.value) {
    lines.push(
      [
        rankOf(row),
        csvCell(row.user_name),
        totalTasksOf(row),
        doneOf(row),
        overdueOf(row),
        formatNumber(row.start_score),
        formatNumber(row.task_adjustment),
        formatNumber(row.bonus),
        formatNumber(row.penalty),
        formatNumber(row.final_score),
        csvCell(row.classification_label ?? ''),
        row.event_count ?? 0,
      ].join(','),
    );
  }
  const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  const stamp = period.value ? `${period.value.from}_${period.value.to}` : todayISO();
  link.href = url;
  link.download = `tong-hop-danh-gia_${stamp}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

function isTypingTarget(el) {
  const tag = el?.tagName;
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el?.isContentEditable;
}

function onKeydown(event) {
  if (confirmRemoveId.value) return;
  if (event.key === 'Escape' && selectedId.value && !isTypingTarget(event.target)) {
    closeDetail();
    return;
  }
  if (isTypingTarget(event.target) || !selectedId.value) return;
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    step(1);
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    step(-1);
  }
}

watch(period, () => {
  if (period.value) draft.occurred_at = period.value.to;
  loadSummary();
});

watch(
  () => [periodFrom.value, periodTo.value],
  () => {
    if (!activeQuickKey.value) periodName.value = '';
  },
);

onMounted(() => {
  const saved = localStorage.getItem(SUMMARY_PERIOD_KEY);
  const match = quickPeriods.value.find((item) => item.key === saved);
  applyQuickPeriod(match ?? quickPeriods.value[0]);
  document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});

watch(activeQuickKey, (value) => {
  try {
    if (value) localStorage.setItem(SUMMARY_PERIOD_KEY, value);
  } catch {
    // Trình duyệt chặn localStorage thì bỏ qua.
  }
});
</script>

<template>
  <section class="board">
    <PageHeader
      title="Tổng hợp đánh giá"
      icon="clipboardCheck"
      description="Chấm điểm cả phòng ban trong một kỳ: việc, tiêu chí và điểm cuối."
      export-label="Xuất bảng"
      :export-options="exportOptions"
    >
      <template #actions>
        <button type="button" class="board__header-btn" :disabled="loading" @click="loadSummary">
          <AppIcon name="refresh" :size="16" :class="{ 'board__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="board__period">
      <div class="board__period-nav">
        <button type="button" class="board__square" aria-label="Kỳ trước" @click="shiftPeriod(-1)">
          <AppIcon name="chevronLeft" :size="18" />
        </button>
        <div class="board__period-copy">
          <p class="board__period-title">{{ periodHeadline }}</p>
          <p v-if="periodRangeText" class="board__period-range">
            {{ periodRangeText }}
            <span v-if="versionNo"> · Khung chấm điểm phiên bản {{ versionNo }}</span>
          </p>
        </div>
        <button type="button" class="board__square" aria-label="Kỳ sau" @click="shiftPeriod(1)">
          <AppIcon name="chevronRight" :size="18" />
        </button>
      </div>

      <div class="board__period-links">
        <button
          v-for="item in quickPeriods"
          :key="item.key"
          type="button"
          class="board__text-btn"
          :class="{ 'board__text-btn--on': activeQuickKey === item.key }"
          @click="applyQuickPeriod(item)"
        >
          {{ item.label }}
        </button>
        <button
          type="button"
          class="board__text-btn"
          :class="{ 'board__text-btn--on': customOpen || (!activeQuickKey && period) }"
          :aria-expanded="customOpen"
          @click="customOpen = !customOpen"
        >
          Khoảng khác
        </button>
      </div>
    </div>

    <div v-if="customOpen" class="board__custom">
      <div class="board__field">
        <label class="board__label" for="board-from">Từ ngày</label>
        <input id="board-from" v-model="periodFrom" type="date" class="board__input" />
      </div>
      <div class="board__field">
        <label class="board__label" for="board-to">Đến ngày</label>
        <input id="board-to" v-model="periodTo" type="date" class="board__input" />
      </div>
      <p v-if="periodInvalid" class="board__warn">Ngày bắt đầu đang sau ngày kết thúc.</p>
    </div>

    <p v-if="summary && summary.missing_total > 0 && !loadError" class="board__note">
      Có {{ summary.missing_total }} chỗ thiếu dữ liệu chấm điểm (độ khó, tiến độ hoặc chất
      lượng công việc) nên điểm việc có thể chưa phản ánh đủ.
    </p>

    <div class="board__workspace">
      <div class="board__roster">
        <div class="board__roster-head">
          <div class="board__field board__field--search">
            <label class="board__label" for="board-q">Tìm nhân sự</label>
            <input id="board-q" v-model="query" type="search" class="board__input" />
          </div>

          <div class="board__focus">
            <button
              v-for="option in FOCUS_OPTIONS"
              :key="option.key"
              type="button"
              class="board__text-btn"
              :class="{ 'board__text-btn--on': focus === option.key }"
              @click="focus = option.key"
            >
              {{ option.label }}
              <span class="board__count">{{ focusCounts[option.key] ?? 0 }}</span>
            </button>
          </div>

          <div class="board__roster-sort">
            <label class="board__label" for="board-sort">Xếp theo</label>
            <select id="board-sort" v-model="sortKey" class="board__input">
              <option v-for="option in SORT_OPTIONS" :key="option.key" :value="option.key">
                {{ option.label }}
              </option>
            </select>
            <button
              type="button"
              class="board__square board__square--sm"
              :aria-label="sortDir === 'asc' ? 'Đang xếp tăng dần' : 'Đang xếp giảm dần'"
              @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'"
            >
              <AppIcon :name="sortDir === 'asc' ? 'chevronsUp' : 'chevronsDown'" :size="14" />
            </button>
          </div>

          <button v-if="filtersActive" type="button" class="board__ghost" @click="clearFilters">
            Bỏ lọc
          </button>
        </div>

        <p v-if="loading" class="board__state">Đang tải…</p>
        <p v-else-if="loadError" class="board__state board__state--error">{{ loadError }}</p>
        <p v-else-if="filteredRows.length === 0" class="board__state">
          <span v-if="filtersActive">Không có nhân sự nào khớp với điều kiện đang lọc.</span>
          <span v-else>Kỳ này chưa có nhân sự nào để tổng hợp.</span>
        </p>

        <ul v-else class="board__list hide-scrollbar" role="list">
          <li
            v-for="row in filteredRows"
            :key="row.user_id"
            class="board__person"
            :class="{ 'board__person--on': selectedId === row.user_id }"
          >
            <button type="button" class="board__person-btn" @click="select(row)">
              <span class="board__person-rank">{{ padRank(row) }}</span>

              <span class="board__person-main">
                <span class="board__person-name">{{ row.user_name }}</span>
                <span class="board__person-meta">
                  <span v-if="totalTasksOf(row) === 0">Không có việc</span>
                  <template v-else>
                    {{ doneOf(row) }}/{{ totalTasksOf(row) }} việc
                    <span v-if="overdueOf(row) > 0" class="board__flag">
                      <span class="board__mark board__mark--cut" />
                      {{ overdueOf(row) }} quá hạn
                    </span>
                  </template>
                </span>
              </span>

              <span class="board__person-score">
                <span class="board__person-score-value">{{ formatNumber(row.final_score) }}</span>
                <span class="board__person-class">
                  {{ row.classification_label ?? 'Chưa xếp loại' }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </div>

      <section v-if="!selected" class="board__intro" aria-label="Tổng quan phòng ban">
        <div v-if="summary && !loadError" class="board__intro-body">
          <div class="board__kpis">
            <div class="board__kpi">
              <span class="board__kpi-label">Nhân sự</span>
              <span class="board__kpi-value">{{ summary.total_people }}</span>
            </div>
            <div class="board__kpi">
              <span class="board__kpi-label">Trung bình phòng</span>
              <span class="board__kpi-value">{{ formatNumber(summary.average_score) }}</span>
            </div>
            <div class="board__kpi">
              <span class="board__kpi-label">Cao nhất</span>
              <span class="board__kpi-value">{{ formatNumber(summary.highest_score) }}</span>
            </div>
            <div class="board__kpi">
              <span class="board__kpi-label">Thấp nhất</span>
              <span class="board__kpi-value">{{ formatNumber(summary.lowest_score) }}</span>
            </div>
          </div>

          <div v-if="distribution.length" class="board__ranks">
            <button
              v-for="(item, index) in distribution"
              :key="item.code ?? index"
              type="button"
              class="board__rank"
              :class="{ 'board__rank--on': rankFilter === item.code }"
              @click="toggleRankFilter(item.code)"
            >
              <span class="board__mark" :class="`board__mark--tone-${index % 5}`" />
              <span class="board__rank-label">{{ item.label }}</span>
              <span class="board__rank-count">{{ item.count }}</span>
              <span class="board__rank-pct">{{ distributionPercent(item) }}%</span>
            </button>
          </div>

          <p class="board__intro-hint">
            Chọn một nhân sự ở danh sách bên trái để xem công việc trong kỳ và chấm điểm.
          </p>
        </div>

        <p v-else-if="!loading" class="board__state">
          Chọn một nhân sự ở danh sách bên trái để bắt đầu chấm điểm.
        </p>
      </section>

      <aside v-else class="board__inspect">
        <header class="board__inspect-head">
          <div class="board__inspect-lead">
            <p class="board__inspect-kicker">Hạng {{ rankOf(selected) }}/{{ rows.length }}</p>
            <h2 class="board__inspect-title">{{ selected.user_name }}</h2>
            <p class="board__inspect-score">
              {{ formatNumber(selected.final_score) }}
              <span>{{ selected.classification_label ?? 'Chưa xếp loại' }}</span>
            </p>
          </div>
          <div class="board__inspect-nav">
            <button
              type="button"
              class="board__square board__square--sm"
              aria-label="Nhân sự trước đó"
              :disabled="!stepInfo.hasPrev"
              @click="step(-1)"
            >
              <AppIcon name="chevronLeft" :size="16" />
            </button>
            <span class="board__inspect-pos">{{ stepInfo.index + 1 }}/{{ stepInfo.total }}</span>
            <button
              type="button"
              class="board__square board__square--sm"
              aria-label="Nhân sự kế tiếp"
              :disabled="!stepInfo.hasNext"
              @click="step(1)"
            >
              <AppIcon name="chevronRight" :size="16" />
            </button>
            <button
              type="button"
              class="board__square board__square--sm"
              aria-label="Đóng phần chi tiết"
              @click="closeDetail"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </header>

        <div class="board__inspect-lead-card">
          <div class="board__stack" aria-hidden="true">
            <span
              v-for="part in compositionParts(selected)"
              :key="part.key"
              class="board__stack-seg"
              :class="`board__stack-seg--${part.tone}`"
              :style="{ width: `${part.width}%` }"
            />
          </div>
          <div class="board__row">
            <span class="board__row-label">Điểm khởi đầu</span>
            <span class="board__row-value">{{ formatNumber(selected.start_score) }}</span>
          </div>
          <div class="board__row">
            <span class="board__row-label">Từ công việc</span>
            <span class="board__row-value">{{ signedText(selected.task_adjustment) }}</span>
          </div>
          <div class="board__row">
            <span class="board__row-label">Điểm cộng</span>
            <span class="board__row-value board__plus">{{ signedText(selected.bonus) }}</span>
          </div>
          <div class="board__row">
            <span class="board__row-label">Điểm trừ</span>
            <span class="board__row-value board__minus">
              {{ selected.penalty ? `-${formatNumber(selected.penalty)}` : '0' }}
            </span>
          </div>
          <div class="board__row">
            <span class="board__row-label">Điểm cuối</span>
            <span class="board__row-value">{{ formatNumber(selected.final_score) }}</span>
          </div>
          <div class="board__row">
            <span class="board__row-label">So với trung bình phòng</span>
            <span
              class="board__row-value"
              :class="deltaVsAvg(selected) >= 0 ? 'board__plus' : 'board__minus'"
            >
              {{ signedText(deltaVsAvg(selected)) }}
            </span>
          </div>
          <div class="board__row board__row--total">
            <span class="board__row-label">Công việc hoàn thành</span>
            <span class="board__row-value">
              {{ doneOf(selected) }}/{{ totalTasksOf(selected) }} ({{ donePercent(selected) }}%)
            </span>
          </div>
          <div class="board__meter" aria-hidden="true">
            <span class="board__meter-fill" :style="{ width: `${donePercent(selected)}%` }" />
          </div>
        </div>

        <div v-if="selectedCriteria.length" class="board__criteria">
          <div v-for="item in selectedCriteria" :key="item.criterion_id" class="board__row">
            <span class="board__row-label">{{ item.criterion_name }}</span>
            <span
              class="board__row-value"
              :class="item.score >= 0 ? 'board__plus' : 'board__minus'"
            >
              {{ signedText(item.score) }} · {{ item.count }} lần
            </span>
          </div>
        </div>

        <div class="board__tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class="board__tab"
            :class="{ 'board__tab--on': detailTab === 'tasks' }"
            :aria-selected="detailTab === 'tasks'"
            @click="detailTab = 'tasks'"
          >
            Công việc ({{ detailTasks.length }})
          </button>
          <button
            type="button"
            role="tab"
            class="board__tab"
            :class="{ 'board__tab--on': detailTab === 'events' }"
            :aria-selected="detailTab === 'events'"
            @click="detailTab = 'events'"
          >
            Ghi nhận ({{ detailEvents.length }})
          </button>
          <button
            type="button"
            role="tab"
            class="board__tab"
            :class="{ 'board__tab--on': detailTab === 'record' }"
            :aria-selected="detailTab === 'record'"
            @click="detailTab = 'record'"
          >
            Chấm điểm
          </button>
        </div>

        <div class="board__inspect-body hide-scrollbar">
          <template v-if="detailTab === 'tasks'">
            <div v-if="detailTasks.length" class="board__subfocus">
              <button
                type="button"
                class="board__text-btn"
                :class="{ 'board__text-btn--on': taskFocus === 'all' }"
                @click="taskFocus = 'all'"
              >
                Tất cả
                <span class="board__count">{{ taskFocusCounts.all }}</span>
              </button>
              <button
                type="button"
                class="board__text-btn"
                :class="{ 'board__text-btn--on': taskFocus === 'overdue' }"
                @click="taskFocus = 'overdue'"
              >
                Quá hạn
                <span class="board__count">{{ taskFocusCounts.overdue }}</span>
              </button>
              <button
                type="button"
                class="board__text-btn"
                :class="{ 'board__text-btn--on': taskFocus === 'open' }"
                @click="taskFocus = 'open'"
              >
                Chưa xong
                <span class="board__count">{{ taskFocusCounts.open }}</span>
              </button>
            </div>

            <p v-if="visibleTasks.length === 0" class="board__state board__state--sm">
              <span v-if="detailTasks.length === 0">
                Nhân sự này không có công việc nào thuộc kỳ đang xem.
              </span>
              <span v-else>Không có công việc nào trong nhóm đang lọc.</span>
            </p>

            <ul v-else class="board__cards">
              <li v-for="task in visibleTasks" :key="task.task_id" class="board__card">
                <p class="board__card-title">{{ task.title }}</p>
                <p class="board__card-meta">
                  <span class="board__flag">
                    <span
                      class="board__mark"
                      :class="`board__mark--${TIMELINESS_TONES[task.on_time_state]}`"
                    />
                    {{ TASK_STATUS_LABELS[task.status] ?? task.status }},
                    {{ TIMELINESS_LABELS[task.on_time_state] }}
                  </span>
                  <span v-if="task.project_name">{{ task.project_name }}</span>
                </p>
                <div class="board__row">
                  <span class="board__row-label">Hạn</span>
                  <span class="board__row-value">
                    {{ task.end_date ? formatDate(task.end_date) : 'chưa đặt' }}
                  </span>
                </div>
                <div class="board__row">
                  <span class="board__row-label">Hoàn thành</span>
                  <span class="board__row-value">
                    {{ task.actual_end_date ? formatDate(task.actual_end_date) : 'chưa xong' }}
                  </span>
                </div>
                <div class="board__row">
                  <span class="board__row-label">Điểm việc</span>
                  <span class="board__row-value">{{ formatNumber(task.contribution) }}</span>
                </div>
                <div v-if="task.standard_score != null" class="board__row">
                  <span class="board__row-label">Chuẩn / thực</span>
                  <span class="board__row-value">
                    {{ formatNumber(task.standard_score) }} / {{ formatNumber(task.actual_score) }}
                  </span>
                </div>
                <button type="button" class="board__link" @click="recordForTask(task)">
                  Chấm điểm cho việc này
                </button>
              </li>
            </ul>
          </template>

          <template v-else-if="detailTab === 'events'">
            <p v-if="detailEvents.length === 0" class="board__state board__state--sm">
              Chưa ghi nhận đánh giá nào cho nhân sự này trong kỳ.
            </p>

            <ul v-else class="board__cards">
              <li v-for="event in detailEvents" :key="event.event_id" class="board__card">
                <div class="board__event-head">
                  <span class="board__card-title">{{ event.criterion_name }}</span>
                  <span
                    class="board__event-score"
                    :class="event.score >= 0 ? 'board__plus' : 'board__minus'"
                  >
                    {{ signedText(event.score) }}
                  </span>
                </div>
                <p class="board__card-meta">
                  {{ event.level_label }} · {{ formatDate(event.occurred_at) }}
                  <span v-if="taskTitleOf(event.task_id)">
                    · việc: {{ taskTitleOf(event.task_id) }}
                  </span>
                </p>
                <p v-if="event.reason" class="board__reason">{{ event.reason }}</p>
                <button
                  type="button"
                  class="board__link board__link--danger"
                  @click="confirmRemoveId = event.event_id"
                >
                  Xoá ghi nhận
                </button>
              </li>
            </ul>
          </template>

          <template v-else>
            <p v-if="criteria.length === 0" class="board__state board__state--sm">
              Phòng ban chưa có tiêu chí cộng / trừ điểm nào đang bật, nên chưa chấm được.
            </p>

            <form v-else class="board__form" @submit.prevent="record">
              <div class="board__form-grid">
                <div class="board__field">
                  <label class="board__label" for="board-criterion">Tiêu chí đánh giá</label>
                  <select
                    id="board-criterion"
                    ref="criterionField"
                    v-model="draft.criterion_id"
                    class="board__input"
                    :disabled="saving"
                    @change="onCriterionChange"
                  >
                    <option value="">Chọn tiêu chí</option>
                    <template v-if="criteriaGroups.length > 1">
                      <optgroup
                        v-for="group in criteriaGroups"
                        :key="group.label"
                        :label="group.label"
                      >
                        <option v-for="item in group.items" :key="item.id" :value="item.id">
                          {{ item.name }}
                        </option>
                      </optgroup>
                    </template>
                    <template v-else>
                      <option v-for="item in criteria" :key="item.id" :value="item.id">
                        {{ item.name }}
                      </option>
                    </template>
                  </select>
                </div>

                <div class="board__field">
                  <label class="board__label" for="board-occurred">Ngày ghi nhận</label>
                  <input
                    id="board-occurred"
                    v-model="draft.occurred_at"
                    type="date"
                    class="board__input"
                    :min="period?.from"
                    :max="period?.to"
                    :disabled="saving"
                  />
                </div>

                <div class="board__field">
                  <label class="board__label" for="board-task">Gắn với công việc</label>
                  <select
                    id="board-task"
                    v-model="draft.task_id"
                    class="board__input"
                    :disabled="saving || detailTasks.length === 0"
                  >
                    <option value="">Không gắn công việc nào</option>
                    <option
                      v-for="task in detailTasks"
                      :key="task.task_id"
                      :value="task.task_id"
                    >
                      {{ task.title }}
                    </option>
                  </select>
                </div>

                <div class="board__field">
                  <label class="board__label" for="board-reason">Lý do</label>
                  <textarea
                    id="board-reason"
                    v-model="draft.reason"
                    class="board__input board__textarea"
                    rows="2"
                    maxlength="500"
                    :disabled="saving"
                  />
                </div>
              </div>

              <div v-if="draftLevels.length" class="board__field">
                <span class="board__label">Mức đánh giá</span>
                <div class="board__levels">
                  <label
                    v-for="level in draftLevels"
                    :key="level.code"
                    class="board__level"
                    :class="{ 'board__level--on': draft.level_code === level.code }"
                  >
                    <input
                      v-model="draft.level_code"
                      type="radio"
                      :value="level.code"
                      class="board__sr"
                      :disabled="saving"
                    />
                    <span>{{ level.label }}</span>
                    <span :class="level.score >= 0 ? 'board__plus' : 'board__minus'">
                      {{ signedText(level.score) }}
                    </span>
                  </label>
                </div>
              </div>

              <p v-if="draftLevel" class="board__preview">
                Sẽ ghi nhận {{ selectedCriterion.name }} mức {{ draftLevel.label }}, thay đổi điểm
                {{ signedText(draftLevel.score) }} cho {{ selected.user_name }}.
              </p>

              <div class="board__form-actions">
                <button type="submit" class="board__submit" :disabled="!canRecord">
                  {{ saving ? 'Đang lưu…' : 'Ghi nhận đánh giá' }}
                </button>
                <button type="button" class="board__ghost" :disabled="saving" @click="resetDraft">
                  Xoá nội dung đang nhập
                </button>
              </div>
            </form>
          </template>
        </div>
      </aside>
    </div>

    <ConfirmDialog
      :open="Boolean(confirmRemoveId)"
      title="Xoá ghi nhận này?"
      description="Điểm cuối của nhân sự sẽ được tính lại ngay sau khi xoá."
      confirm-label="Xoá ghi nhận"
      danger
      :loading="Boolean(removingId)"
      @update:open="onConfirmOpen"
      @confirm="confirmRemove"
    />
  </section>
</template>

<style scoped>
.board {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
  --board-accent: var(--color-secondary);
  --board-accent-hover: var(--color-secondary-hover);
  --board-accent-surface: var(--color-secondary-surface);
  --board-accent-ink: var(--color-secondary-800);
}

.board__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.board__header-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: 0;
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.board__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.board__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.board__spin {
  animation: board-spin 0.8s linear infinite;
}

@keyframes board-spin {
  to {
    transform: rotate(360deg);
  }
}

.board__period {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) 0 var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.board__period-nav {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
}

.board__period-copy {
  min-width: 0;
}

.board__period-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.board__period-range {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.board__square {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  border: none;
  border-radius: 0;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.board__square:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.board__square:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.board__square--sm {
  width: 1.75rem;
  height: 1.75rem;
}

.board__period-links,
.board__focus,
.board__subfocus {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1) var(--space-3);
}

.board__text-btn {
  display: inline-flex;
  align-items: baseline;
  gap: 0.375rem;
  padding: 0.25rem 0;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.board__text-btn:hover {
  color: var(--color-text);
}

.board__text-btn--on {
  color: var(--board-accent-ink);
  box-shadow: 0 2px 0 var(--board-accent);
}

.board__count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.board__custom {
  display: grid;
  flex-shrink: 0;
  grid-template-columns: repeat(2, minmax(0, 14rem));
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding: var(--space-3);
  border-radius: 0;
  background: var(--color-surface-muted);
}

.board__warn {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-umber);
  font-size: 0.8125rem;
}

.board__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  min-width: 0;
  border-radius: 0;
  background: var(--color-surface-muted);
}

.board__kpi {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: var(--space-3) var(--space-4);
  box-shadow: 1px 0 0 var(--color-border);
}

.board__kpi:last-child {
  box-shadow: none;
}

.board__kpi-label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.board__kpi-value {
  color: var(--color-text);
  font-size: 1.375rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 1.15;
}

.board__ranks {
  display: flex;
  flex-wrap: wrap;
  min-width: 0;
  border-radius: 0;
  background: var(--color-surface-muted);
}

.board__rank {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 0.5rem;
  min-width: 8rem;
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.board__rank:hover {
  background: var(--color-surface);
}

.board__rank--on {
  background: var(--color-surface);
  box-shadow: 0 2px 0 var(--board-accent);
}

.board__rank-label {
  flex: 1;
  min-width: 0;
  font-size: 0.8125rem;
}

.board__rank-count {
  font-size: 1rem;
  font-weight: 600;
}

.board__rank-pct {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.board__mark {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: 0;
  background: var(--color-text-muted);
}

.board__mark--ok {
  background: var(--color-success);
}

.board__mark--cut,
.board__mark--danger {
  background: var(--color-umber);
}

.board__mark--muted {
  background: var(--color-text-muted);
}

.board__mark--tone-0 {
  background: var(--color-secondary-500);
}

.board__mark--tone-1 {
  background: var(--color-tertiary-500);
}

.board__mark--tone-2 {
  background: var(--color-gold-500);
}

.board__mark--tone-3 {
  background: var(--color-info);
}

.board__mark--tone-4 {
  background: var(--color-umber-500);
}

.board__note {
  flex-shrink: 0;
  margin: var(--space-2) 0 0;
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
}

.board__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.board__field--search {
  width: 14rem;
  max-width: 100%;
}

.board__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.board__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: 0;
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.board__input:focus-visible {
  outline: 2px solid var(--board-accent);
  outline-offset: 1px;
}

.board__input:disabled {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.board__textarea {
  resize: vertical;
}

.board__ghost {
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.board__ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.board__ghost:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.board__workspace {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* Danh sách nhân sự — cột hẹp cố định, chỉ để chọn người. Mọi thứ cần đọc kỹ
   nằm ở khu chấm điểm bên phải. */
.board__roster {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  width: 22rem;
  min-height: 0;
  overflow: hidden;
  box-shadow: inset -1px 0 0 var(--color-border);
}

.board__roster-head {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  gap: var(--space-2);
  padding: 0 var(--space-3) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.board__roster-sort {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.board__roster-sort .board__label {
  flex-shrink: 0;
}

.board__roster-sort .board__input {
  flex: 1;
  min-width: 0;
}

.board__list {
  flex: 1;
  min-height: 0;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.board__person {
  position: relative;
  box-shadow: 0 1px 0 var(--color-border);
}

.board__person--on::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--board-accent);
}

.board__person-btn {
  display: grid;
  grid-template-columns: 2.25rem minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3);
  border: none;
  background: transparent;
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.board__person-btn:hover {
  background: var(--color-surface-muted);
}

.board__person--on .board__person-btn {
  background: var(--board-accent-surface);
}

.board__person-rank {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.04em;
}

.board__person-main {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.board__person-name {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.board__person-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.board__flag {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.board__meter {
  display: block;
  width: 100%;
  height: 0.25rem;
  margin-top: var(--space-2);
  overflow: hidden;
  border-radius: 0;
  background: var(--color-border);
}

.board__meter-fill {
  display: block;
  height: 100%;
  border-radius: 0;
  background: var(--board-accent);
}

.board__person-score {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
  text-align: right;
}

.board__person-score-value {
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.board__person-class {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
}

.board__plus {
  color: var(--color-success);
}

.board__minus {
  color: var(--color-umber);
}

.board__state {
  margin: var(--space-6) 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

.board__state--sm {
  margin: var(--space-4) 0;
  text-align: left;
}

.board__state--error {
  color: var(--color-umber);
}

/* Khu chấm điểm — vùng làm việc chính, chiếm hết chỗ còn lại sau danh sách.
   Chỉ hiện khi đã chọn một nhân sự. */
.board__inspect {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  border-radius: 0;
  background: var(--color-surface);
}

/* Chưa chọn ai — chỗ này hiện số tổng quan của cả phòng ban. */
.board__intro {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
  min-height: 0;
  padding: var(--space-5);
  overflow-y: auto;
}

.board__intro-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.board__intro-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.board__inspect-head {
  display: flex;
  flex-shrink: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.board__inspect-lead {
  min-width: 0;
}

.board__inspect-kicker {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.board__inspect-title {
  margin: 0.125rem 0 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.board__inspect-score {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0.25rem 0 0;
  color: var(--color-text);
  font-size: 1.5rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.board__inspect-score span {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
  letter-spacing: 0;
}

.board__inspect-nav {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.125rem;
}

.board__inspect-pos {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.board__inspect-lead-card,
.board__criteria {
  position: relative;
  flex-shrink: 0;
  margin: var(--space-3) var(--space-4) 0;
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.board__inspect-lead-card::before,
.board__criteria::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--board-accent);
}

.board__stack {
  display: flex;
  height: 0.375rem;
  margin-bottom: var(--space-2);
  overflow: hidden;
  background: var(--color-border);
}

.board__stack-seg {
  display: block;
  height: 100%;
}

.board__stack-seg--gold {
  background: var(--color-gold-500);
}

.board__stack-seg--teal {
  background: var(--color-secondary-500);
}

.board__stack-seg--ok {
  background: var(--color-success);
}

.board__stack-seg--cut {
  background: var(--color-umber);
}

.board__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.375rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.board__row--total {
  box-shadow: none;
}

.board__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.board__row-label::after {
  content: ':';
}

.board__row-value {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

.board__tabs {
  display: flex;
  flex-shrink: 0;
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding: 0 var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.board__tab {
  padding: 0.5rem 0;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.board__tab:hover {
  color: var(--color-text);
}

.board__tab--on {
  color: var(--board-accent-ink);
  box-shadow: 0 2px 0 var(--board-accent);
}

.board__inspect-body {
  flex: 1;
  min-height: 0;
  padding: var(--space-3) var(--space-4) var(--space-4);
  overflow-y: auto;
}

.board__cards {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.board__card {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: var(--space-3);
  border-radius: 0;
  background: var(--color-surface-muted);
}

.board__card-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  word-break: break-word;
}

.board__card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem var(--space-2);
  margin: 0 0 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.board__event-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.board__event-score {
  font-size: 0.875rem;
  font-weight: 600;
}

.board__reason {
  margin: 0;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
}

.board__link {
  align-self: flex-start;
  margin-top: 0.25rem;
  padding: 0;
  border: none;
  background: none;
  color: var(--board-accent);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
}

.board__link:hover {
  text-decoration: underline;
}

.board__link--danger {
  color: var(--color-umber);
}

.board__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.board__form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.board__levels {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.board__level {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem;
  border-radius: 0;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.board__level:hover {
  background: var(--color-surface-muted);
}

.board__level--on {
  background: var(--board-accent-surface);
  box-shadow: inset 0 0 0 2px var(--board-accent);
}

.board__preview {
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: 0;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.75rem;
  line-height: 1.5;
}

.board__form-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.board__submit {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0;
  background: var(--board-accent);
  color: var(--color-on-secondary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.board__submit:hover:not(:disabled) {
  background: var(--board-accent-hover);
}

.board__submit:disabled {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

@media (max-width: 1280px) {
  .board__roster {
    width: 18rem;
  }

  .board__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .board__kpi:nth-child(2) {
    box-shadow: none;
  }
}

/* Hẹp hơn nữa thì không đủ chỗ cho hai cột — khu chấm điểm phủ lên danh
   sách, đóng lại là quay về danh sách. */
@media (max-width: 900px) {
  .board__workspace {
    position: relative;
  }

  .board__roster {
    width: 100%;
    box-shadow: none;
  }

  .board__inspect {
    position: absolute;
    inset: 0;
    background: var(--color-surface);
  }

  .board__intro {
    display: none;
  }
}

@media (max-width: 768px) {
  .board__period {
    flex-direction: column;
    align-items: stretch;
  }

  .board__custom,
  .board__form-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 480px) {
  .board__field--search {
    width: 100%;
  }

  .board__kpis {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }

  .board__period-title {
    font-size: 1.125rem;
  }

  .board__person-btn {
    grid-template-columns: 2rem minmax(0, 1fr) auto;
  }

  .board__person-score-value {
    font-size: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .board__spin {
    animation: none;
  }
}
</style>
