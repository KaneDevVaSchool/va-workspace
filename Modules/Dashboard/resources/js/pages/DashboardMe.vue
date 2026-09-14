<script setup>
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { computed, onMounted, ref } from 'vue';
import DashboardSkeleton from '../components/DashboardSkeleton.vue';
import DonutChart from '../components/DonutChart.vue';
import MyTaskTable from '../components/MyTaskTable.vue';
import KpiCard from '../components/KpiCard.vue';
import { statusColor } from '../utils/chartColors';
import { asList, asRecord, unwrapOverview } from '../utils/dashboardPayload';

const overview = ref(null);
const overviewLoading = ref(true);
const statusFilter = ref(null);

async function loadOverview() {
  overviewLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/me/overview');
    overview.value = unwrapOverview(data);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được số liệu tổng quan.');
  } finally {
    overviewLoading.value = false;
  }
}

onMounted(loadOverview);

function toggleStatus(value) {
  statusFilter.value = statusFilter.value === value ? null : value;
}

const taskStatusLabels = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  under_review: 'Đang đánh giá',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

const workStatusDonutData = computed(() => {
  const breakdown = asRecord(overview.value?.tasks_status_breakdown);
  if (!breakdown) return { labels: [], series: [] };
  const labels = Object.keys(breakdown).map((key) => ({ value: key, label: taskStatusLabels[key] ?? key, color: statusColor(key) }));
  return { labels, series: Object.values(breakdown) };
});

const allTasks = computed(() => asList(overview.value?.tasks));

const filteredTasks = computed(() => {
  if (!statusFilter.value) return allTasks.value;
  return allTasks.value.filter((task) => task.status === statusFilter.value);
});
</script>

<template>
  <PageHeader title="Dashboard của tôi" subtitle="Tổng quan nhanh tiến độ công việc của bạn">
    <template #actions>
      <button type="button" class="dashboard-me__refresh" aria-label="Làm mới dữ liệu" :disabled="overviewLoading" @click="loadOverview">
        <AppIcon name="refresh" :size="16" />
        <span>Làm mới</span>
      </button>
    </template>
  </PageHeader>

  <div class="dashboard-me">
    <div class="dashboard-me__kpis">
      <KpiCard label="Tổng công việc" :value="overview?.kpis?.total_tasks ?? null" icon="listChecks" tone="tertiary" />
      <KpiCard label="Tiến độ trung bình" :value="overview?.kpis?.average_progress_percent ?? null" suffix="%" icon="percent" :decimals="1" tone="secondary" />
      <KpiCard label="Công việc trễ hạn" :value="overview?.kpis?.overdue_tasks_count ?? null" icon="clock" tone="danger" />
    </div>

    <div class="dashboard-me__grid">
      <section class="dashboard-me__panel dashboard-me__panel--donut">
        <div class="dashboard-me__panel-head">
          <h2>Công việc theo trạng thái</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="240px" />
        <DonutChart v-else :labels="workStatusDonutData.labels" :series="workStatusDonutData.series" :selected="statusFilter" @select="toggleStatus" />
      </section>

      <section class="dashboard-me__panel dashboard-me__panel--table">
        <div class="dashboard-me__panel-head">
          <h2>Danh sách công việc</h2>
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
}

.dashboard-me__refresh {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 2rem;
  padding: 0 var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.dashboard-me__refresh:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.dashboard-me__refresh:disabled {
  opacity: 0.6;
  cursor: default;
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

.dashboard-me__panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
  min-width: 0;
}

.dashboard-me__panel-head {
  margin-bottom: var(--space-3);
}

.dashboard-me__panel-head h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

@media (max-width: 1024px) {
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

  .dashboard-me__kpis {
    grid-template-columns: 1fr;
  }
}
</style>
