<script setup>
/**
 * Tổng hợp đánh giá — ma trận chấm điểm cả phòng ban trong một kỳ.
 *
 * Không dùng mẫu danh sách ActivityLog / TablePagesBar: đây là bảng kín khung
 * nhìn (nhân sự × tiêu chí), không danh sách phẳng. Click tên hoặc ô tiêu chí
 * mở modal form ngang (Công việc / Ghi nhận / Chấm điểm).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import OptionPicker from '@modules/Project/resources/js/components/OptionPicker.vue';
import {
  SUMMARY_PERIOD_KEY,
  TASK_STATUS_LABELS,
  TIMELINESS_LABELS,
} from '@modules/Report/resources/js/constants/report.js';

const CRITERIA_VIS_KEY = 'va-evaluation-matrix-criteria-v1';

const MIN_COL = {
  user: 220,
  tasks: 88,
  start: 104,
  task_adj: 104,
  criterion: 120,
  bonus: 96,
  penalty: 96,
  final: 112,
  klass: 140,
};

const rows = ref([]);
const criteria = ref([]);
const summary = ref(null);
const versionNo = ref(null);
const scoreMode = ref(null);
const periodLock = ref({ locked: false, reports: [] });
const loading = ref(false);
const loadError = ref('');

const isWeightedTaskMode = computed(() => scoreMode.value === 'weighted_task');
const scoreModeInfo = computed(() =>
  isWeightedTaskMode.value
    ? {
        title: 'Cách 2 · Hiệu suất việc',
        formula:
          'Việc chưa xong hoặc thiếu dữ liệu = 0 điểm thực; hiệu suất = Σ thực / Σ chuẩn × 100; mỗi điểm hành vi = 1 điểm phần trăm.',
      }
    : {
        title: 'Cách 1 · Đếm số việc',
        formula:
          'Điểm cuối = điểm khởi đầu ± điểm theo số việc hoàn thành/chưa hoàn thành + điểm ghi nhận.',
      },
);

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
const periodMenuOpen = ref(false);
const periodMenuRoot = ref(null);

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
  periodMenuOpen.value = false;
}

function applyMonth(month) {
  const range = monthRange(month);
  periodFrom.value = range.from;
  periodTo.value = range.to;
  periodName.value = monthTitle(month);
  periodMenuOpen.value = false;
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
  periodMenuOpen.value = false;
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

const periodInvalid = computed(
  () => Boolean(periodFrom.value && periodTo.value && periodFrom.value > periodTo.value),
);

function formatNumber(value) {
  const num = Number(value) || 0;
  return Number.isInteger(num) ? String(num) : num.toFixed(2).replace(/\.?0+$/, '');
}

function signedText(value) {
  const num = Number(value) || 0;
  if (num === 0) return '0';
  return num < 0 ? `−${formatNumber(Math.abs(num))}` : formatNumber(num);
}

function formatPercent(value) {
  return `${formatNumber(value)}%`;
}

function percentChipClass(value) {
  const num = Number(value) || 0;
  if (num >= 100) return 'matrix__chip matrix__chip--pct matrix__chip--ok';
  if (num <= 0) return 'matrix__chip matrix__chip--pct matrix__chip--minus';
  return 'matrix__chip matrix__chip--pct';
}

function scoreClass(value) {
  const num = Number(value) || 0;
  if (num > 0) return 'matrix__plus';
  if (num < 0) return 'matrix__minus';
  return '';
}

function scoreChipClass(value) {
  const num = Number(value) || 0;
  if (num > 0) return 'matrix__chip matrix__chip--plus';
  if (num < 0) return 'matrix__chip matrix__chip--minus';
  return 'matrix__chip';
}

function overdueOf(row) {
  return row.task_status_counts?.by_timeliness?.overdue ?? 0;
}

function doneOf(row) {
  return row.task_status_counts?.by_status?.completed ?? 0;
}

function totalTasksOf(row) {
  return row.task_status_counts?.total ?? 0;
}

function taskBasisOf(row) {
  if (!isWeightedTaskMode.value) return Number(row.start_score) || 0;
  return roundScore(
    (row.task_breakdown ?? []).reduce(
      (total, task) => total + (Number(task.standard_score) || 0),
      0,
    ),
  );
}

function factorText(value) {
  return `×${formatNumber(value ?? 1)}`;
}

function criterionTotal(row, criterionId) {
  return (row.criterion_totals ?? []).find((item) => Number(item.criterion_id) === Number(criterionId)) ?? null;
}

function roundScore(value) {
  return Math.round((Number(value) || 0) * 100) / 100;
}

const shownCriterionIds = computed(
  () => new Set(shownCriteria.value.map((item) => Number(item.id))),
);

function visibleScore(row) {
  return {
    bonus: roundScore(row.bonus),
    penalty: roundScore(row.penalty),
    final: roundScore(row.final_score),
  };
}

const visibleScoresByUser = computed(() => {
  const map = new Map();
  for (const row of rows.value) {
    map.set(row.user_id, visibleScore(row));
  }
  return map;
});

function scoreOf(row) {
  return visibleScoresByUser.value.get(row.user_id) ?? visibleScore(row);
}

const query = ref('');
const onlyMissing = ref(false);

const sheetRows = computed(() =>
  [...rows.value].sort((a, b) => {
    const score = scoreOf(b).final - scoreOf(a).final;
    if (score !== 0) return score;
    return String(a.user_name ?? '').localeCompare(String(b.user_name ?? ''), 'vi');
  }),
);

const filteredRows = computed(() => {
  const needle = foldSearch(query.value).trim();
  return sheetRows.value.filter((row) => {
    if (onlyMissing.value && rowGapCount(row) <= 0) return false;
    if (!needle) return true;
    return (
      foldSearch(row.user_name).includes(needle) ||
      foldSearch(row.user_email).includes(needle)
    );
  });
});

const visibleSummary = computed(() => {
  const list = sheetRows.value;
  if (list.length === 0) {
    return { average: 0, highest: 0, lowest: 0 };
  }
  const scores = list.map((row) => scoreOf(row).final);
  return {
    average: roundScore(scores.reduce((sum, value) => sum + value, 0) / scores.length),
    highest: roundScore(Math.max(...scores)),
    lowest: roundScore(Math.min(...scores)),
  };
});

const visibleCriteriaMap = reactive(loadCriteriaVisibility());

function loadCriteriaVisibility() {
  try {
    const raw = localStorage.getItem(CRITERIA_VIS_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Trình duyệt chặn localStorage thì bỏ qua.
  }
  return {};
}

function persistCriteriaVisibility() {
  try {
    localStorage.setItem(CRITERIA_VIS_KEY, JSON.stringify(visibleCriteriaMap));
  } catch {
    // Bỏ qua.
  }
}

watch(criteria, (list) => {
  for (const item of list) {
    const key = String(item.id);
    if (typeof visibleCriteriaMap[key] !== 'boolean') {
      visibleCriteriaMap[key] = true;
    }
  }
  persistCriteriaVisibility();
});

watch(visibleCriteriaMap, persistCriteriaVisibility, { deep: true });

const shownCriteria = computed(() =>
  criteria.value.filter((item) => visibleCriteriaMap[String(item.id)] !== false),
);

const criteriaGroups = computed(() => {
  const map = new Map();
  for (const item of shownCriteria.value) {
    const label = String(item.criterion_type_name ?? '').trim() || 'Tiêu chí khác';
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(item);
  }
  return [...map.entries()].map(([label, items]) => ({ label, items }));
});

const pickerGroups = computed(() => {
  const map = new Map();
  for (const item of criteria.value) {
    const label = String(item.criterion_type_name ?? '').trim() || 'Tiêu chí khác';
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(item);
  }
  return [...map.entries()].map(([label, items]) => ({ label, items }));
});

const criteriaPickerQuery = ref('');

function foldSearch(text) {
  return String(text ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase();
}

const filteredPickerGroups = computed(() => {
  const needle = foldSearch(criteriaPickerQuery.value).trim();
  const groups = pickerGroups.value;
  if (!needle) return groups;
  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) =>
          foldSearch(item.name).includes(needle) ||
          foldSearch(item.criterion_type_name).includes(needle),
      ),
    }))
    .filter((group) => group.items.length > 0);
});

const shownCriteriaCount = computed(
  () => criteria.value.filter((item) => visibleCriteriaMap[String(item.id)] !== false).length,
);

function isCriterionOn(id) {
  return visibleCriteriaMap[String(id)] !== false;
}

function toggleCriterion(id, checked) {
  visibleCriteriaMap[String(id)] = checked;
}

function setAllCriteria(on) {
  for (const item of criteria.value) {
    visibleCriteriaMap[String(item.id)] = on;
  }
}

function groupShownCount(items) {
  return items.filter((item) => isCriterionOn(item.id)).length;
}

function setGroupCriteria(items, on) {
  for (const item of items) {
    visibleCriteriaMap[String(item.id)] = on;
  }
}

function toggleGroupCriteria(items) {
  setGroupCriteria(items, groupShownCount(items) < items.length);
}

const colSpan = computed(() => 2 + 2 + shownCriteria.value.length + 4);

const tableWrap = ref(null);
const wrapWidth = ref(0);
let wrapObserver = null;

useDragScroll(tableWrap);

const gapSummary = computed(() => summary.value?.missing ?? { difficulty: 0, progress: 0, quality: 0 });
const gapTotal = computed(() => Number(summary.value?.missing_total) || 0);

function rowGapCount(row) {
  return Number(row.missing_total) || 0;
}

function taskZeroReason(task) {
  if (task.zeroed_reason === 'incomplete') return 'Chưa hoàn thành · điểm thực 0';
  if (task.zeroed_reason === 'missing_data') {
    const map = { difficulty: 'thiếu độ khó', progress: 'thiếu hạn/ngày xong', quality: 'chưa chấm chất lượng' };
    const bits = (task.missing_fields ?? []).map((key) => map[key] ?? key);
    return bits.length ? bits.join(', ') : 'Thiếu dữ liệu · điểm thực 0';
  }
  return '';
}

function criterionColKey(id) {
  return `c:${id}`;
}

function defaultWidth(key) {
  if (key.startsWith('c:')) {
    const id = key.slice(2);
    const item = criteria.value.find((c) => String(c.id) === String(id));
    const chars = String(item?.name ?? '').length;
    return Math.max(MIN_COL.criterion, Math.min(176, 48 + chars * 8));
  }
  return MIN_COL[key] ?? 80;
}

const leafKeys = computed(() => [
  'user',
  'tasks',
  'start',
  'task_adj',
  ...shownCriteria.value.map((item) => criterionColKey(item.id)),
  'bonus',
  'penalty',
  'final',
  'klass',
]);

const colWidths = computed(() => {
  const widths = {};
  let sum = 0;
  for (const key of leafKeys.value) {
    const width = defaultWidth(key);
    widths[key] = width;
    sum += width;
  }
  const extra = Math.max(0, wrapWidth.value - sum);
  if (extra > 0) widths.user += extra;
  return widths;
});

function colPx(key) {
  return colWidths.value[key] || defaultWidth(key);
}

function colWidthStyle(key) {
  return `${colPx(key)}px`;
}

const tableWidthPx = computed(() => {
  const sum = leafKeys.value.reduce((total, key) => total + colPx(key), 0);
  return `${sum}px`;
});

async function loadSummary() {
  if (!period.value) {
    loadError.value = periodInvalid.value
      ? 'Ngày bắt đầu đang sau ngày kết thúc, chưa xem được.'
      : 'Chưa chọn kỳ đánh giá.';
    rows.value = [];
    summary.value = null;
    versionNo.value = null;
    scoreMode.value = null;
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
    scoreMode.value = data.mode ?? null;
    periodLock.value = data.period_lock ?? { locked: false, reports: [] };

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
    versionNo.value = null;
    scoreMode.value = null;
    periodLock.value = { locked: false, reports: [] };
  } finally {
    loading.value = false;
  }
}

const selectedId = ref(null);

const selected = computed(
  () => rows.value.find((row) => row.user_id === selectedId.value) ?? null,
);

function resetDraft(row, extras = {}) {
  draft.user_id = row.user_id;
  draft.criterion_id = extras.criterion_id != null ? String(extras.criterion_id) : '';
  draft.level_code = '';
  draft.task_id = extras.task_id != null ? String(extras.task_id) : '';
  draft.reason = '';
  draft.occurred_at = period.value?.to ?? '';
}

function openPerson(row, tab = 'tasks', extras = {}) {
  if (!row) return;
  criteriaDialogOpen.value = false;
  periodMenuOpen.value = false;
  const switched = selectedId.value !== row.user_id;
  selectedId.value = row.user_id;
  detailTab.value = tab;
  if (switched || extras.forceDraft) {
    resetDraft(row, extras);
  } else {
    draft.user_id = row.user_id;
    if (extras.criterion_id != null) {
      draft.criterion_id = String(extras.criterion_id);
      draft.level_code = '';
    }
    if (extras.task_id != null) {
      draft.task_id = String(extras.task_id);
    }
    if (!draft.occurred_at) draft.occurred_at = period.value?.to ?? '';
  }
}

function select(row) {
  openPerson(row);
}

function closeDetail() {
  if (saving.value) return;
  selectedId.value = null;
}

function step(offset) {
  const list = filteredRows.value;
  const index = list.findIndex((row) => row.user_id === selectedId.value);
  const next = list[index + offset];
  if (next) openPerson(next, detailTab.value, { forceDraft: true });
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

const PERSON_TABS = [
  { id: 'tasks', label: 'Công việc', icon: 'listChecks' },
  { id: 'events', label: 'Ghi nhận', icon: 'clipboardCheck' },
  { id: 'score', label: 'Chấm điểm', icon: 'pencil' },
];

function personTabCount(tabId) {
  if (tabId === 'tasks') return detailTasks.value.length;
  if (tabId === 'events') return detailEvents.value.length;
  return 0;
}

const detailTasks = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.task_breakdown ?? [])].sort((a, b) => {
    const weight = (task) => (task.on_time_state === 'overdue' ? 0 : 1);
    if (weight(a) !== weight(b)) return weight(a) - weight(b);
    return String(a.end_date ?? '').localeCompare(String(b.end_date ?? ''));
  });
});

const detailEvents = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.event_breakdown ?? [])].sort((a, b) =>
    String(b.occurred_at ?? '').localeCompare(String(a.occurred_at ?? '')),
  );
});

function taskTitleOf(taskId, row = selected.value) {
  if (!taskId) return '';
  return (row?.task_breakdown ?? []).find((task) => task.task_id === taskId)?.title ?? '';
}

function compositionParts(row) {
  const start = Math.max(0, Number(row.start_score) || 0);
  const task = Number(row.task_adjustment) || 0;
  const { bonus, penalty } = scoreOf(row);
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

function deltaVsAvg(row) {
  return scoreOf(row).final - visibleSummary.value.average;
}

const draft = reactive({
  user_id: null,
  criterion_id: '',
  level_code: '',
  task_id: '',
  reason: '',
  occurred_at: '',
});
const saving = ref(false);
const removingId = ref(null);
const confirmRemoveId = ref(null);

const scoreCriterion = computed(
  () => criteria.value.find((item) => String(item.id) === String(draft.criterion_id)) ?? null,
);

const draftLevels = computed(() => scoreCriterion.value?.levels ?? []);

const scoreTasks = computed(() => selected.value?.task_breakdown ?? []);

const scoreExisting = computed(() => {
  const row = selected.value;
  const criterionId = Number(draft.criterion_id);
  if (!row || !criterionId) return [];
  return (row.event_breakdown ?? []).filter((event) => Number(event.criterion_id) === criterionId);
});

const lockReports = computed(() => periodLock.value?.reports ?? []);
const isPeriodLocked = computed(() => Boolean(periodLock.value?.locked));

function isDateLocked(date) {
  if (!date) return false;
  return lockReports.value.some(
    (report) => report.period_from <= date && report.period_to >= date,
  );
}

const formLocked = computed(
  () => isPeriodLocked.value || isDateLocked(draft.occurred_at),
);

const lockHeadline = computed(() => {
  const first = lockReports.value[0];
  if (!first) return '';
  if (isPeriodLocked.value) {
    return `Kỳ này đã lưu báo cáo «${first.title}». Không ghi nhận hay xoá thêm được.`;
  }
  return 'Một phần kỳ này đã lưu báo cáo. Ngày thuộc tháng đã chốt thì không ghi nhận được.';
});

const criterionOptions = computed(() =>
  criteria.value.map((item) => ({
    value: String(item.id),
    label: item.name,
    description: item.criterion_type_name
      ? `${item.criterion_type_name} · ${(item.levels ?? []).length} mức`
      : `${(item.levels ?? []).length} mức điểm`,
  })),
);

const taskOptions = computed(() =>
  scoreTasks.value.map((task) => ({
    value: String(task.task_id),
    label: task.title,
    description: [
      TASK_STATUS_LABELS[task.status] ?? task.status,
      task.end_date ? `Hạn ${formatDate(task.end_date)}` : null,
    ]
      .filter(Boolean)
      .join(' · '),
  })),
);

const levelOptions = computed(() =>
  draftLevels.value.map((level) => ({
    value: String(level.code),
    label: level.label,
    description: signedText(level.score),
  })),
);

const occurredDateOptions = computed(() => {
  const from = period.value?.from;
  const to = period.value?.to;
  if (!from || !to || from > to) return [];
  const list = [];
  let cursor = from;
  while (cursor <= to) {
    list.push({
      value: cursor,
      label: formatDate(cursor),
    });
    const next = new Date(`${cursor}T12:00:00`);
    next.setDate(next.getDate() + 1);
    const y = next.getFullYear();
    const m = String(next.getMonth() + 1).padStart(2, '0');
    const day = String(next.getDate()).padStart(2, '0');
    cursor = `${y}-${m}-${day}`;
  }
  return list.reverse();
});

function taskStatusTone(task) {
  if (task.on_time_state === 'overdue') return 'danger';
  if (task.status === 'completed') return 'ok';
  if (task.status === 'in_progress') return 'info';
  return 'muted';
}

function eventTone(score) {
  const num = Number(score) || 0;
  if (num > 0) return 'ok';
  if (num < 0) return 'danger';
  return 'muted';
}

const canRecord = computed(
  () =>
    Boolean(draft.user_id && draft.criterion_id && draft.level_code && draft.occurred_at) &&
    !saving.value &&
    !formLocked.value,
);

function openScore(row, criterion, event) {
  event.stopPropagation();
  openPerson(row, 'score', { criterion_id: criterion.id, forceDraft: true });
}

function onCriterionChange() {
  draft.level_code = '';
}

function goToScoreTab() {
  if (criteria.value.length === 0) {
    showClientToast('warning', 'Phòng ban chưa có tiêu chí để ghi nhận.');
    return;
  }
  detailTab.value = 'score';
  if (selected.value) {
    draft.user_id = selected.value.user_id;
    if (!draft.occurred_at) draft.occurred_at = period.value?.to ?? '';
  }
}

async function record() {
  if (!canRecord.value || !period.value) return;

  saving.value = true;
  try {
    const { data } = await window.axios.post('/api/evaluation/events', {
      user_id: draft.user_id,
      criterion_id: draft.criterion_id,
      level_code: draft.level_code,
      occurred_at: draft.occurred_at || period.value.to,
      task_id: draft.task_id || null,
      reason: draft.reason.trim() || null,
      period_from: period.value.from,
      period_to: period.value.to,
    });

    if (data.row) applyRow(data.row);
    draft.level_code = '';
    draft.reason = '';
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
  if (index >= 0) {
    rows.value[index] = {
      ...fresh,
      user_email: fresh.user_email || rows.value[index].user_email,
    };
  }
}

async function removeEvent(event) {
  if (removingId.value || !period.value) return;
  if (isDateLocked(event.occurred_at)) {
    showClientToast('warning', lockHeadline.value || 'Kỳ này đã lưu báo cáo, không xoá được.');
    return;
  }

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

const criteriaDialogOpen = ref(false);

function openCriteriaDialog() {
  periodMenuOpen.value = false;
  criteriaDialogOpen.value = true;
}

function closeCriteriaDialog() {
  criteriaDialogOpen.value = false;
  criteriaPickerQuery.value = '';
}

function togglePeriodMenu() {
  criteriaDialogOpen.value = false;
  periodMenuOpen.value = !periodMenuOpen.value;
}

function footAvg(getter) {
  if (sheetRows.value.length === 0) return 0;
  const sum = sheetRows.value.reduce((total, row) => total + (Number(getter(row)) || 0), 0);
  return sum / sheetRows.value.length;
}

function footSum(getter) {
  return sheetRows.value.reduce((total, row) => total + (Number(getter(row)) || 0), 0);
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
    description: 'Toàn bộ nhân sự trong kỳ đang xem, kèm các tiêu chí đang hiện.',
    icon: 'fileSpreadsheet',
    onSelect: exportCsv,
  },
]);

function exportCsv() {
  const headers = [
    'Nhân sự',
    'Email',
    'Số việc',
    'Hoàn thành',
    'Quá hạn',
    isWeightedTaskMode.value ? 'Tổng điểm chuẩn' : 'Điểm khởi đầu',
    isWeightedTaskMode.value ? 'Hiệu suất việc (%)' : 'Từ công việc',
    ...shownCriteria.value.map((item) => item.name),
    'Điểm cộng',
    'Điểm trừ',
    isWeightedTaskMode.value ? 'Hiệu suất cuối (%)' : 'Điểm cuối',
    'Xếp loại',
    'Việc thiếu dữ liệu',
  ];
  const lines = [headers.join(',')];
  for (const row of sheetRows.value) {
    lines.push(
      [
        csvCell(row.user_name),
        csvCell(row.user_email),
        totalTasksOf(row),
        doneOf(row),
        overdueOf(row),
        formatNumber(taskBasisOf(row)),
        formatNumber(row.task_adjustment),
        ...shownCriteria.value.map((item) => {
          const total = criterionTotal(row, item.id);
          return total ? formatNumber(total.score) : '';
        }),
        formatNumber(scoreOf(row).bonus),
        formatNumber(scoreOf(row).penalty),
        formatNumber(scoreOf(row).final),
        csvCell(row.classification_label ?? ''),
        rowGapCount(row),
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

function onDocumentClick(event) {
  if (periodMenuOpen.value && periodMenuRoot.value && !periodMenuRoot.value.contains(event.target)) {
    periodMenuOpen.value = false;
  }
}

function onKeydown(event) {
  if (confirmRemoveId.value) return;
  if (event.key === 'Escape') {
    if (criteriaDialogOpen.value) {
      closeCriteriaDialog();
      return;
    }
    if (periodMenuOpen.value) {
      periodMenuOpen.value = false;
      return;
    }
    if (selectedId.value && !isTypingTarget(event.target)) {
      closeDetail();
    }
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

watch(selectedId, (id) => {
  document.body.style.overflow = id ? 'hidden' : '';
});

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

watch(activeQuickKey, (value) => {
  try {
    if (value) localStorage.setItem(SUMMARY_PERIOD_KEY, value);
  } catch {
    // Trình duyệt chặn localStorage thì bỏ qua.
  }
});

onMounted(() => {
  const saved = localStorage.getItem(SUMMARY_PERIOD_KEY);
  const match = quickPeriods.value.find((item) => item.key === saved);
  applyQuickPeriod(match ?? quickPeriods.value[0]);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('mousedown', onDocumentClick);
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
  document.removeEventListener('mousedown', onDocumentClick);
  wrapObserver?.disconnect();
  document.body.style.overflow = '';
});
</script>

<template>
  <section class="matrix">
    <PageHeader
      title="Tổng hợp đánh giá"
      icon="clipboardCheck"
      export-label="Xuất bảng"
      :export-options="exportOptions"
    >
      <template #actions>
        <div class="matrix__period">
          <button type="button" class="matrix__icon-btn" aria-label="Kỳ trước" @click="shiftPeriod(-1)">
            <AppIcon name="chevronLeft" :size="16" />
          </button>
          <div ref="periodMenuRoot" class="matrix__picker">
            <button
              type="button"
              class="matrix__header-btn"
              :class="{ 'matrix__header-btn--on': periodMenuOpen }"
              aria-haspopup="menu"
              :aria-expanded="periodMenuOpen"
              @click="togglePeriodMenu"
            >
              <AppIcon name="calendar" :size="16" />
              {{ periodHeadline }}
              <AppIcon
                name="chevronDown"
                :size="14"
                class="matrix__header-caret"
                :class="{ 'matrix__header-caret--open': periodMenuOpen }"
              />
            </button>
            <div v-if="periodMenuOpen" class="matrix__picker-menu matrix__picker-menu--period" role="menu">
              <button
                v-for="item in quickPeriods"
                :key="item.key"
                type="button"
                role="menuitem"
                class="matrix__picker-item"
                :class="{ 'matrix__picker-item--on': activeQuickKey === item.key }"
                @click="applyQuickPeriod(item)"
              >
                {{ item.label }}
              </button>
              <div class="matrix__picker-dates">
                <div class="matrix__field">
                  <label class="matrix__label" for="matrix-from">Từ ngày</label>
                  <input id="matrix-from" v-model="periodFrom" type="date" class="matrix__input" />
                </div>
                <div class="matrix__field">
                  <label class="matrix__label" for="matrix-to">Đến ngày</label>
                  <input id="matrix-to" v-model="periodTo" type="date" class="matrix__input" />
                </div>
                <p v-if="periodInvalid" class="matrix__warn">Ngày bắt đầu đang sau ngày kết thúc.</p>
              </div>
            </div>
          </div>
          <button type="button" class="matrix__icon-btn" aria-label="Kỳ sau" @click="shiftPeriod(1)">
            <AppIcon name="chevronRight" :size="16" />
          </button>
        </div>

        <button
          type="button"
          class="matrix__header-btn"
          :class="{ 'matrix__header-btn--on': criteriaDialogOpen }"
          aria-haspopup="dialog"
          :aria-expanded="criteriaDialogOpen"
          @click="openCriteriaDialog"
        >
          <AppIcon name="columns" :size="16" />
          Tiêu chí
          <span class="matrix__picker-count">{{ shownCriteriaCount }}/{{ criteria.length }}</span>
        </button>

        <button type="button" class="matrix__header-btn" :disabled="loading" @click="loadSummary">
          <AppIcon name="refresh" :size="16" :class="{ 'matrix__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div v-if="scoreMode && !loadError" class="matrix__mode">
      <span class="matrix__mode-badge">{{ scoreModeInfo.title }}</span>
      <span class="matrix__mode-formula">{{ scoreModeInfo.formula }}</span>
      <span v-if="versionNo" class="matrix__mode-version">Phiên bản {{ versionNo }}</span>
      <span
        v-if="shownCriteriaCount < criteria.length"
        class="matrix__mode-note"
        role="status"
      >
        Đang ẩn {{ criteria.length - shownCriteriaCount }}/{{ criteria.length }} cột tiêu chí — điểm cuối vẫn tính đủ.
      </span>
    </div>

    <div v-if="gapTotal > 0 && !loadError" class="matrix__gap" role="status">
      <AppIcon name="alertTriangle" :size="16" />
      <div class="matrix__gap-copy">
        <p>
          {{ gapTotal }} việc chưa đủ dữ liệu nên điểm thực = 0.
          Độ khó {{ gapSummary.difficulty }} · tiến độ {{ gapSummary.progress }} · chất lượng {{ gapSummary.quality }}.
        </p>
        <button type="button" class="matrix__gap-btn" @click="onlyMissing = !onlyMissing">
          {{ onlyMissing ? 'Hiện tất cả' : 'Chỉ người thiếu dữ liệu' }}
        </button>
      </div>
    </div>

    <div v-if="lockReports.length && !loadError" class="matrix__lock" role="status">
      <AppIcon name="lock" :size="16" />
      <div class="matrix__lock-copy">
        <p>{{ lockHeadline }}</p>
        <p v-if="lockReports.length" class="matrix__lock-links">
          <span v-for="report in lockReports" :key="report.id" class="matrix__lock-link">
            {{ report.title }}
          </span>
        </p>
      </div>
    </div>

    <div class="matrix__body">
      <div class="matrix__main">
        <div class="matrix__toolbar">
          <div class="matrix__field">
            <label class="matrix__label" for="matrix-search">Tìm nhân sự</label>
            <input
              id="matrix-search"
              v-model="query"
              type="search"
              class="matrix__input"
              placeholder="Tìm tên hoặc email…"
            />
          </div>
        </div>
        <div ref="tableWrap" class="matrix__wrap hide-scrollbar">
          <table class="matrix__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col :style="{ width: colWidthStyle('user') }" />
              <col :style="{ width: colWidthStyle('tasks') }" />
              <col :style="{ width: colWidthStyle('start') }" />
              <col :style="{ width: colWidthStyle('task_adj') }" />
              <col
                v-for="item in shownCriteria"
                :key="item.id"
                :style="{ width: colWidthStyle(criterionColKey(item.id)) }"
              />
              <col :style="{ width: colWidthStyle('bonus') }" />
              <col :style="{ width: colWidthStyle('penalty') }" />
              <col :style="{ width: colWidthStyle('final') }" />
              <col :style="{ width: colWidthStyle('klass') }" />
            </colgroup>

            <thead>
              <tr class="matrix__groups">
                <th class="matrix__pin" rowspan="2">Nhân sự</th>
                <th rowspan="2">Việc</th>
                <th colspan="2" class="matrix__th-block">
                  {{ isWeightedTaskMode ? 'Hiệu suất việc' : 'Điểm việc' }}
                </th>
                <th
                  v-for="group in criteriaGroups"
                  :key="group.label"
                  class="matrix__th-block"
                  :colspan="group.items.length"
                >
                  {{ group.label }}
                </th>
                <th colspan="4" class="matrix__th-block">Kết quả</th>
              </tr>
              <tr class="matrix__leaves">
                <th>{{ isWeightedTaskMode ? 'Điểm chuẩn' : 'Khởi đầu' }}</th>
                <th>{{ isWeightedTaskMode ? 'Hiệu suất (%)' : 'Từ việc' }}</th>
                <th v-for="item in shownCriteria" :key="item.id" class="matrix__th-score">
                  {{ item.name }}
                </th>
                <th>Cộng</th>
                <th>Trừ</th>
                <th>{{ isWeightedTaskMode ? 'Hiệu suất cuối' : 'Cuối' }}</th>
                <th>Xếp loại</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="matrix__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="loadError">
                <td :colspan="colSpan" class="matrix__empty matrix__empty--error">{{ loadError }}</td>
              </tr>
              <tr v-else-if="sheetRows.length === 0">
                <td :colspan="colSpan" class="matrix__empty">Kỳ này chưa có nhân sự nào để tổng hợp.</td>
              </tr>
              <tr v-else-if="filteredRows.length === 0">
                <td :colspan="colSpan" class="matrix__empty">Không có nhân sự nào khớp bộ lọc đang chọn.</td>
              </tr>
              <tr
                v-for="row in filteredRows"
                v-else
                :key="row.user_id"
                :class="{ 'matrix__row--on': selectedId === row.user_id }"
                @click="select(row)"
              >
                <td class="matrix__pin matrix__td-user">
                  <button type="button" class="matrix__who" @click.stop="openPerson(row)">
                    <span class="matrix__name">{{ row.user_name }}</span>
                    <span v-if="row.user_email" class="matrix__email">{{ row.user_email }}</span>
                  </button>
                </td>
                <td>
                  <span class="matrix__tasks">{{ doneOf(row) }}/{{ totalTasksOf(row) }}</span>
                  <span v-if="overdueOf(row) > 0" class="matrix__overdue">{{ overdueOf(row) }} trễ</span>
                  <span v-if="rowGapCount(row) > 0" class="matrix__gap-chip">{{ rowGapCount(row) }} thiếu</span>
                </td>
                <td>
                  <span class="matrix__chip">{{ formatNumber(taskBasisOf(row)) }}</span>
                </td>
                <td>
                  <span
                    v-if="isWeightedTaskMode"
                    :class="percentChipClass(row.task_adjustment)"
                    :aria-label="`Hiệu suất ${formatPercent(row.task_adjustment)}`"
                  >{{ formatPercent(row.task_adjustment) }}</span>
                  <span v-else :class="scoreChipClass(row.task_adjustment)">{{ signedText(row.task_adjustment) }}</span>
                </td>
                <td
                  v-for="item in shownCriteria"
                  :key="item.id"
                  class="matrix__td-score"
                  :class="{ 'matrix__td-score--multi': (criterionTotal(row, item.id)?.count ?? 0) > 1 }"
                  @click.stop
                >
                  <button
                    type="button"
                    class="matrix__cell-btn"
                    :class="{
                      'matrix__cell-btn--on':
                        selectedId === row.user_id &&
                        detailTab === 'score' &&
                        String(draft.criterion_id) === String(item.id),
                      'matrix__cell-btn--multi': (criterionTotal(row, item.id)?.count ?? 0) > 1,
                    }"
                    :aria-label="`Ghi nhận ${item.name} cho ${row.user_name}`"
                    @click="openScore(row, item, $event)"
                  >
                    <template v-if="criterionTotal(row, item.id)">
                      <span :class="scoreChipClass(criterionTotal(row, item.id).score)">
                        {{ signedText(criterionTotal(row, item.id).score) }}
                      </span>
                      <span v-if="criterionTotal(row, item.id).count > 1" class="matrix__times">
                        {{ criterionTotal(row, item.id).count }} lần
                      </span>
                    </template>
                    <span v-else class="matrix__dash">—</span>
                  </button>
                </td>
                <td>
                  <span :class="scoreChipClass(scoreOf(row).bonus)">{{ signedText(scoreOf(row).bonus) }}</span>
                </td>
                <td>
                  <span :class="scoreChipClass(scoreOf(row).penalty ? -scoreOf(row).penalty : 0)">
                    {{ scoreOf(row).penalty ? `-${formatNumber(scoreOf(row).penalty)}` : '0' }}
                  </span>
                </td>
                <td>
                  <span class="matrix__chip matrix__chip--final">
                    {{ isWeightedTaskMode ? formatPercent(scoreOf(row).final) : formatNumber(scoreOf(row).final) }}
                  </span>
                </td>
                <td>
                  <span class="matrix__badge">{{ row.classification_label ?? '—' }}</span>
                </td>
              </tr>
            </tbody>

            <tfoot v-if="!loading && !loadError && sheetRows.length">
              <tr>
                <th class="matrix__pin">Phòng ban</th>
                <td>{{ footSum(doneOf) }}/{{ footSum(totalTasksOf) }}</td>
                <td>
                  <span class="matrix__chip">{{ formatNumber(footAvg(taskBasisOf)) }}</span>
                </td>
                <td>
                  <span
                    v-if="isWeightedTaskMode"
                    :class="percentChipClass(footAvg((row) => row.task_adjustment))"
                  >
                    {{ formatPercent(footAvg((row) => row.task_adjustment)) }}
                  </span>
                  <span v-else :class="scoreChipClass(footAvg((row) => row.task_adjustment))">
                    {{ signedText(footAvg((row) => row.task_adjustment)) }}
                  </span>
                </td>
                <td v-for="item in shownCriteria" :key="item.id">
                  <span :class="scoreChipClass(footAvg((row) => criterionTotal(row, item.id)?.score ?? 0))">
                    {{ signedText(footAvg((row) => criterionTotal(row, item.id)?.score ?? 0)) }}
                  </span>
                </td>
                <td>
                  <span :class="scoreChipClass(footAvg((row) => scoreOf(row).bonus))">
                    {{ signedText(footAvg((row) => scoreOf(row).bonus)) }}
                  </span>
                </td>
                <td>
                  <span :class="scoreChipClass(footAvg((row) => (scoreOf(row).penalty ? -scoreOf(row).penalty : 0)))">
                    {{ signedText(footAvg((row) => (scoreOf(row).penalty ? -scoreOf(row).penalty : 0))) }}
                  </span>
                </td>
                <td>
                  <span class="matrix__chip matrix__chip--final">
                    {{ isWeightedTaskMode ? formatPercent(footAvg((row) => scoreOf(row).final)) : formatNumber(footAvg((row) => scoreOf(row).final)) }}
                  </span>
                </td>
                <td>
                  <span class="matrix__chip">
                    {{ isWeightedTaskMode ? formatPercent(visibleSummary.average) : formatNumber(visibleSummary.average) }}
                  </span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <Transition name="matrix-dialog-fade">
        <div
          v-if="selected"
          class="matrix__dialog"
          role="presentation"
          @mousedown.self="closeDetail"
        >
          <div
            class="matrix__dialog-panel matrix__dialog-panel--person"
            role="dialog"
            aria-modal="true"
            aria-labelledby="matrix-person-title"
            @mousedown.stop
          >
            <div class="matrix__dialog-head matrix__dialog-head--person">
              <div class="matrix__dialog-head-copy matrix__dialog-head-copy--person">
                <h2 id="matrix-person-title" class="matrix__dialog-title">{{ selected.user_name }}</h2>
                <p v-if="selected.user_email" class="matrix__person-head-email">{{ selected.user_email }}</p>
              </div>
              <div class="matrix__dialog-head-nav">
                <button
                  type="button"
                  class="matrix__icon-btn"
                  aria-label="Nhân sự trước đó"
                  :disabled="!stepInfo.hasPrev || saving"
                  @click="step(-1)"
                >
                  <AppIcon name="chevronLeft" :size="16" />
                </button>
                <span class="matrix__dialog-pos">{{ stepInfo.index + 1 }}/{{ stepInfo.total }}</span>
                <button
                  type="button"
                  class="matrix__icon-btn"
                  aria-label="Nhân sự kế tiếp"
                  :disabled="!stepInfo.hasNext || saving"
                  @click="step(1)"
                >
                  <AppIcon name="chevronRight" :size="16" />
                </button>
                <button
                  type="button"
                  class="matrix__dialog-close"
                  aria-label="Đóng"
                  :disabled="saving"
                  @click="closeDetail"
                >
                  <AppIcon name="close" :size="16" />
                </button>
              </div>
            </div>

            <div class="matrix__person-strip" aria-label="Tóm tắt điểm trong kỳ">
              <div class="matrix__person-strip-main">
                <div class="matrix__person-strip-score">
                  <span class="matrix__person-strip-label">
                    {{ isWeightedTaskMode ? 'Hiệu suất cuối' : 'Điểm cuối' }}
                  </span>
                  <span class="matrix__person-strip-value">
                    {{ isWeightedTaskMode ? formatPercent(scoreOf(selected).final) : formatNumber(scoreOf(selected).final) }}
                  </span>
                </div>
                <span class="matrix__person-strip-divider" aria-hidden="true" />
                <div class="matrix__person-strip-class">
                  <span class="matrix__person-strip-label">Xếp loại</span>
                  <span class="matrix__person-strip-value matrix__person-strip-value--soft">
                    {{ selected.classification_label ?? 'Chưa xếp loại' }}
                  </span>
                </div>
                <span class="matrix__person-strip-divider" aria-hidden="true" />
                <div class="matrix__person-strip-meta">
                  <span>Việc {{ doneOf(selected) }}/{{ totalTasksOf(selected) }}</span>
                  <span v-if="overdueOf(selected) > 0" class="matrix__person-strip-warn">
                    {{ overdueOf(selected) }} trễ
                  </span>
                  <span v-if="rowGapCount(selected) > 0" class="matrix__person-strip-warn">
                    {{ rowGapCount(selected) }} thiếu dữ liệu
                  </span>
                  <span>
                    TB phòng
                    <span :class="scoreClass(deltaVsAvg(selected))">{{ signedText(deltaVsAvg(selected)) }}</span>
                  </span>
                </div>
              </div>
              <div v-if="compositionParts(selected).length" class="matrix__stack matrix__stack--strip" aria-hidden="true">
                <span
                  v-for="part in compositionParts(selected)"
                  :key="part.key"
                  class="matrix__stack-seg"
                  :class="`matrix__stack-seg--${part.tone}`"
                  :style="{ width: `${part.width}%` }"
                />
              </div>
            </div>

            <div class="matrix__person-tabs" role="tablist" aria-label="Chi tiết nhân sự">
              <button
                v-for="tab in PERSON_TABS"
                :key="tab.id"
                type="button"
                role="tab"
                class="matrix__person-tab"
                :class="{ 'matrix__person-tab--active': detailTab === tab.id }"
                :aria-selected="detailTab === tab.id ? 'true' : 'false'"
                :disabled="saving"
                @click="detailTab = tab.id"
              >
                <AppIcon :name="tab.icon" :size="15" :stroke-width="1.75" />
                <span>{{ tab.label }}</span>
                <span v-if="personTabCount(tab.id) > 0" class="matrix__person-tab-badge">
                  {{ personTabCount(tab.id) }}
                </span>
              </button>
            </div>

            <div
              class="matrix__dialog-body matrix__dialog-body--person hide-scrollbar"
              :class="{ 'matrix__dialog-body--score': detailTab === 'score' }"
            >
              <template v-if="detailTab === 'tasks'">
                <div v-if="detailTasks.length === 0" class="matrix__empty-card">
                  <AppIcon name="listChecks" :size="22" :stroke-width="1.75" />
                  <p>Không có việc nào trong kỳ này.</p>
                </div>
                <div v-else class="matrix__person-table-wrap hide-scrollbar">
                  <table class="matrix__person-table">
                    <thead>
                      <tr>
                        <th>Việc</th>
                        <th>Trạng thái</th>
                        <th>Tiến độ</th>
                        <th>Hạn nộp</th>
                        <th>Ngày xong</th>
                        <template v-if="isWeightedTaskMode">
                          <th class="matrix__person-table-num">Độ khó</th>
                          <th class="matrix__person-table-num">Hệ số tiến độ</th>
                          <th class="matrix__person-table-num">Chất lượng</th>
                          <th class="matrix__person-table-num">Điểm chuẩn</th>
                          <th class="matrix__person-table-num">Điểm thực</th>
                          <th>Ghi chú</th>
                        </template>
                        <th v-else class="matrix__person-table-num">Điểm việc</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="task in detailTasks"
                        :key="task.task_id"
                        :class="{
                          'matrix__person-table-row--danger': task.on_time_state === 'overdue',
                          'matrix__person-table-row--gap': Boolean(taskZeroReason(task)),
                        }"
                      >
                        <td class="matrix__person-table-title">
                          {{ task.title }}
                          <span v-if="task.project_name" class="matrix__person-table-sub">{{ task.project_name }}</span>
                        </td>
                        <td>
                          <span class="matrix__pill matrix__pill--sm" :class="`matrix__pill--${taskStatusTone(task)}`">
                            {{ TASK_STATUS_LABELS[task.status] ?? task.status }}
                          </span>
                        </td>
                        <td>
                          <span
                            class="matrix__pill matrix__pill--sm"
                            :class="task.on_time_state === 'overdue' ? 'matrix__pill--danger' : 'matrix__pill--ok'"
                          >
                            {{ TIMELINESS_LABELS[task.on_time_state] ?? 'Chưa xác định hạn' }}
                          </span>
                        </td>
                        <td>{{ task.end_date ? formatDate(task.end_date) : 'chưa đặt' }}</td>
                        <td>{{ task.actual_end_date ? formatDate(task.actual_end_date) : 'chưa xong' }}</td>
                        <template v-if="isWeightedTaskMode">
                          <td class="matrix__person-table-num">{{ factorText(task.difficulty_factor) }}</td>
                          <td class="matrix__person-table-num">{{ factorText(task.progress_factor) }}</td>
                          <td class="matrix__person-table-num">{{ factorText(task.quality_factor) }}</td>
                          <td class="matrix__person-table-num">{{ formatNumber(task.standard_score) }}</td>
                          <td class="matrix__person-table-num">{{ formatNumber(task.actual_score) }}</td>
                          <td class="matrix__person-table-reason">{{ taskZeroReason(task) || '—' }}</td>
                        </template>
                        <td v-else class="matrix__person-table-num">{{ signedText(task.contribution) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <template v-else-if="detailTab === 'events'">
                <div v-if="detailEvents.length === 0" class="matrix__empty-card">
                  <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
                  <p>Chưa ghi nhận lần nào trong kỳ này.</p>
                  <button
                    type="button"
                    class="matrix__dialog-btn matrix__dialog-btn--primary"
                    :disabled="isPeriodLocked"
                    @click="goToScoreTab"
                  >
                    Chấm điểm
                  </button>
                </div>
                <div v-else class="matrix__person-table-wrap hide-scrollbar">
                  <table class="matrix__person-table">
                    <thead>
                      <tr>
                        <th>Ngày</th>
                        <th>Tiêu chí</th>
                        <th>Mức</th>
                        <th class="matrix__person-table-num">Điểm</th>
                        <th>Việc gắn</th>
                        <th>Lý do</th>
                        <th class="matrix__person-table-act" />
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="event in detailEvents"
                        :key="event.event_id"
                        :class="`matrix__person-table-row--${eventTone(event.score)}`"
                      >
                        <td>{{ formatDate(event.occurred_at) }}</td>
                        <td class="matrix__person-table-title">{{ event.criterion_name }}</td>
                        <td>{{ event.level_label }}</td>
                        <td class="matrix__person-table-num">
                          <span :class="scoreChipClass(event.score)">{{ signedText(event.score) }}</span>
                        </td>
                        <td>{{ taskTitleOf(event.task_id) || '—' }}</td>
                        <td class="matrix__person-table-reason">{{ event.reason || '—' }}</td>
                        <td class="matrix__person-table-act">
                          <button
                            v-if="!isDateLocked(event.occurred_at)"
                            type="button"
                            class="matrix__remove-btn matrix__remove-btn--icon"
                            aria-label="Xoá ghi nhận"
                            @click="confirmRemoveId = event.event_id"
                          >
                            <AppIcon name="trash2" :size="15" :stroke-width="1.75" />
                          </button>
                          <span v-else class="matrix__person-table-lock">Đã chốt</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <form
                v-else
                id="matrix-score-form"
                class="matrix__person-form"
                @submit.prevent="record"
              >
                <div v-if="formLocked" class="matrix__lock matrix__lock--in" role="status">
                  <AppIcon name="lock" :size="16" />
                  <div class="matrix__lock-copy">
                    <p>{{ lockHeadline }}</p>
                    <p v-if="lockReports.length" class="matrix__lock-links">
                      <span v-for="report in lockReports" :key="report.id" class="matrix__lock-link">
                        {{ report.title }}
                      </span>
                    </p>
                  </div>
                </div>
                <p v-else-if="criteria.length === 0" class="matrix__person-empty">
                  Phòng ban chưa có tiêu chí để ghi nhận.
                </p>
                <template v-else>
                  <div class="matrix__person-score">
                    <div class="matrix__score-grid">
                      <div class="matrix__field">
                        <label id="matrix-criterion-label" class="matrix__label">Tiêu chí</label>
                        <OptionPicker
                          v-model="draft.criterion_id"
                          :options="criterionOptions"
                          :disabled="saving || formLocked"
                          autocomplete
                          placeholder="Gõ tên tiêu chí…"
                          labelled-by="matrix-criterion-label"
                          @update:modelValue="onCriterionChange"
                        />
                      </div>
                      <div class="matrix__field">
                        <label id="matrix-level-label" class="matrix__label">Mức điểm</label>
                        <OptionPicker
                          :key="String(draft.criterion_id || 'none')"
                          v-model="draft.level_code"
                          :options="levelOptions"
                          :disabled="saving || formLocked || !draft.criterion_id || levelOptions.length === 0"
                          :placeholder="
                            !draft.criterion_id
                              ? 'Chọn tiêu chí trước'
                              : levelOptions.length === 0
                                ? 'Chưa có mức điểm'
                                : 'Chọn mức điểm…'
                          "
                          labelled-by="matrix-level-label"
                        />
                      </div>
                      <div class="matrix__field">
                        <label id="matrix-occurred-label" class="matrix__label">Ngày xảy ra</label>
                        <OptionPicker
                          v-model="draft.occurred_at"
                          :options="occurredDateOptions"
                          :disabled="saving || formLocked || occurredDateOptions.length === 0"
                          searchable
                          placeholder="Chọn ngày…"
                          labelled-by="matrix-occurred-label"
                        />
                      </div>
                      <div class="matrix__field">
                        <label id="matrix-task-label" class="matrix__label">Gắn việc</label>
                        <OptionPicker
                          v-model="draft.task_id"
                          :options="taskOptions"
                          :disabled="saving || formLocked || scoreTasks.length === 0"
                          autocomplete
                          clearable
                          :placeholder="scoreTasks.length === 0 ? 'Không có việc trong kỳ' : 'Gõ tên việc…'"
                          labelled-by="matrix-task-label"
                        />
                      </div>
                      <div class="matrix__field matrix__field--full">
                        <label class="matrix__label" for="matrix-reason">Lý do</label>
                        <div class="matrix__search-field">
                          <AppIcon name="search" :size="16" :stroke-width="2" aria-hidden="true" />
                          <input
                            id="matrix-reason"
                            v-model="draft.reason"
                            type="search"
                            class="matrix__search-field-input"
                            maxlength="500"
                            placeholder="Gõ lý do (tuỳ chọn)…"
                            :disabled="saving || formLocked"
                            autocomplete="off"
                            spellcheck="false"
                          />
                        </div>
                      </div>
                    </div>

                    <aside class="matrix__person-score-side hide-scrollbar" aria-label="Các lần đã ghi">
                      <p class="matrix__person-score-side-title">
                        Đã ghi tiêu chí này
                        <template v-if="scoreExisting.length"> ({{ scoreExisting.length }})</template>
                      </p>
                      <p v-if="!draft.criterion_id" class="matrix__person-placeholder">
                        Chọn tiêu chí để xem các lần đã ghi.
                      </p>
                      <p v-else-if="scoreExisting.length === 0" class="matrix__person-placeholder">
                        Chưa ghi nhận tiêu chí này trong kỳ.
                      </p>
                      <ul v-else class="matrix__person-score-list">
                        <li v-for="event in scoreExisting" :key="event.event_id" class="matrix__person-score-item">
                          <div class="matrix__person-score-item-head">
                            <div class="matrix__event-head">
                              <span class="matrix__card-title">{{ event.level_label }}</span>
                              <span :class="scoreClass(event.score)">{{ signedText(event.score) }}</span>
                            </div>
                            <button
                              v-if="!isDateLocked(event.occurred_at)"
                              type="button"
                              class="matrix__remove-btn"
                              aria-label="Xoá ghi nhận"
                              @click="confirmRemoveId = event.event_id"
                            >
                              <AppIcon name="trash2" :size="14" :stroke-width="1.75" />
                              <span>Xoá ghi nhận</span>
                            </button>
                          </div>
                          <p class="matrix__card-meta">
                            {{ formatDate(event.occurred_at) }}
                            <span v-if="taskTitleOf(event.task_id)"> · {{ taskTitleOf(event.task_id) }}</span>
                          </p>
                          <p v-if="event.reason" class="matrix__reason">{{ event.reason }}</p>
                        </li>
                      </ul>
                    </aside>
                  </div>
                </template>
              </form>
            </div>

            <div class="matrix__dialog-actions">
              <button
                type="button"
                class="matrix__dialog-btn matrix__dialog-btn--ghost"
                :disabled="saving"
                @click="closeDetail"
              >
                {{ detailTab === 'score' && !formLocked ? 'Huỷ' : 'Đóng' }}
              </button>
              <button
                v-if="detailTab !== 'score' && !isPeriodLocked"
                type="button"
                class="matrix__dialog-btn matrix__dialog-btn--primary"
                :disabled="saving"
                @click="goToScoreTab"
              >
                Ghi nhận đánh giá
              </button>
              <button
                v-else-if="detailTab === 'score' && !formLocked"
                type="submit"
                form="matrix-score-form"
                class="matrix__dialog-btn matrix__dialog-btn--primary"
                :disabled="!canRecord"
              >
                {{ saving ? 'Đang lưu…' : 'Ghi nhận' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="matrix-dialog-fade">
        <div
          v-if="criteriaDialogOpen"
          class="matrix__dialog"
          role="presentation"
          @mousedown.self="closeCriteriaDialog"
        >
          <div
            class="matrix__dialog-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="matrix-criteria-title"
            @mousedown.stop
          >
            <div class="matrix__dialog-head">
              <span class="matrix__dialog-icon" aria-hidden="true">
                <AppIcon name="columns" :size="22" :stroke-width="1.75" />
              </span>
              <div class="matrix__dialog-head-copy">
                <h2 id="matrix-criteria-title" class="matrix__dialog-title">Cột tiêu chí</h2>
              </div>
              <button type="button" class="matrix__dialog-close" aria-label="Đóng" @click="closeCriteriaDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="matrix__dialog-body hide-scrollbar">
              <p v-if="criteria.length === 0" class="matrix__dialog-empty">
                Phòng ban chưa có tiêu chí cộng / trừ điểm nào.
              </p>
              <template v-else>
                <div class="matrix__criteria-search">
                  <label class="matrix__sr" for="matrix-criteria-search">Tìm tiêu chí</label>
                  <div class="matrix__criteria-search-field">
                    <AppIcon name="search" :size="16" :stroke-width="2" aria-hidden="true" />
                    <input
                      id="matrix-criteria-search"
                      v-model="criteriaPickerQuery"
                      type="search"
                      class="matrix__criteria-search-input"
                      placeholder="Gõ tên hoặc loại tiêu chí…"
                      autocomplete="off"
                      spellcheck="false"
                    />
                    <button
                      v-if="criteriaPickerQuery"
                      type="button"
                      class="matrix__criteria-search-clear"
                      aria-label="Xoá tìm kiếm"
                      @click="criteriaPickerQuery = ''"
                    >
                      <AppIcon name="close" :size="14" />
                    </button>
                  </div>
                </div>
                <p v-if="filteredPickerGroups.length === 0" class="matrix__dialog-empty">
                  Không có tiêu chí khớp «{{ criteriaPickerQuery }}».
                </p>
                <div v-else class="matrix__dialog-grid">
                <section v-for="group in filteredPickerGroups" :key="group.label" class="matrix__dialog-group">
                  <button
                    type="button"
                    class="matrix__dialog-group-head"
                    @click="toggleGroupCriteria(group.items)"
                  >
                    <span>{{ group.label }}</span>
                    <span class="matrix__dialog-group-count">
                      {{ groupShownCount(group.items) }}/{{ group.items.length }}
                    </span>
                  </button>
                  <label
                    v-for="item in group.items"
                    :key="item.id"
                    class="matrix__dialog-item"
                    :class="{ 'matrix__dialog-item--on': isCriterionOn(item.id) }"
                  >
                    <input
                      type="checkbox"
                      class="matrix__sr"
                      :checked="isCriterionOn(item.id)"
                      @change="toggleCriterion(item.id, $event.target.checked)"
                    />
                    <span class="matrix__dialog-tick" aria-hidden="true" />
                    <span class="matrix__dialog-item-name">{{ item.name }}</span>
                  </label>
                </section>
              </div>
              </template>
            </div>

            <div class="matrix__dialog-actions">
              <button type="button" class="matrix__dialog-btn matrix__dialog-btn--ghost" @click="setAllCriteria(true)">
                Hiện tất cả
              </button>
              <button type="button" class="matrix__dialog-btn matrix__dialog-btn--ghost" @click="setAllCriteria(false)">
                Ẩn tất cả
              </button>
              <button type="button" class="matrix__dialog-btn matrix__dialog-btn--primary" @click="closeCriteriaDialog">
                Xong
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

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
.matrix {
  --matrix-group-h: 2rem;
  --matrix-leaf-h: 3rem;
  --matrix-vline: var(--color-border-strong);
  --matrix-hline: var(--color-border-strong);
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
  padding: var(--space-4);
}

.matrix__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.matrix__header-btn,
.matrix__icon-btn,
.matrix__ghost,
.matrix__submit {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  border: none;
  border-radius: var(--radius-sm);
  font-family: var(--font-family-base);
  font-weight: 500;
  cursor: pointer;
}

.matrix__header-btn {
  height: 2rem;
  padding: 0 0.625rem;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__header-btn:hover:not(:disabled),
.matrix__header-btn--on {
  background: var(--color-surface-muted);
}

.matrix__header-btn--on {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border)),
    0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.matrix__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.matrix__header-caret {
  opacity: 0.7;
  transition: transform 160ms ease;
}

.matrix__header-caret--open {
  transform: rotate(180deg);
}

.matrix__icon-btn {
  width: 2rem;
  height: 2rem;
  padding: 0;
  background: transparent;
  color: var(--color-text);
}

.matrix__icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.matrix__icon-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.matrix__spin {
  animation: matrix-spin 0.8s linear infinite;
}

@keyframes matrix-spin {
  to {
    transform: rotate(360deg);
  }
}

.matrix__period {
  display: flex;
  align-items: center;
  gap: 2px;
}

.matrix__picker-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.matrix__picker {
  position: relative;
}

.matrix__picker-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 40;
  display: flex;
  flex-direction: column;
  width: 18rem;
  max-height: min(24rem, calc(100vh - 8rem));
  overflow: auto;
  padding: var(--space-3);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.matrix__picker-menu--period {
  left: 0;
  right: auto;
  width: 16.5rem;
  padding: 0.375rem 0;
}

.matrix__picker-item {
  display: flex;
  width: 100%;
  align-items: center;
  padding: 0.5rem 0.875rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.matrix__picker-item:hover {
  background: var(--color-surface-muted);
}

.matrix__picker-item--on {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.matrix__picker-dates {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
  margin-top: 0.25rem;
  padding: var(--space-3) 0.875rem 0.5rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.matrix__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-1);
}

.matrix__field--full {
  grid-column: 1 / -1;
}

.matrix__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.matrix__input {
  width: 100%;
  min-width: 0;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.matrix__textarea {
  resize: vertical;
}

.matrix__warn {
  flex-shrink: 0;
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-danger);
  font-size: 0.8125rem;
}

.matrix__mode {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
  margin: 0 0 var(--space-2);
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, var(--color-border));
}

.matrix__mode-badge {
  flex-shrink: 0;
  padding: 0.25rem 0.5rem;
  border-radius: 999px;
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.matrix__mode-formula {
  min-width: 0;
  flex: 1;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.matrix__mode-version {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
}

.matrix__mode-note {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.matrix__gap {
  display: flex;
  flex-shrink: 0;
  align-items: flex-start;
  gap: var(--space-2);
  margin: 0 0 var(--space-2);
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
  box-shadow: var(--shadow-sm);
}

.matrix__gap-copy {
  min-width: 0;
  flex: 1;
}

.matrix__gap-copy p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
}

.matrix__gap-btn {
  margin-top: 0.375rem;
  padding: 0;
  border: 0;
  background: none;
  color: var(--color-warning-tint-fg);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.matrix__toolbar {
  display: grid;
  flex-shrink: 0;
  grid-template-columns: minmax(12rem, 20rem);
  gap: var(--space-2);
  margin: 0 0 var(--space-2);
}

.matrix__lock {
  display: flex;
  flex-shrink: 0;
  align-items: flex-start;
  gap: var(--space-2);
  margin: 0 0 var(--space-2);
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  background: var(--color-gold-surface);
  color: var(--color-gold-800);
  box-shadow: var(--shadow-sm);
}

.matrix__lock--in {
  margin: 0 0 var(--space-3);
}

.matrix__lock-copy {
  min-width: 0;
  flex: 1;
}

.matrix__lock-copy p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
}

.matrix__lock-links {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: 0.375rem;
}

.matrix__lock-link {
  color: var(--color-primary);
  font-weight: 600;
  text-decoration: none;
}

.matrix__lock-link:hover {
  text-decoration: underline;
}

.matrix__body {
  display: flex;
  flex: 1;
  min-height: 0;
  gap: var(--space-3);
}

.matrix__main {
  display: flex;
  flex: 1;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
}

.matrix__wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  background: var(--color-surface);
}

.matrix__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.matrix__table th,
.matrix__table td {
  position: relative;
  height: var(--matrix-leaf-h);
  padding: 0 0.75rem;
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: 400;
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
  box-shadow:
    inset -1px 0 0 var(--matrix-vline),
    inset 0 -1px 0 var(--matrix-hline);
}

.matrix__table thead th {
  z-index: 3;
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.03em;
}

.matrix__groups th {
  top: 0;
  height: var(--matrix-group-h);
  text-transform: uppercase;
  box-shadow:
    inset -1px 0 0 var(--matrix-vline),
    inset 0 1px 0 var(--matrix-hline),
    inset 0 -1px 0 var(--matrix-hline);
}

.matrix__groups th[rowspan='2'] {
  height: calc(var(--matrix-group-h) + var(--matrix-leaf-h));
  text-transform: none;
  font-size: 0.75rem;
  letter-spacing: 0;
}

.matrix__leaves th {
  top: var(--matrix-group-h);
  white-space: normal;
  line-height: 1.25;
}

.matrix__th-score {
  overflow: hidden;
  padding: 0.25rem 0.5rem;
}

.matrix__table thead th,
.matrix__table tbody td.matrix__pin,
.matrix__table tfoot .matrix__pin {
  position: sticky;
}

.matrix__pin {
  left: 0;
  z-index: 2;
  text-align: left;
  box-shadow:
    inset 1px 0 0 var(--matrix-vline),
    inset -1px 0 0 var(--matrix-vline),
    inset 0 -1px 0 var(--matrix-hline);
}

.matrix__table thead .matrix__pin {
  z-index: 5;
  box-shadow:
    inset 1px 0 0 var(--matrix-vline),
    inset -1px 0 0 var(--matrix-vline),
    inset 0 1px 0 var(--matrix-hline),
    inset 0 -1px 0 var(--matrix-hline);
}

.matrix__table tfoot .matrix__pin {
  z-index: 4;
  box-shadow:
    inset 1px 0 0 var(--matrix-vline),
    inset -1px 0 0 var(--matrix-vline),
    inset 0 -1px 0 var(--matrix-hline);
}

.matrix__table tbody tr {
  cursor: pointer;
}

.matrix__table tbody tr:hover td {
  background: var(--color-surface-muted);
}

.matrix__row--on td {
  background: var(--color-secondary-surface);
}

.matrix__td-user {
  text-align: left;
}

.matrix__who {
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

.matrix__who:hover .matrix__name {
  color: var(--color-primary);
}

.matrix__name {
  display: block;
  max-width: 100%;
  overflow: hidden;
  font-weight: 600;
  text-align: left;
  text-overflow: ellipsis;
}

.matrix__email {
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

.matrix__tasks {
  font-weight: 600;
}

.matrix__overdue {
  display: block;
  color: var(--color-danger);
  font-size: 0.6875rem;
  font-weight: 500;
}

.matrix__gap-chip {
  display: block;
  color: var(--color-warning-tint-fg);
  font-size: 0.6875rem;
  font-weight: 500;
}

.matrix__chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  min-width: 2.25rem;
  padding: 0.125rem 0.4375rem;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
}

.matrix__chip--plus {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.matrix__chip--minus {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.matrix__chip--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.matrix__chip--pct {
  min-width: 3.25rem;
}

.matrix__chip--final {
  min-width: 2.75rem;
  background: var(--color-secondary-surface);
  color: var(--color-secondary-800);
  font-size: 0.875rem;
}

.matrix__times {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.25rem;
  padding: 0.0625rem 0.375rem;
  border-radius: 999px;
  background: var(--color-gold-100);
  color: var(--color-umber-800);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  line-height: 1.2;
}

.matrix__badge {
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
}

.matrix__plus {
  color: var(--color-success);
}

.matrix__minus {
  color: var(--color-danger);
}

.matrix__td-score {
  padding: 0;
}

.matrix__td-score--multi {
  background: color-mix(in srgb, var(--color-gold) 8%, var(--color-surface));
}

.matrix__row--on .matrix__td-score--multi,
.matrix__table tbody tr:hover .matrix__td-score--multi {
  background: color-mix(in srgb, var(--color-gold) 12%, var(--color-surface-muted));
}

.matrix__cell-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.125rem;
  width: 100%;
  height: var(--matrix-leaf-h);
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.matrix__cell-btn:hover,
.matrix__cell-btn--on {
  background: color-mix(in srgb, var(--color-secondary) 10%, transparent);
}

.matrix__dash {
  color: var(--color-text-muted);
}

.matrix__empty {
  height: 6rem;
  color: var(--color-text-muted);
  white-space: normal;
}

.matrix__empty--error {
  color: var(--color-danger);
}

.matrix__table tfoot th,
.matrix__table tfoot td {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: var(--color-surface-muted);
  font-weight: 600;
}


.matrix__person-lead {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.matrix__person-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.matrix__person-lead--success::before {
  background: var(--color-success);
}

.matrix__person-lead--danger::before {
  background: var(--color-danger);
}

.matrix__person-lead-kicker {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.matrix__person-lead-score {
  margin: 0.125rem 0 0;
  color: var(--color-text);
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.15;
}

.matrix__person-lead .matrix__stack {
  margin: var(--space-3) 0 var(--space-2);
}

.matrix__person-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
}

.matrix__person-stat {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
  padding: 0.75rem 0.875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.matrix__person-stat-label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
}

.matrix__person-stat-value {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.matrix__person-stat-note {
  color: var(--color-danger);
  font-size: 0.75rem;
  font-weight: 500;
}

.matrix__person-crits {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.matrix__person-crit {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.625rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.matrix__person-crit:hover {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
}

.matrix__person-crit-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.matrix__person-crit-name {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__stack {
  display: flex;
  flex-shrink: 0;
  height: 0.375rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--color-border);
}

.matrix__stack-seg {
  display: block;
  height: 100%;
}

.matrix__stack-seg--gold {
  background: var(--color-gold);
}

.matrix__stack-seg--teal {
  background: var(--color-secondary);
}

.matrix__stack-seg--ok {
  background: var(--color-success);
}

.matrix__stack-seg--cut {
  background: var(--color-danger);
}

.matrix__rows {
  flex-shrink: 0;
}

.matrix__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.5rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.matrix__row-label::after {
  content: ':';
}

.matrix__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.matrix__person-empty,
.matrix__person-placeholder {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.matrix__tab-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.matrix__tab-heading {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.matrix__tab-section--flush {
  gap: var(--space-2);
}

.matrix__person-lead-meta {
  margin: 0.375rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.matrix__person-table-wrap {
  min-width: 0;
  overflow: auto;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.matrix__person-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.matrix__person-table th {
  padding: 0.5rem 0.75rem;
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

.matrix__person-table td {
  padding: 0.5625rem 0.75rem;
  color: var(--color-text);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__person-table tbody tr:last-child td {
  box-shadow: none;
}

.matrix__person-table-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.matrix__person-table th.matrix__person-table-num {
  text-align: right;
}

.matrix__person-table-act {
  width: 3rem;
  text-align: center;
  white-space: nowrap;
}

.matrix__person-table-title {
  min-width: 10rem;
  max-width: 18rem;
  font-weight: 600;
}

.matrix__person-table-sub {
  display: block;
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.matrix__person-table-reason {
  max-width: 14rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  overflow-wrap: anywhere;
}

.matrix__person-table-lock {
  color: var(--color-gold-800);
  font-size: 0.6875rem;
  font-style: italic;
}

.matrix__person-table-row--danger td:first-child {
  box-shadow: inset 3px 0 0 var(--color-danger);
}

.matrix__person-table-row--gap td:first-child {
  box-shadow: inset 3px 0 0 var(--color-warning);
}

.matrix__person-table-row--ok td:first-child {
  box-shadow: inset 3px 0 0 var(--color-success);
}

.matrix__eq {
  display: grid;
  grid-template-columns:
    minmax(4.5rem, 1fr) auto minmax(4.5rem, 1fr) auto
    minmax(4.5rem, 1fr) auto minmax(4.5rem, 1fr) auto minmax(5.5rem, 1.2fr);
  align-items: stretch;
  gap: 0.4rem;
  padding: 0.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.matrix__eq-term {
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
}

.matrix__eq-n {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
}

.matrix__eq-k {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-style: italic;
  line-height: 1.3;
}

.matrix__eq-op {
  align-self: center;
  color: var(--color-text-muted);
  font-size: 0.9375rem;
}

.matrix__eq-term--gold {
  background: var(--color-gold-surface);
}

.matrix__eq-term--gold .matrix__eq-n {
  color: var(--color-gold-800);
}

.matrix__eq-term--teal {
  background: var(--color-secondary-surface);
}

.matrix__eq-term--teal .matrix__eq-n {
  color: var(--color-secondary-800);
}

.matrix__eq-term--ok {
  background: var(--color-success-tint-bg);
}

.matrix__eq-term--ok .matrix__eq-n {
  color: var(--color-success-tint-fg);
}

.matrix__eq-term--cut {
  background: var(--color-danger-tint-bg);
}

.matrix__eq-term--cut .matrix__eq-n {
  color: var(--color-danger-tint-fg);
}

.matrix__eq-term--out {
  background: var(--color-primary-surface);
}

.matrix__eq-term--out .matrix__eq-n {
  color: var(--color-primary);
}

.matrix__empty-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-5);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  text-align: center;
  box-shadow: var(--shadow-sm);
}

.matrix__empty-card p {
  margin: 0;
  font-size: 0.875rem;
}

.matrix__pill {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.3;
}

.matrix__pill--sm {
  font-weight: 500;
}

.matrix__pill--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.matrix__pill--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.matrix__pill--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.matrix__pill--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.matrix__person-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.matrix__person-card {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.875rem 0.875rem 0.875rem calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.matrix__person-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.matrix__person-card--danger::before {
  background: var(--color-danger);
}

.matrix__person-card--ok::before {
  background: var(--color-success);
}

.matrix__card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.matrix__card-kicker {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.matrix__card-lock {
  margin: 0;
  color: var(--color-gold-800);
  font-size: 0.75rem;
  font-style: italic;
}

.matrix__person-card-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2);
}

.matrix__person-card-grid .matrix__row {
  padding: 0;
  box-shadow: none;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
}

.matrix__person-card-grid .matrix__row-value {
  text-align: left;
}

.matrix__person-exist {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.matrix__person-form {
  display: flex;
  min-width: 0;
  min-height: 0;
  flex: 1;
  flex-direction: column;
}

.matrix__person-score {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(17rem, 21rem);
  gap: var(--space-3);
  align-items: start;
  min-height: 0;
  flex: 1;
  overflow: auto;
  padding-bottom: var(--space-2);
}

.matrix__score-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  align-content: start;
  min-width: 0;
}

.matrix__score-grid .matrix__field {
  min-width: 0;
}

.matrix__score-grid .matrix__field--full {
  grid-column: 1 / -1;
}

.matrix__score-grid .opt-picker {
  width: 100%;
}

.matrix__search-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.matrix__search-field:focus-within {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  color: var(--color-text);
}

.matrix__search-field-input {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  outline: none;
}

.matrix__search-field-input::placeholder {
  color: var(--color-text-muted);
  font-weight: 500;
}

.matrix__search-field-input:disabled {
  cursor: not-allowed;
}

.matrix__person-score-side {
  min-width: 0;
  max-height: min(28rem, calc(100vh - 18rem));
  overflow: auto;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__person-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  align-content: start;
}

.matrix__person-fields .matrix__field--full {
  margin: 0;
  padding: 0;
  border: none;
  min-width: 0;
}

.matrix__person-levels {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.matrix__person-score-side-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 700;
}

.matrix__person-score-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.matrix__person-score-item {
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__person-score-item-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.matrix__person-score-item-head .matrix__event-head {
  flex: 1;
  min-width: 0;
}

.matrix__remove-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  gap: 0.3125rem;
  height: 1.625rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-danger-tint-fg);
  font-family: var(--font-family-base);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.matrix__remove-btn:hover:not(:disabled) {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-danger) 35%, var(--color-danger-tint-border));
}

.matrix__remove-btn:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--color-danger) 45%, transparent);
  outline-offset: 1px;
}

.matrix__remove-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.matrix__remove-btn--icon {
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
}

.matrix__person-score-item:first-child {
  padding-top: 0;
}

.matrix__person-score-item:last-child {
  box-shadow: none;
  padding-bottom: 0;
}

.matrix__person-score-side .matrix__card-meta {
  margin-bottom: 0.25rem;
}

.matrix__level {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__level--on {
  background: var(--color-secondary-surface);
  box-shadow: inset 0 0 0 1px var(--color-secondary);
}

.matrix__dialog-panel--person .matrix__input {
  min-height: 2.25rem;
  padding: 0.375rem 0.625rem;
  font-size: 0.875rem;
}

.matrix__dialog-panel--person .matrix__textarea {
  min-height: 4.5rem;
  resize: vertical;
}

.matrix__input::placeholder {
  color: var(--color-text-muted);
  opacity: 1;
}

.matrix__card-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.matrix__card-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.5rem;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.matrix__event-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.matrix__reason {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-style: italic;
}

.matrix__link,
.matrix__ghost,
.matrix__submit {
  font-size: 0.8125rem;
}

.matrix__link {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-secondary-800);
  font-family: var(--font-family-base);
  font-weight: 500;
  cursor: pointer;
}

.matrix__link:hover:not(:disabled) {
  text-decoration: underline;
}

.matrix__link:disabled {
  color: var(--color-text-muted);
  cursor: not-allowed;
  text-decoration: none;
}

.matrix__link--danger {
  color: var(--color-danger);
}

.matrix__chip-n {
  margin-left: 0.25rem;
  font-size: 0.6875rem;
  font-weight: 600;
}

.matrix__ghost {
  height: 2rem;
  padding: 0 0.75rem;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.matrix__submit {
  height: 2rem;
  padding: 0 0.875rem;
  background: var(--color-secondary);
  color: var(--color-on-secondary);
}

.matrix__submit:hover:not(:disabled) {
  background: var(--color-secondary-hover);
}

.matrix__submit:disabled,
.matrix__ghost:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.matrix__dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.matrix__dialog-panel {
  display: flex;
  width: min(56rem, calc(100vw - 2.5rem));
  height: min(42rem, calc(100vh - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  flex-direction: column;
  overflow: hidden;
  padding: 1.5rem 1.75rem 1.25rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.matrix__dialog-panel--person {
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  padding: 1.25rem 1.5rem 1rem;
}

.matrix__dialog-panel--person .matrix__dialog-body {
  flex: 1;
  min-height: 0;
  margin: var(--space-3) 0;
}

.matrix__dialog-panel--person .matrix__dialog-head {
  align-items: flex-start;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
  box-shadow: none;
}

.matrix__dialog-head--person {
  padding-bottom: 0;
}

.matrix__person-strip {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin-top: var(--space-3);
  padding: 0.625rem;
  border-radius: var(--radius-lg);
  background:
    linear-gradient(
      135deg,
      color-mix(in srgb, var(--color-primary) 4%, var(--color-surface)) 0%,
      var(--color-surface) 58%,
      color-mix(in srgb, var(--color-secondary) 4%, var(--color-surface)) 100%
    );
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 12%, var(--color-border)),
    var(--shadow-sm);
}

.matrix__person-strip-main {
  display: grid;
  grid-template-columns: minmax(8rem, 0.7fr) minmax(9rem, 0.8fr) minmax(18rem, 2fr);
  align-items: stretch;
  gap: 0.625rem;
}

.matrix__person-strip-score,
.matrix__person-strip-class {
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-md);
}

.matrix__person-strip-score {
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px var(--color-primary-100);
}

.matrix__person-strip-class {
  background: var(--color-secondary-surface);
  box-shadow: inset 0 0 0 1px var(--color-secondary-100);
}

.matrix__person-strip-score .matrix__person-strip-value {
  color: var(--color-primary);
  font-size: 1.75rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
  line-height: 1;
}

.matrix__person-strip-label {
  display: block;
  margin-bottom: 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.matrix__person-strip-value {
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
}

.matrix__person-strip-value--soft {
  align-self: flex-start;
  padding: 0.1875rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-secondary);
  color: var(--color-on-secondary);
  font-size: 0.8125rem;
  font-style: normal;
  line-height: 1.35;
}

.matrix__person-strip-divider {
  display: none;
}

.matrix__person-strip-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: stretch;
  gap: 0.5rem;
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.matrix__person-strip-meta > span {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  font-weight: 600;
  line-height: 1.35;
  text-align: center;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__person-strip-meta > .matrix__person-strip-warn {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
  font-weight: 700;
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
}

.matrix__stack--strip {
  height: 0.5rem;
  box-shadow: 0 0 0 2px var(--color-surface);
}

.matrix__person-tabs {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-top: var(--space-3);
  padding: 0.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__person-tab {
  display: inline-flex;
  flex: 1 1 auto;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  min-width: 0;
  padding: 0.4375rem 0.625rem;
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.matrix__person-tab:hover:not(:disabled) {
  color: var(--color-text);
}

.matrix__person-tab--active {
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.matrix__person-tab-badge {
  display: inline-flex;
  min-width: 1.125rem;
  align-items: center;
  justify-content: center;
  padding: 0 0.3125rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
}

.matrix__person-tab--active .matrix__person-tab-badge {
  background: color-mix(in srgb, var(--color-primary) 14%, transparent);
}

.matrix__person-tab:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.matrix__dialog-panel--person .matrix__dialog-icon {
  margin-top: 0.125rem;
}

.matrix__dialog-head-copy--person .matrix__dialog-title {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  white-space: normal;
  font-size: 1.0625rem;
  line-height: 1.3;
  letter-spacing: -0.02em;
}

.matrix__person-head-email {
  margin: 0.25rem 0 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__dialog-head,
.matrix__person-strip,
.matrix__person-tabs,
.matrix__dialog-actions {
  flex-shrink: 0;
}

.matrix__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.matrix__dialog-head-nav {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-1);
}

.matrix__dialog-head-nav .matrix__icon-btn {
  width: 1.75rem;
  height: 1.75rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__dialog-pos {
  min-width: 2.5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-align: center;
}

.matrix__dialog-tabs {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__dialog-tab {
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 2px 0 transparent;
  cursor: pointer;
}

.matrix__dialog-tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.matrix__dialog-tab:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.matrix__dialog-body--person {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.matrix__dialog-body--person .matrix__tab-section {
  flex: 1;
  min-height: 0;
}

.matrix__dialog-body--score {
  padding-bottom: var(--space-2);
}

.matrix__criteria-search {
  flex-shrink: 0;
}

.matrix__criteria-search-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.matrix__criteria-search-field:focus-within {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  color: var(--color-text);
}

.matrix__criteria-search-input {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  outline: none;
}

.matrix__criteria-search-input::placeholder {
  color: var(--color-text-muted);
}

.matrix__criteria-search-clear {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.matrix__criteria-search-clear:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.matrix__dialog-icon {
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

.matrix__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.matrix__dialog-title {
  margin: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__dialog-close {
  display: inline-flex;
  flex-shrink: 0;
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

.matrix__dialog-close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.matrix__dialog-close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.matrix__dialog-body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  margin: var(--space-4) 0;
  overflow: auto;
}

.matrix__dialog-empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.matrix__dialog-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
}

.matrix__dialog-group {
  display: flex;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__dialog-group-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  width: 100%;
  padding: 0.625rem 0.75rem;
  border: none;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
  cursor: pointer;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__dialog-group-head:hover {
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface-muted));
}

.matrix__dialog-group-count {
  color: var(--color-text-muted);
  font-weight: 600;
  letter-spacing: 0;
  text-transform: none;
}

.matrix__dialog-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0.5rem 0.75rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__dialog-item:last-child {
  box-shadow: none;
}

.matrix__dialog-item:hover {
  background: var(--color-surface-muted);
}

.matrix__dialog-item--on {
  background: color-mix(in srgb, var(--color-secondary) 8%, var(--color-surface));
}

.matrix__dialog-tick {
  flex-shrink: 0;
  width: 1rem;
  height: 1rem;
  margin-top: 0.125rem;
  border-radius: 0.25rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__dialog-item--on .matrix__dialog-tick {
  background: var(--color-secondary);
  box-shadow: none;
}

.matrix__dialog-item--on .matrix__dialog-tick::after {
  content: '';
  display: block;
  width: 0.3125rem;
  height: 0.5625rem;
  margin: 0.0625rem auto 0;
  border: solid var(--color-on-secondary);
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.matrix__dialog-item-name {
  min-width: 0;
  overflow-wrap: anywhere;
}

.matrix__dialog-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.matrix__dialog-actions .matrix__dialog-btn--primary {
  margin-left: auto;
}

.matrix__dialog-btn {
  padding: 0.625rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.matrix__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.matrix__dialog-btn--primary:hover {
  background: var(--color-primary-hover);
}

.matrix__dialog-btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.matrix__dialog-btn--ghost:hover {
  background: var(--color-surface-muted);
}

.matrix-dialog-fade-enter-active,
.matrix-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.matrix-dialog-fade-enter-from,
.matrix-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 900px) {
  .matrix__person-strip-main {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .matrix__person-strip-meta {
    grid-column: 1 / -1;
  }

  .matrix__person-score {
    grid-template-columns: minmax(0, 1fr);
  }

  .matrix__person-score-side {
    max-height: 16rem;
  }

  .matrix__eq {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .matrix__eq-op {
    display: none;
  }

  .matrix__person-stats,
  .matrix__person-fields,
  .matrix__person-levels,
  .matrix__person-cards,
  .matrix__person-crits,
  .matrix__person-card-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .matrix__stats {
    margin-left: 0;
  }

  .matrix__dialog-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .matrix__person-strip {
    padding: 0.5rem;
  }

  .matrix__person-strip-main {
    gap: 0.5rem;
  }

  .matrix__person-strip-score,
  .matrix__person-strip-class {
    padding: 0.5rem 0.625rem;
  }

  .matrix__person-strip-meta {
    gap: 0.375rem;
  }

  .matrix__person-strip-meta > span {
    padding: 0.4375rem 0.375rem;
    font-size: 0.6875rem;
  }

  .matrix__person-stats,
  .matrix__person-fields,
  .matrix__person-levels,
  .matrix__person-cards,
  .matrix__person-crits,
  .matrix__person-card-grid,
  .matrix__dialog-grid,
  .matrix__eq,
  .matrix__score-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .matrix-dialog-fade-enter-active,
  .matrix-dialog-fade-leave-active,
  .matrix__spin,
  .matrix__header-caret {
    transition: none;
    animation: none;
  }
}
</style>
