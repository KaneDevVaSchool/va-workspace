<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import {
  DON_TU_CONFIG_ROUTE_NAMES,
  DON_TU_CONFIG_TAB_ACTIVE,
  DON_TU_CONFIG_TABS,
  DON_TU_MONITOR_ROUTE_NAMES,
  DON_TU_MONITOR_TABS,
} from '../nav.js';

const route = useRoute();

const currentTitle = computed(() => route.meta.screenTitle || 'Đơn từ');
const isConfigArea = computed(() => DON_TU_CONFIG_ROUTE_NAMES.has(route.name));
const isMonitorArea = computed(() => DON_TU_MONITOR_ROUTE_NAMES.has(route.name));

const breadcrumbs = computed(() => {
  const base = [
    { label: 'Trang chủ', to: { name: 'home' } },
    { label: 'Đơn từ', to: { name: 'manager.don-tu.start' } },
  ];
  if (isConfigArea.value) {
    return [
      ...base,
      { label: 'Cấu hình', to: { name: 'manager.don-tu.config' } },
      { label: currentTitle.value },
    ];
  }
  if (isMonitorArea.value) {
    return [
      ...base,
      { label: 'Giám sát', to: { name: 'manager.don-tu.monitor-hub' } },
      { label: currentTitle.value },
    ];
  }
  return [...base, { label: currentTitle.value }];
});

const hubTabs = computed(() => {
  if (isConfigArea.value) {
    return { label: 'Mục cấu hình đơn từ', items: DON_TU_CONFIG_TABS };
  }
  if (isMonitorArea.value) {
    return { label: 'Mục giám sát đơn từ', items: DON_TU_MONITOR_TABS };
  }
  return null;
});

function tabIsActive(tabName) {
  if (route.name === tabName) {
    return true;
  }
  return DON_TU_CONFIG_TAB_ACTIVE[route.name] === tabName;
}
</script>

<template>
  <section class="don-tu">
    <PageHeader
      :title="currentTitle"
      subtitle="Xem trước — chưa có dữ liệu"
      icon="fileText"
      :breadcrumbs="breadcrumbs"
    />

    <nav
      v-if="hubTabs"
      class="don-tu__hub-tabs hide-scrollbar"
      :aria-label="hubTabs.label"
    >
      <router-link
        v-for="tab in hubTabs.items"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="don-tu__hub-tab"
        :class="{ 'don-tu__hub-tab--active': tabIsActive(tab.name) }"
        :aria-current="tabIsActive(tab.name) ? 'page' : undefined"
      >
        <AppIcon :name="tab.sidebarIcon" :size="15" :stroke-width="1.75" />
        {{ tab.title }}
      </router-link>
    </nav>

    <div class="don-tu__main">
      <router-view />
    </div>
  </section>
</template>

<style scoped>
.don-tu {
  height: 100%;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: var(--space-5);
  overflow: hidden;
}

.don-tu__hub-tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-1);
  margin-bottom: var(--space-4);
  padding-bottom: var(--space-2);
  overflow-x: auto;
  box-shadow: 0 1px 0 var(--color-border);
}

.don-tu__hub-tab {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text-muted);
  text-decoration: none;
}

.don-tu__hub-tab:hover {
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.don-tu__hub-tab--active {
  color: var(--color-text);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.don-tu__main {
  flex: 1;
  min-height: 0;
  overflow: auto;
}
</style>
