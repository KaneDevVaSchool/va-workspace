<script setup>
//
// Trang chủ "của tôi" bản mobile nhân viên — render bởi DashboardMe.vue khi
// useEmployeeMobileView() = true. Dữ liệu mock cố định, chưa gọi API (UI-only).
//
import { computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';

const router = useRouter();

const checklist = reactive([
  { id: 1, title: 'Điểm danh lớp 10A1 - tiết 1', time: '07:00', done: true },
  { id: 2, title: 'Nộp báo cáo tuần cho trưởng phòng', time: '11:00', done: false },
  { id: 3, title: 'Họp giao ban Phòng Công nghệ', time: '14:00', done: false },
  { id: 4, title: 'Cập nhật tiến độ việc "Bảo trì hệ thống"', time: '16:30', done: false },
]);

const doneCount = computed(() => checklist.filter((item) => item.done).length);

function toggleDone(item) {
  item.done = !item.done;
}

const attendanceStatus = {
  checkedInAt: '07:52',
  checkedOutAt: null,
};

const leaveBalance = {
  remainingDays: 8.5,
};

function goAttendance() {
  router.push({ name: 'employee.attendance' });
}

function goLeave() {
  router.push({ name: 'employee.leave' });
}
</script>

<template>
  <div class="employee-home">
    <section class="employee-home__progress">
      <div class="employee-home__progress-text">
        <p class="employee-home__progress-label">Việc hôm nay</p>
        <p class="employee-home__progress-count">{{ doneCount }}/{{ checklist.length }} đã xong</p>
      </div>
      <div class="employee-home__progress-bar">
        <div
          class="employee-home__progress-fill"
          :style="{ width: `${checklist.length ? (doneCount / checklist.length) * 100 : 0}%` }"
        ></div>
      </div>
    </section>

    <section class="employee-home__shortcuts">
      <button type="button" class="shortcut-card shortcut-card--attendance" @click="goAttendance">
        <span class="shortcut-card__icon">
          <AppIcon name="clock" :size="22" :stroke-width="1.8" />
        </span>
        <span class="shortcut-card__body">
          <span class="shortcut-card__title">Chấm công hôm nay</span>
          <span class="shortcut-card__value">
            {{ attendanceStatus.checkedInAt ? `Vào lúc ${attendanceStatus.checkedInAt}` : 'Chưa chấm công' }}
          </span>
        </span>
        <AppIcon name="chevronRight" :size="18" class="shortcut-card__chevron" />
      </button>

      <button type="button" class="shortcut-card shortcut-card--leave" @click="goLeave">
        <span class="shortcut-card__icon">
          <AppIcon name="calendar" :size="22" :stroke-width="1.8" />
        </span>
        <span class="shortcut-card__body">
          <span class="shortcut-card__title">Ngày phép còn lại</span>
          <span class="shortcut-card__value">{{ leaveBalance.remainingDays }} ngày</span>
        </span>
        <AppIcon name="chevronRight" :size="18" class="shortcut-card__chevron" />
      </button>
    </section>

    <section class="employee-home__checklist">
      <h2 class="employee-home__section-title">Việc thường ngày</h2>
      <ul class="checklist">
        <li v-for="item in checklist" :key="item.id" class="checklist__item">
          <button
            type="button"
            class="checklist__check"
            :class="{ 'checklist__check--done': item.done }"
            :aria-label="item.done ? `Bỏ đánh dấu xong: ${item.title}` : `Đánh dấu đã xong: ${item.title}`"
            @click="toggleDone(item)"
          >
            <AppIcon v-if="item.done" name="check" :size="14" :stroke-width="2.5" />
          </button>
          <span class="checklist__text" :class="{ 'checklist__text--done': item.done }">{{ item.title }}</span>
          <span class="checklist__time">{{ item.time }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.employee-home {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.employee-home__progress {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.employee-home__progress-text {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}

.employee-home__progress-label {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.employee-home__progress-count {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.employee-home__progress-bar {
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.employee-home__progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  transition: width 200ms ease;
}

.employee-home__shortcuts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

.shortcut-card {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.shortcut-card__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
}

.shortcut-card--attendance .shortcut-card__icon {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.shortcut-card--leave .shortcut-card__icon {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.shortcut-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.shortcut-card__title {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.shortcut-card__value {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.shortcut-card__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.employee-home__section-title {
  margin: 0 0 var(--space-2);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.checklist {
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

.checklist__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.checklist__item:last-child {
  box-shadow: none;
}

.checklist__check {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.375rem;
  height: 1.375rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1.5px var(--color-border-strong);
  color: var(--color-on-primary);
  cursor: pointer;
}

.checklist__check--done {
  background: var(--color-primary);
  box-shadow: none;
}

.checklist__text {
  flex: 1;
  min-width: 0;
  font-size: 0.875rem;
  color: var(--color-text);
}

.checklist__text--done {
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.checklist__time {
  flex-shrink: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

@media (max-width: 360px) {
  .employee-home__shortcuts {
    grid-template-columns: 1fr;
  }
}
</style>
