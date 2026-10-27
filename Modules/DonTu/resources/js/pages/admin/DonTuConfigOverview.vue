<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import DonTuConfigSummaryBar from '../../components/DonTuConfigSummaryBar.vue';
import { useDonTuConfigHub } from '../../composables/useDonTuConfigHub.js';
import {
  donTuModuleDisplayValue,
  donTuModuleSubline,
  isDonTuModuleReady,
  useDonTuConfigSummaryContext,
} from '../../composables/useDonTuConfigSummary.js';

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

const canSubmit = computed(() => auth.can('don_tu.submit') || auth.can('don_tu.*'));

const progressOffset = computed(() => {
  const r = 36;
  const c = 2 * Math.PI * r;
  return c - (progressPct.value / 100) * c;
});

const circumference = 2 * Math.PI * 36;

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
  const complete = isDonTuModuleReady(mod.key, counts);
  const current = mod.key === nextModuleKey.value;
  return { complete, current, counts };
}
</script>

<template>
  <div v-if="!canConfig" class="dt-config-deny">
    <AppIcon name="lock" :size="28" :stroke-width="1.75" />
    <p>Bạn cần quyền cấu hình đơn từ của phòng để xem trang này.</p>
  </div>

  <div v-else class="dt-config-overview">
    <DonTuConfigSummaryBar :auto-load="false" />

    <div class="dt-config-overview__grid">
      <section class="dt-config-roadmap" aria-label="Lộ trình cấu hình đơn từ">
        <header class="dt-config-roadmap__head">
          <div>
            <p class="dt-config-roadmap__eyebrow">Lộ trình</p>
            <h2 class="dt-config-roadmap__title">Thiết lập theo thứ tự</h2>
          </div>
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
          <div class="dt-config-progress__copy">
            <p class="dt-config-progress__label">Tiến độ thiết lập</p>
            <p class="dt-config-progress__sub">
              {{ loading ? 'Đang tải…' : `${readyCount}/${totalCount} hạng mục đã cấu hình` }}
            </p>
          </div>
        </header>

        <div v-if="allReady && !loading" class="dt-config-roadmap__banner">
          <AppIcon name="sparkles" :size="16" :stroke-width="1.75" />
          <p>Danh mục đã sẵn sàng — có thể bật quy trình và cho nhân sự nộp đơn.</p>
        </div>

        <ol class="dt-config-steps">
          <li
            v-for="(mod, index) in modules"
            :key="mod.key"
            class="dt-config-step"
          >
            <router-link
              :to="{ name: mod.routeName }"
              class="dt-config-step__link"
              :class="{
                'dt-config-step__link--current': stepState(mod).current,
                'dt-config-step__link--done': stepState(mod).complete && !stepState(mod).current,
              }"
              :aria-current="stepState(mod).current ? 'step' : undefined"
            >
              <span class="dt-config-step__rail" aria-hidden="true">
                <span class="dt-config-step__line dt-config-step__line--top" :class="{ 'dt-config-step__line--hide': index === 0 }" />
                <span
                  class="dt-config-step__badge"
                  :class="{
                    'dt-config-step__badge--current': stepState(mod).current,
                    'dt-config-step__badge--done': stepState(mod).complete && !stepState(mod).current,
                  }"
                >
                  <AppIcon
                    v-if="stepState(mod).complete && !stepState(mod).current"
                    name="check"
                    :size="16"
                    :stroke-width="2.25"
                  />
                  <template v-else>{{ index + 1 }}</template>
                </span>
                <span
                  class="dt-config-step__line dt-config-step__line--bottom"
                  :class="{ 'dt-config-step__line--hide': index === modules.length - 1 }"
                />
              </span>

              <span class="dt-config-step__icon" :class="`dt-config-step__icon--${mod.tone}`" aria-hidden="true">
                <AppIcon :name="mod.icon" :size="18" :stroke-width="1.75" />
              </span>

              <span class="dt-config-step__body">
                <span class="dt-config-step__row">
                  <span class="dt-config-step__label">{{ mod.label }}</span>
                  <span v-if="stepState(mod).current" class="dt-config-step__pill dt-config-step__pill--current">
                    {{ index === 0 ? 'Bắt đầu' : 'Tiếp theo' }}
                  </span>
                  <span
                    v-else-if="stepState(mod).complete"
                    class="dt-config-step__pill dt-config-step__pill--done"
                  >
                    Đã có dữ liệu
                  </span>
                </span>
              </span>

              <span class="dt-config-step__stat">
                <span
                  class="dt-config-step__stat-num"
                  :class="{ 'dt-config-step__stat-num--empty': !isDonTuModuleReady(mod.key, stepState(mod).counts) }"
                >
                  {{ loading ? '…' : donTuModuleDisplayValue(mod.key, stepState(mod).counts) }}
                </span>
                <span
                  v-if="!loading && donTuModuleSubline(mod.key, stepState(mod).counts)"
                  class="dt-config-step__stat-sub"
                >
                  {{ donTuModuleSubline(mod.key, stepState(mod).counts) }}
                </span>
              </span>

              <AppIcon name="chevronRight" class="dt-config-step__arrow" :size="16" :stroke-width="2" />
            </router-link>
          </li>
        </ol>
      </section>

      <aside v-if="canSubmit" class="dt-config-aside" aria-label="Vận hành đơn từ">
        <router-link :to="{ name: 'manager.don-tu.start' }" class="dt-config-aside__card dt-config-aside__card--primary">
          <span class="dt-config-aside__icon" aria-hidden="true">
            <AppIcon name="fileText" :size="20" :stroke-width="1.75" />
          </span>
          <h3 class="dt-config-aside__title">Nộp đơn</h3>
          <p class="dt-config-aside__lead">Mở cổng nộp đơn sau khi đã bật ít nhất một quy trình.</p>
          <span class="dt-config-aside__cta">
            Mở cổng
            <AppIcon name="chevronRight" :size="14" :stroke-width="2" />
          </span>
        </router-link>

        <router-link :to="{ name: 'manager.don-tu.mine' }" class="dt-config-aside__card">
          <span class="dt-config-aside__icon dt-config-aside__icon--muted" aria-hidden="true">
            <AppIcon name="folder" :size="20" :stroke-width="1.75" />
          </span>
          <h3 class="dt-config-aside__title">Hồ sơ của tôi</h3>
          <span class="dt-config-aside__cta">
            Xem hồ sơ
            <AppIcon name="chevronRight" :size="14" :stroke-width="2" />
          </span>
        </router-link>
      </aside>
    </div>
  </div>
</template>

<style scoped>
@import './donTuAdminShared.css';
</style>
