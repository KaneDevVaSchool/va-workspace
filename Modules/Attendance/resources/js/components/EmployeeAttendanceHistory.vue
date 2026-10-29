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

const WEEKDAY_HEADERS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
import { readEmployeeAttendanceLog } from '../lib/employeeAttendanceSession';

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

const placeLabel = computed(() => {
  const company = auth.user?.company?.name?.trim();
  const department = auth.user?.department?.name?.trim();
  if (department && company) return `${department} · ${company}`;
  return department || company || 'Văn phòng làm việc';
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

const emptyMonth = computed(() =>
  layoutMode.value === 'list'
  && monthModel.value.rows.length === 0
  && !monthModel.value.stats.absent,
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

function reload() {
  punchLog.value = readEmployeeAttendanceLog();
}

defineExpose({ reload });
</script>

<template>
  <div class="att-history">
    <div class="att-history__toolbar">
      <div class="att-history__modes" role="tablist" aria-label="Cách xem lịch sử">
        <button
          type="button"
          role="tab"
          class="att-history__mode"
          :class="{ 'att-history__mode--active': layoutMode === 'list' }"
          :aria-selected="layoutMode === 'list'"
          @click="layoutMode = 'list'"
        >
          <AppIcon name="listBullet" :size="16" />
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
          <AppIcon name="calendar" :size="16" />
          Lịch tuần
        </button>
      </div>

      <div v-if="layoutMode === 'list'" class="att-history__nav">
        <button type="button" aria-label="Tháng trước" @click="shiftMonth(-1)">
          <AppIcon name="chevronLeft" :size="18" />
        </button>
        <span>{{ monthLabel }}</span>
        <button type="button" aria-label="Tháng sau" :disabled="!canNextMonth" @click="shiftMonth(1)">
          <AppIcon name="chevronRight" :size="18" />
        </button>
      </div>

      <div v-else class="att-history__nav att-history__nav--week">
        <button type="button" aria-label="Tuần trước" @click="shiftWeek(-1)">
          <AppIcon name="chevronLeft" :size="18" />
        </button>
        <span class="att-history__week-label">{{ weekModel.weekLabel }}</span>
        <button type="button" aria-label="Tuần sau" :disabled="!canNextWeek" @click="shiftWeek(1)">
          <AppIcon name="chevronRight" :size="18" />
        </button>
      </div>
      <button
        v-if="layoutMode === 'calendar' && !isCurrentWeek"
        type="button"
        class="att-history__today"
        @click="goCurrentWeek"
      >
        Tuần này
      </button>
    </div>

    <div v-if="layoutMode === 'list'" class="att-history__stats">
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--info">
          <AppIcon name="calendar" :size="16" />
        </span>
        <span><small>Đã chấm</small><strong>{{ monthModel.stats.recorded }} ngày</strong></span>
      </article>
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--ok">
          <AppIcon name="check" :size="16" />
        </span>
        <span><small>Đúng giờ</small><strong>{{ monthModel.stats.onTime }} ngày</strong></span>
      </article>
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--warn">
          <AppIcon name="clock" :size="16" />
        </span>
        <span><small>Trễ</small><strong>{{ monthModel.stats.late }} ngày</strong></span>
      </article>
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--miss">
          <AppIcon name="close" :size="16" />
        </span>
        <span><small>Thiếu trên máy</small><strong>{{ monthModel.stats.absent }} ngày</strong></span>
      </article>
    </div>

    <div v-else class="att-history__stats att-history__stats--week">
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--info">
          <AppIcon name="calendar" :size="16" />
        </span>
        <span><small>Tuần này</small><strong>{{ weekModel.stats.recorded }} ngày chấm</strong></span>
      </article>
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--warn">
          <AppIcon name="clock" :size="16" />
        </span>
        <span><small>Trễ</small><strong>{{ weekModel.stats.late }}</strong></span>
      </article>
    </div>

    <p v-if="emptyMonth" class="att-history__empty-month">
      Tháng này chưa có bản ghi chấm công trên máy bạn.
    </p>

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
            'att-history__day--ok': row.kind === 'work' && !row.late,
          }"
        >
          <h3><span>{{ row.title }}</span></h3>
          <p v-if="row.kind === 'pending'" class="att-history__empty">Chưa chấm công hôm nay</p>
          <ul v-else>
            <li v-if="row.checkIn">
              <span class="att-history__mark att-history__mark--in">
                <AppIcon name="clock" :size="14" />
              </span>
              <span>
                <b>{{ row.checkIn }}</b>
                <small>{{ row.methodIn || 'Chấm công' }}</small>
                <small>{{ placeLabel }}</small>
              </span>
              <em :class="row.late ? 'is-late' : 'is-ok'">{{ row.late ? 'Trễ' : 'Đúng giờ' }}</em>
            </li>
            <li v-if="row.checkOut">
              <span class="att-history__mark att-history__mark--out">
                <AppIcon name="clock" :size="14" />
              </span>
              <span>
                <b>{{ row.checkOut }}</b>
                <small>{{ row.methodOut || 'Chấm công' }}</small>
                <small>{{ placeLabel }}</small>
              </span>
              <em class="is-ok">Ra ca</em>
            </li>
          </ul>
        </article>
      </section>
    </template>

    <div v-else class="att-week-cal" role="grid" aria-label="Lịch chấm công theo tuần">
      <div class="att-week-cal__head" role="row">
        <span
          v-for="label in WEEKDAY_HEADERS"
          :key="label"
          class="att-week-cal__dow"
          role="columnheader"
        >
          {{ label }}
        </span>
      </div>
      <div class="att-week-cal__cells" role="row">
        <button
          v-for="cell in weekModel.days"
          :key="cell.key"
          type="button"
          class="att-week-cal__cell"
          :class="{
            'att-week-cal__cell--today': cell.isToday,
            'att-week-cal__cell--future': cell.isFuture,
            'att-week-cal__cell--work': cell.row?.kind === 'work',
            'att-week-cal__cell--late': cell.row?.kind === 'work' && cell.row.late,
            'att-week-cal__cell--pending': cell.row?.kind === 'pending',
            'att-week-cal__cell--off': cell.weekday === 0,
          }"
          :disabled="cell.isFuture"
        >
          <span class="att-week-cal__date">
            <em>{{ cell.dayNum }}</em>
            <small v-if="cell.month !== viewWeekStart.getMonth() + 1">{{ cell.month }}/</small>
          </span>
          <span v-if="cell.row?.kind === 'pending'" class="att-week-cal__hint">Hôm nay</span>
          <span v-else-if="cell.row?.kind === 'work'" class="att-week-cal__times">
            <span v-if="cell.row.checkIn">{{ cell.row.checkIn }}</span>
            <span v-if="cell.row.checkOut">{{ cell.row.checkOut }}</span>
          </span>
          <span v-else-if="cell.weekday === 0" class="att-week-cal__hint att-week-cal__hint--muted">Nghỉ</span>
          <span v-else-if="!cell.isFuture" class="att-week-cal__hint att-week-cal__hint--muted">—</span>
        </button>
      </div>
    </div>

    <ul v-if="layoutMode === 'calendar'" class="att-week-detail hide-scrollbar">
      <li
        v-for="cell in weekModel.days.filter((d) => d.row?.kind === 'work' || d.row?.kind === 'pending')"
        :key="`detail-${cell.key}`"
        class="att-week-detail__item"
      >
        <span class="att-week-detail__when">
          {{ cell.row.title }}
        </span>
        <template v-if="cell.row.kind === 'pending'">
          <span class="att-week-detail__muted">Chưa chấm</span>
        </template>
        <template v-else>
          <span class="att-week-detail__line">
            Vào <b>{{ cell.row.checkIn || '—' }}</b>
            · Ra <b>{{ cell.row.checkOut || '—' }}</b>
          </span>
        </template>
      </li>
      <li
        v-if="!weekModel.days.some((d) => d.row?.kind === 'work' || d.row?.kind === 'pending')"
        class="att-week-detail__empty"
      >
        Tuần này chưa có chấm công trên máy.
      </li>
    </ul>
  </div>
</template>

<style scoped>
.att-history {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.att-history__toolbar {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.att-history__modes {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-1);
  padding: 3px;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.att-history__mode {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.45rem 0.5rem;
  border: none;
  border-radius: calc(var(--radius-lg) - 3px);
  background: transparent;
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.att-history__mode--active {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-text);
}

.att-history__nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.att-history__nav--week .att-history__week-label {
  text-align: center;
  font-size: 0.8125rem;
  line-height: 1.35;
}

.att-history__nav button {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.att-history__nav button:disabled {
  opacity: 0.35;
  cursor: default;
}

.att-history__today {
  align-self: center;
  padding: 0.35rem 0.85rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.att-history__stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
}

.att-history__stats--week {
  grid-template-columns: 1fr 1fr;
}

.att-history__stats article {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.att-history__stats small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
}

.att-history__stats strong {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.att-history__stat-icon {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
  flex-shrink: 0;
}

.att-history__stat-icon--info {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.att-history__stat-icon--ok {
  background: color-mix(in srgb, var(--color-success) 14%, var(--color-surface));
  color: var(--color-success);
}

.att-history__stat-icon--warn {
  background: color-mix(in srgb, var(--color-warning) 14%, var(--color-surface));
  color: var(--color-warning);
}

.att-history__stat-icon--miss {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.att-history__empty-month {
  margin: 0;
  padding: var(--space-6) var(--space-3);
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--color-text-muted);
  text-align: center;
}

.att-history__week {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.att-history__week-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0 var(--space-1);
}

.att-history__week-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.att-history__week-range {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

.att-history__day {
  position: relative;
  padding: var(--space-3) var(--space-3) var(--space-3)
    calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.att-history__day::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.att-history__day--ok::before {
  background: var(--color-success);
}

.att-history__day--late::before,
.att-history__day--pending::before {
  background: var(--color-warning);
}

.att-history__day h3 {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.att-history__day ul {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.att-history__day li {
  display: grid;
  grid-template-columns: 1.6rem minmax(0, 1fr) auto;
  gap: var(--space-2);
  align-items: start;
}

.att-history__day b {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.att-history__day small {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.att-history__day em {
  font-style: normal;
  font-size: 0.6875rem;
  font-weight: 600;
}

.att-history__day em.is-ok {
  color: var(--color-success);
}

.att-history__day em.is-late {
  color: var(--color-warning);
}

.att-history__mark {
  display: grid;
  place-items: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: var(--radius-full);
  color: var(--color-on-tertiary);
}

.att-history__mark--in {
  background: var(--color-success);
}

.att-history__mark--out {
  background: var(--color-tertiary);
}

.att-history__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.att-week-cal {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.att-week-cal__head {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 2px;
}

.att-week-cal__dow {
  text-align: center;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.att-week-cal__cells {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: var(--space-1);
}

.att-week-cal__cell {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.25rem;
  min-height: 4.25rem;
  padding: 0.35rem 0.25rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  text-align: center;
  cursor: default;
}

.att-week-cal__cell--today {
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.att-week-cal__cell--work {
  background: color-mix(in srgb, var(--color-success) 10%, var(--color-surface));
}

.att-week-cal__cell--late {
  background: color-mix(in srgb, var(--color-warning) 12%, var(--color-surface));
}

.att-week-cal__cell--pending {
  background: color-mix(in srgb, var(--color-warning) 8%, var(--color-surface));
}

.att-week-cal__cell--off {
  opacity: 0.55;
}

.att-week-cal__cell--future {
  opacity: 0.4;
}

.att-week-cal__cell:disabled {
  cursor: default;
}

.att-week-cal__date {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 1px;
  line-height: 1;
}

.att-week-cal__date em {
  font-style: normal;
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.att-week-cal__date small {
  font-size: 0.625rem;
  color: var(--color-text-muted);
}

.att-week-cal__times {
  display: flex;
  flex-direction: column;
  gap: 1px;
  font-size: 0.625rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: var(--color-text);
  line-height: 1.25;
}

.att-week-cal__hint {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--color-primary);
}

.att-week-cal__hint--muted {
  color: var(--color-text-muted);
  font-weight: 500;
}

.att-week-detail {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
  max-height: 14rem;
  overflow-y: auto;
}

.att-week-detail__item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.att-week-detail__when {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text);
}

.att-week-detail__line {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.att-week-detail__line b {
  font-weight: 600;
  color: var(--color-text);
}

.att-week-detail__muted {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.att-week-detail__empty {
  padding: var(--space-4);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  text-align: center;
}
</style>
