<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { DON_TU_ADMIN_SCREENS, DON_TU_CONFIG_ROUTE_NAMES } from '../nav.js';

const route = useRoute();

const currentTitle = computed(() => route.meta.screenTitle || 'Đơn từ');
const isConfigArea = computed(() => DON_TU_CONFIG_ROUTE_NAMES.has(route.name));

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
  return [...base, { label: currentTitle.value }];
});
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
      v-if="isConfigArea"
      class="don-tu__config-tabs hide-scrollbar"
      aria-label="Mục cấu hình đơn từ"
    >
      <router-link
        v-for="tab in DON_TU_ADMIN_SCREENS"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="don-tu__config-tab"
        :class="{ 'don-tu__config-tab--active': route.name === tab.name }"
        :aria-current="route.name === tab.name ? 'page' : undefined"
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

.don-tu__config-tabs {
  flex-shrink: 0;
  display: flex;
  gap: var(--space-1);
  margin-bottom: var(--space-4);
  padding-bottom: var(--space-2);
  overflow-x: auto;
  box-shadow: 0 1px 0 var(--color-border);
}

.don-tu__config-tab {
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

.don-tu__config-tab:hover {
  color: var(--color-text);
  background: var(--color-surface-muted);
}

.don-tu__config-tab--active {
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
