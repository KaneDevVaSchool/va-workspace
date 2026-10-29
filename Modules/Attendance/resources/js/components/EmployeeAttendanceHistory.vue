<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  buildAttendanceMonth,
  buildAttendanceWeekGrid,
  startOfWeekMonday,
} from '../lib/employeeAttendanceHistory';
import { groupItemsByWeek } from '../lib/employeeHistoryWeek';
import { readEmployeeAttendanceLog } from '../lib/employeeAttendanceSession';

const WEEKDAY_HEADERS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
const WEEKDAY_SHORT = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
const WEEKDAY_LONG = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

const VIEW_STORAGE_KEY = 'employee-attendance-history-view';

const auth = useAuthStore();
const now = new Date();
const viewMonth = ref(new Date(now.getFullYear(), now.getMonth(), 1));
const viewWeekStart = ref(startOfWeekMonday(now));
const punchLog = ref(readEmployeeAttendanceLog());

const layoutMode = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(VIEW_STORAGE_KEY) === 'calendar'
    ? 'calendar'
    : 'list',
);

watch(layoutMode, (mode) => {
  if (typeof localStorage === 'undefined') return;
  localStorage.setItem(VIEW_STORAGE_KEY, mode);
});

const placeCaption = computed(() => {
  const company = auth.user?.company?.name?.trim();
  const department = auth.user?.department?.name?.trim();
  if (department && company) return `${department} · ${company}`;
  return department || company || '';
});

const monthLabel = computed(() => `Tháng ${viewMonth.value.getMonth() + 1}/${viewMonth.value.getFullYear()}`);

const canNextMonth = computed(() => {
  const cursor = viewMonth.value;
  const today = new Date();
  return cursor.getFullYear() < today.getFullYear()
    || (cursor.getFullYear() === today.getFullYear() && cursor.getMonth() < today.getMonth());
});

const monthModel = computed(() => buildAttendanceMonth(viewMonth.value, punchLog.value));

const weekModel = computed(() => buildAttendanceWeekGrid(viewWeekStart.value, punchLog.value));

const weekGroups = computed(() =>
  groupItemsByWeek(monthModel.value.rows, (row) => row.date ?? null),
);

const canNextWeek = computed(() => {
  const thisWeek = startOfWeekMonday(new Date());
  return viewWeekStart.value.getTime() < thisWeek.getTime();
});

const isCurrentWeek = computed(() => {
  const thisWeek = startOfWeekMonday(new Date());
  return viewWeekStart.value.getTime() === thisWeek.getTime();
});

const isCurrentMonth = computed(() => {
  const today = new Date();
  return viewMonth.value.getFullYear() === today.getFullYear()
    && viewMonth.value.getMonth() === today.getMonth();
});

const navLabel = computed(() => (
  layoutMode.value === 'list' ? monthLabel.value : weekModel.value.weekLabel
));

const canGoForward = computed(() => (
  layoutMode.value === 'list' ? canNextMonth.value : canNextWeek.value
));

const showJump = computed(() => (
  layoutMode.value === 'list' ? !isCurrentMonth.value : !isCurrentWeek.value
));

const jumpLabel = computed(() => (
  layoutMode.value === 'list' ? 'Tháng này' : 'Tuần này'
));

const summaryStats = computed(() => {
  if (layoutMode.value === 'calendar') {
    const stats = weekModel.value.stats;
    return [
      { label: 'Đã chấm', value: stats.recorded, tone: 'info' },
      { label: 'Đúng giờ', value: stats.onTime, tone: 'ok' },
      { label: 'Trễ', value: stats.late, tone: 'warn' },
    ];
  }
  const stats = monthModel.value.stats;
  return [
    { label: 'Đã chấm', value: stats.recorded, tone: 'info' },
    { label: 'Đúng giờ', value: stats.onTime, tone: 'ok' },
    { label: 'Trễ', value: stats.late, tone: 'warn' },
    { label: 'Thiếu', hint: 'trên máy', value: stats.absent, tone: 'miss' },
  ];
});

const selectedDayKey = ref('');

const selectedCell = computed(() => (
  weekModel.value.days.find((day) => day.key === selectedDayKey.value) ?? null
));

function pickDefaultDay(days) {
  const today = days.find((day) => day.isToday);
  if (today) return today.key;
  const latestWork = [...days].reverse().find((day) => day.row?.kind === 'work');
  if (latestWork) return latestWork.key;
  const past = [...days].reverse().find((day) => !day.isFuture);
  return past?.key ?? days[0]?.key ?? '';
}

watch(
  () => `${layoutMode.value}:${weekModel.value.days.map((day) => day.key).join(',')}`,
  () => {
    if (layoutMode.value !== 'calendar') return;
    const days = weekModel.value.days;
    if (days.some((day) => day.key === selectedDayKey.value)) return;
    selectedDayKey.value = pickDefaultDay(days);
  },
  { immediate: true },
);

function shiftMonth(delta) {
  const cursor = viewMonth.value;
  viewMonth.value = new Date(cursor.getFullYear(), cursor.getMonth() + delta, 1);
}

function shiftWeek(delta) {
  const cursor = viewWeekStart.value;
  const next = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + delta * 7);
  viewWeekStart.value = startOfWeekMonday(next);
}

function goCurrentWeek() {
  viewWeekStart.value = startOfWeekMonday(new Date());
}

function goCurrentMonth() {
  const today = new Date();
  viewMonth.value = new Date(today.getFullYear(), today.getMonth(), 1);
}

function shiftPeriod(delta) {
  if (layoutMode.value === 'list') shiftMonth(delta);
  else shiftWeek(delta);
}

function jumpToNow() {
  if (layoutMode.value === 'list') goCurrentMonth();
  else goCurrentWeek();
}

function shortMethod(value) {
  const text = String(value || '').trim();
  if (!text) return '';
  const stripped = text.replace(/^Chấm bằng\s+/i, '').replace(/^Chấm\s+/i, '');
  if (!stripped) return text;
  return stripped.charAt(0).toUpperCase() + stripped.slice(1);
}

function methodCaption(row) {
  const inbound = shortMethod(row?.methodIn);
  const outbound = shortMethod(row?.methodOut);
  if (inbound && outbound && inbound !== outbound) return `${inbound} · ${outbound}`;
  return inbound || outbound || '';
}

function dayParts(date) {
  const value = date instanceof Date ? date : new Date(date);
  return {
    num: value.getDate(),
    dow: WEEKDAY_SHORT[value.getDay()] ?? '',
  };
}

function dayAria(row) {
  if (row.kind === 'pending') return `${row.title}. Chưa chấm công`;
  const status = row.late ? 'Trễ' : 'Đúng giờ';
  return `${row.title}. Vào ${row.checkIn || 'chưa có'}, ra ${row.checkOut || 'chưa có'}. ${status}`;
}

function cellAria(cell) {
  const dateText = cell.date.toLocaleDateString('vi-VN');
  if (cell.row?.kind === 'work') {
    const status = cell.row.late ? 'trễ' : 'đúng giờ';
    return `${dateText}, vào ${cell.row.checkIn || 'chưa có'}, ra ${cell.row.checkOut || 'chưa có'}, ${status}`;
  }
  if (cell.row?.kind === 'pending') return `${dateText}, chưa chấm công`;
  if (cell.weekday === 0) return `${dateText}, chủ nhật`;
  if (cell.isFuture) return dateText;
  return `${dateText}, chưa có bản ghi`;
}

function selectedHeading(cell) {
  if (!cell) return '';
  const name = WEEKDAY_LONG[cell.date.getDay()] ?? '';
  if (cell.isToday) return `Hôm nay · ${name}`;
  return `${name} · ${cell.date.toLocaleDateString('vi-VN')}`;
}

function reload() {
  punchLog.value = readEmployeeAttendanceLog();
}

defineExpose({ reload });
</script>

<template>
  <div class="att-history">
    <div class="att-history__bar">
      <div class="att-history__nav">
        <button
          type="button"
          :aria-label="layoutMode === 'list' ? 'Tháng trước' : 'Tuần trước'"
          @click="shiftPeriod(-1)"
        >
          <AppIcon name="chevronLeft" :size="18" />
        </button>
        <div class="att-history__nav-label">
          <strong>{{ navLabel }}</strong>
          <button
            v-if="showJump"
            type="button"
            class="att-history__jump"
            @click="jumpToNow"
          >
            {{ jumpLabel }}
          </button>
        </div>
        <button
          type="button"
          :aria-label="layoutMode === 'list' ? 'Tháng sau' : 'Tuần sau'"
          :disabled="!canGoForward"
          @click="shiftPeriod(1)"
        >
          <AppIcon name="chevronRight" :size="18" />
        </button>
      </div>

      <div class="att-history__modes" role="tablist" aria-label="Cách xem lịch sử">
        <button
          type="button"
          role="tab"
          class="att-history__mode"
          :class="{ 'att-history__mode--active': layoutMode === 'list' }"
          :aria-selected="layoutMode === 'list'"
          @click="layoutMode = 'list'"
        >
          Danh sách
        </button>
        <button
          type="button"
          role="tab"
          class="att-history__mode"
          :class="{ 'att-history__mode--active': layoutMode === 'calendar' }"
          :aria-selected="layoutMode === 'calendar'"
          @click="layoutMode = 'calendar'"
        >
          Tuần
        </button>
      </div>
    </div>

    <section
      class="att-history__stats"
      :class="{ 'is-week': layoutMode === 'calendar' }"
      :aria-label="layoutMode === 'list' ? 'Tóm tắt tháng' : 'Tóm tắt tuần'"
    >
      <div v-for="stat in summaryStats" :key="stat.label" class="att-history__stat">
        <strong :class="stat.value > 0 ? `is-${stat.tone}` : ''">{{ stat.value }}</strong>
        <span>
          {{ stat.label }}
          <small v-if="stat.hint">{{ stat.hint }}</small>
        </span>
      </div>
    </section>

    <p v-if="placeCaption" class="att-history__place">{{ placeCaption }}</p>

    <div v-if="layoutMode === 'list' && monthModel.rows.length === 0" class="att-history__empty">
      <span class="att-history__empty-icon" aria-hidden="true">
        <AppIcon name="clock" :size="22" />
      </span>
      <p>Tháng này chưa có bản ghi chấm công trên máy bạn.</p>
    </div>

    <template v-else-if="layoutMode === 'list'">
      <section
        v-for="group in weekGroups"
        :key="group.key"
        class="att-history__week"
        :aria-label="`Tuần ${group.weekNum}`"
      >
        <header class="att-history__week-head">
          <span class="att-history__week-title">Tuần {{ group.weekNum }}</span>
          <span class="att-history__week-range">{{ group.label }}</span>
        </header>

        <article
          v-for="row in group.items"
          :key="row.key"
          class="att-history__day"
          :class="{
            'att-history__day--pending': row.kind === 'pending',
            'att-history__day--late': row.kind === 'work' && row.late,
          }"
          :aria-label="dayAria(row)"
        >
          <div class="att-history__date">
            <strong>{{ dayParts(row.date).num }}</strong>
            <span>{{ dayParts(row.date).dow }}</span>
          </div>
          <p v-if="row.kind === 'pending'" class="att-history__pending">Chưa chấm công hôm nay</p>
          <template v-else>
            <div class="att-history__span">
              <p class="att-history__times">
                <span :class="{ 'is-late': row.late }">{{ row.checkIn || '—' }}</span>
                <i aria-hidden="true"></i>
                <span :class="{ 'is-missing': !row.checkOut }">{{ row.checkOut || '—' }}</span>
              </p>
              <p v-if="methodCaption(row)" class="att-history__method">{{ methodCaption(row) }}</p>
            </div>
            <span class="att-history__pill" :class="row.late ? 'is-late' : 'is-ok'">
              {{ row.late ? 'Trễ' : 'Đúng giờ' }}
            </span>
          </template>
        </article>
      </section>
    </template>

    <template v-else>
      <div class="att-history__cal" role="grid" aria-label="Lịch chấm công theo tuần">
        <div class="att-history__dows" role="row">
          <span
            v-for="label in WEEKDAY_HEADERS"
            :key="label"
            role="columnheader"
          >
            {{ label }}
          </span>
        </div>
        <div class="att-history__cells" role="row">
          <button
            v-for="cell in weekModel.days"
            :key="cell.key"
            type="button"
            class="att-history__cell"
            :class="{
              'is-today': cell.isToday,
              'is-selected': cell.key === selectedDayKey,
              'is-future': cell.isFuture,
              'is-late': cell.row?.kind === 'work' && cell.row.late,
              'is-ok': cell.row?.kind === 'work' && !cell.row.late,
              'is-pending': cell.row?.kind === 'pending',
              'is-off': cell.weekday === 0 && cell.row?.kind !== 'work',
            }"
            role="gridcell"
            :aria-label="cellAria(cell)"
            :aria-pressed="cell.key === selectedDayKey"
            :disabled="cell.isFuture"
            @click="selectedDayKey = cell.key"
          >
            <em>{{ cell.dayNum }}</em>
            <i aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <article v-if="selectedCell" class="att-history__detail" :aria-label="selectedHeading(selectedCell)">
        <header class="att-history__detail-head">
          <h3>{{ selectedHeading(selectedCell) }}</h3>
          <span
            v-if="selectedCell.row?.kind === 'work'"
            class="att-history__pill"
            :class="selectedCell.row.late ? 'is-late' : 'is-ok'"
          >
            {{ selectedCell.row.late ? 'Trễ' : 'Đúng giờ' }}
          </span>
          <span v-else-if="selectedCell.row?.kind === 'pending'" class="att-history__pill is-pending">
            Chưa chấm
          </span>
        </header>

        <div v-if="selectedCell.row?.kind === 'work'" class="att-history__pair">
          <div>
            <small>Vào</small>
            <strong :class="{ 'is-late': selectedCell.row.late }">{{ selectedCell.row.checkIn || '—' }}</strong>
          </div>
          <div>
            <small>Ra</small>
            <strong :class="{ 'is-missing': !selectedCell.row.checkOut }">{{ selectedCell.row.checkOut || '—' }}</strong>
          </div>
        </div>
        <p v-else-if="selectedCell.row?.kind === 'pending'" class="att-history__note">
          Chưa chấm công hôm nay.
        </p>
        <p v-else-if="selectedCell.weekday === 0" class="att-history__note">
          Chủ nhật, không tính công.
        </p>
        <p v-else class="att-history__note">
          Không có bản ghi trên máy.
        </p>
        <p
          v-if="selectedCell.row?.kind === 'work' && methodCaption(selectedCell.row)"
          class="att-history__method"
        >
          {{ methodCaption(selectedCell.row) }}
        </p>
      </article>
    </template>
  </div>
</template>

<style scoped>
.att-history {
  --att-card-shadow: 0 10px 28px color-mix(in srgb, var(--color-tertiary-900) 8%, transparent);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.att-history__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.att-history__nav,
.att-history__modes,
.att-history__stats,
.att-history__week,
.att-history__cal,
.att-history__detail,
.att-history__empty {
  background: var(--color-surface);
  border: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
  box-shadow: var(--att-card-shadow);
}

.att-history__nav {
  display: flex;
  flex: 1 1 12rem;
  align-items: center;
  justify-content: space-between;
  gap: 2px;
  min-width: 0;
  padding: 4px;
  border-radius: 18px;
}

.att-history__nav > button {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.att-history__nav > button:disabled {
  opacity: 0.28;
  cursor: default;
}

.att-history__nav > button:focus-visible,
.att-history__mode:focus-visible,
.att-history__jump:focus-visible,
.att-history__cell:focus-visible {
  outline: 2px solid var(--color-tertiary-500);
  outline-offset: 2px;
}

.att-history__nav-label {
  min-width: 0;
  text-align: center;
}

.att-history__nav-label strong {
  display: block;
  overflow: hidden;
  font-size: 0.84rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.att-history__jump {
  margin-top: 1px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--color-tertiary-700);
  font-family: inherit;
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
}

.att-history__modes {
  display: flex;
  flex-shrink: 0;
  gap: 2px;
  margin-left: auto;
  padding: 4px;
  border-radius: 18px;
}

.att-history__mode {
  height: 2.25rem;
  padding: 0 0.75rem;
  border: 0;
  border-radius: 14px;
  background: transparent;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}

.att-history__mode--active {
  background: var(--color-tertiary-50);
  color: var(--color-tertiary-700);
}

.att-history__stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  padding: 0.9rem 0.15rem;
  border-radius: 18px;
}

.att-history__stats.is-week {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.att-history__stat {
  padding: 0.1rem 0.35rem;
  text-align: center;
}

.att-history__stat:not(:first-child) {
  box-shadow: inset 1px 0 0 var(--color-border);
}

.att-history__stat strong {
  display: block;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
  line-height: 1;
}

.att-history__stat strong.is-info {
  color: var(--color-tertiary-700);
}

.att-history__stat strong.is-ok {
  color: var(--color-success-tint-fg);
}

.att-history__stat strong.is-warn {
  color: var(--color-warning-tint-fg);
}

.att-history__stat strong.is-miss {
  color: var(--color-danger-tint-fg);
}

.att-history__stat span {
  display: block;
  margin-top: 4px;
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 600;
}

.att-history__stat small {
  display: block;
  margin-top: 1px;
  font-size: 0.62rem;
  font-weight: 500;
}

.att-history__place {
  margin: -4px 2px 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.att-history__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  border-radius: 18px;
  text-align: center;
}

.att-history__empty-icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-50);
  color: var(--color-tertiary-700);
}

.att-history__empty p {
  max-width: 16rem;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.84rem;
  line-height: 1.5;
}

.att-history__week {
  overflow: hidden;
  border-radius: 18px;
}

.att-history__week-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.75rem 0.9rem 0.65rem;
}

.att-history__week-title {
  color: var(--color-text);
  font-size: 0.78rem;
  font-weight: 700;
}

.att-history__week-range {
  color: var(--color-text-muted);
  font-size: 0.72rem;
  font-weight: 600;
}

.att-history__day {
  display: grid;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto;
  gap: 0.65rem;
  align-items: center;
  padding: 0.8rem 0.9rem;
  border-top: 1px solid color-mix(in srgb, var(--color-border) 85%, transparent);
}

.att-history__day--pending {
  grid-template-columns: 2.5rem minmax(0, 1fr);
  background: color-mix(in srgb, var(--color-warning-tint-bg) 70%, var(--color-surface));
}

.att-history__date {
  text-align: center;
}

.att-history__date strong {
  display: block;
  font-size: 1.05rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  line-height: 1;
}

.att-history__date span {
  display: block;
  margin-top: 3px;
  color: var(--color-text-muted);
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.att-history__span {
  min-width: 0;
}

.att-history__times {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0;
  color: var(--color-text);
  font-size: 1.02rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
}

.att-history__times i {
  flex-shrink: 0;
  width: 0.7rem;
  height: 1px;
  background: var(--color-border-strong);
}

.att-history__times .is-late {
  color: var(--color-warning-tint-fg);
}

.att-history__times .is-missing {
  color: var(--color-text-muted);
  font-weight: 600;
}

.att-history__method {
  margin: 2px 0 0;
  color: var(--color-text-muted);
  font-size: 0.72rem;
  font-weight: 500;
}

.att-history__pending {
  margin: 0;
  color: var(--color-warning-tint-fg);
  font-size: 0.84rem;
  font-weight: 600;
}

.att-history__pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  border-radius: var(--radius-full);
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
}

.att-history__pill.is-ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.att-history__pill.is-late {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.att-history__pill.is-pending {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.att-history__cal {
  padding: 0.85rem 0.65rem 0.75rem;
  border-radius: 18px;
}

.att-history__dows,
.att-history__cells {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}

.att-history__dows span {
  color: var(--color-text-muted);
  font-size: 0.66rem;
  font-weight: 700;
  text-align: center;
}

.att-history__cells {
  gap: 4px;
  margin-top: 6px;
}

.att-history__cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-height: 3.15rem;
  padding: 0.35rem 0;
  border: 0;
  border-radius: 14px;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.att-history__cell em {
  color: var(--color-text);
  font-style: normal;
  font-size: 0.92rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.att-history__cell i {
  width: 5px;
  height: 5px;
  border-radius: var(--radius-full);
  background: transparent;
}

.att-history__cell.is-ok i {
  background: var(--color-success);
}

.att-history__cell.is-late i {
  background: var(--color-warning);
}

.att-history__cell.is-pending i {
  background: transparent;
  box-shadow: inset 0 0 0 1.5px var(--color-tertiary-500);
}

.att-history__cell.is-selected {
  background: var(--color-tertiary-50);
}

.att-history__cell.is-today em {
  color: var(--color-tertiary-700);
}

.att-history__cell.is-off em,
.att-history__cell.is-future em {
  color: var(--color-text-muted);
}

.att-history__cell.is-future,
.att-history__cell:disabled {
  cursor: default;
}

.att-history__cell.is-future {
  opacity: 0.4;
}

.att-history__detail {
  padding: 0.9rem;
  border-radius: 18px;
}

.att-history__detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.att-history__detail-head h3 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.att-history__pair {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

.att-history__pair div {
  padding: 0.7rem 0.75rem;
  border-radius: 14px;
  background: var(--color-surface-muted);
}

.att-history__pair small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.68rem;
  font-weight: 700;
}

.att-history__pair strong {
  display: block;
  margin-top: 2px;
  color: var(--color-tertiary-900);
  font-size: 1.35rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.04em;
}

.att-history__pair strong.is-late {
  color: var(--color-warning-tint-fg);
}

.att-history__pair strong.is-missing {
  color: var(--color-text-muted);
  font-weight: 700;
}

.att-history__note {
  margin: var(--space-3) 0 0;
  color: var(--color-text-muted);
  font-size: 0.84rem;
  line-height: 1.45;
}

.att-history__detail .att-history__method {
  margin-top: var(--space-2);
}

@media (hover: hover) {
  .att-history__nav > button:not(:disabled):hover,
  .att-history__mode:hover {
    background: var(--color-surface-muted);
  }

  .att-history__mode--active:hover {
    background: var(--color-tertiary-50);
  }

  .att-history__cell:not(:disabled):hover {
    background: color-mix(in srgb, var(--color-tertiary-50) 65%, var(--color-surface));
  }

  .att-history__cell.is-selected:hover {
    background: var(--color-tertiary-50);
  }
}

</style>
