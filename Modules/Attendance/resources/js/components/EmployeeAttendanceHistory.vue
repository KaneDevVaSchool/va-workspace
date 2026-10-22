<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { buildAttendanceMonth } from '../lib/employeeAttendanceHistory';
import { readEmployeeAttendanceLog } from '../lib/employeeAttendanceSession';

const auth = useAuthStore();
const now = new Date();
const viewMonth = ref(new Date(now.getFullYear(), now.getMonth(), 1));
const punchLog = ref(readEmployeeAttendanceLog());

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

function shiftMonth(delta) {
  const cursor = viewMonth.value;
  viewMonth.value = new Date(cursor.getFullYear(), cursor.getMonth() + delta, 1);
}

function reload() {
  punchLog.value = readEmployeeAttendanceLog();
}

defineExpose({ reload });
</script>

<template>
  <div class="att-history">
    <div class="att-history__month">
      <button type="button" aria-label="Tháng trước" @click="shiftMonth(-1)">
        <AppIcon name="chevronLeft" :size="18" />
      </button>
      <span>{{ monthLabel }}</span>
      <button type="button" aria-label="Tháng sau" :disabled="!canNextMonth" @click="shiftMonth(1)">
        <AppIcon name="chevronRight" :size="18" />
      </button>
    </div>

    <div class="att-history__stats">
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--info">
          <AppIcon name="calendar" :size="16" />
        </span>
        <span><small>Tổng công</small><strong>{{ monthModel.stats.scheduled }} ngày</strong></span>
      </article>
      <article>
        <span class="att-history__stat-icon att-history__stat-icon--ok">
          <AppIcon name="check" :size="16" />
        </span>
        <span><small>Đi chấm công</small><strong>{{ monthModel.stats.onTime }} ngày</strong></span>
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
        <span><small>Vắng</small><strong>{{ monthModel.stats.absent }} ngày</strong></span>
      </article>
    </div>

    <p class="att-history__note">
      Ngày chưa chấm trên máy này là số minh họa. Lần chấm của bạn được giữ trên máy và hiện ở đúng ngày đó.
    </p>

    <article
      v-for="row in monthModel.rows"
      :key="row.key"
      class="att-history__day"
      :class="{
        'att-history__day--pending': row.kind === 'pending',
        'att-history__day--absent': row.kind === 'absent',
        'att-history__day--late': row.kind === 'work' && row.late,
        'att-history__day--ok': row.kind === 'work' && !row.late,
      }"
    >
      <h3>
        <span>{{ row.title }}</span>
        <span v-if="row.sample" class="att-history__sample">Minh họa</span>
      </h3>
      <p v-if="row.kind === 'pending'" class="att-history__empty">Chưa chấm công hôm nay</p>
      <p v-else-if="row.kind === 'absent'" class="att-history__empty">Vắng</p>
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
          <em class="is-ok">Đúng giờ</em>
        </li>
      </ul>
    </article>
  </div>
</template>

<style scoped>
.att-history {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.att-history__month {
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

.att-history__month button {
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

.att-history__month button:disabled {
  opacity: 0.35;
  cursor: default;
}

.att-history__stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
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

.att-history__note {
  margin: 0;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
  font-size: 0.75rem;
  line-height: 1.45;
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

.att-history__day--absent::before {
  background: var(--color-primary);
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

.att-history__sample {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
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
</style>
