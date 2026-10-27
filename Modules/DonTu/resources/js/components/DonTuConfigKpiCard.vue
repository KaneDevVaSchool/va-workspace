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
  ready: { type: Boolean, default: false },
});
</script>

<template>
  <router-link
    v-if="to"
    :to="to"
    class="kpi-card group kpi-card--static"
    :class="[`kpi-card--${tone}`, { 'kpi-card--active': active, 'kpi-card--ready': ready }]"
  >
    <div class="kpi-card__layers" aria-hidden="true">
      <div class="kpi-card__shell-outer" />
      <div class="kpi-card__shell-inner" />
      <div class="kpi-card__shell-accent" />
      <div class="kpi-card__shine" />
    </div>
    <div class="kpi-card__body">
      <div class="kpi-card__row">
        <div class="kpi-card__copy">
          <p class="dt-kpi-card__label">{{ label }}</p>
          <p class="dt-kpi-card__value">{{ value }}</p>
        </div>
        <span class="dt-kpi-card__icon" :class="`dt-kpi-card__icon--${tone}`" aria-hidden="true">
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
