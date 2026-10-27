<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  to: { type: Object, default: null },
  label: { type: String, required: true },
  value: { type: String, required: true },
  sub: { type: String, default: '' },
  tone: { type: String, default: 'brand' },
  icon: { type: String, required: true },
  active: { type: Boolean, default: false },
  brandValue: { type: Boolean, default: false },
});
</script>

<template>
  <router-link
    v-if="to"
    :to="to"
    class="kpi-card group kpi-card--static relative block text-left text-inherit no-underline outline-none"
    :class="[`kpi-card--${tone}`, { 'kpi-card--active': active }]"
  >
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
      <div class="kpi-card__shell-outer absolute inset-0 bg-white shadow-sm transition-shadow duration-300 group-hover:shadow-md" />
      <div class="kpi-card__shell-inner absolute inset-[3px] opacity-90" />
      <div class="kpi-card__shell-accent absolute left-0 top-0 h-full w-[38%] max-w-[7rem] opacity-100" />
      <div class="kpi-card__shine absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
    </div>
    <div class="relative z-[1] flex h-full min-h-[3.75rem] flex-col p-2 sm:p-2.5">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <p class="dt-kpi-card__label">{{ label }}</p>
          <p class="dt-kpi-card__value" :class="{ 'dt-kpi-card__value--brand': brandValue }">{{ value }}</p>
        </div>
        <span class="dt-kpi-card__icon" :class="`dt-kpi-card__icon--${tone}`" aria-hidden="true">
          <AppIcon :name="icon" :size="14" :stroke-width="1.75" />
        </span>
      </div>
      <div v-if="sub" class="mt-auto pt-1">
        <p class="dt-kpi-card__sub">{{ sub }}</p>
      </div>
    </div>
  </router-link>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
