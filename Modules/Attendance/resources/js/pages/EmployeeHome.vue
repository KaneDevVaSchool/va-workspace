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
  {
    id: 1,
    title: 'Điểm danh lớp 10A1 – tiết 1',
    time: '07:00',
    status: 'done',
  },
  {
    id: 2,
    title: 'Nộp báo cáo tuần cho trưởng phòng',
    time: '11:00',
    status: 'overdue',
    hint: 'Quá hạn nhẹ',
  },
  {
    id: 3,
    title: 'Họp giao ban Phòng Công nghệ',
    time: '14:00',
    status: 'upcoming',
    hint: 'Sắp diễn ra',
  },
  {
    id: 4,
    title: 'Cập nhật tiến độ việc "Bảo trì hệ thống"',
    time: '16:30',
    status: 'pending',
  },
]);

const doneCount = computed(() => checklist.filter((item) => item.status === 'done').length);

function toggleDone(item) {
  if (item.status === 'done') {
    item.status = 'pending';
    return;
  }
  item.status = 'done';
  item.hint = undefined;
}

const attendanceStatus = {
  checkedInAt: '07:52',
};

const utilities = [
  { id: 'attendance', label: 'Chấm công', icon: 'clock', tone: 'tertiary', route: 'employee.attendance' },
  { id: 'leave', label: 'Nghỉ phép', icon: 'calendar', tone: 'gold', route: 'employee.leave' },
  { id: 'payslip', label: 'Phiếu lương', icon: 'dollarSign', tone: 'primary', route: null },
  { id: 'training', label: 'Đào tạo', icon: 'star', tone: 'secondary', route: null },
  { id: 'schedule', label: 'Lịch dạy', icon: 'layoutGrid', tone: 'tertiary', route: null },
  { id: 'contacts', label: 'Danh bạ', icon: 'users', tone: 'primary', route: null },
  { id: 'request', label: 'Tạo đơn', icon: 'fileText', tone: 'gold', route: 'employee.requests' },
  { id: 'more', label: 'Thêm', icon: 'moreVertical', tone: 'muted', route: null },
];

const notifications = [
  {
    id: 1,
    title: 'Thông báo nghỉ lễ 30/4 – 1/5',
    meta: 'Phòng Nhân sự • 2 giờ trước',
    icon: 'megaphone',
    tone: 'tertiary',
  },
  {
    id: 2,
    title: 'Chúc mừng thành tích thi đua tháng 9',
    meta: 'Ban Giám hiệu • Hôm qua',
    icon: 'star',
    tone: 'success',
  },
];

const upcomingEvent = {
  weekday: 'THỨ 6',
  day: '09',
  title: 'Sinh hoạt chuyên môn Tổ Toán',
  time: '08:00 – 09:30',
  place: 'Phòng A204',
};

function goAttendance() {
  router.push({ name: 'employee.attendance' });
}

function goUtility(item) {
  if (item.route) {
    router.push({ name: item.route });
  }
}

function noopLink(event) {
  event.preventDefault();
}
</script>

<template>
  <Teleport to="#employee-home-stats">
    <div class="employee-home__stat-row">
      <div class="stat-card stat-card--tasks">
        <span class="stat-card__icon stat-card__icon--tasks" aria-hidden="true">
          <AppIcon name="listChecks" :size="20" :stroke-width="1.8" />
        </span>
        <div class="stat-card__body">
          <p class="stat-card__label">Việc hôm nay</p>
          <p class="stat-card__value">{{ doneCount }}/{{ checklist.length }} đã xong</p>
        </div>
        <div class="stat-card__progress" aria-hidden="true">
          <div
            class="stat-card__progress-fill"
            :style="{ width: `${checklist.length ? (doneCount / checklist.length) * 100 : 0}%` }"
          ></div>
        </div>
      </div>

      <button type="button" class="stat-card stat-card--attendance" @click="goAttendance">
        <span class="stat-card__icon stat-card__icon--attendance" aria-hidden="true">
          <AppIcon name="clock" :size="20" :stroke-width="1.8" />
        </span>
        <div class="stat-card__body">
          <p class="stat-card__label">Chấm công hôm nay</p>
          <p class="stat-card__value stat-card__value--inline">
            <AppIcon name="check" :size="14" :stroke-width="2.5" class="stat-card__check" />
            Vào lúc {{ attendanceStatus.checkedInAt }}
          </p>
        </div>
      </button>
    </div>
  </Teleport>

  <div class="employee-home">
    <section class="employee-home__section">
      <div class="section-head">
        <h2 class="section-head__title">Tiện ích nhanh</h2>
        <a href="#" class="section-head__link" @click="noopLink">Xem tất cả</a>
      </div>
      <ul class="utility-grid">
        <li v-for="item in utilities" :key="item.id">
          <button type="button" class="utility-tile" @click="goUtility(item)">
            <span class="utility-tile__icon" :class="`utility-tile__icon--${item.tone}`">
              <AppIcon :name="item.icon" :size="22" :stroke-width="1.75" />
            </span>
            <span class="utility-tile__label">{{ item.label }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section class="employee-home__section">
      <div class="section-head">
        <h2 class="section-head__title">Việc thường ngày</h2>
        <a href="#" class="section-head__link" @click="noopLink">Xem tất cả</a>
      </div>
      <p class="employee-home__hint">
        <AppIcon name="target" :size="14" class="employee-home__hint-icon" />
        Chạm vào vòng tròn để đánh dấu hoàn thành
      </p>
      <ul class="task-list">
        <li
          v-for="item in checklist"
          :key="item.id"
          class="task-card"
          :class="`task-card--${item.status}`"
        >
          <button
            type="button"
            class="task-card__check"
            :class="{ 'task-card__check--done': item.status === 'done' }"
            :aria-label="item.status === 'done' ? `Bỏ đánh dấu xong: ${item.title}` : `Đánh dấu đã xong: ${item.title}`"
            @click="toggleDone(item)"
          >
            <AppIcon v-if="item.status === 'done'" name="check" :size="16" :stroke-width="2.5" />
          </button>
          <div class="task-card__main">
            <p class="task-card__title" :class="{ 'task-card__title--done': item.status === 'done' }">
              {{ item.title }}
            </p>
            <p v-if="item.hint" class="task-card__hint">{{ item.hint }}</p>
          </div>
          <span class="task-card__time">{{ item.time }}</span>
        </li>
      </ul>
    </section>

    <section class="employee-home__section">
      <div class="section-head">
        <h2 class="section-head__title">Thông báo mới</h2>
        <a href="#" class="section-head__link" @click="noopLink">Xem tất cả</a>
      </div>
      <ul class="notice-list">
        <li v-for="note in notifications" :key="note.id">
          <button type="button" class="notice-card">
            <span class="notice-card__icon" :class="`notice-card__icon--${note.tone}`">
              <AppIcon :name="note.icon" :size="20" :stroke-width="1.8" />
            </span>
            <span class="notice-card__body">
              <span class="notice-card__title">{{ note.title }}</span>
              <span class="notice-card__meta">{{ note.meta }}</span>
            </span>
            <AppIcon name="chevronRight" :size="18" class="notice-card__chevron" />
          </button>
        </li>
      </ul>
    </section>

    <section class="employee-home__section employee-home__section--last">
      <div class="section-head">
        <h2 class="section-head__title">Lịch sắp tới</h2>
        <a href="#" class="section-head__link" @click="noopLink">Xem lịch</a>
      </div>
      <article class="schedule-card">
        <div class="schedule-card__date">
          <span class="schedule-card__weekday">{{ upcomingEvent.weekday }}</span>
          <span class="schedule-card__day">{{ upcomingEvent.day }}</span>
        </div>
        <div class="schedule-card__body">
          <h3 class="schedule-card__title">{{ upcomingEvent.title }}</h3>
          <p class="schedule-card__meta">
            <AppIcon name="clock" :size="14" class="schedule-card__meta-icon" />
            {{ upcomingEvent.time }} • {{ upcomingEvent.place }}
          </p>
        </div>
      </article>
    </section>
  </div>
</template>

<style scoped>
.employee-home {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  padding-bottom: var(--space-2);
}

.employee-home__stat-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  margin: 0;
}

.stat-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 6.5rem;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 4px 14px rgba(9, 36, 78, 0.08);
  text-align: left;
  font-family: inherit;
  cursor: default;
}

.stat-card--attendance {
  cursor: pointer;
}

.stat-card__icon {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
}

.stat-card__icon--tasks {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.stat-card__icon--attendance {
  background: var(--color-success-tint-bg);
  color: var(--color-success);
}

.stat-card__body {
  flex: 1;
  min-width: 0;
}

.stat-card__label {
  margin: 0 0 2px;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.stat-card__value {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.3;
}

.stat-card__value--inline {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.stat-card__check {
  color: var(--color-success);
}

.stat-card__progress {
  position: absolute;
  right: var(--space-3);
  bottom: var(--space-3);
  left: var(--space-3);
  height: 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface-strong);
  overflow: hidden;
}

.stat-card__progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
  transition: width 200ms ease;
}

.stat-card--tasks {
  padding-bottom: calc(var(--space-3) + 0.5rem);
}

.employee-home__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.employee-home__section--last {
  padding-bottom: var(--space-2);
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.section-head__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.section-head__link {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-tertiary);
  text-decoration: none;
}

.utility-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-3) var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.utility-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
}

.utility-tile__icon {
  display: grid;
  place-items: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-full);
}

.utility-tile__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.utility-tile__icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.utility-tile__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.utility-tile__icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.utility-tile__icon--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.utility-tile__label {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text);
  text-align: center;
  line-height: 1.25;
}

.employee-home__hint {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin: calc(-1 * var(--space-1)) 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.employee-home__hint-icon {
  flex-shrink: 0;
  opacity: 0.7;
}

.task-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-card--done {
  background: var(--color-surface-muted);
}

.task-card--overdue {
  box-shadow: inset 0 0 0 1.5px var(--color-warning);
}

.task-card--upcoming {
  background: var(--color-tertiary-surface);
  box-shadow: inset 0 0 0 1px var(--color-tertiary-200);
}

.task-card__check {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  box-shadow: inset 0 0 0 2px var(--color-border-strong);
  color: var(--color-on-tertiary);
  cursor: pointer;
}

.task-card--overdue .task-card__check {
  box-shadow: inset 0 0 0 2.5px var(--color-warning);
}

.task-card--upcoming .task-card__check {
  box-shadow: inset 0 0 0 2.5px var(--color-tertiary);
}

.task-card__check--done {
  background: var(--color-success);
  box-shadow: none;
}

.task-card__main {
  flex: 1;
  min-width: 0;
}

.task-card__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text);
  line-height: 1.35;
}

.task-card__title--done {
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.task-card__hint {
  margin: 2px 0 0;
  font-size: 0.75rem;
  font-weight: 500;
}

.task-card--overdue .task-card__hint {
  color: var(--color-warning);
}

.task-card--upcoming .task-card__hint {
  color: var(--color-tertiary);
}

.task-card__time {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.task-card--overdue .task-card__time {
  color: var(--color-warning);
}

.task-card--upcoming .task-card__time {
  color: var(--color-tertiary);
}

.notice-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.notice-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.notice-card__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  color: var(--color-on-tertiary);
}

.notice-card__icon--tertiary {
  background: var(--color-tertiary);
}

.notice-card__icon--success {
  background: var(--color-success);
}

.notice-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.notice-card__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.3;
}

.notice-card__meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.notice-card__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.schedule-card {
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.schedule-card__date {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  padding: var(--space-2) var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.schedule-card__weekday {
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.schedule-card__day {
  font-size: 1.375rem;
  font-weight: 800;
  line-height: 1.1;
}

.schedule-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: var(--space-1);
}

.schedule-card__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.35;
}

.schedule-card__meta {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.schedule-card__meta-icon {
  flex-shrink: 0;
  opacity: 0.85;
}

@media (max-width: 360px) {
  .employee-home__stat-row {
    grid-template-columns: 1fr;
  }

  .utility-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-2) var(--space-1);
  }

  .utility-tile__icon {
    width: 2.875rem;
    height: 2.875rem;
  }
}
</style>
