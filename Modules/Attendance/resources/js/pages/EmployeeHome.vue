<script setup>
//
// Trang chủ "của tôi" bản mobile nhân viên — render bởi DashboardMe.vue khi
// useEmployeeMobileView() = true.
//
import { computed, inject, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { EMPLOYEE_SHELL_BLOCK_MESSAGE } from '@/lib/employeeMobileShellRoutes';
import QuickUtilitiesGrid from '@modules/Dashboard/resources/js/components/QuickUtilitiesGrid.vue';
import {
  EMPLOYEE_HOME_UTILITIES_LIMIT,
  useEmployeeHomeData,
} from '../composables/useEmployeeHomeData';
import { readEmployeeAttendanceToday } from '../lib/employeeAttendanceSession';

const router = useRouter();
const homeSearchQuery = inject('employeeHomeSearch', ref(''));
const registerHomeRefresh = inject('employeeHomeRefreshRegister', null);

const {
  utilities,
  utilitiesLoading,
  openUtility,
  overviewLoading,
  feedPosts,
  feedLoading,
  visibleTasks,
  hiddenTaskCount,
  doneCount,
  tasks,
  taskProgressPercent,
  overdueCount,
  refreshAll,
  postTitle,
  postMeta,
  postAuthor,
  isPostNew,
  isSameCalendarDay,
} = useEmployeeHomeData();

const taskBusyId = ref(null);
const attendanceToday = ref(readEmployeeAttendanceToday());

const filteredUtilities = computed(() => {
  const q = homeSearchQuery.value.trim().toLowerCase();
  let list = utilities.value;
  if (q) {
    list = list.filter((item) => String(item.label || '').toLowerCase().includes(q));
  }
  return list.slice(0, EMPLOYEE_HOME_UTILITIES_LIMIT);
});

const utilitiesEmptySearch = computed(
  () => homeSearchQuery.value.trim() && !utilitiesLoading.value && filteredUtilities.value.length === 0,
);

const attendanceLabel = computed(() => {
  const row = attendanceToday.value;
  if (!row?.checkInTime) return { tone: 'pending', text: 'Chưa chấm công' };
  if (row.checkOutTime) {
    return { tone: 'done', text: `Ra lúc ${row.checkOutTime}` };
  }
  return { tone: 'in', text: `Vào lúc ${row.checkInTime}` };
});

const taskRingOffset = computed(() => {
  const circumference = 326.7;
  const pct = taskProgressPercent.value;
  return circumference - (circumference * pct) / 100;
});

let unregisterRefresh;
onMounted(() => {
  refreshAll();
  if (typeof registerHomeRefresh === 'function') {
    unregisterRefresh = registerHomeRefresh(async () => {
      attendanceToday.value = readEmployeeAttendanceToday();
      await refreshAll({ silent: true });
    });
  }
});

onBeforeUnmount(() => {
  unregisterRefresh?.();
});

watch(
  () => router.currentRoute.value.fullPath,
  () => {
    attendanceToday.value = readEmployeeAttendanceToday();
  },
);

function taskCardStatus(task) {
  if (task.status === 'completed') return 'done';
  if (task.status === 'in_progress') return 'progress';
  if (task.is_overdue) return 'overdue';
  if (task.end_date && isSameCalendarDay(task.end_date, new Date())) return 'upcoming';
  return 'pending';
}

function canMarkInProgress(task) {
  return task.status !== 'completed'
    && task.status !== 'in_progress'
    && task.status !== 'cancelled';
}

function formatTaskTime(task) {
  if (task.due_time && typeof task.due_time === 'string' && task.due_time.length >= 5) {
    return task.due_time.slice(0, 5);
  }
  if (task.end_date) {
    const d = new Date(task.end_date);
    if (!Number.isNaN(d.getTime())) {
      return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
    }
  }
  return '—';
}

function openFeedPost(post) {
  if (!post?.id) return;
  router.push({ name: 'employee.feed', query: { post: String(post.id) } });
}

function openFeedAll() {
  router.push({ name: 'employee.feed' });
}

function postIconTone(post) {
  if (post?.pin_scope === 'system') return 'tertiary';
  if (post?.is_pinned) return 'success';
  return 'tertiary';
}

function postIconName(post) {
  if (post?.pin_scope === 'system' || post?.is_pinned) return 'megaphone';
  return 'fileText';
}

function promptDesktopOnly(event) {
  event?.preventDefault?.();
  showClientToast('info', EMPLOYEE_SHELL_BLOCK_MESSAGE, { duration: 9000 });
}

async function patchTaskStatus(task, nextStatus) {
  if (taskBusyId.value === task.id) return;
  const previousStatus = task.status;
  const previousOverdue = task.is_overdue;

  taskBusyId.value = task.id;
  task.status = nextStatus;
  if (nextStatus === 'completed') {
    task.is_overdue = false;
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      navigator.vibrate(12);
    }
  }

  try {
    await window.axios.put(`/api/project/tasks/${task.id}`, { status: nextStatus });
  } catch (error) {
    task.status = previousStatus;
    task.is_overdue = previousOverdue;
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được trạng thái công việc.');
  } finally {
    taskBusyId.value = null;
  }
}

function toggleDone(task) {
  const nextStatus = task.status === 'completed' ? 'in_progress' : 'completed';
  patchTaskStatus(task, nextStatus);
}

function markInProgress(task) {
  if (!canMarkInProgress(task)) return;
  patchTaskStatus(task, 'in_progress');
}

function goAttendance() {
  openUtility({ link_type: 'route', link_target: 'employee.attendance' });
}

function goQuick(name) {
  router.push({ name }).catch(() => {});
}
</script>

<template>
  <div class="employee-home">
    <div v-if="homeSearchQuery.trim()" class="employee-home__search-hint">
      <AppIcon name="search" :size="16" />
      <span>Kết quả cho “{{ homeSearchQuery.trim() }}”</span>
    </div>

    <div class="employee-home__stat-row">
      <div class="stat-card stat-card--tasks">
        <div class="stat-card__top">
          <span class="stat-card__icon stat-card__icon--tasks" aria-hidden="true">
            <AppIcon name="listChecks" :size="20" :stroke-width="1.8" />
          </span>
          <div
            v-if="!overviewLoading && tasks.length"
            class="stat-card__ring"
            aria-hidden="true"
          >
            <svg viewBox="0 0 120 120" class="stat-card__ring-svg">
              <circle cx="60" cy="60" r="52" class="stat-card__ring-track" />
              <circle
                cx="60"
                cy="60"
                r="52"
                class="stat-card__ring-fill"
                :style="{ strokeDashoffset: taskRingOffset }"
              />
            </svg>
            <span class="stat-card__ring-pct">{{ taskProgressPercent }}%</span>
          </div>
        </div>
        <div class="stat-card__body">
          <p class="stat-card__label">Việc hôm nay</p>
          <p v-if="overviewLoading" class="stat-card__value stat-card__value--sk">
            <span class="employee-home__shimmer" />
          </p>
          <p v-else class="stat-card__value">
            {{ doneCount }}/{{ tasks.length || 0 }} đã xong
            <span v-if="overdueCount > 0" class="stat-card__warn"> · {{ overdueCount }} trễ</span>
          </p>
        </div>
        <div class="stat-card__progress" aria-hidden="true">
          <div
            class="stat-card__progress-fill"
            :style="{ width: `${tasks.length ? taskProgressPercent : 0}%` }"
          ></div>
        </div>
      </div>

      <button type="button" class="stat-card stat-card--attendance" @click="goAttendance">
        <span
          class="stat-card__icon stat-card__icon--attendance"
          :class="`stat-card__icon--${attendanceLabel.tone}`"
          aria-hidden="true"
        >
          <AppIcon :name="attendanceLabel.tone === 'pending' ? 'clock' : 'check'" :size="20" :stroke-width="1.8" />
        </span>
        <div class="stat-card__body">
          <p class="stat-card__label">Chấm công hôm nay</p>
          <p class="stat-card__value stat-card__value--inline">
            {{ attendanceLabel.text }}
          </p>
        </div>
      </button>
    </div>

    <nav class="employee-home__quick" aria-label="Lối tắt nhanh">
      <button type="button" class="quick-chip" @click="goQuick('employee.feed')">
        <AppIcon name="megaphone" :size="16" />
        Bảng tin
      </button>
      <button type="button" class="quick-chip" @click="goQuick('employee.attendance')">
        <AppIcon name="clock" :size="16" />
        Chấm công
      </button>
      <button type="button" class="quick-chip" @click="goQuick('employee.leave')">
        <AppIcon name="calendar" :size="16" />
        Nghỉ phép
      </button>
    </nav>

    <section class="employee-home__section employee-home__section--utilities">
      <div class="section-head">
        <h2 class="section-head__title">Tiện ích nhanh</h2>
        <RouterLink
          :to="{
            name: 'employee.utilities',
            query: homeSearchQuery.trim() ? { q: homeSearchQuery.trim() } : {},
          }"
          class="section-head__link"
        >
          Xem tất cả
        </RouterLink>
      </div>
      <p v-if="utilitiesEmptySearch" class="employee-home__empty">Không có tiện ích khớp tìm kiếm.</p>
      <QuickUtilitiesGrid
        v-else
        :items="filteredUtilities"
        :loading="utilitiesLoading"
        compact
        @select="openUtility"
      />
    </section>

    <section class="employee-home__section">
      <div class="section-head">
        <h2 class="section-head__title">Việc thường ngày</h2>
        <button type="button" class="section-head__link section-head__link--btn" @click="promptDesktopOnly">
          Xem tất cả
        </button>
      </div>
      <p v-if="overviewLoading" class="employee-home__loading">Đang tải công việc…</p>
      <div v-else-if="tasks.length === 0" class="employee-home__empty-state">
        <span class="employee-home__empty-icon" aria-hidden="true">
          <AppIcon name="sparkles" :size="22" />
        </span>
        <p class="employee-home__empty-title">Không có việc nào hôm nay</p>
        <p class="employee-home__empty">Bạn có thể xem bảng tin hoặc mở tiện ích nhanh phía trên.</p>
      </div>
      <div v-else class="employee-home__task-scroll hide-scrollbar">
        <ul class="task-list">
          <li
            v-for="(item, index) in visibleTasks"
            :key="item.id"
            class="task-card"
            :class="`task-card--${taskCardStatus(item)}`"
            :style="{ animationDelay: `${index * 40}ms` }"
          >
            <button
              type="button"
              class="task-card__check"
              :class="{
                'task-card__check--done': item.status === 'completed',
                'task-card__check--progress': item.status === 'in_progress',
              }"
              :disabled="taskBusyId === item.id"
              :aria-label="item.status === 'completed' ? `Bỏ đánh dấu xong: ${item.title}` : `Đánh dấu đã xong: ${item.title}`"
              @click="toggleDone(item)"
            >
              <AppIcon v-if="item.status === 'completed'" name="check" :size="18" :stroke-width="2.5" />
              <span v-else-if="item.status === 'in_progress'" class="task-card__check-dot" aria-hidden="true"></span>
            </button>
            <button
              type="button"
              class="task-card__detail"
              @click="promptDesktopOnly"
            >
              <p class="task-card__title" :class="{ 'task-card__title--done': item.status === 'completed' }">
                {{ item.title }}
              </p>
              <p v-if="item.project_name" class="task-card__project">{{ item.project_name }}</p>
            </button>
            <div class="task-card__aside">
              <button
                v-if="canMarkInProgress(item)"
                type="button"
                class="task-card__quick"
                :disabled="taskBusyId === item.id"
                @click="markInProgress(item)"
              >
                Đang làm
              </button>
              <span class="task-card__time">{{ formatTaskTime(item) }}</span>
            </div>
          </li>
        </ul>
        <p v-if="hiddenTaskCount > 0" class="employee-home__more-tasks">
          + {{ hiddenTaskCount }} việc khác — mở trên máy tính để xem đầy đủ
        </p>
      </div>
    </section>

    <section class="employee-home__section employee-home__section--last">
      <div class="section-head">
        <h2 class="section-head__title">Thông báo mới</h2>
        <button type="button" class="section-head__link section-head__link--btn" @click="openFeedAll">
          Xem tất cả
        </button>
      </div>
      <p v-if="feedLoading" class="employee-home__loading">Đang tải thông báo…</p>
      <div v-else-if="feedPosts.length === 0" class="employee-home__empty-state employee-home__empty-state--compact">
        <span class="employee-home__empty-icon" aria-hidden="true">
          <AppIcon name="megaphone" :size="20" />
        </span>
        <p class="employee-home__empty">Chưa có thông báo mới.</p>
      </div>
      <ul v-else class="notice-list">
        <li v-for="(note, index) in feedPosts" :key="note.id">
          <button
            type="button"
            class="notice-card"
            :class="{ 'notice-card--new': isPostNew(note) }"
            :style="{ animationDelay: `${index * 50}ms` }"
            @click="openFeedPost(note)"
          >
            <span class="notice-card__icon" :class="`notice-card__icon--${postIconTone(note)}`">
              <AppIcon :name="postIconName(note)" :size="18" :stroke-width="1.75" />
            </span>
            <span class="notice-card__body">
              <span class="notice-card__top">
                <span class="notice-card__author">{{ postAuthor(note) }}</span>
                <span v-if="isPostNew(note)" class="notice-card__badge">Mới</span>
              </span>
              <span class="notice-card__title">{{ postTitle(note) }}</span>
              <span class="notice-card__meta">{{ postMeta(note) }}</span>
            </span>
          </button>
        </li>
      </ul>
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

.employee-home__search-hint {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: calc(-1 * var(--space-2)) 0 calc(-1 * var(--space-3));
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.employee-home__stat-row {
  position: relative;
  z-index: 4;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  margin: calc(-1 * var(--employee-stat-overlap, 3.25rem)) 0 var(--space-2);
}

.stat-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 6.75rem;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 4px 18px rgba(9, 36, 78, 0.09);
  text-align: left;
  font-family: inherit;
  cursor: default;
  transition: transform 160ms ease, box-shadow 160ms ease;
}

.stat-card--attendance {
  cursor: pointer;
}

.stat-card--attendance:active {
  transform: scale(0.98);
}

.stat-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
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

.stat-card__icon--pending {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.stat-card__ring {
  position: relative;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.stat-card__ring-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.stat-card__ring-track {
  fill: none;
  stroke: var(--color-tertiary-surface-strong);
  stroke-width: 10;
}

.stat-card__ring-fill {
  fill: none;
  stroke: var(--color-tertiary);
  stroke-width: 10;
  stroke-linecap: round;
  stroke-dasharray: 326.7;
  transition: stroke-dashoffset 0.5s ease;
}

.stat-card__ring-pct {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  font-size: 0.5625rem;
  font-weight: 800;
  color: var(--color-tertiary);
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

.stat-card__value--sk {
  min-height: 1rem;
}

.stat-card__warn {
  color: var(--color-warning);
  font-weight: 700;
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
  background: linear-gradient(90deg, var(--color-tertiary), var(--color-secondary));
  transition: width 280ms ease;
}

.stat-card--tasks {
  padding-bottom: calc(var(--space-3) + 0.5rem);
}

.employee-home__quick {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  margin: calc(-1 * var(--space-2)) 0 0;
  padding: var(--space-1) 0;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.employee-home__quick::-webkit-scrollbar {
  display: none;
}

.quick-chip {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0.45rem 0.75rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease;
}

.quick-chip:active {
  transform: scale(0.97);
  background: var(--color-surface-muted);
}

.employee-home__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  content-visibility: auto;
  contain-intrinsic-size: auto 12rem;
}

.employee-home__section--utilities {
  contain-intrinsic-size: auto 10rem;
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

.section-head__link--btn {
  padding: 0;
  border: none;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.employee-home__task-scroll {
  max-height: min(22rem, 48vh);
  overflow-y: auto;
  margin: 0 calc(-1 * var(--space-1));
  padding: 0 var(--space-1);
}

.employee-home__loading,
.employee-home__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.employee-home__empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-5) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  text-align: center;
}

.employee-home__empty-state--compact {
  padding: var(--space-4);
}

.employee-home__empty-icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.employee-home__empty-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.employee-home__more-tasks {
  margin: var(--space-2) 0 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-align: center;
}

.employee-home__shimmer {
  display: block;
  width: 5rem;
  height: 0.75rem;
  border-radius: var(--radius-sm);
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 25%,
    var(--color-border) 50%,
    var(--color-surface-muted) 75%
  );
  background-size: 200% 100%;
  animation: employee-home-shimmer 1.2s ease infinite;
}

@keyframes employee-home-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
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
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  animation: employee-home-rise 320ms ease both;
}

@media (prefers-reduced-motion: reduce) {
  .task-card,
  .notice-card {
    animation: none;
  }
}

@keyframes employee-home-rise {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.task-card--done {
  background: var(--color-surface-muted);
}

.task-card--progress {
  background: var(--color-tertiary-surface);
  box-shadow: inset 0 0 0 1px var(--color-tertiary-200);
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
  width: 2.125rem;
  height: 2.125rem;
  margin-top: 1px;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  box-shadow: inset 0 0 0 2.5px var(--color-border-strong);
  color: var(--color-on-tertiary);
  cursor: pointer;
}

.task-card__check:disabled {
  opacity: 0.55;
  cursor: wait;
}

.task-card--overdue .task-card__check {
  box-shadow: inset 0 0 0 2.5px var(--color-warning);
}

.task-card--upcoming .task-card__check,
.task-card--progress .task-card__check {
  box-shadow: inset 0 0 0 2.5px var(--color-tertiary);
}

.task-card__check--done {
  background: var(--color-success);
  box-shadow: none;
}

.task-card__check--progress {
  background: var(--color-tertiary-surface-strong, var(--color-tertiary-surface));
}

.task-card__check-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.task-card__detail {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  font-family: inherit;
  cursor: pointer;
}

.task-card__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
}

.task-card__title--done {
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.task-card__project {
  margin: 4px 0 0;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

.task-card__aside {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
  min-width: 3.75rem;
}

.task-card__quick {
  padding: 0.2rem 0.55rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
  color: var(--color-on-tertiary);
  font-family: inherit;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  cursor: pointer;
}

.task-card__quick:disabled {
  opacity: 0.55;
  cursor: wait;
}

.task-card__time {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  line-height: 1.2;
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
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  transition: background 150ms ease, box-shadow 150ms ease;
  animation: employee-home-rise 320ms ease both;
}

.notice-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.notice-card--new::before {
  background: var(--color-tertiary);
}

.notice-card:active {
  background: var(--color-surface-muted);
}

.notice-card__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  margin-top: 1px;
  border-radius: var(--radius-md);
}

.notice-card__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.notice-card__icon--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success);
}

.notice-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.notice-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
}

.notice-card__author {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.notice-card__badge {
  flex-shrink: 0;
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface-strong, var(--color-tertiary-surface));
  color: var(--color-tertiary);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.notice-card__title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text);
  line-height: 1.4;
}

.notice-card__meta {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

@media (max-width: 360px) {
  .employee-home__stat-row {
    grid-template-columns: 1fr;
  }
}
</style>
