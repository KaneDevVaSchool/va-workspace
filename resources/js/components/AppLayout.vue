<script setup>
//
// Layout khung: sidebar + header một hàng (menu + title/actions) + nội dung.
//
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppSidebar from './AppSidebar.vue';
import AppHeader from './AppHeader.vue';
import { providePageHeaderTarget } from '../composables/usePageHeaderTarget';
import { closeHeaderPopovers } from '../composables/useHeaderPopover';

providePageHeaderTarget();

const route = useRoute();
const COLLAPSE_KEY = 'va-sidebar-collapsed';
const DESKTOP_MQ = '(min-width: 1280px)';

const sidebarOpen = ref(false);
const collapsed = ref(
  typeof localStorage !== 'undefined' && localStorage.getItem(COLLAPSE_KEY) === '1',
);

watch(collapsed, (value) => {
  localStorage.setItem(COLLAPSE_KEY, value ? '1' : '0');
});

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
        :collapsed="collapsed"
        @toggle-sidebar="toggleSidebar"
      />

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
</style>
