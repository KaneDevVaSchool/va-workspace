<script setup>
/**
 * Đánh giá nhân sự — ma trận chấm điểm cả phòng ban trong kỳ của báo cáo.
 *
 * Không dùng mẫu danh sách ActivityLog / TablePagesBar: đây là bảng kín khung
 * nhìn (nhân sự × tiêu chí), không danh sách phẳng. Click tên mở modal nhân sự
 * (Công việc / Ghi nhận). Click ô tiêu chí: bản nháp mở modal chấm điểm; báo
 * cáo đã lưu mở chi tiết nhân sự (chỉ xem).
 *
 * Kỳ lấy từ URL (`?from=&to=`) khi mở từ tạo/sửa báo cáo hoặc nút Chi tiết —
 * không chọn lại khoảng ngày trên trang này.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import OptionPicker from '@modules/Project/resources/js/components/OptionPicker.vue';
import {
  REPORT_STATUS_LABELS,
  TASK_STATUS_LABELS,
  TIMELINESS_LABELS,
} from '@modules/Report/resources/js/constants/report.js';

const CRITERIA_VIS_KEY = 'va-evaluation-matrix-criteria-v1';

const route = useRoute();
const router = useRouter();

function goBack() {
  router.push({ name: 'manager.reports.index' });
}

const MIN_COL = {
  user: 220,
  tasks: 152,
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
const scoreMode = ref(null);
const periodLock = ref({ locked: false, reports: [] });
const loading = ref(false);
const loadError = ref('');
const exportingPdf = ref(false);

const isWeightedTaskMode = computed(() => scoreMode.value === 'weighted_task');

function todayISO() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

const periodFrom = ref('');
const periodTo = ref('');

function isIsoDate(value) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function queryDate(value) {
  return Array.isArray(value) ? value[0] : value;
}

function queryId(value) {
  const raw = Array.isArray(value) ? value[0] : value;
  const id = Number(raw);
  return Number.isInteger(id) && id > 0 ? id : null;
}

/** Kỳ từ URL (?from=&to=) khi mở từ báo cáo vừa tạo hoặc Chấm điểm. */
function applyPeriodFromRoute() {
  const from = queryDate(route.query.from);
  const to = queryDate(route.query.to);
  periodFrom.value = isIsoDate(from) ? from : '';
  periodTo.value = isIsoDate(to) ? to : '';
  return Boolean(periodFrom.value && periodTo.value && periodFrom.value <= periodTo.value);
}

function applyReportFromRoute() {
  reportId.value = queryId(route.query.report);
}

const reportId = ref(null);
const boundDisplay = ref(null);
const appendixConfirmOpen = ref(false);
const saveConfirmOpen = ref(false);
const savingDisplay = ref(false);
const savingReport = ref(false);

applyPeriodFromRoute();
applyReportFromRoute();

const period = computed(() => {
  if (!periodFrom.value || !periodTo.value) return null;
  if (periodFrom.value > periodTo.value) return null;
  return { from: periodFrom.value, to: periodTo.value };
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

function stripTone(row) {
  if (!row) return '';
  if (overdueOf(row) > 0 || rowGapCount(row) > 0) return 'danger';
  if (deltaVsAvg(row) < 0) return 'info';
  return 'success';
}

function stripClass(row) {
  const tone = stripTone(row);
  return tone ? `matrix__person-strip--${tone}` : '';
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

const sheetRows = computed(() =>
  [...rows.value].sort((a, b) => {
    const score = scoreOf(b).final - scoreOf(a).final;
    if (score !== 0) return score;
    return String(a.user_name ?? '').localeCompare(String(b.user_name ?? ''), 'vi');
  }),
);

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
  if (reportId.value) return {};
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
  if (boundDisplay.value) return;
  try {
    localStorage.setItem(CRITERIA_VIS_KEY, JSON.stringify(visibleCriteriaMap));
  } catch {
    // Bỏ qua.
  }
}

function isOriginalAllCriteria(display) {
  const ids = (display?.criterion_ids ?? []).map(Number).filter((id) => id > 0);
  return (!display?.kind || display.kind === 'original') && ids.length === 0;
}

function applyCriteriaFromDisplay(list, display) {
  const ids = new Set((display?.criterion_ids ?? []).map((id) => String(id)));
  const showAll = !display || isOriginalAllCriteria(display);
  for (const item of list) {
    visibleCriteriaMap[String(item.id)] = showAll ? true : ids.has(String(item.id));
  }
}

watch(criteria, (list) => {
  if (boundDisplay.value) {
    applyCriteriaFromDisplay(list, boundDisplay.value);
    return;
  }
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

function currentCriterionIds() {
  return criteria.value.filter((item) => isCriterionOn(item.id)).map((item) => Number(item.id));
}

function criteriaDirty() {
  if (!boundDisplay.value) return false;
  const current = currentCriterionIds();
  if (isOriginalAllCriteria(boundDisplay.value)) {
    return current.length !== criteria.value.length;
  }
  const saved = (boundDisplay.value.criterion_ids ?? []).map(Number).filter((id) => id > 0);
  if (current.length !== saved.length) return true;
  const left = [...current].sort((a, b) => a - b);
  const right = [...saved].sort((a, b) => a - b);
  return left.some((id, index) => id !== right[index]);
}

function nextAppendixLabel() {
  const current = String(boundDisplay.value?.revision || '1.0');
  const parts = current.split('.');
  const major = Number.parseInt(parts[0], 10);
  const minor = Number.parseInt(parts[1] ?? '0', 10);
  const nextMajor = Number.isInteger(major) ? major : 1;
  const nextMinor = Number.isInteger(minor) ? minor + 1 : 1;
  return `${nextMajor}.${nextMinor}`;
}

const revisionHeadline = computed(() => {
  const display = boundDisplay.value;
  if (!display?.revision) return '';
  if (display.kind === 'appendix') return `${display.revision} · Phụ lục`;
  return display.revision;
});

const reportIsDraft = computed(
  () => Boolean(reportId.value && boundDisplay.value?.status === 'draft'),
);

const reportIsSaved = computed(
  () => Boolean(reportId.value && boundDisplay.value?.status === 'saved'),
);

const pageTitle = computed(() =>
  reportIsSaved.value ? 'Chi tiết đánh giá nhân sự' : 'Đánh giá nhân sự',
);

const reportPeriodHint = computed(() => {
  if (!boundDisplay.value) return '';
  const statusLabel =
    REPORT_STATUS_LABELS[boundDisplay.value.status] ?? boundDisplay.value.status ?? '';
  const parts = ['Cột tiêu chí theo báo cáo'];
  if (statusLabel) parts.push(statusLabel);
  return parts.join(' · ');
});

function scoreColOn(key) {
  const keys = boundDisplay.value?.column_keys;
  if (!Array.isArray(keys) || keys.length === 0) return true;
  return keys.includes(key);
}

function groupScoringTone(items) {
  let pos = false;
  let neg = false;
  for (const item of items) {
    for (const level of item.levels ?? []) {
      const score = Number(level.score) || 0;
      if (score > 0) pos = true;
      if (score < 0) neg = true;
      if (pos && neg) return 'mixed';
    }
  }
  if (pos) return 'ok';
  if (neg) return 'cut';
  return '';
}

function pickerGroupClass(group) {
  const tone = groupScoringTone(group.items);
  const allOn = group.items.length > 0 && groupShownCount(group.items) === group.items.length;
  return {
    [`matrix__dialog-group--${tone}`]: Boolean(tone),
    'matrix__dialog-group--all': allOn,
  };
}

const workGroupSpan = computed(
  () => Number(scoreColOn('start_score')) + Number(scoreColOn('task_adjustment')),
);

const resultGroupSpan = computed(
  () =>
    Number(scoreColOn('bonus')) +
    Number(scoreColOn('penalty')) +
    Number(scoreColOn('final_score')) +
    Number(scoreColOn('classification')),
);

const colSpan = computed(() => {
  let count = 1;
  if (scoreColOn('tasks')) count += 1;
  count += workGroupSpan.value;
  count += shownCriteria.value.length;
  count += resultGroupSpan.value;
  return count;
});

const tableWrap = ref(null);
const wrapWidth = ref(0);
let wrapObserver = null;
const personTableWrap = ref(null);

useDragScroll(tableWrap);
useDragScroll(personTableWrap);

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
  ...(scoreColOn('tasks') ? ['tasks'] : []),
  ...(scoreColOn('start_score') ? ['start'] : []),
  ...(scoreColOn('task_adjustment') ? ['task_adj'] : []),
  ...shownCriteria.value.map((item) => criterionColKey(item.id)),
  ...(scoreColOn('bonus') ? ['bonus'] : []),
  ...(scoreColOn('penalty') ? ['penalty'] : []),
  ...(scoreColOn('final_score') ? ['final'] : []),
  ...(scoreColOn('classification') ? ['klass'] : []),
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
    scoreMode.value = null;
    return;
  }

  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await window.axios.get('/api/evaluation/summary', {
      params: {
        from: period.value.from,
        to: period.value.to,
        ...(reportId.value ? { report: reportId.value } : {}),
      },
    });
    rows.value = data.rows ?? [];
    boundDisplay.value = data.report ?? null;
    criteria.value = data.criteria ?? [];
    scoreMode.value = data.mode ?? null;
    periodLock.value = data.period_lock ?? { locked: false, reports: [] };
    if (boundDisplay.value) {
      applyCriteriaFromDisplay(criteria.value, boundDisplay.value);
    }

    if (selectedId.value && !rows.value.some((row) => row.user_id === selectedId.value)) {
      selectedId.value = null;
    }
  } catch (error) {
    loadError.value =
      error?.response?.data?.errors?.version?.[0] ??
      error?.response?.data?.message ??
      'Không tải được bảng đánh giá.';
    rows.value = [];
    criteria.value = [];
    scoreMode.value = null;
    periodLock.value = { locked: false, reports: [] };
    boundDisplay.value = null;
  } finally {
    loading.value = false;
  }
}

const selectedId = ref(null);
const scoreModalOpen = ref(false);
const scoreFromPerson = ref(false);
const scoreModalTab = ref('record');

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
  scoreModalOpen.value = false;
  scoreFromPerson.value = false;
  const switched = selectedId.value !== row.user_id;
  selectedId.value = row.user_id;
  detailTab.value = tab === 'score' ? 'events' : tab;
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
  if (scoreModalOpen.value) {
    closeScoreModal();
    return;
  }
  selectedId.value = null;
  scoreFromPerson.value = false;
}

function step(offset) {
  const list = sheetRows.value;
  const index = list.findIndex((row) => row.user_id === selectedId.value);
  const next = list[index + offset];
  if (next) openPerson(next, detailTab.value, { forceDraft: true });
}

const stepInfo = computed(() => {
  const list = sheetRows.value;
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
  return (row.event_breakdown ?? [])
    .filter((event) => Number(event.criterion_id) === criterionId)
    .sort((a, b) => String(b.occurred_at ?? '').localeCompare(String(a.occurred_at ?? '')));
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
  if (reportIsSaved.value || isPeriodLocked.value) {
    openPerson(row, 'events', { criterion_id: criterion.id });
    return;
  }
  criteriaDialogOpen.value = false;
  scoreFromPerson.value = false;
  selectedId.value = row.user_id;
  resetDraft(row, { criterion_id: criterion.id });
  scoreModalTab.value = 'record';
  scoreModalOpen.value = true;
}

function closeScoreModal() {
  if (saving.value) return;
  scoreModalOpen.value = false;
  if (scoreFromPerson.value) {
    scoreFromPerson.value = false;
    if (detailTab.value === 'score') detailTab.value = 'events';
    return;
  }
  selectedId.value = null;
}

function onCriterionChange() {
  draft.level_code = '';
}

function goToScoreTab() {
  if (criteria.value.length === 0) {
    showClientToast('warning', 'Phòng ban chưa có tiêu chí để ghi nhận.');
    return;
  }
  if (!selected.value) return;
  scoreFromPerson.value = true;
  draft.user_id = selected.value.user_id;
  if (!draft.occurred_at) draft.occurred_at = period.value?.to ?? '';
  scoreModalTab.value = 'record';
  scoreModalOpen.value = true;
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
  const id = confirmRemoveId.value;
  const event =
    detailEvents.value.find((item) => item.event_id === id) ??
    scoreExisting.value.find((item) => item.event_id === id);
  if (event) removeEvent(event);
}

function onConfirmOpen(open) {
  if (!open) confirmRemoveId.value = null;
}

const criteriaDialogOpen = ref(false);

function openCriteriaDialog() {
  if (reportIsSaved.value) {
    showClientToast('warning', 'Báo cáo đã lưu, không đổi cột tiêu chí được nữa.');
    return;
  }
  criteriaDialogOpen.value = true;
}

function closeCriteriaDialog() {
  if (boundDisplay.value && criteriaDirty()) {
    appendixConfirmOpen.value = true;
    return;
  }
  criteriaDialogOpen.value = false;
  criteriaPickerQuery.value = '';
}

async function confirmAppendix() {
  if (!reportId.value || savingDisplay.value) return;
  savingDisplay.value = true;
  try {
    const { data } = await window.axios.patch(`/api/report/${reportId.value}/display`, {
      criterion_ids: currentCriterionIds(),
      column_keys: boundDisplay.value?.column_keys,
    });
    boundDisplay.value = data.report?.display ?? data.report ?? boundDisplay.value;
    if (boundDisplay.value) {
      applyCriteriaFromDisplay(criteria.value, boundDisplay.value);
    }
    showClientToast('success', `Đã lưu phụ lục ${boundDisplay.value?.revision ?? nextAppendixLabel()}.`);
    appendixConfirmOpen.value = false;
    criteriaDialogOpen.value = false;
    criteriaPickerQuery.value = '';
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không lưu được phụ lục.');
  } finally {
    savingDisplay.value = false;
  }
}

function onAppendixOpen(open) {
  if (open) {
    appendixConfirmOpen.value = true;
    return;
  }
  if (savingDisplay.value) return;
  if (criteriaDirty()) {
    applyCriteriaFromDisplay(criteria.value, boundDisplay.value);
  }
  appendixConfirmOpen.value = false;
  criteriaDialogOpen.value = false;
  criteriaPickerQuery.value = '';
}

function applyBoundReportDetail(detail) {
  if (!detail?.display) return;
  boundDisplay.value = {
    ...detail.display,
    status: detail.status,
    title: detail.title,
    period_from: detail.period_from,
    period_to: detail.period_to,
  };
  if (boundDisplay.value) {
    applyCriteriaFromDisplay(criteria.value, boundDisplay.value);
  }
}

function openSaveConfirm() {
  if (!reportIsDraft.value || savingReport.value) return;
  saveConfirmOpen.value = true;
}

function onSaveConfirmOpen(open) {
  if (savingReport.value) return;
  saveConfirmOpen.value = open;
}

async function confirmSaveReport() {
  if (!reportId.value || savingReport.value || !reportIsDraft.value) return;
  savingReport.value = true;
  try {
    const { data } = await window.axios.patch(`/api/report/${reportId.value}/save`);
    applyBoundReportDetail(data.report);
    await loadSummary();
    saveConfirmOpen.value = false;
    showClientToast('success', 'Đã lưu báo cáo. Kỳ này không ghi nhận hay xoá điểm thêm được nữa.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message ?? 'Không lưu được báo cáo.');
  } finally {
    savingReport.value = false;
  }
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
    key: 'pdf',
    label: 'Xuất PDF',
    description: 'Form ngang A3, bảng tổng hợp + phiếu chi tiết từng người, có dấu chìm VA.',
    icon: 'fileText',
    onSelect: exportPdf,
  },
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
  link.download = `danh-gia-nhan-su_${stamp}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

async function downloadBlob(url, params, busyRef, defaultFilename, okMsg, failMsg) {
  busyRef.value = true;
  try {
    const response = await window.axios.get(url, {
      params,
      responseType: 'blob',
      timeout: 180000,
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được file.');
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || defaultFilename);

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', okMsg);
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || Object.values(json.errors || {})[0]?.[0];
      } catch {
        message = failMsg;
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || failMsg);
  } finally {
    busyRef.value = false;
  }
}

async function exportPdf() {
  if (!period.value) {
    showClientToast('error', 'Chưa chọn kỳ đánh giá.');
    return;
  }
  const stamp = `${period.value.from}_${period.value.to}`;
  await downloadBlob(
    '/api/evaluation/summary/export-pdf',
    {
      from: period.value.from,
      to: period.value.to,
      ...(reportId.value ? { report: reportId.value } : {}),
      criterion_ids: shownCriteria.value.map((item) => item.id),
    },
    exportingPdf,
    `Danh_gia_nhan_su_${stamp}.pdf`,
    'Đã tải file PDF.',
    'Không xuất được file PDF.',
  );
}

function isTypingTarget(el) {
  const tag = el?.tagName;
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el?.isContentEditable;
}

function onKeydown(event) {
  if (confirmRemoveId.value || appendixConfirmOpen.value || saveConfirmOpen.value) return;
  if (event.key === 'Escape') {
    if (criteriaDialogOpen.value) {
      closeCriteriaDialog();
      return;
    }
    if (selectedId.value && !isTypingTarget(event.target)) {
      closeDetail();
    }
    return;
  }
  if (isTypingTarget(event.target) || !selectedId.value || scoreModalOpen.value) return;
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
}, { immediate: true });

watch(reportId, () => {
  loadSummary();
});

watch(
  () => [route.query.from, route.query.to, route.query.report],
  () => {
    applyPeriodFromRoute();
    applyReportFromRoute();
  },
);

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
</script>

<template>
  <section class="matrix" :class="{ 'matrix--view': reportIsSaved }">
    <PageHeader
      :title="pageTitle"
      icon="clipboardCheck"
      export-label="Xuất bảng"
      :export-options="exportOptions"
      :export-busy-key="exportingPdf ? 'pdf' : undefined"
      :period="revisionHeadline"
      :period-hint="boundDisplay ? reportPeriodHint : ''"
    >
      <template #actions>
        <button type="button" class="matrix__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách báo cáo
        </button>

        <button
          v-if="reportIsDraft"
          type="button"
          class="matrix__header-btn matrix__header-btn--primary"
          :disabled="loading || savingReport"
          @click="openSaveConfirm"
        >
          <AppIcon name="check" :size="16" />
          {{ savingReport ? 'Đang lưu…' : 'Lưu báo cáo' }}
        </button>

        <span
          v-else-if="reportIsSaved"
          class="matrix__status matrix__status--saved"
          role="status"
        >
          {{ REPORT_STATUS_LABELS.saved }}
        </span>

        <button
          v-if="reportIsSaved"
          type="button"
          class="matrix__header-btn"
          :disabled="exportingPdf || loading"
          @click="exportPdf"
        >
          <AppIcon name="fileText" :size="16" />
          {{ exportingPdf ? 'Đang xuất PDF…' : 'Xuất PDF' }}
        </button>

        <button
          v-if="!reportIsSaved"
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
        <div ref="tableWrap" class="matrix__wrap hide-scrollbar">
          <table class="matrix__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col :style="{ width: colWidthStyle('user') }" />
              <col v-if="scoreColOn('tasks')" :style="{ width: colWidthStyle('tasks') }" />
              <col v-if="scoreColOn('start_score')" :style="{ width: colWidthStyle('start') }" />
              <col v-if="scoreColOn('task_adjustment')" :style="{ width: colWidthStyle('task_adj') }" />
              <col
                v-for="item in shownCriteria"
                :key="item.id"
                :style="{ width: colWidthStyle(criterionColKey(item.id)) }"
              />
              <col v-if="scoreColOn('bonus')" :style="{ width: colWidthStyle('bonus') }" />
              <col v-if="scoreColOn('penalty')" :style="{ width: colWidthStyle('penalty') }" />
              <col v-if="scoreColOn('final_score')" :style="{ width: colWidthStyle('final') }" />
              <col v-if="scoreColOn('classification')" :style="{ width: colWidthStyle('klass') }" />
            </colgroup>

            <thead>
              <tr class="matrix__groups">
                <th class="matrix__pin" rowspan="2">Nhân sự</th>
                <th v-if="scoreColOn('tasks')" rowspan="2">Việc</th>
                <th v-if="workGroupSpan" :colspan="workGroupSpan" class="matrix__th-block">
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
                <th v-if="resultGroupSpan" :colspan="resultGroupSpan" class="matrix__th-block">Kết quả</th>
              </tr>
              <tr class="matrix__leaves">
                <th v-if="scoreColOn('start_score')">{{ isWeightedTaskMode ? 'Điểm chuẩn' : 'Khởi đầu' }}</th>
                <th v-if="scoreColOn('task_adjustment')">{{ isWeightedTaskMode ? 'Hiệu suất (%)' : 'Từ việc' }}</th>
                <th v-for="item in shownCriteria" :key="item.id" class="matrix__th-score">
                  {{ item.name }}
                </th>
                <th v-if="scoreColOn('bonus')">Cộng</th>
                <th v-if="scoreColOn('penalty')">Trừ</th>
                <th v-if="scoreColOn('final_score')">{{ isWeightedTaskMode ? 'Hiệu suất cuối' : 'Cuối' }}</th>
                <th v-if="scoreColOn('classification')">Xếp loại</th>
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
              <tr
                v-for="row in sheetRows"
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
                <td v-if="scoreColOn('tasks')" class="matrix__td-tasks">
                  <span class="matrix__tasks-cell">
                    <span class="matrix__tasks">{{ doneOf(row) }}/{{ totalTasksOf(row) }} việc xong</span>
                    <span v-if="overdueOf(row) > 0 || rowGapCount(row) > 0" class="matrix__tasks-warn">
                      <span v-if="overdueOf(row) > 0" class="matrix__warn-item matrix__warn-item--danger">
                        <span class="matrix__warn-dot" />{{ overdueOf(row) }} trễ
                      </span>
                      <span v-if="rowGapCount(row) > 0" class="matrix__warn-item matrix__warn-item--gap">
                        <span class="matrix__warn-dot" />{{ rowGapCount(row) }} thiếu điểm
                      </span>
                    </span>
                  </span>
                </td>
                <td v-if="scoreColOn('start_score')">
                  <span class="matrix__chip">{{ formatNumber(taskBasisOf(row)) }}</span>
                </td>
                <td v-if="scoreColOn('task_adjustment')">
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
                        scoreModalOpen &&
                        selectedId === row.user_id &&
                        String(draft.criterion_id) === String(item.id),
                      'matrix__cell-btn--multi': (criterionTotal(row, item.id)?.count ?? 0) > 1,
                      'matrix__cell-btn--view': reportIsSaved,
                    }"
                    :aria-label="
                      reportIsSaved
                        ? `Chi tiết ${item.name} của ${row.user_name}`
                        : `Ghi nhận ${item.name} cho ${row.user_name}`
                    "
                    @click="openScore(row, item, $event)"
                  >
                    <template v-if="criterionTotal(row, item.id)">
                      <span :class="scoreChipClass(criterionTotal(row, item.id).score)">
                        {{ signedText(criterionTotal(row, item.id).score) }}
                      </span>
                      <span
                        v-if="criterionTotal(row, item.id).count > 1"
                        class="matrix__times"
                        aria-hidden="true"
                      >×{{ criterionTotal(row, item.id).count }}</span>
                      <span v-if="criterionTotal(row, item.id).count > 1" class="matrix__sr">
                        {{ criterionTotal(row, item.id).count }} lần
                      </span>
                    </template>
                    <span v-else class="matrix__dash">—</span>
                  </button>
                </td>
                <td v-if="scoreColOn('bonus')">
                  <span :class="scoreChipClass(scoreOf(row).bonus)">{{ signedText(scoreOf(row).bonus) }}</span>
                </td>
                <td v-if="scoreColOn('penalty')">
                  <span :class="scoreChipClass(scoreOf(row).penalty ? -scoreOf(row).penalty : 0)">
                    {{ scoreOf(row).penalty ? `-${formatNumber(scoreOf(row).penalty)}` : '0' }}
                  </span>
                </td>
                <td v-if="scoreColOn('final_score')">
                  <span class="matrix__chip matrix__chip--final">
                    {{ isWeightedTaskMode ? formatPercent(scoreOf(row).final) : formatNumber(scoreOf(row).final) }}
                  </span>
                </td>
                <td v-if="scoreColOn('classification')">
                  <span class="matrix__badge">{{ row.classification_label ?? '—' }}</span>
                </td>
              </tr>
            </tbody>

            <tfoot v-if="!loading && !loadError && sheetRows.length">
              <tr>
                <th class="matrix__pin">Phòng ban</th>
                <td v-if="scoreColOn('tasks')" class="matrix__td-tasks">{{ footSum(doneOf) }}/{{ footSum(totalTasksOf) }}</td>
                <td v-if="scoreColOn('start_score')">
                  <span class="matrix__chip">{{ formatNumber(footAvg(taskBasisOf)) }}</span>
                </td>
                <td v-if="scoreColOn('task_adjustment')">
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
                <td v-if="scoreColOn('bonus')">
                  <span :class="scoreChipClass(footAvg((row) => scoreOf(row).bonus))">
                    {{ signedText(footAvg((row) => scoreOf(row).bonus)) }}
                  </span>
                </td>
                <td v-if="scoreColOn('penalty')">
                  <span :class="scoreChipClass(footAvg((row) => (scoreOf(row).penalty ? -scoreOf(row).penalty : 0)))">
                    {{ signedText(footAvg((row) => (scoreOf(row).penalty ? -scoreOf(row).penalty : 0))) }}
                  </span>
                </td>
                <td v-if="scoreColOn('final_score')">
                  <span class="matrix__chip matrix__chip--final">
                    {{ isWeightedTaskMode ? formatPercent(footAvg((row) => scoreOf(row).final)) : formatNumber(footAvg((row) => scoreOf(row).final)) }}
                  </span>
                </td>
                <td v-if="scoreColOn('classification')">
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
          v-if="selected && !scoreModalOpen"
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

            <div
              class="matrix__person-strip"
              :class="stripClass(selected)"
              aria-label="Tóm tắt điểm trong kỳ"
            >
              <div class="matrix__person-strip-main">
                <div class="matrix__person-strip-score">
                  <span class="matrix__person-strip-label">
                    {{ isWeightedTaskMode ? 'Hiệu suất cuối' : 'Điểm cuối' }}
                  </span>
                  <span class="matrix__person-strip-value">
                    {{ isWeightedTaskMode ? formatPercent(scoreOf(selected).final) : formatNumber(scoreOf(selected).final) }}
                  </span>
                </div>
                <div class="matrix__person-strip-class">
                  <span class="matrix__person-strip-label">Xếp loại</span>
                  <span class="matrix__person-strip-value">
                    {{ selected.classification_label ?? 'Chưa xếp loại' }}
                  </span>
                </div>
                <div class="matrix__person-strip-meta">
                  <span>
                    <span class="matrix__person-strip-label">Việc</span>
                    <span class="matrix__person-strip-value">{{ doneOf(selected) }}/{{ totalTasksOf(selected) }}</span>
                  </span>
                  <span v-if="overdueOf(selected) > 0" class="matrix__person-strip-warn">
                    <span class="matrix__person-strip-label">Trễ hạn</span>
                    <span class="matrix__person-strip-value">{{ overdueOf(selected) }}</span>
                  </span>
                  <span v-if="rowGapCount(selected) > 0" class="matrix__person-strip-warn">
                    <span class="matrix__person-strip-label">Thiếu dữ liệu</span>
                    <span class="matrix__person-strip-value">{{ rowGapCount(selected) }}</span>
                  </span>
                  <span>
                    <span class="matrix__person-strip-label">So với TB phòng</span>
                    <span class="matrix__person-strip-value" :class="scoreClass(deltaVsAvg(selected))">
                      {{ signedText(deltaVsAvg(selected)) }}
                    </span>
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
            >
              <template v-if="detailTab === 'tasks'">
                <div v-if="detailTasks.length === 0" class="matrix__empty-card">
                  <AppIcon name="listChecks" :size="22" :stroke-width="1.75" />
                  <p>Không có việc nào trong kỳ này.</p>
                </div>
                <div v-else class="matrix__person-sheet">
                  <div class="matrix__person-sheet-head">
                    <span class="matrix__person-sheet-title">Công việc trong kỳ</span>
                    <span class="matrix__person-sheet-count">{{ detailTasks.length }} việc</span>
                  </div>
                  <div ref="personTableWrap" class="matrix__person-table-wrap hide-scrollbar">
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
                          <td class="matrix__person-table-date">{{ task.end_date ? formatDate(task.end_date) : 'chưa đặt' }}</td>
                          <td class="matrix__person-table-date">{{ task.actual_end_date ? formatDate(task.actual_end_date) : 'chưa xong' }}</td>
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
                </div>
              </template>

              <template v-else-if="detailTab === 'events'">
                <div v-if="detailEvents.length === 0" class="matrix__empty-card">
                  <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
                  <p>Chưa ghi nhận lần nào trong kỳ này.</p>
                  <button
                    v-if="!isPeriodLocked"
                    type="button"
                    class="matrix__dialog-btn matrix__dialog-btn--primary"
                    @click="goToScoreTab"
                  >
                    Chấm điểm
                  </button>
                </div>
                <div v-else class="matrix__person-sheet">
                  <div class="matrix__person-sheet-head">
                    <span class="matrix__person-sheet-title">Ghi nhận trong kỳ</span>
                    <span class="matrix__person-sheet-count">{{ detailEvents.length }} lần</span>
                  </div>
                  <div ref="personTableWrap" class="matrix__person-table-wrap hide-scrollbar">
                    <table class="matrix__person-table">
                      <thead>
                        <tr>
                          <th>Ngày</th>
                          <th>Tiêu chí</th>
                          <th>Mức</th>
                          <th class="matrix__person-table-num">Điểm</th>
                          <th>Việc gắn</th>
                          <th>Người ghi nhận</th>
                          <th>Lý do</th>
                          <th v-if="!isPeriodLocked" class="matrix__person-table-act" />
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="event in detailEvents"
                          :key="event.event_id"
                          :class="`matrix__person-table-row--${eventTone(event.score)}`"
                        >
                          <td class="matrix__person-table-date">{{ formatDate(event.occurred_at) }}</td>
                          <td class="matrix__person-table-title">{{ event.criterion_name }}</td>
                          <td>{{ event.level_label }}</td>
                          <td class="matrix__person-table-num">
                            <span :class="scoreChipClass(event.score)">{{ signedText(event.score) }}</span>
                          </td>
                          <td>{{ taskTitleOf(event.task_id) || '—' }}</td>
                          <td>{{ event.recorded_by_name || '—' }}</td>
                          <td class="matrix__person-table-reason">{{ event.reason || '—' }}</td>
                          <td v-if="!isPeriodLocked" class="matrix__person-table-act">
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
                </div>
              </template>
            </div>

            <div class="matrix__dialog-actions">
              <button
                type="button"
                class="matrix__dialog-btn matrix__dialog-btn--ghost"
                :disabled="saving"
                @click="closeDetail"
              >
                Đóng
              </button>
              <button
                v-if="!isPeriodLocked"
                type="button"
                class="matrix__dialog-btn matrix__dialog-btn--primary"
                :disabled="saving"
                @click="goToScoreTab"
              >
                Ghi nhận đánh giá
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="matrix-dialog-fade">
        <div
          v-if="scoreModalOpen && selected"
          class="matrix__dialog matrix__dialog--score"
          role="presentation"
          @mousedown.self="closeScoreModal"
        >
          <div
            class="matrix__dialog-panel matrix__dialog-panel--score"
            role="dialog"
            aria-modal="true"
            aria-labelledby="matrix-score-title"
            @mousedown.stop
          >
            <div class="matrix__dialog-head">
              <span class="matrix__dialog-icon" aria-hidden="true">
                <AppIcon
                  :name="scoreModalTab === 'history' ? 'clipboardCheck' : 'pencil'"
                  :size="22"
                  :stroke-width="1.75"
                />
              </span>
              <div class="matrix__dialog-head-copy">
                <h2 id="matrix-score-title" class="matrix__dialog-title">Chấm điểm</h2>
                <div class="matrix__dialog-head-meta">
                  <p class="matrix__dialog-head-person">{{ selected.user_name }}</p>
                  <span v-if="scoreCriterion" class="matrix__dialog-head-criterion">
                    {{ scoreCriterion.name }}
                  </span>
                </div>
              </div>
              <button
                type="button"
                class="matrix__dialog-close"
                aria-label="Đóng"
                :disabled="saving"
                @click="closeScoreModal"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div
              class="matrix__dialog-tabs"
              role="tablist"
              aria-label="Ghi nhận hoặc các lần đã ghi"
            >
              <button
                type="button"
                class="matrix__dialog-tab"
                :class="{ 'matrix__dialog-tab--active': scoreModalTab === 'record' }"
                role="tab"
                :aria-selected="scoreModalTab === 'record' ? 'true' : 'false'"
                :disabled="saving"
                @click="scoreModalTab = 'record'"
              >
                Ghi nhận mới
              </button>
              <button
                type="button"
                class="matrix__dialog-tab"
                :class="{ 'matrix__dialog-tab--active': scoreModalTab === 'history' }"
                role="tab"
                :aria-selected="scoreModalTab === 'history' ? 'true' : 'false'"
                :disabled="saving"
                @click="scoreModalTab = 'history'"
              >
                Đã ghi tiêu chí này
                <span v-if="scoreExisting.length" class="matrix__dialog-tab-badge">
                  {{ scoreExisting.length }}
                </span>
              </button>
            </div>

            <form
              id="matrix-score-form"
              class="matrix__dialog-body matrix__dialog-body--score-modal hide-scrollbar"
              @submit.prevent="record"
            >
              <div v-if="formLocked && scoreModalTab === 'record'" class="matrix__lock matrix__lock--in" role="status">
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
              <p v-else-if="criteria.length === 0 && scoreModalTab === 'record'" class="matrix__person-empty">
                Phòng ban chưa có tiêu chí để ghi nhận.
              </p>
              <template v-else-if="scoreModalTab === 'record'">
                <div class="matrix__score-grid">
                  <div class="matrix__field matrix__field--wide">
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
                  <div class="matrix__field matrix__field--wide">
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
                    <textarea
                      id="matrix-reason"
                      v-model="draft.reason"
                      class="matrix__input matrix__reason-input"
                      rows="3"
                      maxlength="500"
                      placeholder="Gõ lý do (tuỳ chọn)…"
                      :disabled="saving || formLocked"
                      spellcheck="false"
                    />
                  </div>
                </div>
              </template>
              <div v-else class="matrix__person-score-side-body hide-scrollbar">
                <div v-if="!draft.criterion_id" class="matrix__person-score-empty">
                  <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
                  <p>Chọn tiêu chí ở tab Ghi nhận mới để xem các lần đã ghi.</p>
                </div>
                <div v-else-if="scoreExisting.length === 0" class="matrix__person-score-empty">
                  <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
                  <p>Chưa ghi nhận tiêu chí này trong kỳ.</p>
                </div>
                <ul v-else class="matrix__person-score-list">
                  <li
                    v-for="event in scoreExisting"
                    :key="event.event_id"
                    class="matrix__person-score-item"
                    :class="`matrix__person-score-item--${eventTone(event.score)}`"
                  >
                    <div class="matrix__person-score-item-head">
                      <div class="matrix__event-head">
                        <span class="matrix__card-title">{{ event.level_label }}</span>
                        <span :class="scoreChipClass(event.score)">{{ signedText(event.score) }}</span>
                      </div>
                      <button
                        v-if="!isDateLocked(event.occurred_at)"
                        type="button"
                        class="matrix__remove-btn"
                        aria-label="Xoá ghi nhận"
                        @click="confirmRemoveId = event.event_id"
                      >
                        <AppIcon name="trash2" :size="14" :stroke-width="1.75" />
                        <span>Xoá</span>
                      </button>
                    </div>
                    <p class="matrix__card-meta">
                      {{ formatDate(event.occurred_at) }}
                      <span v-if="taskTitleOf(event.task_id)"> · {{ taskTitleOf(event.task_id) }}</span>
                    </p>
                    <dl class="matrix__person-score-details">
                      <div v-if="event.recorded_by_name" class="matrix__person-score-detail">
                        <dt>Người ghi nhận</dt>
                        <dd>{{ event.recorded_by_name }}</dd>
                      </div>
                      <div v-if="event.reason" class="matrix__person-score-detail matrix__person-score-detail--wide">
                        <dt>Lý do</dt>
                        <dd>{{ event.reason }}</dd>
                      </div>
                    </dl>
                  </li>
                </ul>
              </div>
            </form>

            <div class="matrix__dialog-actions">
              <button
                type="button"
                class="matrix__dialog-btn matrix__dialog-btn--ghost"
                :disabled="saving"
                @click="closeScoreModal"
              >
                Huỷ
              </button>
              <button
                v-if="!formLocked && scoreModalTab === 'record'"
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
            class="matrix__dialog-panel matrix__dialog-panel--criteria"
            role="dialog"
            aria-modal="true"
            aria-labelledby="matrix-criteria-title"
            aria-describedby="matrix-criteria-sub"
            @mousedown.stop
          >
            <div class="matrix__dialog-head">
              <span class="matrix__dialog-icon" aria-hidden="true">
                <AppIcon name="columns" :size="22" :stroke-width="1.75" />
              </span>
              <div class="matrix__dialog-head-copy">
                <h2 id="matrix-criteria-title" class="matrix__dialog-title">Cột tiêu chí</h2>
                <p id="matrix-criteria-sub" class="matrix__dialog-sub">
                  {{
                    boundDisplay
                      ? `Đang theo báo cáo ${revisionHeadline || '1.0'}. Đổi cột rồi đóng hộp thoại sẽ lưu thành phụ lục ${nextAppendixLabel()}.`
                      : 'Hiện hoặc ẩn cột trên bảng. Bấm tên nhóm để chọn cả nhóm.'
                  }}
                </p>
              </div>
              <button type="button" class="matrix__dialog-close" aria-label="Đóng" @click="closeCriteriaDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div v-if="criteria.length > 0" class="matrix__criteria-toolbar">
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
              <p class="matrix__criteria-meta">
                Đang hiện {{ shownCriteriaCount }}/{{ criteria.length }}
              </p>
            </div>
            <p v-if="boundDisplay?.revisions?.length" class="matrix__criteria-history">
              Lịch sử:
              <span
                v-for="item in boundDisplay.revisions"
                :key="item.revision"
                class="matrix__criteria-rev"
              >
                {{ item.revision }}{{ item.kind === 'appendix' ? ' · phụ lục' : '' }}
              </span>
            </p>

            <div class="matrix__dialog-body hide-scrollbar">
              <p v-if="criteria.length === 0" class="matrix__dialog-empty">
                Phòng ban chưa có tiêu chí cộng / trừ điểm nào.
              </p>
              <p v-else-if="filteredPickerGroups.length === 0" class="matrix__dialog-empty">
                Không có tiêu chí khớp «{{ criteriaPickerQuery }}».
              </p>
              <div v-else class="matrix__dialog-grid">
                <section
                  v-for="group in filteredPickerGroups"
                  :key="group.label"
                  class="matrix__dialog-group"
                  :class="pickerGroupClass(group)"
                >
                  <button
                    type="button"
                    class="matrix__dialog-group-head"
                    :aria-label="`Hiện hoặc ẩn cả nhóm ${group.label}`"
                    @click="toggleGroupCriteria(group.items)"
                  >
                    <span class="matrix__dialog-group-title">{{ group.label }}</span>
                    <span class="matrix__dialog-group-count">
                      {{ groupShownCount(group.items) }}/{{ group.items.length }}
                    </span>
                  </button>
                  <div class="matrix__dialog-group-list hide-scrollbar">
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
                  </div>
                </section>
              </div>
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
      :open="appendixConfirmOpen"
      :title="`Lưu thành phụ lục ${nextAppendixLabel()}?`"
      description="Bản gốc giữ nguyên cột tiêu chí lúc tạo. Phụ lục chỉ đổi những cột đang hiện trên bảng."
      confirm-label="Lưu phụ lục"
      :loading="savingDisplay"
      @update:open="onAppendixOpen"
      @confirm="confirmAppendix"
    />
    <ConfirmDialog
      :open="saveConfirmOpen"
      title="Lưu báo cáo?"
      description="Sau khi lưu, kỳ báo cáo sẽ bị khoá — không ghi nhận hay xoá điểm trong kỳ này nữa. Danh sách nhân sự trong phạm vi được chụp lại đúng lúc lưu."
      confirm-label="Lưu báo cáo"
      :loading="savingReport"
      @update:open="onSaveConfirmOpen"
      @confirm="confirmSaveReport"
    />
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

.matrix__header-btn--primary {
  background: var(--color-secondary);
  color: var(--color-on-secondary);
  box-shadow: none;
}

.matrix__header-btn--primary:hover:not(:disabled) {
  background: var(--color-secondary-hover);
}

.matrix__status {
  display: inline-flex;
  align-items: center;
  height: 2rem;
  padding: 0 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__status--saved {
  background: color-mix(in srgb, var(--color-success) 8%, var(--color-surface));
  color: var(--color-success);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-success) 35%, var(--color-border));
}

.matrix__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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

.matrix__picker-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
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

.matrix__td-tasks {
  padding-inline: 0.5rem;
}

.matrix__tasks-cell {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  max-width: 100%;
}

.matrix__tasks {
  font-weight: 600;
  white-space: nowrap;
}

.matrix__tasks-warn {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.matrix__warn-item {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.3125rem;
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.2;
  white-space: nowrap;
}

.matrix__warn-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.matrix__warn-item--danger {
  color: var(--color-danger);
}

.matrix__warn-item--danger .matrix__warn-dot {
  background: var(--color-danger);
}

.matrix__warn-item--gap {
  color: var(--color-warning-tint-fg);
}

.matrix__warn-item--gap .matrix__warn-dot {
  background: var(--color-warning);
}

.matrix__chip {
  display: inline-flex;
  flex-shrink: 0;
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
  white-space: nowrap;
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
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 1.375rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: 999px;
  background: var(--color-gold-100);
  color: var(--color-gold-800);
  font-size: 0.625rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
  line-height: 1;
  white-space: nowrap;
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
  display: inline-flex;
  flex-direction: row;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  width: 100%;
  height: var(--matrix-leaf-h);
  padding: 0 0.25rem;
  border: none;
  background: transparent;
  color: inherit;
  font: inherit;
  white-space: nowrap;
  cursor: pointer;
}

.matrix__cell-btn--multi {
  gap: 0.1875rem;
}

.matrix__cell-btn:hover,
.matrix__cell-btn--on {
  background: color-mix(in srgb, var(--color-secondary) 10%, transparent);
}

.matrix--view .matrix__cell-btn {
  cursor: default;
}

.matrix--view .matrix__cell-btn:hover,
.matrix--view .matrix__cell-btn--on {
  background: color-mix(in srgb, var(--color-primary) 6%, transparent);
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
  height: 0.25rem;
  overflow: hidden;
  border-radius: 0;
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

.matrix__person-sheet {
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

.matrix__person-sheet-head {
  display: flex;
  flex-shrink: 0;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.75rem 1rem 0.625rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__person-sheet-title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.matrix__person-sheet-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.matrix__person-table-wrap {
  min-width: 0;
  flex: 1;
  overflow: auto;
}

.matrix__person-table {
  width: 100%;
  min-width: max-content;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.matrix__person-table th {
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

.matrix__person-table td {
  position: relative;
  padding: 0.6875rem 0.875rem;
  color: var(--color-text);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__person-table tbody tr:hover td {
  background: var(--color-surface-muted);
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
  min-width: 11rem;
  max-width: 20rem;
  font-weight: 600;
}

.matrix__person-table-sub {
  display: block;
  margin-top: 0.125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.matrix__person-table-date {
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.matrix__person-table-reason {
  max-width: 16rem;
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

.matrix__person-table-row--danger td:first-child,
.matrix__person-table-row--gap td:first-child,
.matrix__person-table-row--ok td:first-child {
  padding-left: calc(0.875rem + 3px + var(--space-2));
}

.matrix__person-table-row--danger td:first-child::before,
.matrix__person-table-row--gap td:first-child::before,
.matrix__person-table-row--ok td:first-child::before {
  content: '';
  position: absolute;
  top: 0.5rem;
  bottom: 0.5rem;
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
}

.matrix__person-table-row--danger td:first-child::before {
  background: var(--color-danger);
}

.matrix__person-table-row--gap td:first-child::before {
  background: var(--color-warning);
}

.matrix__person-table-row--ok td:first-child::before {
  background: var(--color-success);
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
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
}

.matrix__score-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
  min-width: 0;
}

.matrix__score-grid .matrix__field {
  min-width: 0;
}

.matrix__score-grid .matrix__field--wide {
  grid-column: span 2;
}

.matrix__score-grid .matrix__field--full {
  grid-column: 1 / -1;
}

.matrix__score-grid .opt-picker {
  width: 100%;
}

.matrix__reason-input {
  min-height: 5.25rem;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.45;
  resize: none;
}

.matrix__reason-input:focus {
  outline: none;
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.matrix__reason-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.matrix__person-score-side-body {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  overflow: auto;
}

.matrix__dialog-panel--score .matrix__person-score-side-body:has(.matrix__person-score-list) {
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__person-score-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  color: var(--color-text-muted);
  text-align: center;
}

.matrix__dialog-panel--score .matrix__person-score-empty {
  min-height: 9rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.matrix__person-score-empty p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
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

.matrix__person-score-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.matrix__person-score-item {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
  transition:
    box-shadow 0.15s ease,
    background 0.15s ease;
}

.matrix__person-score-item--ok {
  background: color-mix(in srgb, var(--color-success) 5%, var(--color-surface));
}

.matrix__person-score-item--danger {
  background: color-mix(in srgb, var(--color-danger) 5%, var(--color-surface));
}

.matrix__person-score-item:hover {
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-border) 55%, var(--color-text-muted)),
    var(--shadow-md);
}

.matrix__person-score-item::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.matrix__person-score-item--ok::before {
  background: var(--color-success);
}

.matrix__person-score-item--danger::before {
  background: var(--color-danger);
}

.matrix__person-score-item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.matrix__person-score-item-head .matrix__event-head {
  flex: 1;
  min-width: 0;
  align-items: center;
  justify-content: flex-start;
  flex-wrap: wrap;
  gap: var(--space-2) var(--space-3);
}

.matrix__person-score-item .matrix__card-title {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
}

.matrix__person-score-item .matrix__chip {
  font-size: 0.8125rem;
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

.matrix__person-score-item .matrix__card-meta {
  margin: 0;
  padding-top: var(--space-2);
  box-shadow: 0 -1px 0 color-mix(in srgb, var(--color-border) 65%, transparent);
  font-size: 0.6875rem;
  letter-spacing: 0.01em;
}

.matrix__person-score-details {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
  margin: 0;
}

.matrix__person-score-detail {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
  margin: 0;
}

.matrix__person-score-detail--wide {
  grid-column: 1 / -1;
}

.matrix__person-score-detail dt {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1.3;
}

.matrix__person-score-detail dt::after {
  content: ':';
}

.matrix__person-score-detail dd {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-style: italic;
  font-weight: 400;
  line-height: 1.45;
  overflow-wrap: anywhere;
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

.matrix__dialog-panel--criteria {
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  padding: 1.25rem 1.5rem 1rem;
}

.matrix__dialog-panel--criteria .matrix__dialog-head {
  align-items: flex-start;
  gap: var(--space-3);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__dialog-panel--criteria .matrix__dialog-icon {
  margin-top: 0.125rem;
}

.matrix__dialog-panel--criteria .matrix__dialog-close {
  margin-top: 0.125rem;
}

.matrix__dialog-panel--criteria .matrix__dialog-title {
  letter-spacing: -0.02em;
}

.matrix__dialog-sub {
  margin: 0.25rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.4;
}

.matrix__criteria-toolbar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-3);
}

.matrix__dialog-panel--criteria .matrix__dialog-body {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  margin: var(--space-3) 0;
  padding: var(--space-3);
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.matrix__dialog-panel--criteria .matrix__dialog-actions {
  padding-top: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-border);
}

.matrix__dialog-panel--criteria .hide-scrollbar {
  cursor: default;
}

.matrix__dialog-panel--person {
  width: min(72rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  padding: 1.25rem 1.5rem 1rem;
}

.matrix__dialog--score {
  z-index: 310;
}

.matrix__dialog-panel--score {
  width: min(46rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: calc(100vh - 2.5rem);
  padding: 1.25rem 1.5rem 1rem;
  overflow: hidden;
}

.matrix__dialog-panel--score .matrix__dialog-head {
  align-items: flex-start;
  padding-bottom: var(--space-2);
  box-shadow: none;
}

.matrix__dialog-panel--score .matrix__dialog-icon {
  margin-top: 0.125rem;
}

.matrix__dialog-panel--score .matrix__dialog-close {
  margin-top: 0.5rem;
}

.matrix__dialog-panel--score .matrix__dialog-title {
  letter-spacing: -0.02em;
}

.matrix__dialog-panel--score .matrix__dialog-body {
  display: flex;
  flex: 0 1 auto;
  min-height: 0;
  flex-direction: column;
  gap: var(--space-3);
  margin: var(--space-3) 0;
  overflow: hidden;
}

.matrix__dialog-panel--score .matrix__score-grid,
.matrix__dialog-panel--score .matrix__person-score-side-body {
  flex: 0 1 auto;
  min-height: 0;
}

.matrix__dialog-panel--score .matrix__person-score-side-body {
  max-height: min(26rem, calc(100vh - 20rem));
  overflow-y: auto;
}

.matrix__dialog-panel--score .matrix__person-score-list {
  gap: var(--space-3);
}

.matrix__dialog-panel--score .matrix__dialog-actions {
  padding-top: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-border);
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
  position: relative;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-sm);
}

.matrix__person-strip::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.matrix__person-strip--success::before {
  background: var(--color-success);
}

.matrix__person-strip--danger::before {
  background: var(--color-danger);
}

.matrix__person-strip--info::before {
  background: var(--color-info);
}

.matrix__person-strip-main {
  display: grid;
  grid-template-columns: minmax(7.5rem, auto) minmax(8.5rem, auto) minmax(0, 1fr);
  align-items: stretch;
  min-width: 0;
}

.matrix__person-strip-score,
.matrix__person-strip-class,
.matrix__person-strip-meta > span {
  display: flex;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  gap: 0.125rem;
}

.matrix__person-strip-class,
.matrix__person-strip-meta {
  padding-left: var(--space-4);
  box-shadow: -1px 0 0 var(--color-border);
}

.matrix__person-strip-score .matrix__person-strip-value {
  font-size: 1.5rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.matrix__person-strip-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.matrix__person-strip-value {
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  line-height: 1.25;
}

.matrix__person-strip-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  min-width: 0;
}

.matrix__person-strip-meta > span {
  padding: 0 var(--space-4);
}

.matrix__person-strip-meta > span:first-child {
  padding-left: 0;
}

.matrix__person-strip-meta > span + span {
  box-shadow: -1px 0 0 var(--color-border);
}

.matrix__person-strip-warn .matrix__person-strip-label,
.matrix__person-strip-warn .matrix__person-strip-value {
  color: var(--color-danger);
}

.matrix__stack--strip {
  height: 0.25rem;
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

.matrix__dialog-head-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.625rem;
  min-width: 0;
  margin-top: 0.25rem;
}

.matrix__dialog-head-person {
  margin: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__dialog-head-criterion {
  display: inline-flex;
  max-width: min(100%, 28rem);
  overflow: hidden;
  padding: 0.125rem 0.5625rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__dialog-head,
.matrix__person-strip,
.matrix__person-tabs,
.matrix__dialog-tabs,
.matrix__criteria-toolbar,
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
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
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

.matrix__dialog-tab-badge {
  display: inline-flex;
  min-width: 1.125rem;
  align-items: center;
  justify-content: center;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
  color: var(--color-primary);
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
}

.matrix__dialog-tab--active .matrix__dialog-tab-badge {
  background: color-mix(in srgb, var(--color-primary) 14%, transparent);
}

.matrix__dialog-body--person {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.matrix__dialog-body--person .matrix__person-sheet {
  flex: 1;
  min-height: 0;
}

.matrix__dialog-body--person .matrix__tab-section {
  flex: 1;
  min-height: 0;
}

.matrix__dialog-body--score {
  padding-bottom: var(--space-2);
}

.matrix__criteria-search {
  flex: 1;
  min-width: 0;
}

.matrix__criteria-search-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.matrix__criteria-search-field:focus-within {
  background: var(--color-surface);
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
  background: var(--color-surface);
  color: var(--color-text);
}

.matrix__criteria-meta {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.matrix__criteria-history {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.75rem;
  margin: 0;
  padding: 0 var(--space-5) var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.matrix__criteria-rev {
  color: var(--color-text);
  font-weight: 600;
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
  margin: auto;
  padding: var(--space-5) var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-style: italic;
  text-align: center;
}

.matrix__dialog-grid {
  display: grid;
  flex: 1;
  min-height: 0;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-auto-rows: minmax(0, 1fr);
  gap: var(--space-3);
  align-content: stretch;
}

.matrix__dialog-group {
  position: relative;
  display: flex;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.matrix__dialog-group::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  z-index: 1;
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.matrix__dialog-group--ok::before {
  background: var(--color-secondary);
}

.matrix__dialog-group--cut::before {
  background: var(--color-danger);
}

.matrix__dialog-group--mixed::before {
  background: var(--color-gold);
}

.matrix__dialog-group-head {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  width: 100%;
  padding: 0.75rem 0.75rem 0.75rem calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
  text-align: left;
  cursor: pointer;
  box-shadow: 0 1px 0 var(--color-border);
}

.matrix__dialog-group-head:hover {
  background: color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
}

.matrix__dialog-group-head:focus-visible {
  background: color-mix(in srgb, var(--color-primary) 5%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border), inset 0 0 0 1.5px var(--color-primary);
}

.matrix__dialog-group-title {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.matrix__dialog-group-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 2.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.matrix__dialog-group--all .matrix__dialog-group-count {
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface));
  color: var(--color-secondary);
}

.matrix__dialog-group-list {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-1);
  overflow: auto;
}

.matrix__dialog-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem 0.5rem calc(var(--space-2) + 3px + var(--space-2));
  border-radius: calc(var(--radius-md) - 2px);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
  cursor: pointer;
}

.matrix__dialog-item:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.matrix__dialog-item:has(:focus-visible) {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.matrix__dialog-item--on {
  background: color-mix(in srgb, var(--color-secondary) 8%, var(--color-surface));
  color: var(--color-text);
}

.matrix__dialog-item--on:hover {
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface));
}

.matrix__dialog-tick {
  flex-shrink: 0;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.0625rem;
  border-radius: 0.25rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1.5px var(--color-border);
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
  margin: 0.125rem auto 0;
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
    gap: var(--space-3) 0;
  }

  .matrix__person-strip-meta {
    grid-column: 1 / -1;
    padding-left: 0;
    padding-top: var(--space-3);
    box-shadow: 0 -1px 0 var(--color-border);
  }

  .matrix__score-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .matrix__score-grid .matrix__field--wide {
    grid-column: 1 / -1;
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
    grid-auto-rows: minmax(12rem, 1fr);
  }
}

@media (max-width: 640px) {
  .matrix__person-strip-main {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-3) 0;
  }

  .matrix__person-strip-class {
    padding-left: 0;
    padding-top: var(--space-3);
    box-shadow: 0 -1px 0 var(--color-border);
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

  .matrix__dialog-panel--criteria .matrix__dialog-body {
    overflow: auto;
  }

  .matrix__dialog-grid {
    flex: none;
    grid-auto-rows: auto;
    align-content: start;
  }

  .matrix__dialog-group {
    max-height: 20rem;
  }

  .matrix__criteria-toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: var(--space-2);
  }

  .matrix__criteria-meta {
    text-align: right;
  }

  .matrix__dialog-panel--criteria {
    width: calc(100vw - 2.5rem);
    height: calc(100vh - 2.5rem);
  }

  .matrix__score-grid .matrix__field--wide {
    grid-column: 1 / -1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .matrix-dialog-fade-enter-active,
  .matrix-dialog-fade-leave-active,
  .matrix__spin {
    transition: none;
    animation: none;
  }
}
</style>
