<script setup>
import AppIcon from '@/components/AppIcon.vue';
import {
  donTuModuleDisplayValue,
  donTuModuleSubline,
  isDonTuModuleReady,
} from '../composables/useDonTuConfigSummary.js';

const props = defineProps({
  mod: { type: Object, required: true },
  step: { type: Number, required: true },
  isFirst: { type: Boolean, required: true },
  isLast: { type: Boolean, required: true },
  isCurrent: { type: Boolean, required: true },
  isComplete: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  counts: { type: Object, required: true },
});
</script>

<template>
  <li class="relative">
    <router-link
      :to="{ name: mod.routeName }"
      class="dt-roadmap-step group"
      :class="{
        'dt-roadmap-step--current': isCurrent,
        'dt-roadmap-step--done': isComplete && !isCurrent,
      }"
      :aria-current="isCurrent ? 'step' : undefined"
    >
      <span class="dt-roadmap-step__rail" aria-hidden="true">
        <span class="dt-roadmap-step__line dt-roadmap-step__line--top" :class="{ 'dt-roadmap-step__line--off': isFirst }" />
        <span
          class="dt-roadmap-step__badge"
          :class="{
            'dt-roadmap-step__badge--current': isCurrent,
            'dt-roadmap-step__badge--done': isComplete && !isCurrent,
            'dt-roadmap-step__badge--partial': !isComplete && isDonTuModuleReady(mod.key, counts),
          }"
        >
          <AppIcon v-if="isComplete && !isCurrent" name="check" :size="16" :stroke-width="2.25" />
          <template v-else>{{ step }}</template>
        </span>
        <span class="dt-roadmap-step__line dt-roadmap-step__line--bottom" :class="{ 'dt-roadmap-step__line--off': isLast }" />
      </span>

      <span class="dt-roadmap-step__main">
        <span
          class="dt-roadmap-step__icon"
          :class="{
            'dt-roadmap-step__icon--current': isCurrent,
            'dt-roadmap-step__icon--done': isComplete && !isCurrent,
          }"
          aria-hidden="true"
        >
          <AppIcon :name="mod.icon" :size="18" :stroke-width="1.75" />
        </span>

        <span class="min-w-0 flex-1">
          <span class="dt-roadmap-step__title-row">
            <span class="dt-roadmap-step__label">{{ mod.label }}</span>
            <span v-if="isCurrent" class="dt-roadmap-step__pill dt-roadmap-step__pill--current">
              {{ step === 1 ? 'Bắt đầu' : 'Tiếp theo' }}
            </span>
            <span v-else-if="isComplete" class="dt-roadmap-step__pill dt-roadmap-step__pill--done">
              Đã có dữ liệu
            </span>
          </span>
        </span>

        <span class="dt-roadmap-step__stat">
          <span
            class="dt-roadmap-step__stat-num"
            :class="{ 'dt-roadmap-step__stat-num--empty': !isDonTuModuleReady(mod.key, counts) }"
          >
            {{ loading ? '…' : donTuModuleDisplayValue(mod.key, counts) }}
          </span>
          <span v-if="!loading && donTuModuleSubline(mod.key, counts)" class="dt-roadmap-step__stat-sub">
            {{ donTuModuleSubline(mod.key, counts) }}
          </span>
        </span>

        <AppIcon name="chevronRight" class="dt-roadmap-step__arrow" :size="16" :stroke-width="2" />
      </span>
    </router-link>
  </li>
</template>

<style scoped>
@import '../pages/admin/donTuAdminShared.css';
</style>
