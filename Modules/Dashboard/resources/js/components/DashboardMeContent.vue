<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import DashboardSkeleton from './DashboardSkeleton.vue';
import MyTaskTable from './MyTaskTable.vue';
import KpiCard from './KpiCard.vue';
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';
import { asList, asRecord } from '../utils/dashboardPayload';

const props = defineProps({
  overview: { type: Object, default: null },
  overviewLoading: { type: Boolean, default: false },
  utilities: { type: Array, default: () => [] },
  utilitiesLoading: { type: Boolean, default: false },
  preview: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
  /** Giữ layout desktop (2 cột hero, 3 KPI, donut+bảng) dù viewport hẹp — xem trước cấu hình */
  forceDesktopLayout: { type: Boolean, default: false },
});

const emit = defineEmits(['utility-select', 'task-accepted']);

const auth = useAuthStore();
const router = useRouter();
const statusFilter = ref(null);
const quickFilter = ref(null);
const taskSearch = ref('');
const acceptingTaskId = ref(null);

const quickLinks = [
  { route: 'employee.attendance', label: 'Chấm công', icon: 'clock', tone: 'primary' },
  { route: 'employee.leave', label: 'Nghỉ phép', icon: 'calendar', tone: 'secondary' },
  { route: 'employee.feed', label: 'Bảng tin', icon: 'megaphone', tone: 'tertiary' },
  { route: 'employee.chat', label: 'Trò chuyện', icon: 'messageCircle', tone: 'gold' },
  { route: 'employee.profile', label: 'Hồ sơ', icon: 'user', tone: 'umber' },
];

function isDueToday(endDate) {
  if (!endDate) return false;
  const d = new Date(endDate);
  const today = new Date();
  return (
    d.getFullYear() === today.getFullYear()
    && d.getMonth() === today.getMonth()
    && d.getDate() === today.getDate()
  );
}

const todayLabel = computed(() =>
  new Date().toLocaleDateString('vi-VN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }),
);

const pendingAcceptCount = computed(() => allTasks.value.filter((t) => t.can_accept).length);

const dueTodayCount = computed(() =>
  allTasks.value.filter(
    (t) => !['completed', 'cancelled'].includes(t.status) && isDueToday(t.end_date),
  ).length,
);

const inProgressCount = computed(() => {
  const breakdown = asRecord(props.overview?.tasks_status_breakdown);
  return breakdown?.in_progress ?? 0;
});

const heroQuickFilters = computed(() => [
  {
    key: 'due_today',
    label: 'Hôm nay',
    value: dueTodayCount.value,
    icon: 'calendar',
    tone: 'info',
  },
  {
    key: 'overdue',
    label: 'Trễ hạn',
    value: overdueCount.value,
    icon: 'alertTriangle',
    tone: 'danger',
  },
  {
    key: 'pending_accept',
    label: 'Chờ xác nhận',
    value: pendingAcceptCount.value,
    icon: 'clipboardCheck',
    tone: 'gold',
  },
  {
    key: 'in_progress',
    label: 'Đang làm',
    value: inProgressCount.value,
    icon: 'play',
    tone: 'tertiary',
  },
]);

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 11) return 'Buổi sáng tốt lành';
  if (hour < 14) return 'Buổi trưa vui vẻ';
  if (hour < 18) return 'Buổi chiều hiệu quả';
  return 'Buổi tối an lành';
});

const displayName = computed(() => auth.user?.name?.split(' ')?.slice(-1)?.[0] || auth.user?.name || 'bạn');

const progressPercent = computed(() => {
  const value = props.overview?.kpis?.average_progress_percent;
  if (value === null || value === undefined || Number.isNaN(Number(value))) return null;
  return Math.min(100, Math.max(0, Number(value)));
});

const taskStatusLabels = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  under_review: 'Đang đánh giá',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

const activeStatusLabel = computed(() => {
  if (!statusFilter.value) return null;
  return taskStatusLabels[statusFilter.value] ?? statusFilter.value;
});

const allTasks = computed(() => asList(props.overview?.tasks));

const filteredTasks = computed(() => {
  let list = allTasks.value;

  if (statusFilter.value) {
    list = list.filter((task) => task.status === statusFilter.value);
  }

  if (quickFilter.value === 'overdue') {
    list = list.filter((task) => task.is_overdue);
  } else if (quickFilter.value === 'due_today') {
    list = list.filter(
      (task) => !['completed', 'cancelled'].includes(task.status) && isDueToday(task.end_date),
    );
  } else if (quickFilter.value === 'pending_accept') {
    list = list.filter((task) => task.can_accept);
  } else if (quickFilter.value === 'in_progress') {
    list = list.filter((task) => task.status === 'in_progress');
  }

  const q = taskSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter((task) => {
      const haystack = [task.title, task.project_name, task.creator_name, task.status_label]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return haystack.includes(q);
    });
  }

  return list;
});

const hasActiveFilters = computed(
  () => Boolean(statusFilter.value || quickFilter.value || taskSearch.value.trim()),
);

const quickFilterLabel = computed(() => {
  const chip = heroQuickFilters.value.find((item) => item.key === quickFilter.value);
  return chip?.label ?? null;
});

const overdueCount = computed(() => props.overview?.kpis?.overdue_tasks_count ?? 0);

function clearAllFilters() {
  statusFilter.value = null;
  quickFilter.value = null;
  taskSearch.value = '';
}

function toggleQuickFilter(key) {
  if (props.preview) return;
  statusFilter.value = null;
  quickFilter.value = quickFilter.value === key ? null : key;
}

function onKpiClick(kind) {
  if (props.preview) return;
  clearAllFilters();
  if (kind === 'overdue') {
    quickFilter.value = 'overdue';
  } else if (kind === 'progress') {
    statusFilter.value = 'in_progress';
  }
}

function goQuickLink(routeName) {
  if (props.preview) return;
  router.push({ name: routeName }).catch(() => {});
}

function onUtilitySelect(item) {
  if (props.preview) return;
  emit('utility-select', item);
}

async function onAcceptTask(task) {
  if (props.preview || acceptingTaskId.value) return;
  acceptingTaskId.value = task.id;
  try {
    const { data } = await window.axios.post(`/api/project/tasks/${task.id}/accept`);
    emit('task-accepted', {
      taskId: task.id,
      acceptedAt: data?.task?.accepted_at ?? new Date().toISOString(),
    });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xác nhận được công việc.');
  } finally {
    acceptingTaskId.value = null;
  }
}
</script>

<template>
  <div
    class="dashboard-me"
    :class="{
      'dashboard-me--preview': preview,
      'dashboard-me--desktop-layout': forceDesktopLayout,
    }"
  >
    <section class="dashboard-me__hero">
      <div class="dashboard-me__hero-copy">
        <p class="dashboard-me__hero-kicker">{{ todayLabel }}</p>
        <h2 class="dashboard-me__hero-title">{{ greeting }}, {{ displayName }}</h2>
        <p class="dashboard-me__hero-lead">Hôm nay bạn tập trung vào việc gì?</p>
        <div class="dashboard-me__hero-actions">
          <button
            v-for="chip in heroQuickFilters"
            :key="chip.key"
            type="button"
            class="dashboard-me__hero-chip"
            :class="[
              `dashboard-me__hero-chip--${chip.tone}`,
              { 'dashboard-me__hero-chip--active': quickFilter === chip.key },
            ]"
            :disabled="preview || overviewLoading"
            @click="toggleQuickFilter(chip.key)"
          >
            <AppIcon :name="chip.icon" :size="14" />
            <span class="dashboard-me__hero-chip-label">{{ chip.label }}</span>
            <span class="dashboard-me__hero-chip-value">{{ chip.value }}</span>
          </button>
        </div>
        <div v-if="hasActiveFilters" class="dashboard-me__filter-chip">
          <AppIcon name="sliders" :size="14" />
          <span v-if="activeStatusLabel">Trạng thái: {{ activeStatusLabel }}</span>
          <span v-else-if="quickFilterLabel">{{ quickFilterLabel }}</span>
          <span v-else-if="taskSearch.trim()">Tìm: «{{ taskSearch.trim() }}»</span>
          <button
            type="button"
            class="dashboard-me__filter-clear"
            aria-label="Bỏ mọi bộ lọc"
            :disabled="preview"
            @click="clearAllFilters"
          >
            <AppIcon name="close" :size="12" />
          </button>
        </div>
      </div>

      <div class="dashboard-me__hero-stats">
        <div class="dashboard-me__ring" :class="{ 'dashboard-me__ring--loading': overviewLoading }">
          <svg viewBox="0 0 120 120" class="dashboard-me__ring-svg" aria-hidden="true">
            <circle cx="60" cy="60" r="52" class="dashboard-me__ring-track" />
            <circle
              cx="60"
              cy="60"
              r="52"
              class="dashboard-me__ring-fill"
              :style="{ strokeDashoffset: progressPercent === null ? 326.7 : 326.7 - (326.7 * progressPercent) / 100 }"
            />
          </svg>
          <div class="dashboard-me__ring-center">
            <span class="dashboard-me__ring-value">
              {{ progressPercent === null ? '—' : `${progressPercent.toLocaleString('vi-VN', { maximumFractionDigits: 1 })}%` }}
            </span>
            <span class="dashboard-me__ring-label">Tiến độ TB</span>
          </div>
        </div>
        <ul class="dashboard-me__hero-metrics">
          <li>
            <span class="dashboard-me__metric-value">{{ overview?.kpis?.total_tasks ?? '—' }}</span>
            <span class="dashboard-me__metric-label">Tổng việc</span>
          </li>
          <li :class="{ 'dashboard-me__metric--warn': overdueCount > 0 }">
            <span class="dashboard-me__metric-value">{{ overview?.kpis?.overdue_tasks_count ?? '—' }}</span>
            <span class="dashboard-me__metric-label">Trễ hạn</span>
          </li>
        </ul>
      </div>
    </section>

    <div class="dashboard-me__kpis">
      <KpiCard
        label="Tổng công việc"
        :value="overview?.kpis?.total_tasks ?? null"
        icon="listChecks"
        tone="primary"
        clickable
        :active="!hasActiveFilters"
        @click="onKpiClick('total')"
      />
      <KpiCard
        label="Tiến độ trung bình"
        :value="overview?.kpis?.average_progress_percent ?? null"
        suffix="%"
        icon="percent"
        :decimals="1"
        tone="secondary"
        clickable
        :active="statusFilter === 'in_progress'"
        @click="onKpiClick('progress')"
      />
      <KpiCard
        label="Công việc trễ hạn"
        :value="overview?.kpis?.overdue_tasks_count ?? null"
        icon="clock"
        tone="danger"
        clickable
        :active="quickFilter === 'overdue'"
        @click="onKpiClick('overdue')"
      />
    </div>

    <div class="dashboard-me__tools">
      <section class="dashboard-me__panel dashboard-me__panel--utilities dashboard-me__panel--accent-gold">
        <div class="dashboard-me__panel-head dashboard-me__panel-head--row">
          <div>
            <h2>Tiện ích nhanh</h2>
            <p class="dashboard-me__panel-sub">Lối tắt tới công cụ thường dùng</p>
          </div>
          <AppIcon name="sparkles" :size="18" class="dashboard-me__panel-accent-icon" aria-hidden="true" />
        </div>
        <QuickUtilitiesGrid
          :items="utilities"
          :loading="utilitiesLoading"
          :preview="preview"
          :active-key="activeUtilityKey"
          @select="onUtilitySelect"
        />
      </section>

      <section class="dashboard-me__panel dashboard-me__panel--shortcuts dashboard-me__panel--accent-primary">
        <div class="dashboard-me__panel-head">
          <h2>Đi tới nhanh</h2>
          <p class="dashboard-me__panel-sub">Chấm công, bảng tin, trò chuyện và hồ sơ</p>
        </div>
        <ul class="dashboard-me__shortcuts">
          <li v-for="link in quickLinks" :key="link.route">
            <button
              type="button"
              class="dashboard-me__shortcut"
              :class="`dashboard-me__shortcut--${link.tone}`"
              :disabled="preview"
              @click="goQuickLink(link.route)"
            >
              <span class="dashboard-me__shortcut-icon" aria-hidden="true">
                <AppIcon :name="link.icon" :size="18" />
              </span>
              <span class="dashboard-me__shortcut-label">{{ link.label }}</span>
            </button>
          </li>
        </ul>
      </section>
    </div>

    <section class="dashboard-me__panel dashboard-me__panel--table dashboard-me__panel--accent-secondary">
        <div class="dashboard-me__panel-head dashboard-me__panel-head--row">
          <div class="dashboard-me__panel-head-main">
            <div class="dashboard-me__panel-title">
              <h2>Danh sách công việc</h2>
              <p v-if="activeStatusLabel" class="dashboard-me__panel-sub">Chỉ hiển thị: {{ activeStatusLabel }}</p>
            </div>
            <label class="dashboard-me__task-search">
              <AppIcon name="search" :size="16" class="dashboard-me__task-search-icon" aria-hidden="true" />
              <input
                v-model="taskSearch"
                type="search"
                class="dashboard-me__task-search-input"
                placeholder="Tìm công việc, dự án, người giao…"
                :disabled="preview"
                autocomplete="off"
              />
              <button
                v-if="taskSearch.trim()"
                type="button"
                class="dashboard-me__task-search-clear"
                aria-label="Xóa từ khóa tìm kiếm"
                :disabled="preview"
                @click="taskSearch = ''"
              >
                <AppIcon name="close" :size="14" />
              </button>
            </label>
          </div>
          <span class="dashboard-me__count-badge">{{ filteredTasks.length }} việc</span>
        </div>
        <DashboardSkeleton v-if="overviewLoading" :rows="6" />
        <MyTaskTable
          v-else
          :rows="filteredTasks"
          :loading="overviewLoading"
          :accepting-task-id="acceptingTaskId"
          @accept-task="onAcceptTask"
        />
    </section>
  </div>
</template>

<style scoped>
.dashboard-me {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
  max-width: 100%;
  background: var(--color-surface-muted);
  border-radius: var(--radius-lg);
}

.dashboard-me--preview {
  pointer-events: none;
  user-select: none;
}

.dashboard-me--preview .dashboard-me__panel--utilities {
  pointer-events: auto;
  user-select: auto;
}

.dashboard-me--preview .dashboard-me__panel--utilities :deep(.quick-utilities) {
  pointer-events: auto;
  user-select: auto;
}

.dashboard-me__hero {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(16rem, 0.7fr);
  gap: var(--space-5);
  align-items: center;
  padding: var(--space-5);
  padding-left: calc(var(--space-5) + 0.35rem);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dashboard-me__hero::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.dashboard-me__hero-copy {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.dashboard-me__hero-kicker {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: var(--color-text-muted);
  text-transform: capitalize;
}

.dashboard-me__hero-lead {
  margin: 0;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.dashboard-me__hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

.dashboard-me__hero-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.dashboard-me__hero-chip:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: inset 0 0 0 1px var(--color-border-strong), var(--shadow-sm);
}

.dashboard-me__hero-chip--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.dashboard-me__hero-chip--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.dashboard-me__hero-chip--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-800);
}

.dashboard-me__hero-chip--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-800);
}

.dashboard-me__hero-chip--active {
  box-shadow: inset 0 0 0 1.5px currentColor;
}

.dashboard-me__hero-chip-value {
  min-width: 1.25rem;
  padding: 0.05rem 0.4rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  font-size: 0.6875rem;
  font-weight: 800;
  text-align: center;
}

.dashboard-me__hero-title {
  margin: 0;
  font-size: clamp(1.375rem, 2vw, 1.75rem);
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.dashboard-me__filter-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  align-self: flex-start;
  margin-top: var(--space-1);
  padding: 0.3rem 0.45rem 0.3rem 0.65rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary-900);
  font-size: 0.75rem;
  font-weight: 600;
}

.dashboard-me__filter-clear {
  display: grid;
  place-items: center;
  width: 1.25rem;
  height: 1.25rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-primary);
  cursor: pointer;
}

.dashboard-me__hero-stats {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-4);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.dashboard-me__ring {
  position: relative;
  width: 7.5rem;
  height: 7.5rem;
  flex-shrink: 0;
}

.dashboard-me__ring-svg {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.dashboard-me__ring-track {
  fill: none;
  stroke: var(--color-border);
  stroke-width: 10;
}

.dashboard-me__ring-fill {
  fill: none;
  stroke: var(--color-primary);
  stroke-width: 10;
  stroke-linecap: round;
  stroke-dasharray: 326.7;
  transition: stroke-dashoffset 0.6s ease;
}

.dashboard-me__ring--loading .dashboard-me__ring-fill {
  opacity: 0.35;
}

.dashboard-me__ring-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.dashboard-me__ring-value {
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.1;
  color: var(--color-text);
}

.dashboard-me__ring-label {
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.dashboard-me__hero-metrics {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 6.5rem;
}

.dashboard-me__hero-metrics li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dashboard-me__metric-value {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.1;
  color: var(--color-text);
}

.dashboard-me__metric-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.dashboard-me__metric--warn {
  background: var(--color-danger-tint-bg);
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
}

.dashboard-me__metric--warn .dashboard-me__metric-value,
.dashboard-me__metric--warn .dashboard-me__metric-label {
  color: var(--color-danger-tint-fg);
}

.dashboard-me__panel {
  position: relative;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  padding-left: calc(var(--space-4) + 0.35rem);
  min-width: 0;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dashboard-me__panel--accent-primary::before,
.dashboard-me__panel--accent-secondary::before,
.dashboard-me__panel--accent-gold::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
}

.dashboard-me__panel--accent-primary::before {
  background: var(--color-primary);
}

.dashboard-me__panel--accent-secondary::before {
  background: var(--color-secondary);
}

.dashboard-me__panel--accent-gold::before {
  background: var(--color-gold);
}

.dashboard-me__panel-head {
  margin-bottom: var(--space-3);
}

.dashboard-me__panel-head--row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}

.dashboard-me__panel-head h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.dashboard-me__panel-sub {
  margin: 0.25rem 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dashboard-me__panel-accent-icon {
  color: var(--color-gold);
  flex-shrink: 0;
  margin-top: 2px;
}

.dashboard-me__count-badge {
  flex-shrink: 0;
  padding: 0.25rem 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-800, var(--color-tertiary));
  font-size: 0.6875rem;
  font-weight: 700;
}

.dashboard-me__panel-head-main {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  min-width: 0;
  flex: 1;
}

.dashboard-me__panel-title {
  min-width: 0;
}

.dashboard-me__task-search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  max-width: 22rem;
  margin-left: auto;
  padding: 0.4rem 0.7rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dashboard-me__task-search-icon {
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.dashboard-me__task-search-input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--color-text);
}

.dashboard-me__task-search-input::placeholder {
  color: var(--color-text-muted);
}

.dashboard-me__task-search-clear {
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.dashboard-me__shortcuts {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  list-style: none;
  margin: 0;
  padding: 0;
}

.dashboard-me__shortcut {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: var(--space-3);
  width: 100%;
  padding: 0.55rem var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.dashboard-me__shortcut:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: inset 0 0 0 1px var(--color-border-strong, var(--color-border)), var(--shadow-sm);
}

.dashboard-me__shortcut-icon {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
}

.dashboard-me__shortcut--primary .dashboard-me__shortcut-icon {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.dashboard-me__shortcut--secondary .dashboard-me__shortcut-icon {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.dashboard-me__shortcut--tertiary .dashboard-me__shortcut-icon {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.dashboard-me__shortcut--gold .dashboard-me__shortcut-icon {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}

.dashboard-me__shortcut--umber .dashboard-me__shortcut-icon {
  background: var(--color-umber-surface);
  color: var(--color-umber);
}

.dashboard-me__shortcut-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  text-align: left;
  line-height: 1.25;
}

.dashboard-me__kpis {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
}

.dashboard-me__kpis :deep(.kpi-card) {
  clip-path: none;
  border-radius: var(--radius-lg);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dashboard-me__kpis :deep(.kpi-card::before) {
  top: var(--space-2);
  right: auto;
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  height: auto;
  border-radius: 0;
}

.dashboard-me__tools {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(16rem, 0.85fr);
  gap: var(--space-3);
  align-items: start;
}

@media (max-width: 1024px) {
  .dashboard-me__hero {
    grid-template-columns: 1fr;
  }

  .dashboard-me__hero-stats {
    justify-content: flex-start;
  }

  .dashboard-me__tools {
    grid-template-columns: 1fr;
  }

  .dashboard-me__shortcuts {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }

  .dashboard-me__shortcut {
    flex-direction: column;
    justify-content: center;
    padding: var(--space-3) var(--space-2);
  }

  .dashboard-me__shortcut-label {
    font-size: 0.6875rem;
    text-align: center;
  }

  .dashboard-me__panel-head-main {
    flex-direction: column;
    align-items: stretch;
  }

  .dashboard-me__task-search {
    margin-left: 0;
    max-width: none;
  }
}

@media (max-width: 768px) {
  .dashboard-me__kpis {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-me__shortcuts {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 480px) {
  .dashboard-me {
    padding: var(--space-3);
  }

  .dashboard-me__hero {
    padding: var(--space-4);
    padding-left: calc(var(--space-4) + 0.35rem);
  }

  .dashboard-me__kpis {
    grid-template-columns: 1fr;
  }

  .dashboard-me__shortcuts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard-me__hero-actions {
    gap: var(--space-1);
  }
}

/* Xem trước admin: không co theo breakpoint — đúng desktop >1024px */
.dashboard-me.dashboard-me--desktop-layout {
  min-width: 56rem;
  padding: var(--space-4);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__hero {
  grid-template-columns: minmax(0, 1.45fr) minmax(16rem, 0.7fr);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__hero-stats {
  justify-content: flex-end;
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__kpis {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__tools {
  grid-template-columns: minmax(0, 1.6fr) minmax(16rem, 0.85fr);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__shortcuts {
  display: flex;
  flex-direction: column;
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__shortcut {
  flex-direction: row;
  justify-content: flex-start;
  align-items: center;
  padding: 0.55rem var(--space-3);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__shortcut-label {
  font-size: 0.8125rem;
  text-align: left;
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__panel-head-main {
  flex-direction: row;
  align-items: center;
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__task-search {
  margin-left: auto;
  max-width: 22rem;
}
</style>
