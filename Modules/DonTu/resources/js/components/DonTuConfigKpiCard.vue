<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  to: { type: Object, default: null },
  label: { type: String, required: true },
  value: { type: String, required: true },
  sub: { type: String, default: '' },
  tone: { type: String, default: 'c1' },
  icon: { type: String, required: true },
  active: { type: Boolean, default: false },
  ready: { type: Boolean, default: false },
});
</script>

<template>
  <router-link
    v-if="to"
    :to="to"
    class="dt-kpi-card"
    :class="[`dt-kpi-card--${tone}`, { 'dt-kpi-card--active': active, 'dt-kpi-card--ready': ready }]"
  >
    <div class="dt-kpi-card__body">
      <div class="dt-kpi-card__row">
        <div class="dt-kpi-card__copy">
          <p class="dt-kpi-card__label">{{ label }}</p>
          <p class="dt-kpi-card__value">{{ value }}</p>
        </div>
        <span class="dt-kpi-card__icon" aria-hidden="true">
          <AppIcon :name="icon" :size="14" :stroke-width="1.75" />
        </span>
      </div>
      <p v-if="sub" class="dt-kpi-card__sub">{{ sub }}</p>
    </div>
  </router-link>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
