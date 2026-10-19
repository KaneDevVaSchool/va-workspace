<script setup>
import AppIcon from '@/components/AppIcon.vue';
import DashboardMeContent from './DashboardMeContent.vue';
import DashboardEmployeeHomePreview from './DashboardEmployeeHomePreview.vue';
import DashboardEmployeeUtilitiesAllPreview from './DashboardEmployeeUtilitiesAllPreview.vue';
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
      <div class="dqu-screen-preview__desktop-canvas">
        <div class="dqu-screen-preview__chrome" aria-hidden="true">
          <div class="dqu-screen-preview__title-wrap">
            <h2 class="dqu-screen-preview__title">Dashboard của tôi</h2>
            <p class="dqu-screen-preview__subtitle">Tổng quan tiến độ và lối tắt công việc hằng ngày</p>
          </div>
          <span class="dqu-screen-preview__refresh">
            <AppIcon name="refresh" :size="16" />
            <span>Làm mới</span>
          </span>
        </div>

        <DashboardMeContent
          :overview="dashboardMePreviewOverview"
          :overview-loading="false"
          :utilities="utilities"
          :utilities-loading="loading"
          preview
          force-desktop-layout
          :active-utility-key="activeUtilityKey"
        />
      </div>
    </template>

    <DashboardEmployeeHomePreview
      v-else-if="mode === 'mobile-home'"
      :utilities="utilities"
      :loading="loading"
      :active-utility-key="activeUtilityKey"
    />

    <DashboardEmployeeUtilitiesAllPreview
      v-else
      :utilities="utilities"
      :loading="loading"
      :active-utility-key="activeUtilityKey"
    />
  </div>
</template>

<style scoped>
.dqu-screen-preview {
  width: 100%;
  min-width: 0;
}

.dqu-screen-preview--desktop {
  display: block;
  min-width: 0;
  background: var(--color-bg);
}

.dqu-screen-preview__desktop-canvas {
  width: max-content;
  min-width: 100%;
}

.dqu-screen-preview__chrome {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 56rem;
  min-height: 2.75rem;
  padding: var(--space-2) var(--space-4);
  background: var(--color-bg);
  box-shadow: 0 1px 0 var(--color-border);
}

.dqu-screen-preview__title-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.0625rem;
}

.dqu-screen-preview__title {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.3;
}

.dqu-screen-preview__subtitle {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.3;
}

.dqu-screen-preview__refresh {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  flex-shrink: 0;
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

.dqu-screen-preview--mobile-home,
.dqu-screen-preview--mobile-all {
  display: flex;
  justify-content: center;
  padding: var(--space-2) 0;
  background: var(--color-tertiary-surface);
}
</style>
