<script setup>
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import DashboardMeContent from './DashboardMeContent.vue';
import DashboardEmployeeHomePreview from './DashboardEmployeeHomePreview.vue';
import { dashboardMePreviewOverview } from '../constants/dashboardMePreviewFixtures';

defineProps({
  mode: { type: String, default: 'desktop' },
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});
</script>

<template>
  <div class="dqu-screen-preview" :class="`dqu-screen-preview--${mode}`">
    <template v-if="mode === 'desktop'">
      <PageHeader title="Dashboard của tôi" subtitle="Tổng quan tiến độ và lối tắt công việc hằng ngày">
        <template #actions>
          <span class="dqu-screen-preview__refresh" aria-hidden="true">
            <AppIcon name="refresh" :size="16" />
            <span>Làm mới</span>
          </span>
        </template>
      </PageHeader>

      <DashboardMeContent
        :overview="dashboardMePreviewOverview"
        :overview-loading="false"
        :utilities="utilities"
        :utilities-loading="loading"
        preview
        :active-utility-key="activeUtilityKey"
      />
    </template>

    <DashboardEmployeeHomePreview
      v-else
      :utilities="utilities"
      :loading="loading"
      :active-utility-key="activeUtilityKey"
    />
  </div>
</template>

<style scoped>
.dqu-screen-preview {
  min-width: 0;
  background: var(--color-bg, var(--color-surface-muted));
}

.dqu-screen-preview--desktop {
  min-width: 42rem;
}

.dqu-screen-preview__refresh {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 2rem;
  padding: 0 var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
  opacity: 0.85;
}

.dqu-screen-preview--mobile {
  display: flex;
  justify-content: center;
  padding: var(--space-2) 0;
  background: var(--color-tertiary-surface);
}
</style>
