<script setup>
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { computed, onMounted, ref } from 'vue';
import BarChart from '../components/BarChart.vue';
import DashboardSkeleton from '../components/DashboardSkeleton.vue';
import DonutChart from '../components/DonutChart.vue';
import EmployeeDataTable from '../components/EmployeeDataTable.vue';
import EmployeeDetailDrawer from '../components/EmployeeDetailDrawer.vue';
import KpiCard from '../components/KpiCard.vue';
import { statusColor } from '../utils/chartColors';
import { asList, asRecord, unwrapOverview, unwrapTablePage } from '../utils/dashboardPayload';

const overview = ref(null);
const overviewLoading = ref(true);

const table = ref({ data: [], current_page: 1, last_page: 1, per_page: 20, total: 0 });
const tableLoading = ref(true);
const tablePerPage = ref(20);
const tableSearch = ref('');
const statusFilter = ref(null);

const selectedUserId = ref(null);
const selectedEmployee = ref(null);
const selectedLoading = ref(false);

const filtersActive = computed(() => Boolean(statusFilter.value || tableSearch.value));

async function loadOverview() {
  overviewLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/department/overview');
    overview.value = unwrapOverview(data);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được số liệu tổng quan.');
  } finally {
    overviewLoading.value = false;
  }
}

async function loadTable(page = 1) {
  tableLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/department/employees', {
      params: {
        status: statusFilter.value || undefined,
        q: tableSearch.value || undefined,
        per_page: tablePerPage.value,
        page,
      },
    });
    table.value = unwrapTablePage(data, {
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: tablePerPage.value,
      total: 0,
    });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách nhân viên.');
  } finally {
    tableLoading.value = false;
  }
}

function refreshAll() {
  loadOverview();
  loadTable(1);
}

onMounted(refreshAll);

function toggleStatus(value) {
  statusFilter.value = statusFilter.value === value ? null : value;
  loadTable(1);
}

function onSearch(term) {
  tableSearch.value = term;
  loadTable(1);
}

function clearTableFilters() {
  statusFilter.value = null;
  tableSearch.value = '';
  loadTable(1);
}

async function openEmployee(userId) {
  selectedUserId.value = userId;
  selectedEmployee.value = null;
  selectedLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/dashboard/department/employees/${userId}`);
    selectedEmployee.value = data;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được chi tiết nhân viên.');
  } finally {
    selectedLoading.value = false;
  }
}

function closeDrawer() {
  selectedUserId.value = null;
  selectedEmployee.value = null;
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

const workloadBarData = computed(() => {
  const rows = asList(overview.value?.workload).slice(0, 12);
  return {
    categories: rows.map((r) => r.name),
    values: rows.map((r) => r.user_id),
    series: [{ name: 'Số công việc', data: rows.map((r) => r.tasks_total) }],
  };
});

const employeeProgressBarData = computed(() => {
  const rows = asList(overview.value?.employee_progress).slice(0, 12);
  return {
    categories: rows.map((r) => r.name),
    values: rows.map((r) => r.user_id),
    series: [{ name: 'Tiến độ TB (%)', data: rows.map((r) => r.average_progress_percent) }],
  };
});

const agingLabels = { '0_3': '0–3 ngày', '4_7': '4–7 ngày', '8_14': '8–14 ngày', over_14: '>14 ngày' };
const workAgingBarData = computed(() => {
  const aging = asRecord(overview.value?.work_aging);
  if (!aging) return { categories: [], series: [] };
  return {
    categories: Object.keys(aging).map((k) => agingLabels[k]),
    series: [{ name: 'Công việc chưa hoàn thành', data: Object.values(aging) }],
  };
});

const tableRows = computed(() => asList(table.value?.data));

function onWorkloadSelect(userId) {
  openEmployee(userId);
}
</script>

<template>
  <PageHeader
    title="Dashboard phòng ban"
    :subtitle="overview?.department_name ? `Phòng ${overview.department_name} — tiến độ, hiệu suất và khối lượng công việc` : 'Tiến độ, hiệu suất và khối lượng công việc của phòng ban'"
  >
    <template #actions>
      <button type="button" class="dashboard-department__refresh" aria-label="Làm mới dữ liệu" :disabled="overviewLoading" @click="refreshAll">
        <AppIcon name="refresh" :size="16" />
        <span>Làm mới</span>
      </button>
    </template>
  </PageHeader>

  <div class="dashboard-department">
    <div class="dashboard-department__kpis">
      <KpiCard label="Tổng dự án" :value="overview?.kpis?.total_projects ?? null" icon="layers" tone="primary" />
      <KpiCard label="Tổng công việc" :value="overview?.kpis?.total_tasks ?? null" icon="listChecks" tone="tertiary" />
      <KpiCard label="Tiến độ trung bình" :value="overview?.kpis?.average_progress_percent ?? null" suffix="%" icon="percent" :decimals="1" tone="secondary" />
      <KpiCard label="Công việc trễ hạn" :value="overview?.kpis?.overdue_tasks_count ?? null" icon="clock" tone="danger" />
    </div>

    <p class="dashboard-department__note">
      Số lượng công việc là tín hiệu tham khảo phân bổ, không phải thước đo hiệu suất tuyệt đối — công việc còn khác nhau về độ quan trọng và độ phức tạp.
    </p>

    <div class="dashboard-department__grid">
      <section class="dashboard-department__panel">
        <div class="dashboard-department__panel-head">
          <h2>Công việc theo trạng thái</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="240px" />
        <DonutChart v-else :labels="workStatusDonutData.labels" :series="workStatusDonutData.series" :selected="statusFilter" @select="toggleStatus" />
      </section>

      <section class="dashboard-department__panel">
        <div class="dashboard-department__panel-head">
          <h2>Khối lượng việc theo người</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="260px" />
        <BarChart
          v-else
          horizontal
          :categories="workloadBarData.categories"
          :series="workloadBarData.series"
          :clickable-categories="workloadBarData.values"
          :height="Math.max(220, workloadBarData.categories.length * 30)"
          @select="onWorkloadSelect"
        />
      </section>
    </div>

    <div class="dashboard-department__grid">
      <section class="dashboard-department__panel">
        <div class="dashboard-department__panel-head">
          <h2>Tiến độ trung bình theo người</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="260px" />
        <BarChart
          v-else
          horizontal
          value-suffix="%"
          :categories="employeeProgressBarData.categories"
          :series="employeeProgressBarData.series"
          :clickable-categories="employeeProgressBarData.values"
          :height="Math.max(220, employeeProgressBarData.categories.length * 30)"
          @select="onWorkloadSelect"
        />
      </section>

      <section class="dashboard-department__panel">
        <div class="dashboard-department__panel-head">
          <h2>Công việc đang tồn theo số ngày</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="220px" />
        <BarChart v-else :categories="workAgingBarData.categories" :series="workAgingBarData.series" :height="220" />
      </section>
    </div>

    <section class="dashboard-department__table-section">
      <div class="dashboard-department__table-area">
        <h2 class="dashboard-department__table-title">Danh sách nhân viên</h2>
        <DashboardSkeleton v-if="tableLoading && tableRows.length === 0" :rows="6" />
        <EmployeeDataTable
          v-else
          :rows="tableRows"
          :loading="tableLoading"
          :page="table.current_page"
          :last-page="table.last_page"
          :per-page="table.per_page"
          :total="table.total"
          :filters-active="filtersActive"
          @update:page="loadTable($event)"
          @update:per-page="(tablePerPage = $event), loadTable(1)"
          @search="onSearch"
          @clear-filters="clearTableFilters"
          @row-click="openEmployee"
        />
      </div>

      <EmployeeDetailDrawer v-if="selectedUserId" :employee="selectedEmployee" :loading="selectedLoading" @close="closeDrawer" />
    </section>
  </div>
</template>

<style scoped>
.dashboard-department {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
  max-width: 100%;
}

.dashboard-department__refresh {
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

.dashboard-department__refresh:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.dashboard-department__refresh:disabled {
  opacity: 0.6;
  cursor: default;
}

.dashboard-department__kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-3);
}

.dashboard-department__note {
  margin: 0;
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-muted);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.dashboard-department__grid {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: var(--space-3);
  align-items: start;
}

.dashboard-department__panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
  min-width: 0;
}

.dashboard-department__panel-head {
  margin-bottom: var(--space-3);
}

.dashboard-department__panel-head h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.dashboard-department__table-section {
  display: flex;
  gap: var(--space-4);
  align-items: flex-start;
}

.dashboard-department__table-area {
  flex: 1;
  min-width: 0;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
}

.dashboard-department__table-title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

@media (max-width: 1024px) {
  .dashboard-department__grid {
    grid-template-columns: 1fr;
  }

  .dashboard-department__table-section {
    flex-direction: column;
  }
}

@media (max-width: 768px) {
  .dashboard-department__kpis {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .dashboard-department {
    padding: var(--space-3);
  }

  .dashboard-department__kpis {
    grid-template-columns: 1fr;
  }
}
</style>
