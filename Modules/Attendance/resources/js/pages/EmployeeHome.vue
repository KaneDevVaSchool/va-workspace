<script setup>
//
// Trang chủ "của tôi" bản mobile nhân viên — render bởi DashboardMe.vue khi
// useEmployeeMobileView() = true.
//
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { EMPLOYEE_SHELL_BLOCK_MESSAGE } from '@/lib/employeeMobileShellRoutes';
import QuickUtilitiesGrid from '@modules/Dashboard/resources/js/components/QuickUtilitiesGrid.vue';
import { useDashboardQuickUtilities } from '@modules/Dashboard/resources/js/composables/useDashboardQuickUtilities';
import { unwrapOverview, asList } from '@modules/Dashboard/resources/js/utils/dashboardPayload';
import { formatSocialTime } from '@modules/Social/resources/js/lib/formatSocialTime.js';
import { postDisplayHeading } from '@modules/Social/resources/js/lib/postContentSnippet.js';

const SOCIAL_HOME_LIMIT = 3;

const { utilities, loading: utilitiesLoading, load: loadUtilities, openUtility } = useDashboardQuickUtilities();

const overview = ref(null);
const overviewLoading = ref(true);
const feedPosts = ref([]);
const feedLoading = ref(true);
const taskBusyId = ref(null);

const tasks = computed(() => {
  const rows = asList(overview.value?.tasks).filter((task) => task.status !== 'cancelled');
  return [...rows].sort((a, b) => taskSortRank(a) - taskSortRank(b));
});

const doneCount = computed(() => tasks.value.filter((task) => task.status === 'completed').length);

const attendanceStatus = {
  checkedInAt: '07:52',
};

onMounted(() => {
  loadUtilities();
  loadOverview();
  loadFeedPosts();
});

async function loadOverview() {
  overviewLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/me/overview');
    overview.value = unwrapOverview(data);
  } catch (error) {
    overview.value = null;
    showClientToast('error', error?.response?.data?.message || 'Không tải được công việc được giao.');
  } finally {
    overviewLoading.value = false;
  }
}

async function loadFeedPosts() {
  feedLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/social/posts', {
      params: {
        page: 1,
        per_page: SOCIAL_HOME_LIMIT,
        scope: 'all',
        post_scope: 'company',
      },
    });
    feedPosts.value = Array.isArray(data?.posts) ? data.posts.slice(0, SOCIAL_HOME_LIMIT) : [];
  } catch (error) {
    feedPosts.value = [];
    showClientToast('error', error?.response?.data?.message || 'Không tải được thông báo bảng tin.');
  } finally {
    feedLoading.value = false;
  }
}

function taskSortRank(task) {
  if (task.status === 'completed') return 4;
  if (task.is_overdue) return 0;
  if (task.end_date && isSameCalendarDay(task.end_date, new Date())) return 1;
  if (task.status === 'in_progress') return 2;
  return 3;
}

function isSameCalendarDay(value, date) {
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return false;
  return d.getFullYear() === date.getFullYear()
    && d.getMonth() === date.getMonth()
    && d.getDate() === date.getDate();
}

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

function postTitle(post) {
  return postDisplayHeading(post);
}

function postMeta(post) {
  return formatSocialTime(post?.created_at);
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
</script>

<template>
  <div class="employee-home">
    <div class="employee-home__stat-row">
      <div class="stat-card stat-card--tasks">
        <span class="stat-card__icon stat-card__icon--tasks" aria-hidden="true">
          <AppIcon name="listChecks" :size="20" :stroke-width="1.8" />
        </span>
        <div class="stat-card__body">
          <p class="stat-card__label">Việc hôm nay</p>
          <p class="stat-card__value">
            {{
              overviewLoading
                ? '…'
                : `${doneCount}/${tasks.length || 0} đã xong`
            }}
          </p>
        </div>
        <div class="stat-card__progress" aria-hidden="true">
          <div
            class="stat-card__progress-fill"
            :style="{ width: `${tasks.length ? (doneCount / tasks.length) * 100 : 0}%` }"
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
    <section class="employee-home__section">
      <div class="section-head">
        <h2 class="section-head__title">Tiện ích nhanh</h2>
        <RouterLink :to="{ name: 'employee.utilities' }" class="section-head__link">Xem tất cả</RouterLink>
      </div>
      <QuickUtilitiesGrid
        :items="utilities"
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
      <p v-else-if="tasks.length === 0" class="employee-home__empty">Bạn chưa có công việc nào được giao.</p>
      <div v-else class="employee-home__task-scroll hide-scrollbar">
        <ul class="task-list">
          <li
            v-for="item in tasks"
            :key="item.id"
            class="task-card"
            :class="`task-card--${taskCardStatus(item)}`"
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
      </div>
    </section>

    <section class="employee-home__section employee-home__section--last">
      <div class="section-head">
        <h2 class="section-head__title">Thông báo mới</h2>
        <button type="button" class="section-head__link section-head__link--btn" @click="promptDesktopOnly">
          Xem tất cả
        </button>
      </div>
      <p v-if="feedLoading" class="employee-home__loading">Đang tải thông báo…</p>
      <p v-else-if="feedPosts.length === 0" class="employee-home__empty">Chưa có thông báo mới.</p>
      <ul v-else class="notice-list">
        <li v-for="note in feedPosts" :key="note.id">
          <button type="button" class="notice-card" @click="promptDesktopOnly">
            <span class="notice-card__icon" :class="`notice-card__icon--${postIconTone(note)}`">
              <AppIcon :name="postIconName(note)" :size="20" :stroke-width="1.8" />
            </span>
            <span class="notice-card__body">
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

.section-head__link--btn {
  padding: 0;
  border: none;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.employee-home__task-scroll {
  max-height: min(20rem, 42vh);
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
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.35;
  letter-spacing: -0.01em;
}

.notice-card__meta {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--color-text-muted);
}

@media (max-width: 360px) {
  .employee-home__stat-row {
    grid-template-columns: 1fr;
  }
}
</style>
