<script setup>
import { inject, onBeforeUnmount, onMounted, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { useEmployeeMobileView } from '@/composables/useEmployeeMobileView';
import MemberDirectoryPanel from '../components/MemberDirectoryPanel.vue';

const { isEmployeeMobile } = useEmployeeMobileView();
const directoryRef = ref(null);
const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

onMounted(() => {
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(async () => {
      await directoryRef.value?.reload?.();
    });
  }
});

onBeforeUnmount(() => {
  unregisterPageRefresh?.();
});
</script>

<template>
  <PageHeader v-if="!isEmployeeMobile" title="Danh bạ" />

  <div
    class="dashboard-contacts"
    :class="{ 'dashboard-contacts--mobile': isEmployeeMobile }"
  >
    <MemberDirectoryPanel ref="directoryRef" />
  </div>
</template>

<style scoped>
.dashboard-contacts {
  padding: var(--space-4);
  max-width: 40rem;
  margin-inline: auto;
}

.dashboard-contacts--mobile {
  margin-inline: 0;
  padding: var(--space-3) var(--space-4) var(--space-6);
  max-width: none;
}

.dashboard-contacts--mobile :deep(.member-directory__sections) {
  max-height: none;
}

.dashboard-contacts--mobile :deep(.member-directory__card) {
  padding: var(--space-2) var(--space-3);
  padding-left: calc(var(--space-3) + var(--space-2) + 3px);
}

.dashboard-contacts--mobile :deep(.member-directory__mail-btn) {
  width: 2.25rem;
  height: 2.25rem;
}
</style>
