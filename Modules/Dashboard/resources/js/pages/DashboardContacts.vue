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
  margin-inline: calc(-1 * var(--space-3));
  padding: 0 0 var(--space-4);
  max-width: none;
}

.dashboard-contacts--mobile :deep(.member-directory__sections) {
  max-height: none;
}

.dashboard-contacts--mobile :deep(.member-directory__row) {
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
}

.dashboard-contacts--mobile :deep(.member-directory__letter) {
  padding-inline: var(--space-2);
}
</style>
