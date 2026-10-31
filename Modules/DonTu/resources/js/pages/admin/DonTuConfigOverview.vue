<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuConfigStepLink from '../../components/DonTuConfigStepLink.vue';
import DonTuConfigSummaryBar from '../../components/DonTuConfigSummaryBar.vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import { isDonTuModuleReady, useDonTuConfigSummaryContext } from '../../composables/useDonTuConfigSummary.js';

const auth = useAuthStore();
const configHub = useDonTuConfigHub();

const {
  loading,
  summary,
  modules,
  readyCount,
  totalCount,
  progressPct,
  nextModuleKey,
  allReady,
  loadSummary,
} = useDonTuConfigSummaryContext();

const canConfig = computed(
  () =>
    auth.can('don_tu.manage_department') ||
    auth.can('don_tu.manage_system') ||
    auth.can('don_tu.*'),
);

const progressOffset = computed(() => {
  const c = 2 * Math.PI * 36;
  return c - (progressPct.value / 100) * c;
});

const circumference = 2 * Math.PI * 36;

const chapterLabel = 'Thiết lập đơn từ';

onMounted(async () => {
  configHub?.clearPrimaryAction();
  configHub?.registerReload(reloadAll);
  if (canConfig.value) {
    await loadSummary();
  }
});

onBeforeUnmount(() => {
  configHub?.unregisterReload();
});

async function reloadAll() {
  await loadSummary();
}

function stepState(mod) {
  const counts = summary.value[mod.key];
  return {
    complete: isDonTuModuleReady(mod.key, counts),
    current: mod.key === nextModuleKey.value,
    counts,
  };
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ của phòng để xem trang này.</p>
  </div>

  <div v-else class="dt-config-overview dt-config-hub-theme">
    <DonTuConfigSummaryBar :auto-load="false" />

    <div class="dt-config-overview__grid">
      <section class="dt-config-roadmap" aria-label="Lộ trình cấu hình đơn từ" data-tour="don-tu-config-roadmap">
        <img
          src="/images/background/background-logo.png"
          alt=""
          class="dt-config-watermark dt-config-watermark--roadmap"
          aria-hidden="true"
          :style="{ filter: 'url(#dt-config-wm-boost)' }"
        />
        <header class="dt-config-roadmap__head">
          <div class="dt-config-roadmap__head-top">
            <div>
              <p class="dt-config-roadmap__eyebrow">Lộ trình</p>
              <h2 class="dt-config-roadmap__title">Thiết lập theo thứ tự</h2>
            </div>
            <div class="dt-config-roadmap__progress-wrap">
              <div class="dt-config-progress" aria-hidden="true">
                <svg class="dt-config-progress__ring" viewBox="0 0 88 88">
                  <circle cx="44" cy="44" r="36" fill="none" class="dt-config-progress__track" stroke-width="8" />
                  <circle
                    cx="44"
                    cy="44"
                    r="36"
                    fill="none"
                    class="dt-config-progress__fill"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="progressOffset"
                  />
                </svg>
                <span class="dt-config-progress__pct">{{ loading ? '…' : `${progressPct}%` }}</span>
              </div>
              <div>
                <p class="dt-config-progress__label">Tiến độ thiết lập</p>
                <p class="dt-config-progress__sub">
                  {{ loading ? 'Đang tải…' : `${readyCount}/${totalCount} hạng mục đã cấu hình` }}
                </p>
              </div>
            </div>
          </div>
        </header>

        <div v-if="allReady && !loading" class="dt-config-roadmap__banner">
          <AppIcon name="sparkles" :size="16" :stroke-width="1.75" />
          <p>Danh mục đã sẵn sàng.</p>
        </div>

        <div class="dt-config-roadmap__body">
          <div class="dt-config-roadmap__chapter">
            <p class="dt-config-roadmap__chapter-label">{{ chapterLabel }}</p>
            <ol>
              <DonTuConfigStepLink
                v-for="(mod, index) in modules"
                :key="mod.key"
                :mod="mod"
                :step="index + 1"
                :is-first="index === 0"
                :is-last="index === modules.length - 1"
                :is-current="stepState(mod).current"
                :is-complete="stepState(mod).complete"
                :loading="loading"
                :counts="stepState(mod).counts"
              />
            </ol>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';
</style>
