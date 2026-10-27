<script setup>
import { computed, provide, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { DON_TU_CONFIG_HUB_KEY } from '../composables/useDonTuConfigHub.js';
import {
  DON_TU_CONFIG_SUMMARY_KEY,
  useDonTuConfigSummary,
} from '../composables/useDonTuConfigSummary.js';
import {
  DON_TU_CONFIG_ROUTE_NAMES,
  DON_TU_MONITOR_ROUTE_NAMES,
  DON_TU_MONITOR_TABS,
} from '../nav.js';

const route = useRoute();

const isStatusConfigPage = computed(() => route.name === 'manager.don-tu.statuses');
const isConfigEditor = computed(
  () => route.name === 'manager.don-tu.form' || route.name === 'manager.don-tu.workflow',
);

const currentTitle = computed(() => {
  if (isStatusConfigPage.value) {
    return 'Thiết lập trạng thái';
  }
  return route.meta.screenTitle || 'Đơn từ';
});
const headerSubtitle = computed(() => {
  if (isStatusConfigPage.value) {
    return 'Quản lý bộ trạng thái và các bước trên hồ sơ đơn';
  }
  if (isConfigArea.value) {
    return 'Thiết lập trạng thái, mẫu biểu và quy trình duyệt đơn';
  }
  if (isMonitorArea.value) {
    return 'Giám sát tiến độ và báo cáo đơn từ';
  }
  return 'Nộp đơn, theo dõi và duyệt hồ sơ';
});
const isConfigArea = computed(() => DON_TU_CONFIG_ROUTE_NAMES.has(route.name));
const isMonitorArea = computed(() => DON_TU_MONITOR_ROUTE_NAMES.has(route.name));

const breadcrumbs = computed(() => {
  const base = [
    { label: 'Trang chủ', to: { name: 'home' } },
    { label: 'Đơn từ', to: { name: 'manager.don-tu.start' } },
  ];
  if (isConfigArea.value) {
    return [...base, { label: 'Cấu hình', to: { name: 'manager.don-tu.config' } }, { label: currentTitle.value }];
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
  if (isMonitorArea.value) {
    return { label: 'Mục giám sát đơn từ', items: DON_TU_MONITOR_TABS };
  }
  return null;
});

function tabIsActive(tabName) {
  return route.name === tabName;
}

const configSummary = useDonTuConfigSummary();
provide(DON_TU_CONFIG_SUMMARY_KEY, configSummary);

watch(
  isConfigArea,
  (active) => {
    if (active) {
      configSummary.loadSummary();
    }
  },
  { immediate: true },
);

const reloadChild = ref(null);
const reloading = ref(false);
const primaryAction = ref(null);

const headerPrimaryAction = computed(() => primaryAction.value);

const headerIcon = computed(() => {
  if (isStatusConfigPage.value) return 'layers';
  if (isConfigArea.value) return 'settings';
  return 'fileText';
});

provide(DON_TU_CONFIG_HUB_KEY, {
  registerReload(fn) {
    reloadChild.value = fn;
  },
  unregisterReload() {
    reloadChild.value = null;
  },
  setPrimaryAction(action) {
    primaryAction.value = action;
  },
  clearPrimaryAction() {
    primaryAction.value = null;
  },
});

async function reload() {
  if (reloading.value) return;
  reloading.value = true;
  try {
    if (isConfigArea.value) {
      await configSummary.loadSummary();
    }
    if (reloadChild.value) {
      await reloadChild.value();
    }
  } finally {
    reloading.value = false;
  }
}
</script>

<template>
  <section class="don-tu">
    <PageHeader
      :title="currentTitle"
      :subtitle="headerSubtitle"
      :icon="headerIcon"
      :breadcrumbs="breadcrumbs"
      :primary-action="isConfigArea ? headerPrimaryAction : null"
    >
      <template v-if="isConfigArea" #actions>
        <button type="button" class="don-tu__header-btn" :disabled="reloading" @click="reload">
          <AppIcon name="refresh" :size="16" :class="{ 'don-tu__spin': reloading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

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

.don-tu__header-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.don-tu__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.don-tu__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.don-tu__spin {
  animation: don-tu-spin 0.8s linear infinite;
}

@keyframes don-tu-spin {
  to {
    transform: rotate(360deg);
  }
}

.don-tu__hub-tabs {
  position: relative;
  z-index: 8;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-4);
  min-height: 2.5rem;
  margin-bottom: var(--space-3);
  padding: 0 var(--space-1);
  overflow-x: auto;
  box-shadow: 0 1px 0 var(--color-border);
}

.don-tu__hub-tab {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-1);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 2px 0 transparent;
}

.don-tu__hub-tab:hover {
  color: var(--color-text);
}

.don-tu__hub-tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.don-tu__main {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

@media (max-width: 768px) {
  .don-tu {
    padding: var(--space-4);
  }
}

@media (prefers-reduced-motion: reduce) {
  .don-tu__spin {
    animation: none;
  }
}
</style>
