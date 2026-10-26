<script setup>
//
// Bottom tab bar (5 mục cố định) của shell mobile nhân viên. CSS dùng chung:
// resources/css/employee-mobile-shell/base.css (khung). Không cộng safe-area iOS.
//
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  tabs: { type: Array, required: true },
  activeName: { type: String, default: '' },
  /** Đổi khi chuyển trang trong shell — kích hoạt lại animation nổi lên của bar. */
  shellPageKey: { type: String, default: '' },
  isMobilePwa: { type: Boolean, default: false },
  /** Xem thử tĩnh (desktop cấu hình) — không router-link. */
  preview: { type: Boolean, default: false },
});

const emit = defineEmits(['reselect']);

const navEl = ref(null);
const barEl = ref(null);
const indicatorStyle = ref({});
const indicatorVisible = ref(false);
const barEnterKind = ref('');

defineExpose({ navEl });

let barResizeObserver;

function prefersReducedMotion() {
  return typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function syncIndicator() {
  const bar = barEl.value;
  if (!bar) return;
  const active = bar.querySelector('.employee-tabbar__item--active:not(.employee-tabbar__item--primary)');
  if (!active) {
    indicatorVisible.value = false;
    return;
  }
  const barRect = bar.getBoundingClientRect();
  const itemRect = active.getBoundingClientRect();
  const x = itemRect.left - barRect.left;
  indicatorStyle.value = {
    width: `${Math.round(itemRect.width)}px`,
    transform: `translate3d(${Math.round(x)}px, -50%, 0)`,
  };
  indicatorVisible.value = true;
}

function scheduleIndicatorSync() {
  nextTick(() => {
    syncIndicator();
    requestAnimationFrame(syncIndicator);
  });
}

function replayBarEnter(kind) {
  if (prefersReducedMotion()) {
    barEnterKind.value = '';
    return;
  }
  barEnterKind.value = '';
  requestAnimationFrame(() => {
    barEnterKind.value = kind;
    scheduleIndicatorSync();
  });
}

watch(
  () => props.activeName,
  () => scheduleIndicatorSync(),
);

watch(
  () => props.shellPageKey,
  (next, prev) => {
    if (!next || next === prev) return;
    if (prev) {
      replayBarEnter('route');
    }
  },
);

onMounted(() => {
  replayBarEnter('initial');
  scheduleIndicatorSync();
  const bar = barEl.value;
  if (bar && typeof ResizeObserver !== 'undefined') {
    barResizeObserver = new ResizeObserver(() => syncIndicator());
    barResizeObserver.observe(bar);
  }
});

onBeforeUnmount(() => {
  barResizeObserver?.disconnect();
});

function onTabClick(event, tab) {
  if (props.preview) return;
  if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
    navigator.vibrate(10);
  }
  if (props.activeName !== tab.name) return;
  event.preventDefault();
  emit('reselect', tab.name);
}

function tabItemClass(tab) {
  return {
    'employee-tabbar__item--active': props.activeName === tab.name,
    'employee-tabbar__item--primary': tab.primary,
  };
}
</script>

<template>
  <nav
    ref="navEl"
    class="employee-tabbar employee-tabbar--shell"
    :class="isMobilePwa ? 'employee-tabbar--pwa' : 'employee-tabbar--browser'"
    :aria-label="preview ? undefined : 'Điều hướng chính'"
    :aria-hidden="preview ? 'true' : undefined"
  >
    <div
      ref="barEl"
      class="employee-tabbar__bar"
      :class="{
        'employee-tabbar__bar--enter-initial': barEnterKind === 'initial',
        'employee-tabbar__bar--enter-route': barEnterKind === 'route',
      }"
    >
      <span
        class="employee-tabbar__indicator"
        :class="{ 'employee-tabbar__indicator--visible': indicatorVisible }"
        :style="indicatorStyle"
        aria-hidden="true"
      />
      <template v-for="tab in tabs" :key="tab.name">
        <router-link
          v-if="!preview"
          :to="{ name: tab.name }"
          class="employee-tabbar__item"
          :class="tabItemClass(tab)"
          :aria-current="activeName === tab.name ? 'page' : undefined"
          @click="onTabClick($event, tab)"
        >
          <span class="employee-tabbar__icon">
            <AppIcon
              :name="tab.icon"
              :size="tab.primary ? 22 : 20"
              :stroke-width="1.9"
            />
          </span>
          <span v-if="tab.badge" class="employee-tabbar__badge">{{ tab.badge }}</span>
          <span class="employee-tabbar__label">{{ tab.label }}</span>
        </router-link>
        <span
          v-else
          class="employee-tabbar__item"
          :class="tabItemClass(tab)"
        >
          <span class="employee-tabbar__icon">
            <AppIcon
              :name="tab.icon"
              :size="tab.primary ? 22 : 20"
              :stroke-width="1.9"
            />
          </span>
          <span v-if="tab.badge" class="employee-tabbar__badge">{{ tab.badge }}</span>
          <span class="employee-tabbar__label">{{ tab.label }}</span>
        </span>
      </template>
    </div>
  </nav>
</template>
