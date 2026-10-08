<script setup>
//
// Chấm công — UI only, chưa nối API. Nút check-in/check-out chỉ đổi state
// cục bộ để minh hoạ luồng, đồng hồ cập nhật mỗi giây bằng setInterval.
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const now = ref(new Date());
let timer;

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date();
  }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const timeLabel = computed(() => new Intl.DateTimeFormat('vi-VN', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
}).format(now.value));

const dateLabel = computed(() => new Intl.DateTimeFormat('vi-VN', {
  weekday: 'long',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
}).format(now.value));

const checkedIn = ref(true);
const checkInTime = ref('07:52');
const checkOutTime = ref(null);

function handleToggle() {
  const label = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(new Date());
  if (!checkInTime.value) {
    checkInTime.value = label;
    checkedIn.value = true;
    showClientToast('success', `Đã chấm công vào lúc ${label}`);
  } else if (!checkOutTime.value) {
    checkOutTime.value = label;
    checkedIn.value = false;
    showClientToast('success', `Đã chấm công ra lúc ${label}`);
  } else {
    checkInTime.value = label;
    checkOutTime.value = null;
    checkedIn.value = true;
    showClientToast('success', `Đã chấm công vào lúc ${label}`);
  }
}

const buttonLabel = computed(() => {
  if (!checkInTime.value || (checkInTime.value && checkOutTime.value)) return 'Chấm công vào';
  return 'Chấm công ra';
});

const history = [
  { id: 1, date: 'Hôm nay', checkIn: '07:52', checkOut: null, total: '—' },
  { id: 2, date: 'Hôm qua', checkIn: '07:48', checkOut: '17:05', total: '9h17' },
  { id: 3, date: 'Thứ Hai, 06/10', checkIn: '07:55', checkOut: '17:00', total: '9h05' },
  { id: 4, date: 'Chủ nhật, 05/10', checkIn: '—', checkOut: '—', total: 'Nghỉ' },
];
</script>

<template>
  <div class="attendance">
    <section class="attendance__clock">
      <p class="attendance__date">{{ dateLabel }}</p>
      <p class="attendance__time">{{ timeLabel }}</p>

      <button
        type="button"
        class="attendance__button"
        :class="{ 'attendance__button--active': checkedIn }"
        @click="handleToggle"
      >
        <AppIcon name="clock" :size="28" :stroke-width="1.6" />
        <span>{{ buttonLabel }}</span>
      </button>

      <p class="attendance__hint">
        <template v-if="checkInTime && !checkOutTime">Đã vào lúc {{ checkInTime }}</template>
        <template v-else-if="checkInTime && checkOutTime">Vào {{ checkInTime }} · Ra {{ checkOutTime }}</template>
        <template v-else>Chưa chấm công hôm nay</template>
      </p>
    </section>

    <section class="attendance__history">
      <h2 class="attendance__section-title">Lịch sử chấm công</h2>
      <ul class="history-list">
        <li v-for="row in history" :key="row.id" class="history-row">
          <span class="history-row__date">{{ row.date }}</span>
          <span class="history-row__times">
            <span>{{ row.checkIn }}</span>
            <AppIcon name="arrowRight" :size="12" />
            <span>{{ row.checkOut ?? '—' }}</span>
          </span>
          <span class="history-row__total">{{ row.total }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.attendance {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.attendance__clock {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-5) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: center;
}

.attendance__date {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  text-transform: capitalize;
}

.attendance__time {
  margin: 0;
  font-size: 2.25rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: var(--color-text);
  letter-spacing: -0.01em;
}

.attendance__button {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  width: 9.5rem;
  height: 9.5rem;
  margin: var(--space-3) 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  box-shadow: var(--shadow-lg);
  cursor: pointer;
}

.attendance__button--active {
  background: var(--color-success);
}

.attendance__hint {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.attendance__section-title {
  margin: 0 0 var(--space-2);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.history-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.history-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.history-row:last-child {
  box-shadow: none;
}

.history-row__date {
  flex: 1;
  min-width: 0;
  font-size: 0.8125rem;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.history-row__times {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-muted);
}

.history-row__total {
  flex-shrink: 0;
  width: 3.5rem;
  text-align: right;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}
</style>
