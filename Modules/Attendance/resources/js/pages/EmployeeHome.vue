<script setup>
//
// Trang chủ "của tôi" bản mobile nhân viên — render bởi DashboardMe.vue khi
// useEmployeeMobileView() = true.
//
import { computed, inject, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
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
  if (post?.is_pinned) return 'gold';
  return 'primary';
}

function postIconName(post) {
  if (post?.pin_scope === 'system' || post?.is_pinned) return 'megaphone';
  return 'fileText';
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
      <button type="button" class="quick-chip quick-chip--primary" @click="goQuick('employee.feed')">
        <AppIcon name="megaphone" :size="16" />
        Bảng tin
      </button>
      <button type="button" class="quick-chip quick-chip--secondary" @click="goQuick('employee.attendance')">
        <AppIcon name="clock" :size="16" />
        Chấm công
      </button>
      <button type="button" class="quick-chip quick-chip--tertiary" @click="goQuick('employee.leave')">
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

    <section class="employee-home__section employee-home__section--daily">
      <div class="section-head">
        <h2 class="section-head__title">Việc thường ngày</h2>
        <span
          v-if="!overviewLoading && tasks.length"
          class="daily-tasks__summary"
          :class="{ 'daily-tasks__summary--warn': overdueCount > 0 }"
        >
          {{ doneCount }}/{{ tasks.length }}
          <span v-if="overdueCount > 0" class="daily-tasks__summary-overdue">{{ overdueCount }} trễ</span>
        </span>
      </div>

      <div class="daily-tasks">
        <ul v-if="overviewLoading" class="daily-tasks__list" aria-busy="true">
          <li v-for="n in 4" :key="n" class="daily-tasks__row daily-tasks__row--sk">
            <span class="daily-tasks__sk-check" />
            <span class="daily-tasks__sk-lines">
              <span class="employee-home__shimmer daily-tasks__sk-line" />
              <span class="employee-home__shimmer daily-tasks__sk-line daily-tasks__sk-line--short" />
            </span>
          </li>
        </ul>

        <div v-else-if="tasks.length === 0" class="daily-tasks__empty">
          <AppIcon name="listChecks" :size="20" aria-hidden="true" />
          <p>Không có việc hôm nay</p>
        </div>

        <ul v-else class="daily-tasks__list hide-scrollbar daily-tasks__list--scroll">
          <li
            v-for="item in tasks"
            :key="item.id"
            class="daily-tasks__row"
            :class="[
              `daily-tasks__row--${taskCardStatus(item)}`,
              { 'daily-tasks__row--done': item.status === 'completed' },
            ]"
          >
            <button
              type="button"
              class="daily-tasks__check"
              :class="{
                'daily-tasks__check--done': item.status === 'completed',
                'daily-tasks__check--progress': item.status === 'in_progress',
              }"
              :disabled="taskBusyId === item.id"
              :aria-label="item.status === 'completed' ? `Bỏ đánh dấu xong: ${item.title}` : `Đánh dấu đã xong: ${item.title}`"
              @click="toggleDone(item)"
            >
              <AppIcon v-if="item.status === 'completed'" name="check" :size="16" :stroke-width="2.5" />
              <span v-else-if="item.status === 'in_progress'" class="daily-tasks__check-dot" aria-hidden="true" />
            </button>

            <div class="daily-tasks__body">
              <p class="daily-tasks__title">{{ item.title }}</p>
              <p v-if="item.project_name" class="daily-tasks__meta">{{ item.project_name }}</p>
            </div>

            <div class="daily-tasks__end">
              <button
                v-if="canMarkInProgress(item)"
                type="button"
                class="daily-tasks__action"
                :disabled="taskBusyId === item.id"
                aria-label="Đánh dấu đang làm"
                @click="markInProgress(item)"
              >
                <AppIcon name="play" :size="14" :stroke-width="2.2" />
              </button>
              <span class="daily-tasks__time">{{ formatTaskTime(item) }}</span>
            </div>
          </li>
        </ul>
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
  --eh-shadow: var(--eh-card-shadow, 0 4px 18px color-mix(in srgb, var(--color-tertiary-900) 7%, transparent));
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
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
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
  margin: 0;
}

.stat-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-height: 5.25rem;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--eh-shadow);
  text-align: left;
  font-family: inherit;
  cursor: default;
  transition: transform 160ms ease, box-shadow 160ms ease;
  overflow: hidden;
}

.stat-card--tasks {
  padding-bottom: calc(var(--space-3) + 0.5rem);
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-tertiary-surface) 88%, var(--color-surface) 12%) 0%,
    var(--color-surface) 72%
  );
}

.stat-card--attendance {
  cursor: pointer;
  background: linear-gradient(
    145deg,
    color-mix(in srgb, var(--color-secondary-surface) 90%, var(--color-surface) 10%) 0%,
    var(--color-surface) 70%
  );
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
  width: 1.875rem;
  height: 1.875rem;
  border-radius: var(--radius-sm);
}

.stat-card__icon--tasks {
  background: color-mix(in srgb, var(--color-tertiary-surface-strong) 65%, var(--color-surface) 35%);
  color: var(--color-tertiary-700);
}

.stat-card__icon--attendance {
  background: color-mix(in srgb, var(--color-secondary-surface-strong) 65%, var(--color-surface) 35%);
  color: var(--color-secondary-700);
}

.stat-card__icon--pending {
  background: color-mix(in srgb, var(--color-gold-surface-strong) 60%, var(--color-surface) 40%);
  color: var(--color-gold-700);
}

.stat-card__icon--in,
.stat-card__icon--done {
  background: color-mix(in srgb, var(--color-secondary-surface-strong) 65%, var(--color-surface) 35%);
  color: var(--color-secondary-700);
}

.stat-card__ring {
  position: relative;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
}

.stat-card__ring-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.stat-card__ring-track {
  fill: none;
  stroke: color-mix(in srgb, var(--color-tertiary-200) 55%, var(--color-surface) 45%);
  stroke-width: 10;
}

.stat-card__ring-fill {
  fill: none;
  stroke: var(--color-tertiary-600);
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
  color: var(--color-tertiary-700);
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
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
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
  color: var(--color-primary-600);
  font-weight: 700;
}

.stat-card__progress {
  position: absolute;
  right: var(--space-3);
  bottom: var(--space-3);
  left: var(--space-3);
  height: 0.3125rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-tertiary-100) 70%, var(--color-surface) 30%);
  overflow: hidden;
}

.stat-card__progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background: linear-gradient(90deg, var(--color-tertiary-400), var(--color-tertiary-600));
  transition: width 280ms ease;
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
  box-shadow: var(--eh-shadow);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 150ms ease, transform 150ms ease, box-shadow 150ms ease;
}

.quick-chip:active {
  transform: scale(0.97);
}

.quick-chip--primary {
  background: color-mix(in srgb, var(--color-primary-surface) 75%, var(--color-surface) 25%);
  color: var(--color-primary-800);
}

.quick-chip--primary :deep(.app-icon) {
  color: var(--color-primary-700);
}

.quick-chip--secondary {
  background: color-mix(in srgb, var(--color-secondary-surface) 75%, var(--color-surface) 25%);
  color: var(--color-secondary-800);
}

.quick-chip--secondary :deep(.app-icon) {
  color: var(--color-secondary-700);
}

.quick-chip--tertiary {
  background: color-mix(in srgb, var(--color-tertiary-surface) 75%, var(--color-surface) 25%);
  color: var(--color-tertiary-800);
}

.quick-chip--tertiary :deep(.app-icon) {
  color: var(--color-tertiary-700);
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

.employee-home__section--utilities :deep(.quick-utilities__tile) {
  box-shadow: var(--eh-shadow);
  background: color-mix(in srgb, var(--color-surface) 92%, var(--color-primary-50) 8%);
}

.employee-home__section--utilities :deep(.quick-utilities__tile:not(.quick-utilities__tile--preview):active) {
  background: color-mix(in srgb, var(--color-surface) 85%, var(--color-primary-100) 15%);
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
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--color-text);
}

.employee-home__section--utilities .section-head__title {
  color: var(--color-primary-900);
}

.employee-home__section--daily .section-head__title {
  color: color-mix(in srgb, var(--color-tertiary-800) 55%, var(--color-text) 45%);
}

.employee-home__section--last .section-head__title {
  color: color-mix(in srgb, var(--color-gold-800) 40%, var(--color-text) 60%);
}

.section-head__link {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
}

.section-head__link--btn {
  padding: 0;
  border: none;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
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
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}

.employee-home__section--daily {
  gap: var(--space-2);
}

.daily-tasks__summary {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.75rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: var(--color-text);
}

.daily-tasks__summary--warn {
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
}

.daily-tasks__summary-overdue {
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--color-primary);
}

.daily-tasks {
  border-radius: var(--radius-lg);
  background: color-mix(in srgb, var(--color-surface) 88%, var(--color-primary-50) 12%);
  box-shadow: var(--eh-shadow);
  overflow: hidden;
}

.daily-tasks__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.daily-tasks__list--scroll {
  max-height: min(20rem, 46vh);
  overflow-y: auto;
}

.daily-tasks__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-4);
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.daily-tasks__empty p {
  margin: 0;
}

.daily-tasks__empty :deep(.app-icon) {
  color: var(--color-text-muted);
  opacity: 0.75;
}

.daily-tasks__row {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  background: var(--color-surface);
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.daily-tasks__row:last-child {
  box-shadow: none;
}

.daily-tasks__row::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.daily-tasks__row--overdue::before {
  background: var(--color-primary);
}

.daily-tasks__row--progress::before {
  background: var(--color-tertiary);
}

.daily-tasks__row--upcoming::before {
  background: var(--color-secondary);
}

.daily-tasks__row--done {
  background: color-mix(in srgb, var(--color-surface) 88%, var(--color-surface-muted));
}

.daily-tasks__row--done .daily-tasks__title {
  color: var(--color-text-muted);
  text-decoration: line-through;
}

.daily-tasks__row--sk {
  pointer-events: none;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.daily-tasks__sk-check {
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.daily-tasks__sk-lines {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.daily-tasks__sk-line {
  display: block;
  height: 0.625rem;
  width: 100%;
  border-radius: var(--radius-sm);
}

.daily-tasks__sk-line--short {
  width: 55%;
}

.daily-tasks__check {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  box-shadow: inset 0 0 0 2px var(--color-border-strong);
  color: var(--color-on-tertiary);
  cursor: pointer;
}

.daily-tasks__check:disabled {
  opacity: 0.55;
  cursor: wait;
}

.daily-tasks__row--overdue .daily-tasks__check {
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.daily-tasks__row--progress .daily-tasks__check,
.daily-tasks__row--upcoming .daily-tasks__check {
  box-shadow: inset 0 0 0 2px var(--color-tertiary);
}

.daily-tasks__check--done {
  background: var(--color-secondary-surface);
  box-shadow: inset 0 0 0 2px var(--color-secondary);
  color: var(--color-secondary);
}

.daily-tasks__check--progress {
  background: var(--color-tertiary-surface-strong, var(--color-tertiary-surface));
}

.daily-tasks__check-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.daily-tasks__body {
  flex: 1;
  min-width: 0;
}

.daily-tasks__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.daily-tasks__meta {
  margin: 2px 0 0;
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.daily-tasks__end {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  min-width: 2.75rem;
}

.daily-tasks__action {
  display: grid;
  place-items: center;
  width: 1.625rem;
  height: 1.625rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  cursor: pointer;
}

.daily-tasks__action:active:not(:disabled) {
  background: var(--color-tertiary);
  color: var(--color-on-tertiary);
}

.daily-tasks__action:disabled {
  opacity: 0.5;
  cursor: wait;
}

.daily-tasks__time {
  font-size: 0.6875rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-muted);
}

.daily-tasks__row--overdue .daily-tasks__time {
  color: var(--color-primary);
}

.daily-tasks__row--upcoming .daily-tasks__time {
  color: var(--color-secondary-700);
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

@media (prefers-reduced-motion: reduce) {
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
  box-shadow: var(--eh-shadow);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  transition: background 150ms ease, box-shadow 150ms ease, transform 150ms ease;
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
  background: linear-gradient(180deg, var(--color-gold-400), var(--color-gold-700));
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

.notice-card__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.notice-card__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.notice-card__icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
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
  background: color-mix(in srgb, var(--color-gold-surface-strong) 80%, var(--color-surface) 20%);
  color: var(--color-gold-800);
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
