<script setup>
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';
import DashboardEmployeeShellPreview from './DashboardEmployeeShellPreview.vue';

defineProps({
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});
</script>

<template>
  <DashboardEmployeeShellPreview active-route="employee.utilities" page-title="Tiện ích nhanh">
    <div class="employee-utilities-all">
      <QuickUtilitiesGrid
        :items="utilities"
        :loading="loading"
        preview
        :active-key="activeUtilityKey"
      />
    </div>
  </DashboardEmployeeShellPreview>
</template>

<style scoped>
.employee-utilities-all {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-6);
}
</style>
