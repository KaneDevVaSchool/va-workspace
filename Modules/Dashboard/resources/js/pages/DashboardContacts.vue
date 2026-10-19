<script setup>
import PageHeader from '@/components/PageHeader.vue';
import { useEmployeeMobileView } from '@/composables/useEmployeeMobileView';
import MemberDirectoryPanel from '../components/MemberDirectoryPanel.vue';

const { isEmployeeMobile } = useEmployeeMobileView();
</script>

<template>
  <PageHeader v-if="!isEmployeeMobile" title="Danh bạ" />

  <div
    class="dashboard-contacts"
    :class="{ 'dashboard-contacts--mobile': isEmployeeMobile }"
  >
    <MemberDirectoryPanel />
  </div>
</template>

<style scoped>
.dashboard-contacts {
  padding: var(--space-4);
  max-width: 42rem;
}

.dashboard-contacts--mobile {
  padding: var(--space-4) var(--space-4) var(--space-6);
  max-width: none;
}

.dashboard-contacts--mobile :deep(.member-directory__sections) {
  max-height: none;
}
</style>
