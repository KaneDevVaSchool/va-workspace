<script setup>
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useEmployeeMobileView } from '@/composables/useEmployeeMobileView';
import { onMounted, ref } from 'vue';
import DashboardMeContent from '../components/DashboardMeContent.vue';
import { useDashboardQuickUtilities } from '../composables/useDashboardQuickUtilities';
import { unwrapOverview } from '../utils/dashboardPayload';
import { defineAsyncComponent } from 'vue';

const EmployeeHome = defineAsyncComponent(() =>
  import('@modules/Attendance/resources/js/pages/EmployeeHome.vue'),
);

const { isEmployeeMobile } = useEmployeeMobileView();

const overview = ref(null);
const overviewLoading = ref(true);

const { utilities, loading: utilitiesLoading, load: loadUtilities, openUtility } = useDashboardQuickUtilities();

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

async function refreshAll() {
  await Promise.all([loadOverview(), loadUtilities()]);
}

onMounted(() => {
  if (!isEmployeeMobile.value) {
    refreshAll();
  }
});
</script>

<template>
  <EmployeeHome v-if="isEmployeeMobile" />

  <template v-else>
    <PageHeader title="Dashboard của tôi">
      <template #actions>
        <button
          type="button"
          class="dashboard-me__refresh"
          aria-label="Làm mới dữ liệu"
          :disabled="overviewLoading || utilitiesLoading"
          @click="refreshAll"
        >
          <AppIcon name="refresh" :size="16" />
          <span>Làm mới</span>
        </button>
      </template>
    </PageHeader>

    <DashboardMeContent
      :overview="overview"
      :overview-loading="overviewLoading"
      :utilities="utilities"
      :utilities-loading="utilitiesLoading"
      @utility-select="openUtility"
    />
  </template>
</template>

<style scoped>
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
</style>
