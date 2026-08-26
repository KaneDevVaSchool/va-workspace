<script setup>
//
// Layout khung: sidebar + header một hàng (menu + title/actions) + nội dung.
//
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from './AppSidebar.vue';
import AppHeader from './AppHeader.vue';
import AppIcon from './AppIcon.vue';
import { providePageHeaderTarget } from '../composables/usePageHeaderTarget';
import { closeHeaderPopovers } from '../composables/useHeaderPopover';

providePageHeaderTarget();

const route = useRoute();
const COLLAPSE_KEY = 'va-sidebar-collapsed';
const HEADER_HIDDEN_KEY = 'va-header-hidden';
const DESKTOP_MQ = '(min-width: 1280px)';

const sidebarOpen = ref(false);
const collapsed = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(COLLAPSE_KEY) === '1',
);
const headerHidden = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(HEADER_HIDDEN_KEY) === '1',
);

watch(collapsed, (value) => {
  localStorage.setItem(COLLAPSE_KEY, value ? '1' : '0');
});

watch(headerHidden, (value) => {
  localStorage.setItem(HEADER_HIDDEN_KEY, value ? '1' : '0');
});

function toggleHeader() {
  headerHidden.value = !headerHidden.value;
}

watch(
  () => route.fullPath,
  () => {
    closeHeaderPopovers();
  },
);

function isDesktop() {
  return typeof window !== 'undefined' && window.matchMedia(DESKTOP_MQ).matches;
}

function toggleSidebar() {
  if (isDesktop()) {
    collapsed.value = !collapsed.value;
    return;
  }
  sidebarOpen.value = !sidebarOpen.value;
}

function onDesktopMqChange(event) {
  if (event.matches) {
    sidebarOpen.value = false;
  }
}

let desktopMq;

onMounted(() => {
  desktopMq = window.matchMedia(DESKTOP_MQ);
  desktopMq.addEventListener('change', onDesktopMqChange);
});

onBeforeUnmount(() => {
  desktopMq?.removeEventListener('change', onDesktopMqChange);
});
</script>

<template>
  <div class="app-layout">
    <AppSidebar
      :open="sidebarOpen"
      :collapsed="collapsed"
      @close="sidebarOpen = false"
    />

    <div class="app-layout__main">
      <AppHeader
        class="app-layout__header"
        :class="{ 'app-layout__header--hidden': headerHidden }"
        :collapsed="collapsed"
        @toggle-sidebar="toggleSidebar"
        @toggle-header="toggleHeader"
      />

      <button
        v-if="headerHidden"
        type="button"
        class="app-layout__header-reveal"
        aria-label="Hiện thanh menu trên cùng"
        @click="toggleHeader"
      >
        <AppIcon name="chevronsDown" :size="14" />
      </button>

      <main class="app-layout__content">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.app-layout {
  height: 100%;
  display: flex;
}

.app-layout__main {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.app-layout__content {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

/* Ẩn bằng CSS thay vì v-if/unmount: AppHeader chứa #app-content-header,
   target Teleport của PageHeader — gỡ khỏi DOM sẽ làm Teleport fallback
   render PageHeader ngay tại vị trí khai báo trong trang, vỡ layout. */
.app-layout__header--hidden {
  height: 0 !important;
  min-height: 0 !important;
  padding: 0 !important;
  overflow: hidden !important;
  box-shadow: none !important;
  pointer-events: none;
  visibility: hidden;
}

.app-layout__header-reveal {
  position: relative;
  z-index: 30;
  flex-shrink: 0;
  align-self: center;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 0.9375rem;
  margin-top: -1px;
  border: none;
  border-radius: 0 0 var(--radius-md) var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow:
    0 0 0 1px var(--color-border),
    var(--shadow-sm);
  cursor: pointer;
}

.app-layout__header-reveal:hover {
  color: var(--color-primary);
  background: var(--color-surface-muted);
}

.app-layout__header-reveal:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}
</style>
