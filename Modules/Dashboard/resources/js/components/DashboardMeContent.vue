<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { computed, ref } from 'vue';
import DashboardSkeleton from './DashboardSkeleton.vue';
import DonutChart from './DonutChart.vue';
import MyTaskTable from './MyTaskTable.vue';
import KpiCard from './KpiCard.vue';
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';
import { statusColor } from '../utils/chartColors';
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

const emit = defineEmits(['utility-select']);

const auth = useAuthStore();
const statusFilter = ref(null);

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

const workStatusDonutData = computed(() => {
  const breakdown = asRecord(props.overview?.tasks_status_breakdown);
  if (!breakdown) return { labels: [], series: [] };
  const labels = Object.keys(breakdown).map((key) => ({
    value: key,
    label: taskStatusLabels[key] ?? key,
    color: statusColor(key),
  }));
  return { labels, series: Object.values(breakdown) };
});

const allTasks = computed(() => asList(props.overview?.tasks));

const filteredTasks = computed(() => {
  if (!statusFilter.value) return allTasks.value;
  return allTasks.value.filter((task) => task.status === statusFilter.value);
});

const overdueCount = computed(() => props.overview?.kpis?.overdue_tasks_count ?? 0);

function toggleStatus(value) {
  if (props.preview) return;
  statusFilter.value = statusFilter.value === value ? null : value;
}

function onUtilitySelect(item) {
  if (props.preview) return;
  emit('utility-select', item);
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
        <p class="dashboard-me__hero-kicker">{{ greeting }}, {{ displayName }}</p>
        <h2 class="dashboard-me__hero-title">Hôm nay bạn tập trung vào việc gì?</h2>
        <div v-if="activeStatusLabel" class="dashboard-me__filter-chip">
          <AppIcon name="sliders" :size="14" />
          <span>Đang lọc: {{ activeStatusLabel }}</span>
          <button
            type="button"
            class="dashboard-me__filter-clear"
            aria-label="Bỏ lọc trạng thái"
            :disabled="preview"
            @click="statusFilter = null"
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

    <section class="dashboard-me__panel dashboard-me__panel--utilities">
      <div class="dashboard-me__panel-head dashboard-me__panel-head--row">
        <div>
          <h2>Tiện ích nhanh</h2>
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

    <div class="dashboard-me__kpis">
      <KpiCard label="Tổng công việc" :value="overview?.kpis?.total_tasks ?? null" icon="listChecks" tone="primary" />
      <KpiCard
        label="Tiến độ trung bình"
        :value="overview?.kpis?.average_progress_percent ?? null"
        suffix="%"
        icon="percent"
        :decimals="1"
        tone="secondary"
      />
      <KpiCard label="Công việc trễ hạn" :value="overview?.kpis?.overdue_tasks_count ?? null" icon="clock" tone="danger" />
    </div>

    <div class="dashboard-me__grid">
      <section class="dashboard-me__panel dashboard-me__panel--donut">
        <div class="dashboard-me__panel-head">
          <h2>Công việc theo trạng thái</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="240px" />
        <DonutChart
          v-else
          :labels="workStatusDonutData.labels"
          :series="workStatusDonutData.series"
          :selected="statusFilter"
          @select="toggleStatus"
        />
      </section>

      <section class="dashboard-me__panel dashboard-me__panel--table">
        <div class="dashboard-me__panel-head dashboard-me__panel-head--row">
          <div>
            <h2>Danh sách công việc</h2>
            <p v-if="activeStatusLabel" class="dashboard-me__panel-sub">Chỉ hiển thị: {{ activeStatusLabel }}</p>
          </div>
          <span class="dashboard-me__count-badge">{{ filteredTasks.length }} việc</span>
        </div>
        <DashboardSkeleton v-if="overviewLoading" :rows="6" />
        <MyTaskTable v-else :rows="filteredTasks" :loading="overviewLoading" />
      </section>
    </div>
  </div>
</template>

<style scoped>
.dashboard-me {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
  max-width: 100%;
  background: var(--color-primary-surface);
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
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: var(--space-4);
  padding: var(--space-5);
  border-radius: var(--radius-xl);
  overflow: hidden;
  background:
    radial-gradient(ellipse 80% 120% at 100% 0%, rgba(255, 255, 255, 0.22) 0%, transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-700) 48%, var(--color-secondary) 100%);
  color: #fff;
  box-shadow: var(--shadow-md);
}

.dashboard-me__hero::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 0.45rem;
  background: color-mix(in srgb, var(--color-gold) 78%, var(--color-on-primary));
}

.dashboard-me__hero-copy {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-left: var(--space-2);
}

.dashboard-me__hero-kicker {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  opacity: 0.92;
}

.dashboard-me__hero-title {
  margin: 0;
  font-size: clamp(1.25rem, 2vw, 1.625rem);
  font-weight: 800;
  line-height: 1.25;
}

.dashboard-me__filter-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  margin-top: var(--space-2);
  padding: 0.35rem 0.5rem 0.35rem 0.65rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.16);
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
  background: rgba(255, 255, 255, 0.2);
  color: inherit;
  cursor: pointer;
}

.dashboard-me__hero-stats {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-4);
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
  stroke: rgba(255, 255, 255, 0.22);
  stroke-width: 10;
}

.dashboard-me__ring-fill {
  fill: none;
  stroke: color-mix(in srgb, var(--color-gold) 55%, var(--color-on-primary));
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
  font-weight: 800;
  line-height: 1.1;
}

.dashboard-me__ring-label {
  font-size: 0.625rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  opacity: 0.85;
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
  background: rgba(255, 255, 255, 0.12);
}

.dashboard-me__metric-value {
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.1;
}

.dashboard-me__metric-label {
  font-size: 0.6875rem;
  font-weight: 600;
  opacity: 0.88;
}

.dashboard-me__metric--warn {
  background: rgba(255, 255, 255, 0.22);
}

.dashboard-me__panel {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
  min-width: 0;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dashboard-me__panel--utilities {
  background: linear-gradient(180deg, var(--color-surface) 0%, var(--color-surface-muted) 140%);
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

.dashboard-me__kpis {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
}

.dashboard-me__grid {
  display: grid;
  grid-template-columns: 5fr 7fr;
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

  .dashboard-me__grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-me__kpis {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .dashboard-me {
    padding: var(--space-3);
  }

  .dashboard-me__hero {
    padding: var(--space-4);
  }

  .dashboard-me__kpis {
    grid-template-columns: 1fr;
  }
}

/* Xem trước admin: không co theo breakpoint — đúng desktop >1024px */
.dashboard-me.dashboard-me--desktop-layout {
  min-width: 56rem;
  padding: var(--space-4);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__hero {
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__hero-stats {
  justify-content: flex-end;
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__kpis {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.dashboard-me.dashboard-me--desktop-layout .dashboard-me__grid {
  grid-template-columns: 5fr 7fr;
}
</style>
