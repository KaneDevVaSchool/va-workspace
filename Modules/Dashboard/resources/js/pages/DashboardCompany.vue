<script setup>
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { computed, onMounted, ref } from 'vue';
import BarChart from '../components/BarChart.vue';
import DashboardSkeleton from '../components/DashboardSkeleton.vue';
import DonutChart from '../components/DonutChart.vue';
import KpiCard from '../components/KpiCard.vue';
import ProjectDataTable from '../components/ProjectDataTable.vue';
import ProjectDetailDrawer from '../components/ProjectDetailDrawer.vue';
import ProjectHealth from '../components/ProjectHealth.vue';
import { statusColor } from '../utils/chartColors';
import { asList, asRecord, formatYearMonth, unwrapOverview, unwrapTablePage } from '../utils/dashboardPayload';

const overview = ref(null);
const overviewLoading = ref(true);
const departmentFilter = ref('');

const table = ref({ data: [], total: 0 });
const tableLoading = ref(true);
// Bảng nhóm theo phòng ban — tải toàn bộ 1 lần (không phân trang, xem
// ProjectDataTable.vue), per_page lớn đủ phủ toàn bộ dự án công ty.
const TABLE_PER_PAGE = 2000;
const tableSortBy = ref('end_date');
const tableSortDir = ref('asc');
const tableSearch = ref('');

// Drill-down filter — đồng bộ 2 chiều giữa chart (donut/health/aging) và bảng.
const statusFilter = ref(null);
const healthFilter = ref(null);
const agingFilter = ref(null);
const overdueOnlyFilter = ref(false);

const selectedProjectId = ref(null);
const selectedProject = ref(null);
const selectedLoading = ref(false);

const timelineTab = ref('projects'); // 'projects' | 'tasks'
const departmentSort = ref('overdue'); // 'overdue' | 'progress' | 'running'

const filtersActive = computed(() => Boolean(statusFilter.value || healthFilter.value || agingFilter.value || overdueOnlyFilter.value || tableSearch.value));

async function loadOverview() {
  overviewLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/company/overview', {
      params: { department_id: departmentFilter.value || undefined },
    });
    overview.value = unwrapOverview(data);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được số liệu tổng quan.');
  } finally {
    overviewLoading.value = false;
  }
}

async function loadTable() {
  tableLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/company/projects', {
      params: {
        status: statusFilter.value || undefined,
        health: healthFilter.value || undefined,
        overdue_bucket: agingFilter.value || undefined,
        overdue_only: overdueOnlyFilter.value ? 1 : undefined,
        department_id: departmentFilter.value || undefined,
        q: tableSearch.value || undefined,
        sort_by: tableSortBy.value,
        sort_dir: tableSortDir.value,
        per_page: TABLE_PER_PAGE,
        page: 1,
      },
    });
    table.value = unwrapTablePage(data, { data: [], total: 0 });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách dự án.');
  } finally {
    tableLoading.value = false;
  }
}

function refreshAll() {
  loadOverview();
  loadTable();
}

onMounted(refreshAll);

function formatUpdatedAt(iso) {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
}

function toggleStatus(value) {
  statusFilter.value = statusFilter.value === value ? null : value;
  loadTable();
}

function scrollToProjectList() {
  document.getElementById('dashboard-project-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggleHealth(value) {
  healthFilter.value = healthFilter.value === value ? null : value;
  loadTable();
}

function toggleAging(value) {
  agingFilter.value = agingFilter.value === value ? null : value;
  loadTable();
}

function onSearch(term) {
  tableSearch.value = term;
  loadTable();
}

function clearTableFilters() {
  statusFilter.value = null;
  healthFilter.value = null;
  agingFilter.value = null;
  overdueOnlyFilter.value = false;
  tableSearch.value = '';
  loadTable();
}

/** Click KPI "Dự án đang trễ hạn" — lọc nhanh bảng chỉ còn dự án có days_overdue > 0. Click lại để bỏ lọc. */
function toggleOverdueQuickFilter() {
  overdueOnlyFilter.value = !overdueOnlyFilter.value;
  loadTable();
}

function onSort({ column, dir }) {
  tableSortBy.value = column;
  tableSortDir.value = dir;
  loadTable();
}

async function openProject(id) {
  selectedProjectId.value = id;
  selectedProject.value = null;
  selectedLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/dashboard/company/projects/${id}`);
    selectedProject.value = data;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được chi tiết dự án.');
  } finally {
    selectedLoading.value = false;
  }
}

function closeDrawer() {
  selectedProjectId.value = null;
  selectedProject.value = null;
}

const statusLabels = {
  planning: 'Lên kế hoạch',
  in_progress: 'Đang thực hiện',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};

const statusDonutData = computed(() => {
  const breakdown = asRecord(overview.value?.status_breakdown);
  if (!breakdown) return { labels: [], series: [] };
  const labels = Object.keys(breakdown).map((key) => ({ value: key, label: statusLabels[key] ?? key, color: statusColor(key) }));
  const series = Object.values(breakdown);
  return { labels, series };
});

const agingLabels = { '0_3': '0–3 ngày', '4_7': '4–7 ngày', '8_14': '8–14 ngày', over_14: '>14 ngày' };
const agingBarData = computed(() => {
  const aging = asRecord(overview.value?.overdue_aging);
  if (!aging) return { categories: [], values: [], series: [] };
  return {
    categories: Object.keys(aging).map((k) => agingLabels[k]),
    values: Object.keys(aging),
    series: [{ name: 'Dự án trễ', data: Object.values(aging) }],
  };
});

const departmentRows = computed(() => {
  const rows = [...asList(overview.value?.departments_performance)];
  if (departmentSort.value === 'progress') {
    rows.sort((a, b) => (a.average_progress_percent ?? 999) - (b.average_progress_percent ?? 999));
  } else if (departmentSort.value === 'running') {
    rows.sort((a, b) => b.projects_running - a.projects_running);
  } else {
    rows.sort((a, b) => b.projects_overdue - a.projects_overdue || (a.average_progress_percent ?? 999) - (b.average_progress_percent ?? 999));
  }
  return rows;
});

const departmentBarData = computed(() => {
  const rows = departmentRows.value.slice(0, 10);
  const key = departmentSort.value === 'progress' ? 'average_progress_percent' : departmentSort.value === 'running' ? 'projects_running' : 'projects_overdue';
  return {
    categories: rows.map((r) => r.department_name),
    series: [{ name: departmentSort.value === 'progress' ? 'Tiến độ TB (%)' : departmentSort.value === 'running' ? 'Đang thực hiện' : 'Trễ hạn', data: rows.map((r) => r[key] ?? 0) }],
  };
});

const timelineData = computed(() => {
  const t = asRecord(overview.value?.timeline);
  const months = asList(t?.months);
  if (!months.length) return { categories: [], series: [] };
  const startKey = timelineTab.value === 'projects' ? 'projects_starting' : 'tasks_starting';
  const endKey = timelineTab.value === 'projects' ? 'projects_ending' : 'tasks_ending';
  const zeros = months.map(() => 0);
  return {
    categories: months.map(formatYearMonth),
    series: [
      { name: 'Bắt đầu', data: asList(t[startKey]).length ? asList(t[startKey]) : zeros },
      { name: 'Kết thúc', data: asList(t[endKey]).length ? asList(t[endKey]) : zeros },
    ],
  };
});

const tableRows = computed(() => asList(table.value?.data));
</script>

<template>
  <PageHeader title="Dashboard tổng công ty" subtitle="Xem tổng quan để biết phòng ban nào cần chú ý và dự án nào cần xử lý ngay">
    <template #actions>
      <span v-if="overview?.generated_at" class="dashboard-company__updated">Cập nhật lúc {{ formatUpdatedAt(overview.generated_at) }}</span>
      <button type="button" class="dashboard-company__refresh" aria-label="Làm mới dữ liệu" :disabled="overviewLoading" @click="refreshAll">
        <AppIcon name="refresh" :size="16" />
        <span>Làm mới</span>
      </button>
    </template>
  </PageHeader>

  <div class="dashboard-company">
    <div class="dashboard-company__kpis">
      <KpiCard label="Tổng dự án" :value="overview?.kpis?.total_projects ?? null" icon="layers" tone="primary" />
      <KpiCard label="Tổng công việc" :value="overview?.kpis?.total_tasks ?? null" icon="listChecks" tone="tertiary" />
      <KpiCard label="Tiến độ trung bình" :value="overview?.kpis?.average_progress_percent ?? null" suffix="%" icon="percent" :decimals="1" tone="secondary" />
      <KpiCard
        label="Dự án đang trễ hạn"
        :value="overview?.kpis?.overdue_projects_count ?? null"
        icon="clock"
        tone="danger"
        clickable
        :active="overdueOnlyFilter"
        @click="toggleOverdueQuickFilter"
      />
    </div>

    <div class="dashboard-company__grid">
      <section class="dashboard-company__panel">
        <div class="dashboard-company__panel-head">
          <h2>Dự án theo thời gian</h2>
          <div class="dashboard-company__tabs">
            <button type="button" :class="{ active: timelineTab === 'projects' }" @click="timelineTab = 'projects'">Dự án</button>
            <button type="button" :class="{ active: timelineTab === 'tasks' }" @click="timelineTab = 'tasks'">Công việc</button>
          </div>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="260px" />
        <BarChart v-else :categories="timelineData.categories" :series="timelineData.series" :height="260" />
      </section>
    </div>

    <div class="dashboard-company__grid dashboard-company__grid--mid">
      <section class="dashboard-company__panel">
        <div class="dashboard-company__panel-head">
          <h2>So sánh phòng ban</h2>
          <div class="dashboard-company__tabs">
            <button type="button" :class="{ active: departmentSort === 'overdue' }" @click="departmentSort = 'overdue'">Trễ hạn</button>
            <button type="button" :class="{ active: departmentSort === 'progress' }" @click="departmentSort = 'progress'">Tiến độ</button>
            <button type="button" :class="{ active: departmentSort === 'running' }" @click="departmentSort = 'running'">Đang chạy</button>
          </div>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="260px" />
        <BarChart
          v-else
          horizontal
          :categories="departmentBarData.categories"
          :series="departmentBarData.series"
          :height="Math.max(220, departmentBarData.categories.length * 34)"
        />
      </section>

      <section class="dashboard-company__panel">
        <div class="dashboard-company__panel-head">
          <h2>Dự án theo trạng thái</h2>
        </div>
        <DashboardSkeleton v-if="overviewLoading" height="240px" />
        <DonutChart v-else :labels="statusDonutData.labels" :series="statusDonutData.series" :selected="statusFilter" @select="toggleStatus" />
      </section>
    </div>

    <section class="dashboard-company__panel">
      <div class="dashboard-company__panel-head">
        <h2>Dự án trễ hạn theo mức độ</h2>
      </div>
      <DashboardSkeleton v-if="overviewLoading" height="200px" />
      <BarChart
        v-else
        :categories="agingBarData.categories"
        :series="agingBarData.series"
        :clickable-categories="agingBarData.values"
        :height="200"
        @select="toggleAging"
      />
    </section>

    <section class="dashboard-company__panel">
      <div class="dashboard-company__panel-head">
        <h2>Sức khoẻ dự án</h2>
        <button type="button" class="dashboard-company__scroll-link" @click="scrollToProjectList">
          Xem danh sách dự án
        </button>
      </div>
      <DashboardSkeleton v-if="overviewLoading" height="220px" />
      <ProjectHealth
        v-else
        :good="overview?.health?.good ?? 0"
        :warning="overview?.health?.warning ?? 0"
        :risk="overview?.health?.risk ?? 0"
        :selected="healthFilter"
        @select="toggleHealth"
      />
    </section>

    <section id="dashboard-project-list" class="dashboard-company__table-section">
      <div class="dashboard-company__table-area">
        <h2 class="dashboard-company__table-title">Danh sách dự án</h2>
        <DashboardSkeleton v-if="tableLoading && tableRows.length === 0" :rows="6" />
        <ProjectDataTable
          v-else
          :rows="tableRows"
          :loading="tableLoading"
          :total="table.total"
          :sort-by="tableSortBy"
          :sort-dir="tableSortDir"
          :filters-active="filtersActive"
          @sort="onSort"
          @search="onSearch"
          @clear-filters="clearTableFilters"
          @row-click="openProject"
        />
      </div>

      <ProjectDetailDrawer v-if="selectedProjectId" :project="selectedProject" :loading="selectedLoading" @close="closeDrawer" />
    </section>
  </div>
</template>

<style scoped>
.dashboard-company {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-4);
  max-width: 100%;
}

.dashboard-company__updated {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  white-space: nowrap;
  margin-right: var(--space-2);
}

.dashboard-company__refresh {
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

.dashboard-company__refresh:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.dashboard-company__refresh:disabled {
  opacity: 0.6;
  cursor: default;
}

.dashboard-company__kpis {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--space-3);
}

.dashboard-company__grid {
  display: grid;
  gap: var(--space-3);
  align-items: start;
}

.dashboard-company__grid--top {
  grid-template-columns: 2fr 1fr;
}

.dashboard-company__grid--mid {
  grid-template-columns: 7fr 5fr;
}

.dashboard-company__panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
  min-width: 0;
}

.dashboard-company__panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.dashboard-company__panel-head h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.dashboard-company__scroll-link {
  border: none;
  background: transparent;
  color: var(--color-primary);
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 700;
  cursor: pointer;
}

.dashboard-company__tabs {
  display: flex;
  gap: var(--space-1);
}

.dashboard-company__tabs button {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  cursor: pointer;
}

.dashboard-company__tabs button.active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  border-color: var(--color-primary-200, var(--color-primary));
}

.dashboard-company__table-section {
  display: flex;
  gap: var(--space-4);
  align-items: flex-start;
}

.dashboard-company__table-area {
  flex: 1;
  min-width: 0;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-sm);
}

.dashboard-company__table-title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

@media (max-width: 1024px) {
  .dashboard-company__grid--top,
  .dashboard-company__grid--mid {
    grid-template-columns: 1fr;
  }

  .dashboard-company__table-section {
    flex-direction: column;
  }
}

@media (max-width: 768px) {
  .dashboard-company__kpis {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 480px) {
  .dashboard-company {
    padding: var(--space-3);
  }

  .dashboard-company__kpis {
    grid-template-columns: 1fr;
  }
}
</style>
