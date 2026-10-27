<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DonTuConfigKpiCard from './DonTuConfigKpiCard.vue';
import {
  donTuModuleDisplayValue,
  donTuModuleSubline,
  isDonTuModuleReady,
  useDonTuConfigSummaryContext,
} from '../composables/useDonTuConfigSummary.js';

const props = defineProps({
  autoLoad: { type: Boolean, default: true },
});

const route = useRoute();
const { loading, summary, modules, readyCount, totalCount, loadSummary } = useDonTuConfigSummaryContext();

const hint = computed(() =>
  totalCount.value > 0 ? `${readyCount.value}/${totalCount.value} đã cấu hình` : undefined,
);

const chapterLabel = 'Thiết lập đơn từ';

function isActive(routeName) {
  if (route.name === routeName) return true;
  if (route.name === 'manager.don-tu.form' && routeName === 'manager.don-tu.forms') return true;
  if (route.name === 'manager.don-tu.workflow' && routeName === 'manager.don-tu.workflows') return true;
  return false;
}

onMounted(() => {
  if (props.autoLoad) {
    loadSummary();
  }
});

defineExpose({ loadSummary, loading, summary });
</script>

<template>
  <section class="kpi-strip dt-kpi-strip" aria-label="Thống kê danh mục cấu hình đơn từ" data-tour="kpi-strip">
    <div class="dt-kpi-strip__bg" aria-hidden="true">
      <div class="dt-kpi-strip__bg-outer" />
      <div class="dt-kpi-strip__bg-inner" />
    </div>

    <header class="dt-kpi-strip__head">
      <div>
        <p class="dt-kpi-strip__eyebrow">Thống kê</p>
        <h2 class="dt-kpi-strip__title">Danh mục thiết lập</h2>
      </div>
      <p v-if="hint" class="dt-kpi-strip__hint">{{ hint }}</p>
    </header>

    <div class="dt-kpi-strip__groups">
      <div>
        <p class="dt-kpi-strip__chapter">{{ chapterLabel }}</p>
        <div class="dt-kpi-strip__grid">
          <DonTuConfigKpiCard
            v-for="mod in modules"
            :key="mod.key"
            :to="{ name: mod.routeName }"
            :label="mod.label"
            :value="loading ? '…' : donTuModuleDisplayValue(mod.key, summary[mod.key])"
            :sub="!loading ? donTuModuleSubline(mod.key, summary[mod.key]) || '' : ''"
            :tone="mod.tone"
            :icon="mod.icon"
            :active="isActive(mod.routeName)"
            :brand-value="mod.tone === 'brand' && isDonTuModuleReady(mod.key, summary[mod.key])"
            :aria-label="`${mod.label}: ${loading ? '…' : donTuModuleDisplayValue(mod.key, summary[mod.key])}`"
          />
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
