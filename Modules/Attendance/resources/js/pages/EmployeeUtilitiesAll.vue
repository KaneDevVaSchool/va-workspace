<script setup>
import { computed, inject, onBeforeUnmount, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import QuickUtilitiesGrid from '@modules/Dashboard/resources/js/components/QuickUtilitiesGrid.vue';
import { useDashboardQuickUtilities } from '@modules/Dashboard/resources/js/composables/useDashboardQuickUtilities';

const route = useRoute();
const { utilities, loading, load, openUtility } = useDashboardQuickUtilities();

const searchQuery = computed(() => String(route.query.q || '').trim().toLowerCase());

const filteredUtilities = computed(() => {
  if (!searchQuery.value) return utilities.value;
  return utilities.value.filter((item) =>
    String(item.label || '').toLowerCase().includes(searchQuery.value),
  );
});

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

onMounted(() => {
  load();
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(() => load());
  }
});

onBeforeUnmount(() => {
  unregisterPageRefresh?.();
});
</script>

<template>
  <div class="employee-utilities-all">
    <p v-if="searchQuery" class="employee-utilities-all__filter">
      Đang lọc: “{{ route.query.q }}”
    </p>
    <p v-if="!loading && filteredUtilities.length === 0" class="employee-utilities-all__empty">
      Không có tiện ích khớp tìm kiếm.
    </p>
    <QuickUtilitiesGrid
      v-else
      :items="filteredUtilities"
      :loading="loading"
      @select="openUtility"
    />
  </div>
</template>

<style scoped>
.employee-utilities-all {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-6);
}

.employee-utilities-all__filter,
.employee-utilities-all__empty {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.employee-utilities-all__filter {
  font-weight: 600;
  color: var(--color-text);
}
</style>
