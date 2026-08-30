<script setup>
//
// Lịch công việc: tháng / tuần (toggle). Thanh điều hướng bám mẫu
// "Hôm nay + mũi tên + Xem theo…". Nút đổi chế độ xem (Danh sách /
// Kanban / Lịch) do trang cha Teleport vào #task-cal-view-mode-host.
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { computeExpectedProgress } from '@/lib/progress';
import TaskCalendarChip from './TaskCalendarChip.vue';
import {
  CALENDAR_MODE_KEY,
  CALENDAR_MODE_VALUES,
  TASK_PRIORITY_LABELS,
  TASK_PRIORITY_TONES,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  WEEKDAY_SHORT,
  addDays,
  calendarOverlapRange,
  parseYmd,
  startOfWeekMonday,
  toYmd,
} from '../constants/task.js';

const props = defineProps({
  tasks: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canViewAll: { type: Boolean, default: false },
  currentUserId: { type: Number, default: null },
});

const emit = defineEmits(['range-change', 'mode-change', 'inspect', 'edit', 'delete']);

const datePicker = ref(null);
const peek = ref(null);
const monthEl = ref(null);
const weekEl = ref(null);

useDragScroll(monthEl, { axis: 'y', closest: '.task-cal__cell-list' });
useDragScroll(weekEl, { axis: 'both' });

const mode = ref(loadCalendarMode());
const cursor = ref(startOfToday());

// ---------- Bộ lọc lịch (chỉ hiện với người có quyền xem tất cả) ----------
const calFilterMode = ref('mine'); // 'all' | 'mine' | 'person' | 'dept'
const calFilterPersonIds = ref(new Set());
const calFilterPersonSearch = ref('');
const calFilterDeptIds = ref(new Set());
const calFilterPersonOpen = ref(false);
const calFilterDeptOpen = ref(false);

const taskAssignees = computed(() => {
  const seen = new Map();
  for (const task of props.tasks) {
    if (task.assignee?.id && !seen.has(task.assignee.id)) {
      seen.set(task.assignee.id, task.assignee);
    }
  }
  return Array.from(seen.values()).sort((a, b) =>
    String(a.name || '').localeCompare(String(b.name || ''), 'vi'),
  );
});

const taskDepartments = computed(() => {
  const seen = new Map();
  for (const task of props.tasks) {
    const dept = task.department;
    if (dept?.id && !seen.has(dept.id)) seen.set(dept.id, dept);
  }
  return Array.from(seen.values()).sort((a, b) =>
    String(a.name || '').localeCompare(String(b.name || ''), 'vi'),
  );
});

const showFilter = computed(
  () => props.canViewAll && (taskAssignees.value.length > 1 || taskDepartments.value.length > 0),
);

const filteredTasks = computed(() => {
  if (calFilterMode.value === 'mine' && props.currentUserId) {
    return props.tasks.filter((t) => t.assignee_id === props.currentUserId);
  }
  if (calFilterMode.value === 'person' && calFilterPersonIds.value.size > 0) {
    const ids = calFilterPersonIds.value;
    return props.tasks.filter((t) => ids.has(t.assignee_id));
  }
  if (calFilterMode.value === 'dept' && calFilterDeptIds.value.size > 0) {
    const ids = calFilterDeptIds.value;
    return props.tasks.filter((t) => ids.has(t.department?.id));
  }
  return props.tasks;
});

const calFilterPersonLabel = computed(() => {
  const size = calFilterPersonIds.value.size;
  if (size === 0) return 'Người';
  if (size === 1) {
    const id = Array.from(calFilterPersonIds.value)[0];
    return taskAssignees.value.find((u) => u.id === id)?.name || 'Người';
  }
  return `${size} người`;
});

const visibleAssignees = computed(() => {
  const q = calFilterPersonSearch.value.trim().toLowerCase();
  if (!q) return taskAssignees.value;
  return taskAssignees.value.filter((u) => (u.name || '').toLowerCase().includes(q));
});

const calFilterDeptLabel = computed(() => {
  const size = calFilterDeptIds.value.size;
  if (size === 0) return 'Phòng ban';
  if (size === 1) {
    const id = Array.from(calFilterDeptIds.value)[0];
    return taskDepartments.value.find((d) => d.id === id)?.name || 'Phòng ban';
  }
  return `${size} phòng ban`;
});

const range = computed(() => calendarOverlapRange(mode.value, cursor.value));

const titleLabel = computed(() => {
  const c = cursor.value;
  if (mode.value === 'week') {
    const start = startOfWeekMonday(c);
    const end = addDays(start, 6);
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
      return `${pad2(start.getDate())} – ${pad2(end.getDate())}/${pad2(end.getMonth() + 1)}/${end.getFullYear()}`;
    }
    return `${pad2(start.getDate())}/${pad2(start.getMonth() + 1)} – ${pad2(end.getDate())}/${pad2(end.getMonth() + 1)}/${end.getFullYear()}`;
  }
  return `Tháng ${pad2(c.getMonth() + 1)}/${c.getFullYear()}`;
});

const monthCells = computed(() => {
  const first = new Date(cursor.value.getFullYear(), cursor.value.getMonth(), 1);
  const start = startOfWeekMonday(first);
  const today = toYmd(startOfToday());
  const month = cursor.value.getMonth();
  return Array.from({ length: 42 }, (_, i) => {
    const date = addDays(start, i);
    const ymd = toYmd(date);
    const items = tasksOnDay(ymd);
    return {
      key: ymd,
      date,
      day: date.getDate(),
      ymd,
      outside: date.getMonth() !== month,
      isToday: ymd === today,
      items,
    };
  });
});

const weekDays = computed(() => daysFrom(startOfWeekMonday(cursor.value), 7));

function daysFrom(start, count) {
  const today = toYmd(startOfToday());
  return Array.from({ length: count }, (_, i) => {
    const date = addDays(start, i);
    const ymd = toYmd(date);
    const items = tasksOnDay(ymd);
    return {
      key: ymd,
      date,
      ymd,
      day: date.getDate(),
      weekday: WEEKDAY_SHORT[(date.getDay() + 6) % 7],
      isToday: ymd === today,
      items,
      timed: items.filter((task) => !isAllDay(task, ymd)),
      allDay: items.filter((task) => isAllDay(task, ymd)),
    };
  });
}

function loadCalendarMode() {
  try {
    const raw = localStorage.getItem(CALENDAR_MODE_KEY);
    if (CALENDAR_MODE_VALUES.includes(raw)) return raw;
  } catch {
    // Bỏ qua.
  }
  return 'month';
}

function persistMode(next) {
  try {
    localStorage.setItem(CALENDAR_MODE_KEY, next);
  } catch {
    // Bỏ qua.
  }
}

function startOfToday() {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function formatClock(value) {
  if (!value) return '';
  const match = /^(\d{1,2}):(\d{2})/.exec(String(value));
  if (!match) return '';
  return `${pad2(Number(match[1]))}:${match[2]}`;
}

function clockMinutes(value, fallback) {
  const match = /^(\d{1,2}):(\d{2})/.exec(String(value || ''));
  if (!match) return fallback;
  return Number(match[1]) * 60 + Number(match[2]);
}

function taskStart(task) {
  return parseYmd(task.start_date) || parseYmd(task.end_date);
}

function taskEnd(task) {
  return parseYmd(task.end_date) || parseYmd(task.start_date);
}

function taskOverlapsDay(task, ymd) {
  const start = taskStart(task);
  const end = taskEnd(task);
  if (!start || !end) return false;
  const day = parseYmd(ymd);
  return day && start.getTime() <= day.getTime() && end.getTime() >= day.getTime();
}

function tasksOnDay(ymd) {
  return filteredTasks.value.filter((task) => taskOverlapsDay(task, ymd)).sort((a, b) => {
    const ta = clockMinutes(a.start_time, 0);
    const tb = clockMinutes(b.start_time, 0);
    if (ta !== tb) return ta - tb;
    return String(a.title || '').localeCompare(String(b.title || ''), 'vi');
  });
}

function setCalFilter(mode) {
  calFilterMode.value = mode;
  calFilterPersonOpen.value = false;
  calFilterDeptOpen.value = false;
  if (mode !== 'person') calFilterPersonIds.value = new Set();
  if (mode !== 'dept') calFilterDeptIds.value = new Set();
}

function toggleCalPersonOpen() {
  calFilterPersonOpen.value = !calFilterPersonOpen.value;
  if (calFilterPersonOpen.value) {
    calFilterDeptOpen.value = false;
    calFilterPersonSearch.value = '';
  }
}

function toggleCalPerson(userId) {
  const next = new Set(calFilterPersonIds.value);
  if (next.has(userId)) next.delete(userId);
  else next.add(userId);
  calFilterPersonIds.value = next;
  calFilterMode.value = next.size > 0 ? 'person' : 'all';
}

function toggleCalDeptOpen() {
  calFilterDeptOpen.value = !calFilterDeptOpen.value;
  if (calFilterDeptOpen.value) calFilterPersonOpen.value = false;
}

function toggleCalDept(deptId) {
  const next = new Set(calFilterDeptIds.value);
  if (next.has(deptId)) next.delete(deptId);
  else next.add(deptId);
  calFilterDeptIds.value = next;
  calFilterMode.value = next.size > 0 ? 'dept' : 'all';
}

function closeFilterDropdowns() {
  calFilterPersonOpen.value = false;
  calFilterDeptOpen.value = false;
}

function isAllDay(task, ymd) {
  if (!task.start_time && !task.due_time) return true;
  const start = taskStart(task);
  const end = taskEnd(task);
  const day = parseYmd(ymd);
  if (!start || !end || !day) return true;
  return start.getTime() !== end.getTime() && day.getTime() !== start.getTime();
}

function timeLabel(task) {
  const start = formatClock(task.start_time);
  const end = formatClock(task.due_time);
  if (start && end) return `${start} - ${end}`;
  if (start) return start;
  if (end) return `Hạn ${end}`;
  return 'Cả ngày';
}

function statusLabel(value) {
  return TASK_STATUS_LABELS[value] || value || '—';
}

function statusTone(value) {
  return TASK_STATUS_TONES[value] || 'neutral';
}

/** Class tone cho card lịch — luôn tô theo trạng thái công việc, nhất quán
 *  với bảng danh sách/Kanban (TASK_STATUS_TONES), nền nhạt + chấm màu thay
 *  vì nền màu rắn để scale tốt khi nhiều việc/nhiều phòng ban cùng ngày. */
function cardToneClass(task) {
  return `task-cal__chip--${calendarTone(task.status)}`;
}

/** Tone lịch — không dùng primary (#9a0036), chuyển sang info nhạt. */
function calendarTone(value) {
  const tone = statusTone(value);
  return tone === 'primary' ? 'info' : tone;
}

function priorityLabel(value) {
  return value ? TASK_PRIORITY_LABELS[value] || value : '—';
}

function priorityTone(value) {
  return TASK_PRIORITY_TONES[value] || 'neutral';
}

function formatDate(value) {
  const date = parseYmd(value);
  if (!date) return '--';
  return date.toLocaleDateString('vi-VN');
}

function personName(user) {
  return user?.name || '--';
}

function departmentName(task) {
  return (
    task?.department?.name
    || task?.delegated_to_department?.name
    || task?.origin_department?.name
    || task?.project?.executing_department?.name
    || task?.project?.owner_department?.name
    || '—'
  );
}

function chipDept(task) {
  const dept = departmentName(task);
  return dept && dept !== '—' ? dept : '';
}

function assigner(task) {
  return task.manager || task.creator || null;
}

function follower(task) {
  return task.delegated_to_employee || null;
}

function emitRange() {
  emit('range-change', { ...range.value });
}

function emitMode() {
  emit('mode-change', mode.value);
}

function setMode(next) {
  if (!CALENDAR_MODE_VALUES.includes(next) || mode.value === next) return;
  mode.value = next;
  persistMode(next);
  emitMode();
  emitRange();
}

function goToday() {
  cursor.value = startOfToday();
  emitRange();
}

function shiftCursor(dir) {
  const c = cursor.value;
  if (mode.value === 'week') {
    cursor.value = addDays(c, dir * 7);
  } else {
    cursor.value = new Date(c.getFullYear(), c.getMonth() + dir, 1);
  }
  emitRange();
}

function openDatePicker() {
  const el = datePicker.value;
  if (!el) return;
  el.value = toYmd(cursor.value);
  if (typeof el.showPicker === 'function') {
    el.showPicker();
    return;
  }
  el.click();
}

function onPickDate(event) {
  const next = parseYmd(event.target.value);
  if (!next) return;
  cursor.value = next;
  emitRange();
}

function openWeek(ymd) {
  const next = parseYmd(ymd);
  if (!next) return;
  cursor.value = next;
  if (mode.value !== 'week') {
    mode.value = 'week';
    persistMode('week');
    emitMode();
  }
  emitRange();
}

function openPeek(task, event) {
  event?.stopPropagation();
  peek.value = task;
}

function closePeek() {
  peek.value = null;
}

function inspectFromPeek() {
  const task = peek.value;
  closePeek();
  if (task) emit('inspect', task);
}

function editFromPeek() {
  const task = peek.value;
  closePeek();
  if (task) emit('edit', task);
}

function deleteFromPeek() {
  const task = peek.value;
  closePeek();
  if (task) emit('delete', task);
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (calFilterPersonOpen.value || calFilterDeptOpen.value) {
    closeFilterDropdowns();
    event.stopPropagation();
    return;
  }
  if (peek.value) {
    closePeek();
    event.stopPropagation();
  }
}

function onDocumentClickFilter(event) {
  // Tutup dropdown khi click ra ngoài — filter menus dùng @click.stop ở bên trong
  if (calFilterPersonOpen.value || calFilterDeptOpen.value) {
    closeFilterDropdowns();
  }
}

onMounted(() => {
  emitMode();
  emitRange();
  document.addEventListener('keydown', onDocumentKeydown, true);
  document.addEventListener('click', onDocumentClickFilter);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onDocumentKeydown, true);
  document.removeEventListener('click', onDocumentClickFilter);
});
</script>

<template>
  <div class="task-cal" :class="{ 'task-cal--month': mode === 'month' }">
    <div class="task-cal__toolbar">
      <!-- Nhóm điều hướng: kỳ + tỉ lệ lịch -->
      <div class="task-cal__nav-group">
        <div class="task-cal__nav">
          <button type="button" class="task-cal__today" @click="goToday">Hôm nay</button>
          <button type="button" class="task-cal__arrow" aria-label="Kỳ trước" @click="shiftCursor(-1)">
            <AppIcon name="chevronLeft" :size="16" :stroke-width="1.75" />
          </button>
          <button type="button" class="task-cal__title" @click="openDatePicker">
            <span>{{ titleLabel }}</span>
            <AppIcon name="calendar" :size="15" :stroke-width="1.75" />
          </button>
          <input
            ref="datePicker"
            type="date"
            class="task-cal__date-input"
            :value="toYmd(cursor)"
            @change="onPickDate"
          />
          <button type="button" class="task-cal__arrow" aria-label="Kỳ sau" @click="shiftCursor(1)">
            <AppIcon name="chevronRight" :size="16" :stroke-width="1.75" />
          </button>
        </div>

        <div class="task-cal__toggle" role="group" aria-label="Kiểu lịch">
          <button
            type="button"
            class="task-cal__view-btn"
            :class="{ 'task-cal__view-btn--on': mode === 'month' }"
            :aria-pressed="mode === 'month'"
            @click="setMode('month')"
          >Tháng</button>
          <button
            type="button"
            class="task-cal__view-btn"
            :class="{ 'task-cal__view-btn--on': mode === 'week' }"
            :aria-pressed="mode === 'week'"
            @click="setMode('week')"
          >Tuần</button>
        </div>
      </div>

      <!-- Nhóm bộ lọc + đổi chế độ xem -->
      <div class="task-cal__views">
        <!-- Bộ lọc người/phòng ban — chỉ hiện với người có quyền rộng -->
        <div v-if="showFilter" class="task-cal__filter" @click.stop>
          <div class="task-cal__filter-modes" role="group" aria-label="Lọc công việc theo người/phòng ban">
            <button
              type="button"
              class="task-cal__filter-pill"
              :class="{ 'task-cal__filter-pill--on': calFilterMode === 'all' }"
              :aria-pressed="calFilterMode === 'all'"
              @click="setCalFilter('all')"
            >Tất cả</button>
            <button
              type="button"
              class="task-cal__filter-pill"
              :class="{ 'task-cal__filter-pill--on': calFilterMode === 'mine' }"
              :aria-pressed="calFilterMode === 'mine'"
              @click="setCalFilter('mine')"
            >Của tôi</button>

            <div v-if="taskAssignees.length > 1" class="task-cal__filter-drop">
              <button
                type="button"
                class="task-cal__filter-pill task-cal__filter-pill--arrow"
                :class="{ 'task-cal__filter-pill--on': calFilterMode === 'person' }"
                :aria-expanded="calFilterPersonOpen"
                @click.stop="toggleCalPersonOpen"
              >
                <span>{{ calFilterPersonLabel }}</span>
                <AppIcon name="chevronDown" :size="11" :stroke-width="2.25" />
              </button>
              <div v-if="calFilterPersonOpen" class="task-cal__filter-menu" role="listbox" aria-label="Chọn người">
                <div class="task-cal__filter-search" @click.stop>
                  <AppIcon name="search" :size="13" class="task-cal__filter-search-icon" />
                  <input
                    v-model="calFilterPersonSearch"
                    type="text"
                    class="task-cal__filter-search-input"
                    placeholder="Tìm người…"
                    @click.stop
                  />
                </div>
                <label
                  v-for="user in visibleAssignees"
                  :key="user.id"
                  class="task-cal__filter-menu-item task-cal__filter-menu-item--check"
                  :class="{ 'task-cal__filter-menu-item--on': calFilterPersonIds.has(user.id) }"
                  @click.stop
                >
                  <input
                    type="checkbox"
                    class="task-cal__filter-check"
                    :checked="calFilterPersonIds.has(user.id)"
                    @change="toggleCalPerson(user.id)"
                  />
                  <span class="task-cal__filter-menu-avatar">{{ (user.name || '?').charAt(0).toUpperCase() }}</span>
                  <span>{{ user.name }}</span>
                </label>
                <p v-if="visibleAssignees.length === 0" class="task-cal__filter-empty">Không tìm thấy</p>
              </div>
            </div>

            <div v-if="taskDepartments.length > 0" class="task-cal__filter-drop">
              <button
                type="button"
                class="task-cal__filter-pill task-cal__filter-pill--arrow"
                :class="{ 'task-cal__filter-pill--on': calFilterMode === 'dept' }"
                :aria-expanded="calFilterDeptOpen"
                @click.stop="toggleCalDeptOpen"
              >
                <span>{{ calFilterDeptLabel }}</span>
                <AppIcon name="chevronDown" :size="11" :stroke-width="2.25" />
              </button>
              <div v-if="calFilterDeptOpen" class="task-cal__filter-menu task-cal__filter-menu--right" role="listbox" aria-label="Chọn phòng ban">
                <label
                  v-for="dept in taskDepartments"
                  :key="dept.id"
                  class="task-cal__filter-menu-item task-cal__filter-menu-item--check"
                  @click.stop
                >
                  <input
                    type="checkbox"
                    class="task-cal__filter-check"
                    :checked="calFilterDeptIds.has(dept.id)"
                    @change="toggleCalDept(dept.id)"
                  />
                  <span>{{ dept.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <span v-if="calFilterMode !== 'all'" class="task-cal__filter-count">
            {{ filteredTasks.length }}/{{ props.tasks.length }}
          </span>
        </div>

        <div class="task-cal__view-mode-host">
          <slot name="view-mode" />
        </div>
      </div>
    </div>

    <p v-if="loading" class="task-cal__hint">Đang tải lịch công việc…</p>

    <div v-else-if="mode === 'month'" ref="monthEl" class="task-cal__month hide-scrollbar">
      <div class="task-cal__weekdays">
        <span v-for="day in WEEKDAY_SHORT" :key="day">{{ day }}</span>
      </div>
      <div class="task-cal__month-grid">
        <div
          v-for="cell in monthCells"
          :key="cell.key"
          class="task-cal__cell"
          :class="{ 'task-cal__cell--out': cell.outside, 'task-cal__cell--today': cell.isToday }"
        >
          <div class="task-cal__cell-head">
            <button type="button" class="task-cal__cell-day" @click="openWeek(cell.ymd)">{{ cell.day }}</button>
            <span v-if="cell.items.length" class="task-cal__cell-cnt">{{ cell.items.length }} việc</span>
          </div>
          <div class="task-cal__cell-list">
            <TaskCalendarChip
              v-for="task in cell.items"
              :key="task.id"
              :task="task"
              :time="timeLabel(task)"
              :department="chipDept(task)"
              :tone-class="cardToneClass(task)"
              @open="openPeek(task, $event)"
            />
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="mode === 'week'" ref="weekEl" class="task-cal__week hide-scrollbar">
      <!-- Shared sticky header — one row for all 7 days, no per-column sticking -->
      <div class="task-cal__week-head">
        <button
          v-for="day in weekDays"
          :key="`wh-${day.key}`"
          type="button"
          class="task-cal__week-hcol"
          :class="{ 'task-cal__week-hcol--today': day.isToday }"
          @click="cursor = day.date"
        >
          <span class="task-cal__week-dow">{{ day.weekday }}</span>
          <span class="task-cal__week-num" :class="{ 'task-cal__week-num--today': day.isToday }">{{ day.day }}</span>
          <span v-if="day.items.length" class="task-cal__week-cnt">{{ day.items.length }} việc</span>
        </button>
      </div>

      <!-- Body: 7 columns with column-border separators -->
      <div class="task-cal__week-body">
        <div
          v-for="day in weekDays"
          :key="`wc-${day.key}`"
          class="task-cal__week-col"
          :class="{ 'task-cal__week-col--today': day.isToday }"
        >
          <template v-if="day.allDay.length">
            <div class="task-cal__week-group">
              <AppIcon name="calendar" :size="9" :stroke-width="2.25" />
              Cả ngày
            </div>
            <TaskCalendarChip
              v-for="task in day.allDay"
              :key="`all-${task.id}`"
              :task="task"
              :time="timeLabel(task)"
              :department="chipDept(task)"
              :tone-class="cardToneClass(task)"
              @open="openPeek(task, $event)"
            />
          </template>

          <template v-if="day.timed.length">
            <div class="task-cal__week-group" :class="{ 'task-cal__week-group--sep': day.allDay.length }">
              <AppIcon name="clock" :size="9" :stroke-width="2.25" />
              Theo giờ
            </div>
            <TaskCalendarChip
              v-for="task in day.timed"
              :key="`timed-${task.id}`"
              :task="task"
              :time="timeLabel(task)"
              :department="chipDept(task)"
              :tone-class="cardToneClass(task)"
              @open="openPeek(task, $event)"
            />
          </template>

          <p v-if="!day.items.length" class="task-cal__week-empty">Trống</p>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="peek" class="task-cal-peek" @click.self="closePeek">
        <div class="task-cal-peek__panel" role="dialog" aria-modal="true" :aria-label="peek.title">
          <header class="task-cal-peek__head" :class="`task-cal-peek__head--${calendarTone(peek.status)}`">
            <div class="task-cal-peek__head-main">
              <p class="task-cal-peek__code">{{ peek.code || peek.project?.code || `CV_${peek.id}` }}</p>
              <h3 class="task-cal-peek__title">{{ peek.title }}</h3>
              <span class="task-cal-peek__status">
                <span class="task-cal-peek__status-dot" />
                {{ statusLabel(peek.status) }}
              </span>
            </div>
            <div class="task-cal-peek__actions">
              <button
                v-if="canEdit"
                type="button"
                class="task-cal-peek__icon"
                aria-label="Sửa công việc"
                @click="editFromPeek"
              >
                <AppIcon name="pencil" :size="16" :stroke-width="1.75" />
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="task-cal-peek__icon"
                aria-label="Xoá công việc"
                @click="deleteFromPeek"
              >
                <AppIcon name="trash" :size="16" :stroke-width="1.75" />
              </button>
              <button type="button" class="task-cal-peek__icon" aria-label="Đóng" @click="closePeek">
                <AppIcon name="close" :size="16" :stroke-width="1.75" />
              </button>
            </div>
          </header>

          <div class="task-cal-peek__body hide-scrollbar">
            <section class="task-cal-peek__section">
              <h4 class="task-cal-peek__section-title">Phân loại</h4>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Phòng ban</span>
                <span class="task-cal-peek__value">{{ departmentName(peek) }}</span>
              </div>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Dự án</span>
                <span class="task-cal-peek__value">{{ peek.project?.name || '—' }}</span>
              </div>
            </section>

            <section class="task-cal-peek__section">
              <h4 class="task-cal-peek__section-title">Tiến độ</h4>
              <div class="task-cal-peek__row task-cal-peek__row--progress">
                <DualProgressBar
                  :actual="peek.progress_percent"
                  :expected="computeExpectedProgress(peek.start_date, peek.end_date)"
                  size="md"
                />
              </div>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Ưu tiên</span>
                <span class="task-cal-peek__value task-cal-peek__priority">
                  <AppIcon name="flag" :size="14" :class="`task-cal-peek__flag--${priorityTone(peek.priority)}`" />
                  {{ priorityLabel(peek.priority) }}
                </span>
              </div>
            </section>

            <section class="task-cal-peek__section">
              <h4 class="task-cal-peek__section-title">Thời gian</h4>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Bắt đầu thực tế</span>
                <span class="task-cal-peek__value">{{ formatDate(peek.actual_start_date) }}</span>
              </div>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Kết thúc thực tế</span>
                <span class="task-cal-peek__value">{{ formatDate(peek.actual_end_date) }}</span>
              </div>
            </section>

            <section class="task-cal-peek__section">
              <h4 class="task-cal-peek__section-title">Người liên quan</h4>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Người giao việc</span>
                <span class="task-cal-peek__value task-cal-peek__person">
                  <UserAvatarTip :user="assigner(peek)" label="Người giao việc" />
                  <span>{{ personName(assigner(peek)) }}</span>
                </span>
              </div>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Người thực hiện</span>
                <span class="task-cal-peek__value task-cal-peek__person">
                  <UserAvatarTip :user="peek.assignee" label="Người thực hiện" />
                  <span>{{ personName(peek.assignee) }}</span>
                </span>
              </div>
              <div class="task-cal-peek__row">
                <span class="task-cal-peek__label">Theo dõi/phối hợp thực hiện</span>
                <span class="task-cal-peek__value task-cal-peek__person">
                  <UserAvatarTip v-if="follower(peek)" :user="follower(peek)" label="Người theo dõi" />
                  <span>{{ personName(follower(peek)) }}</span>
                </span>
              </div>
            </section>
          </div>

          <footer class="task-cal-peek__foot">
            <button type="button" class="task-cal-peek__details" @click="inspectFromPeek">
              Xem chi tiết công việc
              <AppIcon name="externalLink" :size="14" :stroke-width="1.75" />
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.task-cal {
  --cal-chip-gap: 0.3rem;
  --cal-cell-h: clamp(10rem, 20vh, 12.5rem);
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.task-cal--month {
  flex: 0 0 auto;
  min-height: auto;
  overflow: visible;
}

@media (min-width: 768px) {
  .task-cal {
    --cal-cell-h: clamp(11.25rem, 22vh, 13.75rem);
  }
}

@media (min-width: 1280px) {
  .task-cal {
    --cal-cell-h: 250px;
  }
}

.task-cal__toolbar {
  position: relative;
  z-index: 12;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0 var(--space-3);
  flex-wrap: wrap;
}

.task-cal__nav-group {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.task-cal__nav {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  min-width: 0;
  padding: 0.1875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-cal__views {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.task-cal__view-mode-host {
  display: flex;
  align-items: center;
}

.task-cal__toggle {
  display: inline-flex;
  align-items: stretch;
  padding: 0.1875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-cal__today,
.task-cal__title,
.task-cal__view-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  height: 1.875rem;
  padding: 0 0.7rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.task-cal__arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.875rem;
  height: 1.875rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.task-cal__today:hover,
.task-cal__arrow:hover,
.task-cal__title:hover,
.task-cal__view-btn:hover {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-cal__arrow:hover {
  color: var(--color-text);
}

.task-cal__title {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.task-cal__title:hover {
  color: var(--color-text);
}

.task-cal__date-input {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
}

.task-cal__view-btn--on {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.task-cal__view-btn--on:hover {
  background: var(--color-surface);
}

.task-cal__hint {
  margin: var(--space-6) 0;
  color: var(--color-text-muted);
  text-align: center;
}

/* ===== MONTH VIEW ===== */
.task-cal__month {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: auto;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-border); /* shows through gap as grid lines */
}

.task-cal--month .task-cal__month {
  flex: 0 0 auto;
  min-height: auto;
  overflow: visible;
}

.task-cal__week {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: auto;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
}

.task-cal__weekdays {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--color-surface);
  box-shadow: 0 2px 0 var(--color-border);
}

.task-cal__weekdays span {
  padding: 0.55rem 0.5rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  box-shadow: 1px 0 0 var(--color-border);
}

.task-cal__weekdays span:last-child {
  box-shadow: none;
}

.task-cal__month-grid {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  grid-template-rows: repeat(6, var(--cal-cell-h));
  align-items: stretch;
  gap: 1px; /* container background shows through as grid lines */
}

.task-cal__cell,
.task-cal__cell--out {
  height: var(--cal-cell-h);
  min-height: var(--cal-cell-h);
  max-height: var(--cal-cell-h);
  min-width: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0.12rem;
  padding: 0.25rem 0.25rem 0.2rem;
  background: var(--color-surface-muted);
}

.task-cal__cell--out {
  background: color-mix(in srgb, var(--color-surface-muted) 78%, var(--color-border));
}

.task-cal__cell--out .task-cal__cell-day {
  color: var(--color-text-muted);
  opacity: 0.55;
}

.task-cal__cell--today {
  background: color-mix(in srgb, var(--color-info-tint-bg) 60%, var(--color-surface));
}

.task-cal__cell-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 0.25rem;
  min-width: 0;
}

.task-cal__cell-cnt {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 1.25rem;
  padding: 0 0.45rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.task-cal__cell--today .task-cal__cell-cnt {
  background: color-mix(in srgb, var(--color-info) 16%, transparent);
  color: var(--color-info-tint-fg);
}

.task-cal__cell-day {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: flex-start;
  min-width: 1.5rem;
  height: 1.5rem;
  padding: 0 0.35rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.task-cal__cell-day:hover {
  background: var(--color-surface-muted);
}

.task-cal__cell--today .task-cal__cell-day {
  background: var(--color-info);
  color: var(--color-on-primary);
  font-weight: 700;
}

.task-cal__cell--today .task-cal__cell-day:hover {
  background: var(--color-info);
  filter: brightness(0.92);
}

.task-cal__cell-list {
  display: flex;
  flex-direction: column;
  flex: 1;
  gap: var(--cal-chip-gap);
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  cursor: grab;
  scrollbar-width: none;
}

.task-cal__cell-list:hover,
.task-cal__cell-list:focus-within,
.task-cal__cell-list.drag-scrolling {
  scrollbar-width: thin;
  scrollbar-color: color-mix(in srgb, var(--color-text-muted) 55%, transparent) transparent;
}

.task-cal__cell-list::-webkit-scrollbar {
  width: 0;
  height: 0;
}

.task-cal__cell-list:hover::-webkit-scrollbar,
.task-cal__cell-list:focus-within::-webkit-scrollbar,
.task-cal__cell-list.drag-scrolling::-webkit-scrollbar {
  width: 6px;
  height: 0;
}

.task-cal__cell-list::-webkit-scrollbar-track {
  background: transparent;
}

.task-cal__cell-list::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--color-text-muted) 45%, transparent);
  border-radius: var(--radius-full);
}

.task-cal__cell-list:hover::-webkit-scrollbar-thumb,
.task-cal__cell-list.drag-scrolling::-webkit-scrollbar-thumb {
  background: color-mix(in srgb, var(--color-text-muted) 70%, transparent);
}

.task-cal__cell-list.drag-scrolling {
  cursor: grabbing;
  user-select: none;
}

/* ===== WEEK VIEW ===== */

/* Shared sticky header row — single z-context, no per-column sticky */
.task-cal__week-head {
  display: grid;
  grid-template-columns: repeat(7, minmax(8.5rem, 1fr));
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 3;
  background: var(--color-surface);
  box-shadow: 0 2px 0 var(--color-border);
}

.task-cal__week-hcol {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
  padding: 0.7rem 0.35rem 0.6rem;
  border: none;
  box-shadow: 1px 0 0 var(--color-border);
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
  cursor: pointer;
  transition: background-color 0.12s ease;
}

.task-cal__week-hcol:last-child {
  box-shadow: none;
}

.task-cal__week-hcol:hover {
  background: var(--color-surface-muted);
}

.task-cal__week-hcol--today {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.task-cal__week-hcol--today:hover {
  background: color-mix(in srgb, var(--color-info-tint-bg) 80%, var(--color-surface-muted));
}

.task-cal__week-dow {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.task-cal__week-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
  transition: background-color 0.12s ease;
}

.task-cal__week-num--today {
  background: var(--color-info);
  color: var(--color-on-primary);
}

.task-cal__week-cnt {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 1.125rem;
  padding: 0 0.4rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.5625rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  white-space: nowrap;
}

.task-cal__week-hcol--today .task-cal__week-cnt {
  background: color-mix(in srgb, var(--color-info) 15%, transparent);
  color: var(--color-info-tint-fg);
}

/* Body grid: 7 columns with right-border separators */
.task-cal__week-body {
  display: grid;
  grid-template-columns: repeat(7, minmax(8.5rem, 1fr));
  align-items: stretch;
  flex: 1;
  min-height: min-content;
}

.task-cal__week-col {
  min-height: 16rem;
  min-width: 0;
  padding: 0.45rem 0.4rem 0.6rem;
  display: flex;
  flex-direction: column;
  gap: 0.28rem;
  box-shadow: 1px 0 0 var(--color-border);
}

.task-cal__week-col:last-child {
  box-shadow: none;
}

.task-cal__week-col--today {
  background: color-mix(in srgb, var(--color-info-tint-bg) 40%, transparent);
}

.task-cal__week-group {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.1rem 0 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.5625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  box-shadow: 0 1px 0 var(--color-border);
  margin-bottom: 0.1rem;
}

.task-cal__week-group--sep {
  margin-top: 0.5rem;
}

.task-cal__week-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  text-align: center;
  opacity: 0.55;
}

.task-cal-peek {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.task-cal-peek__panel {
  position: relative;
  display: flex;
  flex-direction: column;
  width: min(56rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.task-cal-peek__head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-3);
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.task-cal-peek__head-main {
  min-width: 0;
}

.task-cal-peek__code {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.task-cal-peek__title {
  margin: 0.25rem 0 0;
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.task-cal-peek__status {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.task-cal-peek__status-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.task-cal-peek__head--info .task-cal-peek__status { color: var(--color-info); }
.task-cal-peek__head--info .task-cal-peek__status-dot { background: var(--color-info); }

.task-cal-peek__head--tertiary .task-cal-peek__status { color: var(--color-tertiary); }
.task-cal-peek__head--tertiary .task-cal-peek__status-dot { background: var(--color-tertiary); }

.task-cal-peek__head--gold .task-cal-peek__status { color: var(--color-gold); }
.task-cal-peek__head--gold .task-cal-peek__status-dot { background: var(--color-gold); }

.task-cal-peek__head--success .task-cal-peek__status { color: var(--color-success); }
.task-cal-peek__head--success .task-cal-peek__status-dot { background: var(--color-success); }

.task-cal-peek__head--umber .task-cal-peek__status { color: var(--color-umber); }
.task-cal-peek__head--umber .task-cal-peek__status-dot { background: var(--color-umber); }

.task-cal-peek__actions {
  flex-shrink: 0;
  display: flex;
  gap: 0.15rem;
}

.task-cal-peek__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.task-cal-peek__icon:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.task-cal-peek__body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-content: start;
  gap: var(--space-3);
  overflow: auto;
  padding: var(--space-4);
}

.task-cal-peek__section {
  min-width: 0;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-cal-peek__section + .task-cal-peek__section {
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-cal-peek__section-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.task-cal-peek__row {
  display: grid;
  grid-template-columns: minmax(7rem, 40%) minmax(0, 1fr);
  align-items: center;
  gap: var(--space-3);
  padding: 0.55rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-cal-peek__row:last-child {
  box-shadow: none;
}

.task-cal-peek__row--progress {
  align-items: stretch;
  grid-template-columns: minmax(0, 1fr);
}

.task-cal-peek__label {
  min-width: 0;
  color: var(--color-text-muted);
}

.task-cal-peek__label::after {
  content: ':';
}

.task-cal-peek__value {
  min-width: 0;
  color: var(--color-text);
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

.task-cal-peek__person,
.task-cal-peek__priority {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.4rem;
}

.task-cal-peek__flag--info { color: var(--color-info); }
.task-cal-peek__flag--gold { color: var(--color-gold); }
.task-cal-peek__flag--danger { color: var(--color-danger); }
.task-cal-peek__flag--neutral { color: var(--color-text-muted); }

.task-cal-peek__foot {
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4) var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.task-cal-peek__details {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  background: transparent;
  color: var(--color-info-tint-fg);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}

@media (max-width: 720px) {
  .task-cal-peek {
    padding: var(--space-3);
  }

  .task-cal-peek__panel {
    width: calc(100vw - 1.5rem);
    max-height: calc(100vh - 1.5rem);
  }

  .task-cal-peek__body {
    grid-template-columns: minmax(0, 1fr);
    padding: var(--space-3);
  }
}

/* -------- Bộ lọc lịch — nằm trong .task-cal__views, cùng hàng toolbar -------- */
.task-cal__filter {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.1875rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-cal__filter-modes {
  display: flex;
  align-items: center;
  gap: 0.1rem;
}

.task-cal__filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  height: 1.875rem;
  padding: 0 0.7rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.task-cal__filter-pill:hover {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-text);
}

.task-cal__filter-pill--on {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.task-cal__filter-pill--on:hover {
  background: var(--color-surface);
}

.task-cal__filter-pill--arrow {
  padding-right: 0.5rem;
}

.task-cal__filter-drop {
  position: relative;
}

.task-cal__filter-menu {
  position: absolute;
  top: calc(100% + 0.3rem);
  left: 0;
  z-index: 25;
  min-width: 13rem;
  max-height: 18rem;
  overflow-y: auto;
  padding: 0.3rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
}

.task-cal__filter-menu--right {
  left: auto;
  right: 0;
}

.task-cal__filter-menu-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.4rem 0.5rem;
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

.task-cal__filter-menu-item:hover {
  background: var(--color-surface-muted);
}

.task-cal__filter-menu-item--on {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.task-cal__filter-menu-item--check {
  cursor: pointer;
  user-select: none;
}

.task-cal__filter-menu-avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 1.375rem;
  height: 1.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.5625rem;
  font-weight: 700;
}

.task-cal__filter-menu-check {
  margin-left: auto;
  color: var(--color-info-tint-fg);
}

.task-cal__filter-check {
  width: 0.875rem;
  height: 0.875rem;
  flex-shrink: 0;
  accent-color: var(--color-info);
  cursor: pointer;
}

.task-cal__filter-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0 0.25rem;
}

.task-cal__filter-search {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.5rem;
  margin-bottom: 0.2rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-cal__filter-search-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.task-cal__filter-search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  outline: none;
}

.task-cal__filter-search-input::placeholder {
  color: var(--color-text-muted);
}

.task-cal__filter-empty {
  margin: 0;
  padding: 0.6rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  text-align: center;
}

</style>
