<script setup>
import { onMounted } from 'vue';
import QuickUtilitiesGrid from '@modules/Dashboard/resources/js/components/QuickUtilitiesGrid.vue';
import { useDashboardQuickUtilities } from '@modules/Dashboard/resources/js/composables/useDashboardQuickUtilities';

const { utilities, loading, load, openUtility } = useDashboardQuickUtilities();

onMounted(() => {
  load();
});
</script>

<template>
  <div class="employee-utilities-all">
    <p class="employee-utilities-all__hint">Chạm vào lối tắt bên dưới để mở chức năng.</p>
    <QuickUtilitiesGrid :items="utilities" :loading="loading" @select="openUtility" />
  </div>
</template>

<style scoped>
.employee-utilities-all {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-6);
}

.employee-utilities-all__hint {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}
</style>
