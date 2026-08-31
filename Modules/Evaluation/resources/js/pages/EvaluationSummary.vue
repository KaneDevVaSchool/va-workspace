<script setup>
/**
 * Tổng hợp đánh giá — màn hình làm việc chính khi chấm điểm cuối kỳ.
 *
 * CỐ Ý KHÔNG theo mẫu bảng dữ liệu (.cursor/rules/data-table.mdc): trang này
 * không phải danh sách bản ghi để tra cứu, mà là chỗ nhìn cả phòng ban rồi
 * chấm điểm từng người. Bảng 30 cột kéo ngang trả lời sai câu hỏi người dùng
 * đang hỏi ("ai đang yếu, vì sao"), nên ở đây dùng:
 *
 *   - Danh sách người, mỗi người một dòng, số cột cố định và luôn vừa khung.
 *   - Ngăn chi tiết bên phải cho từng người (công việc + ghi nhận), thay cho
 *     dòng bung ra giữa bảng.
 *   - Không kéo cột, không kéo ngang, không thanh cuộn ngang ở đâu cả.
 */
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import {
  SUMMARY_PERIOD_KEY,
  TASK_STATUS_LABELS,
  TIMELINESS_LABELS,
  TIMELINESS_TONES,
} from '@modules/Report/resources/js/constants/report.js';

const rows = ref([]);
const criteria = ref([]);
const summary = ref(null);
const versionNo = ref(null);
const loading = ref(false);
const loadError = ref('');

/* ---------- Kỳ đánh giá ---------- */

function todayISO() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

function isoOf(date) {
  const offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

/** Ngày cuối của tháng dạng "2026-08". */
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

/**
 * Kỳ đang xem — luôn là một khoảng ngày.
 *
 * Người dùng chọn nhanh bằng nút (tháng này / tháng trước / quý này), hoặc mở
 * phần "Chọn khoảng khác" để gõ tay. Máy chủ chỉ nhận từ ngày / đến ngày.
 */
const periodFrom = ref('');
const periodTo = ref('');
const periodName = ref('');
const customOpen = ref(false);

/** Các kỳ bấm một nút là xong — phủ gần hết nhu cầu thật. */
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

/** Kỳ nhanh nào đang trùng khoảng đang xem — để tô nút đang chọn. */
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

const periodLabel = computed(() => {
  if (!period.value) return '';
  const range = `${formatDate(period.value.from)} – ${formatDate(period.value.to)}`;
  return activeQuickKey.value ? `${periodName.value} (${range})` : range;
});

const periodInvalid = computed(
  () => Boolean(periodFrom.value && periodTo.value && periodFrom.value > periodTo.value),
);

/* ---------- Lọc và sắp xếp ---------- */

const query = ref('');
const sortKey = ref('final_score');
const sortDir = ref('desc');

/** Lọc nhanh theo tình trạng — trả lời thẳng "ai cần để mắt tới". */
const focus = ref('all');

const FOCUS_OPTIONS = [
  { key: 'all', label: 'Tất cả' },
  { key: 'overdue', label: 'Có việc quá hạn' },
  { key: 'unrated', label: 'Chưa ghi nhận lần nào' },
  { key: 'no_task', label: 'Không có việc trong kỳ' },
];

function overdueOf(row) {
  return row.task_status_counts?.by_timeliness?.overdue ?? 0;
}

function onTimeOf(row) {
  return row.task_status_counts?.by_timeliness?.on_time ?? 0;
}

function doneOf(row) {
  return row.task_status_counts?.by_status?.completed ?? 0;
}

function totalTasksOf(row) {
  return row.task_status_counts?.total ?? 0;
}

/** Một người có thuộc nhóm lọc `key` không — nhận key rời để đếm được cả các
 * nhóm đang KHÔNG chọn, phục vụ con số cạnh mỗi nút lọc. */
function matchesFocusKey(row, key) {
  if (key === 'overdue') return overdueOf(row) > 0;
  if (key === 'unrated') return (row.event_count ?? 0) === 0;
  if (key === 'no_task') return totalTasksOf(row) === 0;
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
    if (needle && !String(row.user_name ?? '').toLowerCase().includes(needle)) return false;
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
    return ((Number(a[sortKey.value]) || 0) - (Number(b[sortKey.value]) || 0)) * dir;
  });
});

/** Cột xếp được — bấm lại cột đang xếp thì đảo chiều. */
const SORT_OPTIONS = [
  { key: 'final_score', label: 'Điểm cuối' },
  { key: 'user_name', label: 'Tên nhân sự' },
  { key: 'tasks', label: 'Số công việc' },
  { key: 'overdue', label: 'Việc quá hạn' },
];

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    return;
  }
  sortKey.value = key;
  sortDir.value = key === 'user_name' ? 'asc' : 'desc';
}

const filtersActive = computed(() => Boolean(query.value) || focus.value !== 'all');

function clearFilters() {
  query.value = '';
  focus.value = 'all';
}

/* ---------- Hiển thị số ---------- */

function formatNumber(value) {
  const num = Number(value) || 0;
  return Number.isInteger(num) ? String(num) : num.toFixed(2).replace(/\.?0+$/, '');
}

function signedText(value) {
  const num = Number(value) || 0;
  if (num === 0) return '0';
  return num > 0 ? `+${formatNumber(num)}` : formatNumber(num);
}

/** Tỉ lệ hoàn thành của một người — thanh nhỏ trên dòng cho thấy ngay khối lượng. */
function donePercent(row) {
  const total = totalTasksOf(row);
  if (total === 0) return 0;
  return Math.round((doneOf(row) / total) * 100);
}

/**
 * Vị trí điểm của một người trên thang điểm cả phòng ban — dùng vẽ thanh so
 * sánh. Cả phòng bằng điểm nhau thì vẽ đầy, không chia cho 0.
 */
function scorePercent(row) {
  const scores = rows.value.map((item) => Number(item.final_score) || 0);
  if (scores.length === 0) return 0;
  const max = Math.max(...scores, 0);
  const min = Math.min(...scores, 0);
  if (max === min) return 100;
  return Math.round(((Number(row.final_score) || 0) - min) / (max - min) * 100);
}

/* ---------- Phân bố xếp loại ---------- */

/** Các mức xếp loại có người đạt — mức trống không chiếm chỗ trên thanh. */
const distribution = computed(() =>
  (summary.value?.distribution ?? []).filter((item) => item.count > 0),
);

function distributionPercent(item) {
  const total = summary.value?.total_people ?? 0;
  if (!total) return 0;
  return Math.round((item.count / total) * 100);
}

/* ---------- Tải dữ liệu ---------- */

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

/* ---------- Ngăn chi tiết một nhân sự ---------- */

const selectedId = ref(null);

const selected = computed(
  () => rows.value.find((row) => row.user_id === selectedId.value) ?? null,
);

/** Bấm một lần để xem — không đổi dữ liệu gì (quy tắc 14). */
function select(row) {
  selectedId.value = row.user_id;
  detailTab.value = 'tasks';
}

function closeDetail() {
  selectedId.value = null;
  resetDraft();
}

/** Chuyển sang người kế tiếp / trước đó ngay trong ngăn chi tiết. */
function step(offset) {
  const list = filteredRows.value;
  const index = list.findIndex((row) => row.user_id === selectedId.value);
  const next = list[index + offset];
  if (next) select(next);
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

/** Công việc của người đang mở, xếp việc quá hạn lên trước cho dễ xử lý. */
const detailTasks = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.task_breakdown ?? [])].sort((a, b) => {
    const weight = (task) => (task.on_time_state === 'overdue' ? 0 : 1);
    if (weight(a) !== weight(b)) return weight(a) - weight(b);
    return String(a.end_date ?? '').localeCompare(String(b.end_date ?? ''));
  });
});

/** Toàn bộ ghi nhận của người đang mở, mới nhất lên trước. */
const detailEvents = computed(() => {
  const row = selected.value;
  if (!row) return [];
  return [...(row.event_breakdown ?? [])].sort((a, b) =>
    String(b.occurred_at ?? '').localeCompare(String(a.occurred_at ?? '')),
  );
});

function taskTitleOf(taskId) {
  if (!taskId) return '';
  return (
    (selected.value?.task_breakdown ?? []).find((task) => task.task_id === taskId)?.title ?? ''
  );
}

/* ---------- Ghi nhận đánh giá ---------- */

/**
 * Form ghi nhận — một form duy nhất trong ngăn chi tiết, gắn công việc là tuỳ
 * chọn. Trước đây mỗi dòng công việc có một cặp select riêng, nhìn rất rối và
 * người dùng không biết cái nào ăn vào đâu.
 */
const draft = reactive({ criterion_id: '', level_code: '', task_id: '', reason: '' });
const saving = ref(false);
const removingId = ref(null);

function resetDraft() {
  draft.criterion_id = '';
  draft.level_code = '';
  draft.task_id = '';
  draft.reason = '';
}

const selectedCriterion = computed(
  () => criteria.value.find((item) => String(item.id) === String(draft.criterion_id)) ?? null,
);

const draftLevels = computed(() => selectedCriterion.value?.levels ?? []);

const draftLevel = computed(
  () => draftLevels.value.find((level) => level.code === draft.level_code) ?? null,
);

const canRecord = computed(
  () => Boolean(draft.criterion_id && draft.level_code) && !saving.value,
);

function onCriterionChange() {
  draft.level_code = '';
}

/** Mở sẵn form ghi nhận cho đúng công việc vừa bấm. */
function recordForTask(task) {
  detailTab.value = 'record';
  draft.task_id = String(task.task_id);
  nextTick(() => criterionField.value?.focus());
}

const criterionField = ref(null);

/**
 * Ghi nhận một mức tiêu chí cho nhân sự đang mở.
 *
 * Máy chủ trả về dòng đã tính lại — thay nguyên dòng đó vào danh sách. KHÔNG
 * tự cộng điểm ở đây: điểm cuối còn phụ thuộc khung chấm điểm, công việc và
 * xếp loại, tự cộng ở trình duyệt sẽ sớm lệch với máy chủ.
 */
async function record() {
  const row = selected.value;
  if (!row || !canRecord.value || !period.value) return;

  saving.value = true;
  try {
    const { data } = await window.axios.post('/api/evaluation/events', {
      user_id: row.user_id,
      criterion_id: draft.criterion_id,
      level_code: draft.level_code,
      occurred_at: period.value.to,
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

/** Thay đúng một dòng bằng số liệu máy chủ vừa tính lại. */
function applyRow(fresh) {
  const index = rows.value.findIndex((row) => row.user_id === fresh.user_id);
  if (index >= 0) rows.value[index] = fresh;
}

const confirmRemoveId = ref(null);

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

/* ---------- Vòng đời ---------- */

watch(period, () => loadSummary());

watch(
  () => [periodFrom.value, periodTo.value],
  () => {
    if (!activeQuickKey.value) periodName.value = '';
  },
);

onMounted(() => {
  // Kỳ mở sẵn: tháng này, hoặc kỳ đã chọn lần trước nếu còn hợp lệ.
  const saved = localStorage.getItem(SUMMARY_PERIOD_KEY);
  const match = quickPeriods.value.find((item) => item.key === saved);
  applyQuickPeriod(match ?? quickPeriods.value[0]);
});

watch(activeQuickKey, (value) => {
  try {
    if (value) localStorage.setItem(SUMMARY_PERIOD_KEY, value);
  } catch {
    // Trình duyệt chặn localStorage thì bỏ qua, không làm hỏng trang.
  }
});
</script>

<template>
  <section class="eval">
    <PageHeader
      title="Tổng hợp đánh giá"
      icon="clipboardCheck"
      description="Toàn bộ nhân sự phòng ban trong một kỳ: công việc, điểm theo tiêu chí và điểm cuối."
    />

    <!-- Chọn kỳ — bấm nút là xong, gõ tay chỉ khi cần khoảng lạ. -->
    <div class="eval__period">
      <div class="eval__quick">
        <button
          v-for="item in quickPeriods"
          :key="item.key"
          type="button"
          class="eval__quick-btn"
          :class="{ 'eval__quick-btn--on': activeQuickKey === item.key }"
          @click="applyQuickPeriod(item)"
        >
          {{ item.label }}
        </button>
        <button
          type="button"
          class="eval__quick-btn eval__quick-btn--more"
          :class="{ 'eval__quick-btn--on': customOpen || !activeQuickKey }"
          :aria-expanded="customOpen"
          @click="customOpen = !customOpen"
        >
          Chọn khoảng khác
          <AppIcon :name="customOpen ? 'chevronDown' : 'chevronRight'" :size="14" />
        </button>
      </div>

      <p class="eval__period-now">
        <span v-if="periodLabel">Đang xem: <strong>{{ periodLabel }}</strong></span>
        <span v-if="versionNo" class="eval__period-version">
          Khung chấm điểm phiên bản {{ versionNo }}
        </span>
      </p>
    </div>

    <div v-if="customOpen" class="eval__custom">
      <div class="eval__field">
        <label class="eval__label" for="eval-from">Từ ngày</label>
        <input id="eval-from" v-model="periodFrom" type="date" class="eval__input" />
      </div>
      <div class="eval__field">
        <label class="eval__label" for="eval-to">Đến ngày</label>
        <input id="eval-to" v-model="periodTo" type="date" class="eval__input" />
      </div>
      <p v-if="periodInvalid" class="eval__custom-warn">
        Ngày bắt đầu đang sau ngày kết thúc.
      </p>
    </div>

    <!-- Số liệu tổng của kỳ + phân bố xếp loại. -->
    <div v-if="summary && !loadError" class="eval__overview">
      <div class="eval__stats">
        <div class="eval__stat">
          <span class="eval__stat-label">Số nhân sự</span>
          <span class="eval__stat-value">{{ summary.total_people }}</span>
        </div>
        <div class="eval__stat">
          <span class="eval__stat-label">Điểm trung bình</span>
          <span class="eval__stat-value">{{ formatNumber(summary.average_score) }}</span>
        </div>
        <div class="eval__stat">
          <span class="eval__stat-label">Cao nhất</span>
          <span class="eval__stat-value">{{ formatNumber(summary.highest_score) }}</span>
        </div>
        <div class="eval__stat">
          <span class="eval__stat-label">Thấp nhất</span>
          <span class="eval__stat-value">{{ formatNumber(summary.lowest_score) }}</span>
        </div>
      </div>

      <div v-if="distribution.length" class="eval__dist">
        <div class="eval__dist-bar">
          <span
            v-for="(item, index) in distribution"
            :key="item.code ?? index"
            class="eval__dist-seg"
            :class="`eval__dist-seg--${index % 5}`"
            :style="{ width: `${distributionPercent(item)}%` }"
          />
        </div>
        <ul class="eval__dist-legend">
          <li v-for="(item, index) in distribution" :key="item.code ?? index">
            <span class="eval__dot" :class="`eval__dot--rank-${index % 5}`" />
            {{ item.label }}: {{ item.count }} người
          </li>
        </ul>
      </div>
    </div>

    <p v-if="summary && summary.missing_total > 0 && !loadError" class="eval__warn">
      Có {{ summary.missing_total }} chỗ thiếu dữ liệu chấm điểm (độ khó, tiến độ hoặc chất
      lượng công việc) nên điểm công việc có thể chưa phản ánh đủ.
    </p>

    <!-- Lọc nhanh theo tình trạng — trả lời "ai cần để mắt tới". -->
    <div class="eval__toolbar">
      <div class="eval__focus">
        <button
          v-for="option in FOCUS_OPTIONS"
          :key="option.key"
          type="button"
          class="eval__focus-btn"
          :class="{ 'eval__focus-btn--on': focus === option.key }"
          @click="focus = option.key"
        >
          {{ option.label }}
          <span class="eval__focus-count">{{ focusCounts[option.key] ?? 0 }}</span>
        </button>
      </div>

      <div class="eval__toolbar-right">
        <div class="eval__search">
          <AppIcon name="search" :size="16" class="eval__search-icon" />
          <label class="eval__sr" for="eval-q">Tìm nhân sự theo tên</label>
          <input id="eval-q" v-model="query" type="search" class="eval__search-input" />
        </div>

        <button
          v-if="filtersActive"
          type="button"
          class="eval__ghost-btn"
          @click="clearFilters"
        >
          Bỏ lọc
        </button>
      </div>
    </div>

    <div class="eval__main">
      <div class="eval__list-wrap">
        <!-- Đầu danh sách: bấm để đổi cách sắp xếp. -->
        <div class="eval__sortbar">
          <span class="eval__sortbar-label">Sắp xếp theo</span>
          <button
            v-for="option in SORT_OPTIONS"
            :key="option.key"
            type="button"
            class="eval__sort-btn"
            :class="{ 'eval__sort-btn--on': sortKey === option.key }"
            @click="toggleSort(option.key)"
          >
            {{ option.label }}
            <span v-if="sortKey === option.key" class="eval__sort-dir">
              {{ sortDir === 'asc' ? 'tăng dần' : 'giảm dần' }}
            </span>
          </button>
        </div>

        <p v-if="loading" class="eval__state">Đang tải…</p>
        <p v-else-if="loadError" class="eval__state eval__state--error">{{ loadError }}</p>
        <p v-else-if="filteredRows.length === 0" class="eval__state">
          <span v-if="filtersActive">Không có nhân sự nào khớp với điều kiện đang lọc.</span>
          <span v-else>Kỳ này chưa có nhân sự nào để tổng hợp.</span>
        </p>

        <ul v-else class="eval__list">
          <li
            v-for="row in filteredRows"
            :key="row.user_id"
            class="eval__person"
            :class="{ 'eval__person--on': selectedId === row.user_id }"
          >
            <button type="button" class="eval__person-btn" @click="select(row)">
              <span class="eval__person-main">
                <span class="eval__person-name">{{ row.user_name }}</span>
                <span class="eval__person-sub">
                  <span v-if="totalTasksOf(row) === 0">Không có việc nào trong kỳ</span>
                  <template v-else>
                    {{ doneOf(row) }}/{{ totalTasksOf(row) }} việc hoàn thành
                    <span v-if="overdueOf(row) > 0" class="eval__flag">
                      <span class="eval__dot eval__dot--danger" />
                      {{ overdueOf(row) }} việc quá hạn
                    </span>
                    <span v-else-if="onTimeOf(row) > 0" class="eval__flag">
                      <span class="eval__dot eval__dot--ok" />
                      đúng hạn cả kỳ
                    </span>
                  </template>
                </span>
                <span class="eval__meter" aria-hidden="true">
                  <span class="eval__meter-fill" :style="{ width: `${donePercent(row)}%` }" />
                </span>
              </span>

              <span class="eval__person-events">
                <span class="eval__person-cap">Ghi nhận</span>
                <span class="eval__person-events-value">
                  <span v-if="row.bonus" class="eval__plus">+{{ formatNumber(row.bonus) }}</span>
                  <span v-if="row.penalty" class="eval__minus">
                    -{{ formatNumber(row.penalty) }}
                  </span>
                  <span v-if="!row.bonus && !row.penalty" class="eval__muted">chưa có</span>
                </span>
              </span>

              <span class="eval__person-score">
                <span class="eval__person-cap">Điểm cuối</span>
                <span class="eval__person-score-value">{{ formatNumber(row.final_score) }}</span>
                <span class="eval__meter eval__meter--score" aria-hidden="true">
                  <span
                    class="eval__meter-fill eval__meter-fill--score"
                    :style="{ width: `${scorePercent(row)}%` }"
                  />
                </span>
              </span>

              <span class="eval__person-rank">
                <span class="eval__person-cap">Xếp loại</span>
                <span class="eval__person-rank-value">
                  {{ row.classification_label ?? 'Chưa xếp loại' }}
                </span>
              </span>

              <AppIcon name="chevronRight" :size="16" class="eval__person-caret" />
            </button>
          </li>
        </ul>
      </div>

      <!-- Ngăn chi tiết — chỉ hiện khi đã chọn một người (quy tắc 14). -->
      <aside v-if="selected" class="eval__detail">
        <header class="eval__detail-head">
          <div class="eval__detail-title-wrap">
            <h2 class="eval__detail-title">{{ selected.user_name }}</h2>
            <p class="eval__detail-sub">
              Điểm cuối {{ formatNumber(selected.final_score) }} ·
              {{ selected.classification_label ?? 'Chưa xếp loại' }}
            </p>
          </div>

          <div class="eval__detail-nav">
            <button
              type="button"
              class="eval__icon-btn"
              aria-label="Nhân sự trước đó"
              :disabled="!stepInfo.hasPrev"
              @click="step(-1)"
            >
              <AppIcon name="chevronLeft" :size="16" />
            </button>
            <span class="eval__detail-pos">
              {{ stepInfo.index + 1 }}/{{ stepInfo.total }}
            </span>
            <button
              type="button"
              class="eval__icon-btn"
              aria-label="Nhân sự kế tiếp"
              :disabled="!stepInfo.hasNext"
              @click="step(1)"
            >
              <AppIcon name="chevronRight" :size="16" />
            </button>
            <button
              type="button"
              class="eval__icon-btn"
              aria-label="Đóng phần chi tiết"
              @click="closeDetail"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </header>

        <!-- Điểm cuối được ghép từ đâu — nhìn là hiểu, không phải đoán. -->
        <dl class="eval__breakdown">
          <div class="eval__breakdown-row">
            <dt class="eval__breakdown-key">Điểm khởi đầu</dt>
            <dd class="eval__breakdown-val">{{ formatNumber(selected.start_score) }}</dd>
          </div>
          <div class="eval__breakdown-row">
            <dt class="eval__breakdown-key">Từ công việc</dt>
            <dd class="eval__breakdown-val">{{ signedText(selected.task_adjustment) }}</dd>
          </div>
          <div class="eval__breakdown-row">
            <dt class="eval__breakdown-key">Điểm cộng</dt>
            <dd class="eval__breakdown-val eval__plus">{{ signedText(selected.bonus) }}</dd>
          </div>
          <div class="eval__breakdown-row">
            <dt class="eval__breakdown-key">Điểm trừ</dt>
            <dd class="eval__breakdown-val eval__minus">
              {{ selected.penalty ? `-${formatNumber(selected.penalty)}` : '0' }}
            </dd>
          </div>
          <div class="eval__breakdown-row eval__breakdown-row--total">
            <dt class="eval__breakdown-key">Điểm cuối</dt>
            <dd class="eval__breakdown-val">{{ formatNumber(selected.final_score) }}</dd>
          </div>
        </dl>

        <div class="eval__tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class="eval__tab"
            :class="{ 'eval__tab--on': detailTab === 'tasks' }"
            :aria-selected="detailTab === 'tasks'"
            @click="detailTab = 'tasks'"
          >
            Công việc ({{ detailTasks.length }})
          </button>
          <button
            type="button"
            role="tab"
            class="eval__tab"
            :class="{ 'eval__tab--on': detailTab === 'events' }"
            :aria-selected="detailTab === 'events'"
            @click="detailTab = 'events'"
          >
            Ghi nhận ({{ detailEvents.length }})
          </button>
          <button
            type="button"
            role="tab"
            class="eval__tab"
            :class="{ 'eval__tab--on': detailTab === 'record' }"
            :aria-selected="detailTab === 'record'"
            @click="detailTab = 'record'"
          >
            Chấm điểm
          </button>
        </div>

        <div class="eval__detail-body hide-scrollbar">
          <!-- Công việc trong kỳ -->
          <template v-if="detailTab === 'tasks'">
            <p v-if="detailTasks.length === 0" class="eval__state eval__state--sm">
              Nhân sự này không có công việc nào thuộc kỳ đang xem.
            </p>

            <ul v-else class="eval__tasks">
              <li v-for="task in detailTasks" :key="task.task_id" class="eval__task">
                <p class="eval__task-title">{{ task.title }}</p>
                <p class="eval__task-meta">
                  <span class="eval__state-line">
                    <span
                      class="eval__dot"
                      :class="`eval__dot--${TIMELINESS_TONES[task.on_time_state]}`"
                    />
                    {{ TASK_STATUS_LABELS[task.status] ?? task.status }},
                    {{ TIMELINESS_LABELS[task.on_time_state] }}
                  </span>
                  <span v-if="task.project_name">· {{ task.project_name }}</span>
                </p>
                <p class="eval__task-meta">
                  <span>Hạn: {{ task.end_date ? formatDate(task.end_date) : 'chưa đặt' }}</span>
                  <span>
                    Hoàn thành:
                    {{ task.actual_end_date ? formatDate(task.actual_end_date) : 'chưa xong' }}
                  </span>
                  <span>Điểm việc: {{ formatNumber(task.contribution) }}</span>
                </p>
                <button type="button" class="eval__link" @click="recordForTask(task)">
                  Chấm điểm cho việc này
                </button>
              </li>
            </ul>
          </template>

          <!-- Các lần đã ghi nhận -->
          <template v-else-if="detailTab === 'events'">
            <p v-if="detailEvents.length === 0" class="eval__state eval__state--sm">
              Chưa ghi nhận đánh giá nào cho nhân sự này trong kỳ.
            </p>

            <ul v-else class="eval__events">
              <li v-for="event in detailEvents" :key="event.event_id" class="eval__event">
                <div class="eval__event-head">
                  <span class="eval__event-name">{{ event.criterion_name }}</span>
                  <span
                    class="eval__event-score"
                    :class="event.score >= 0 ? 'eval__plus' : 'eval__minus'"
                  >
                    {{ signedText(event.score) }}
                  </span>
                </div>
                <p class="eval__event-meta">
                  {{ event.level_label }} · {{ formatDate(event.occurred_at) }}
                  <span v-if="taskTitleOf(event.task_id)">
                    · việc: {{ taskTitleOf(event.task_id) }}
                  </span>
                </p>
                <p v-if="event.reason" class="eval__event-reason">{{ event.reason }}</p>

                <div v-if="confirmRemoveId === event.event_id" class="eval__confirm">
                  <span>Xoá ghi nhận này?</span>
                  <button
                    type="button"
                    class="eval__link eval__link--danger"
                    :disabled="removingId === event.event_id"
                    @click="removeEvent(event)"
                  >
                    {{ removingId === event.event_id ? 'Đang xoá…' : 'Xoá' }}
                  </button>
                  <button type="button" class="eval__link" @click="confirmRemoveId = null">
                    Giữ lại
                  </button>
                </div>
                <button
                  v-else
                  type="button"
                  class="eval__link eval__link--danger"
                  @click="confirmRemoveId = event.event_id"
                >
                  Xoá ghi nhận
                </button>
              </li>
            </ul>
          </template>

          <!-- Chấm điểm: một form duy nhất, đọc từ trên xuống là xong -->
          <template v-else>
            <p v-if="criteria.length === 0" class="eval__state eval__state--sm">
              Phòng ban chưa có tiêu chí cộng / trừ điểm nào đang bật, nên chưa chấm được.
            </p>

            <form v-else class="eval__form" @submit.prevent="record">
              <div class="eval__field">
                <label class="eval__label" for="eval-criterion">Tiêu chí đánh giá</label>
                <select
                  id="eval-criterion"
                  ref="criterionField"
                  v-model="draft.criterion_id"
                  class="eval__input"
                  :disabled="saving"
                  @change="onCriterionChange"
                >
                  <option value="">Chọn tiêu chí</option>
                  <option v-for="item in criteria" :key="item.id" :value="item.id">
                    {{ item.name }}
                  </option>
                </select>
              </div>

              <div v-if="draftLevels.length" class="eval__field">
                <span class="eval__label">Mức đánh giá</span>
                <div class="eval__levels">
                  <label
                    v-for="level in draftLevels"
                    :key="level.code"
                    class="eval__level"
                    :class="{ 'eval__level--on': draft.level_code === level.code }"
                  >
                    <input
                      v-model="draft.level_code"
                      type="radio"
                      :value="level.code"
                      class="eval__sr"
                      :disabled="saving"
                    />
                    <span class="eval__level-label">{{ level.label }}</span>
                    <span
                      class="eval__level-score"
                      :class="level.score >= 0 ? 'eval__plus' : 'eval__minus'"
                    >
                      {{ signedText(level.score) }}
                    </span>
                  </label>
                </div>
              </div>

              <div class="eval__field">
                <label class="eval__label" for="eval-task">Gắn với công việc</label>
                <select
                  id="eval-task"
                  v-model="draft.task_id"
                  class="eval__input"
                  :disabled="saving || detailTasks.length === 0"
                >
                  <option value="">Không gắn công việc nào</option>
                  <option v-for="task in detailTasks" :key="task.task_id" :value="task.task_id">
                    {{ task.title }}
                  </option>
                </select>
              </div>

              <div class="eval__field">
                <label class="eval__label" for="eval-reason">Lý do</label>
                <textarea
                  id="eval-reason"
                  v-model="draft.reason"
                  class="eval__input eval__textarea"
                  rows="2"
                  maxlength="500"
                  :disabled="saving"
                />
              </div>

              <p v-if="draftLevel" class="eval__preview">
                Sẽ ghi nhận <strong>{{ selectedCriterion.name }}</strong> mức
                <strong>{{ draftLevel.label }}</strong>, thay đổi điểm
                <strong :class="draftLevel.score >= 0 ? 'eval__plus' : 'eval__minus'">
                  {{ signedText(draftLevel.score) }}
                </strong>
                cho {{ selected.user_name }}.
              </p>

              <div class="eval__form-actions">
                <button type="submit" class="eval__btn" :disabled="!canRecord">
                  {{ saving ? 'Đang lưu…' : 'Ghi nhận đánh giá' }}
                </button>
                <button
                  type="button"
                  class="eval__ghost-btn"
                  :disabled="saving"
                  @click="resetDraft"
                >
                  Xoá nội dung đang nhập
                </button>
              </div>
            </form>
          </template>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.eval {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

.eval__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

/* ---------- Chọn kỳ ---------- */

.eval__period {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}

.eval__quick {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.eval__quick-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.4375rem 0.875rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.eval__quick-btn:hover {
  background: var(--color-surface-muted);
}

.eval__quick-btn--on {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: none;
}

.eval__quick-btn--on:hover {
  background: var(--color-primary-hover);
}

.eval__period-now {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.eval__period-now strong {
  color: var(--color-text);
  font-weight: 600;
}

.eval__period-version {
  margin-left: var(--space-3);
}

.eval__custom {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 14rem));
  align-items: end;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.eval__custom-warn {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-danger);
  font-size: 0.8125rem;
}

/* ---------- Số liệu tổng ---------- */

.eval__overview {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.eval__stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-5);
}

.eval__stat {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.eval__stat-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__stat-value {
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 600;
  line-height: 1.2;
}

.eval__dist {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 16rem;
  flex: 1;
  max-width: 32rem;
}

.eval__dist-bar {
  display: flex;
  height: 0.5rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.eval__dist-seg--0 {
  background: var(--color-secondary-500);
}

.eval__dist-seg--1 {
  background: var(--color-tertiary-500);
}

.eval__dist-seg--2 {
  background: var(--color-gold-500);
}

.eval__dist-seg--3 {
  background: var(--color-primary-400);
}

.eval__dist-seg--4 {
  background: var(--color-umber-500);
}

.eval__dist-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__dist-legend li {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.eval__warn {
  flex-shrink: 0;
  margin: var(--space-2) 0 0;
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
}

/* ---------- Chấm màu trạng thái (quy tắc 14 — không badge) ---------- */

.eval__dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.eval__dot--ok {
  background: var(--color-success);
}

.eval__dot--danger {
  background: var(--color-danger);
}

.eval__dot--muted {
  background: var(--color-text-muted);
}

.eval__dot--rank-0 {
  background: var(--color-secondary-500);
}

.eval__dot--rank-1 {
  background: var(--color-tertiary-500);
}

.eval__dot--rank-2 {
  background: var(--color-gold-500);
}

.eval__dot--rank-3 {
  background: var(--color-primary-400);
}

.eval__dot--rank-4 {
  background: var(--color-umber-500);
}

/* ---------- Thanh lọc ---------- */

.eval__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}

.eval__focus {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.eval__focus-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.eval__focus-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.eval__focus-btn--on {
  background: var(--color-primary-surface);
  color: var(--color-primary-900);
}

.eval__focus-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.eval__focus-btn--on .eval__focus-count {
  color: var(--color-primary-800);
}

.eval__toolbar-right {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.eval__search {
  position: relative;
  display: flex;
  align-items: center;
}

.eval__search-icon {
  position: absolute;
  left: 0.625rem;
  color: var(--color-text-muted);
  pointer-events: none;
}

.eval__search-input {
  width: 14rem;
  max-width: 100%;
  padding: 0.4375rem 0.75rem 0.4375rem 2rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.eval__search-input:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

/* ---------- Khu chính: danh sách + ngăn chi tiết ---------- */

.eval__main {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.eval__list-wrap {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.eval__sortbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.eval__sortbar-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.eval__sort-btn {
  display: inline-flex;
  align-items: baseline;
  gap: 0.375rem;
  padding: 0.25rem 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  cursor: pointer;
}

.eval__sort-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.eval__sort-btn--on {
  color: var(--color-primary-900);
  font-weight: 600;
  box-shadow: 0 2px 0 var(--color-primary);
}

.eval__sort-dir {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 400;
}

.eval__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.eval__person {
  box-shadow: 0 1px 0 var(--color-border);
}

.eval__person-btn {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 7rem 8rem 9rem 1.25rem;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-2);
  border: none;
  background: transparent;
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.eval__person-btn:hover {
  background: var(--color-surface-muted);
}

.eval__person--on .eval__person-btn {
  background: var(--color-primary-surface);
}

.eval__person-main {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.eval__person-name {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval__person-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__flag {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.eval__meter {
  display: block;
  width: 100%;
  max-width: 12rem;
  height: 0.25rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.eval__meter-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-secondary-500);
}

.eval__meter--score {
  max-width: 100%;
}

.eval__meter-fill--score {
  background: var(--color-primary);
}

.eval__person-cap {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.eval__person-events,
.eval__person-score,
.eval__person-rank {
  display: flex;
  flex-direction: column;
  gap: 0.1875rem;
  min-width: 0;
}

.eval__person-events-value {
  display: flex;
  gap: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval__person-score-value {
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 600;
  line-height: 1.1;
}

.eval__person-rank-value {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval__person-caret {
  color: var(--color-text-muted);
}

.eval__plus {
  color: var(--color-success);
}

.eval__minus {
  color: var(--color-danger);
}

.eval__muted {
  color: var(--color-text-muted);
  font-weight: 400;
}

.eval__state {
  margin: var(--space-6) 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

.eval__state--sm {
  margin: var(--space-4) 0;
  text-align: left;
}

.eval__state--error {
  color: var(--color-danger);
}

/* ---------- Ngăn chi tiết ---------- */

.eval__detail {
  flex-shrink: 0;
  width: 28rem;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.eval__detail-head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.eval__detail-title-wrap {
  min-width: 0;
}

.eval__detail-title {
  margin: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval__detail-sub {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__detail-nav {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.125rem;
}

.eval__detail-pos {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.eval__icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.eval__icon-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.eval__breakdown {
  flex-shrink: 0;
  margin: 0;
  padding: var(--space-2) var(--space-4);
}

.eval__breakdown-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0.375rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.eval__breakdown-row--total {
  box-shadow: none;
  font-weight: 600;
}

.eval__breakdown-key {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.eval__breakdown-val {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.eval__breakdown-row--total .eval__breakdown-key {
  color: var(--color-text);
}

.eval__breakdown-row--total .eval__breakdown-val {
  font-size: 1.125rem;
}

.eval__tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-1);
  padding: 0 var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.eval__tab {
  padding: 0.5rem 0.625rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.eval__tab:hover {
  color: var(--color-text);
}

.eval__tab--on {
  color: var(--color-primary-900);
  font-weight: 600;
  box-shadow: 0 -2px 0 var(--color-primary) inset;
}

.eval__detail-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3) var(--space-4) var(--space-4);
}

/* ---------- Công việc ---------- */

.eval__tasks,
.eval__events {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.eval__task,
.eval__event {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.eval__task-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  word-break: break-word;
}

.eval__task-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem var(--space-2);
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__state-line {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.eval__link {
  align-self: flex-start;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
}

.eval__link:hover {
  text-decoration: underline;
}

.eval__link--danger {
  color: var(--color-danger);
}

.eval__link:disabled {
  color: var(--color-text-muted);
  cursor: not-allowed;
}

/* ---------- Ghi nhận đã có ---------- */

.eval__event-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.eval__event-name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval__event-score {
  font-size: 0.875rem;
  font-weight: 600;
}

.eval__event-meta {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.eval__event-reason {
  margin: 0;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
}

.eval__confirm {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-size: 0.75rem;
}

/* ---------- Form chấm điểm ---------- */

.eval__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.eval__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.eval__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.eval__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.eval__input:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.eval__input:disabled {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.eval__textarea {
  resize: vertical;
}

.eval__levels {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.eval__level {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.eval__level:hover {
  background: var(--color-surface-muted);
}

.eval__level--on {
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.eval__level-label {
  color: var(--color-text);
  font-size: 0.8125rem;
}

.eval__level-score {
  font-size: 0.8125rem;
  font-weight: 600;
}

.eval__preview {
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.5;
}

.eval__preview strong {
  color: var(--color-text);
}

.eval__form-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.eval__btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.eval__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.eval__btn:disabled {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.eval__ghost-btn {
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.eval__ghost-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.eval__ghost-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

/* ---------- Responsive ---------- */

@media (max-width: 1280px) {
  .eval__detail {
    width: 24rem;
  }

  .eval__person-btn {
    grid-template-columns: minmax(0, 1fr) 6rem 7rem 1.25rem;
  }

  .eval__person-rank {
    display: none;
  }
}

@media (max-width: 900px) {
  /* Hết chỗ cho hai cột — ngăn chi tiết chiếm cả khung, danh sách nhường chỗ. */
  .eval__main {
    position: relative;
  }

  .eval__detail {
    position: absolute;
    inset: 0;
    width: auto;
  }
}

@media (max-width: 768px) {
  .eval__overview {
    flex-direction: column;
    align-items: stretch;
  }

  .eval__stats {
    gap: var(--space-4);
  }

  .eval__dist {
    max-width: none;
  }

  .eval__person-btn {
    grid-template-columns: minmax(0, 1fr) 6rem 1.25rem;
  }

  .eval__person-events {
    display: none;
  }

  .eval__custom {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 480px) {
  .eval__toolbar,
  .eval__period {
    flex-direction: column;
    align-items: stretch;
  }

  .eval__search-input {
    width: 100%;
  }

  .eval__person-btn {
    grid-template-columns: minmax(0, 1fr) 5rem;
    gap: var(--space-2);
  }

  .eval__person-caret {
    display: none;
  }

  .eval__stat-value {
    font-size: 1.125rem;
  }
}
</style>
