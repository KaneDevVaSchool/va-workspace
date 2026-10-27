<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import {
  donTuModuleDisplayValue,
  donTuModuleSubline,
  isDonTuModuleReady,
  useDonTuConfigSummaryContext,
} from '../composables/useDonTuConfigSummary.js';

const props = defineProps({
  /** Tự gọi API khi mount (mặc định bật nếu chưa có provide từ layout). */
  autoLoad: { type: Boolean, default: true },
});

const route = useRoute();
const { loading, summary, modules, readyCount, totalCount, loadSummary } = useDonTuConfigSummaryContext();

const hint = computed(() =>
  totalCount.value > 0 ? `${readyCount.value}/${totalCount.value} đã cấu hình` : undefined,
);

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
  <section class="dt-kpi-strip" aria-label="Thống kê danh mục cấu hình đơn từ">
    <header class="dt-kpi-strip__head">
      <h2 class="dt-kpi-strip__title">Danh mục thiết lập</h2>
      <p v-if="hint" class="dt-kpi-strip__hint">{{ hint }}</p>
    </header>
    <div class="dt-kpi-strip__grid">
      <router-link
        v-for="mod in modules"
        :key="mod.key"
        :to="{ name: mod.routeName }"
        class="dt-kpi-card"
        :class="[
          `dt-kpi-card--${mod.tone}`,
          { 'dt-kpi-card--active': isActive(mod.routeName) },
          { 'dt-kpi-card--ready': isDonTuModuleReady(mod.key, summary[mod.key]) },
        ]"
        :aria-current="isActive(mod.routeName) ? 'page' : undefined"
      >
        <span class="dt-kpi-card__icon" aria-hidden="true">
          <AppIcon :name="mod.icon" :size="18" :stroke-width="1.75" />
        </span>
        <span class="dt-kpi-card__body">
          <span class="dt-kpi-card__label">{{ mod.label }}</span>
          <span
            class="dt-kpi-card__value"
            :class="{ 'dt-kpi-card__value--empty': !isDonTuModuleReady(mod.key, summary[mod.key]) }"
          >
            {{ loading ? '…' : donTuModuleDisplayValue(mod.key, summary[mod.key]) }}
          </span>
          <span v-if="!loading && donTuModuleSubline(mod.key, summary[mod.key])" class="dt-kpi-card__sub">
            {{ donTuModuleSubline(mod.key, summary[mod.key]) }}
          </span>
        </span>
      </router-link>
    </div>
  </section>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
