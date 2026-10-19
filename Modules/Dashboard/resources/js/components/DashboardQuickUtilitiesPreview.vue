<script setup>
import { computed } from 'vue';
import { EMPLOYEE_PWA_TAB_PREVIEW } from '../constants/employeePwaPreview.js';
import DashboardEmployeeHomePreview from './DashboardEmployeeHomePreview.vue';
import DashboardEmployeeTabPagePreview from './DashboardEmployeeTabPagePreview.vue';
import DashboardEmployeeUtilitiesAllPreview from './DashboardEmployeeUtilitiesAllPreview.vue';

const props = defineProps({
  mode: { type: String, default: 'mobile-home' },
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});

const tabPreview = computed(() => EMPLOYEE_PWA_TAB_PREVIEW[props.mode] ?? null);
</script>

<template>
  <div class="dqu-screen-preview" :class="`dqu-screen-preview--${mode}`">
    <DashboardEmployeeHomePreview
      v-if="mode === 'mobile-home'"
      :utilities="utilities"
      :loading="loading"
      :active-utility-key="activeUtilityKey"
    />

    <DashboardEmployeeUtilitiesAllPreview
      v-else-if="mode === 'mobile-all'"
      :utilities="utilities"
      :loading="loading"
      :active-utility-key="activeUtilityKey"
    />

    <DashboardEmployeeTabPagePreview
      v-else-if="tabPreview"
      :active-route="tabPreview.activeRoute"
      :kicker="tabPreview.kicker"
      :title="tabPreview.title"
      :hint="tabPreview.hint"
    />
  </div>
</template>

<style scoped>
.dqu-screen-preview {
  width: 100%;
  min-width: 0;
}

.dqu-screen-preview[class*='dqu-screen-preview--mobile-'] {
  display: flex;
  justify-content: center;
  padding: var(--space-2) 0;
  background: var(--color-tertiary-surface);
}
</style>
