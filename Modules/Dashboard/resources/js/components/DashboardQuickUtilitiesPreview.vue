<script setup>
import DashboardEmployeeHomePreview from './DashboardEmployeeHomePreview.vue';
import DashboardEmployeeUtilitiesAllPreview from './DashboardEmployeeUtilitiesAllPreview.vue';

defineProps({
  mode: { type: String, default: 'mobile-home' },
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});
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

.dqu-screen-preview--mobile-home,
.dqu-screen-preview--mobile-all {
  display: flex;
  justify-content: center;
  padding: var(--space-2) 0;
  background: var(--color-tertiary-surface);
}
</style>
