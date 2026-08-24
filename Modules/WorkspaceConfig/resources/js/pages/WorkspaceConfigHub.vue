<script setup>
//
// manager/workspace-config — hub cấu hình workspace của phòng ban mình.
// Entry point duy nhất trong sidebar; các mục con (thành viên, menu, và
// sau này tiêu chí đánh giá) điều hướng qua tab nội bộ trang này, không
// phải mục sidebar riêng.
//
import { computed, provide, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const TABS = [
  {
    name: 'manager.workspace-config.members',
    label: 'Thành viên',
    permission: 'workspace_config.view_department',
  },
  {
    name: 'manager.workspace-config.sidebar',
    label: 'Menu hiển thị',
    permission: 'workspace_config.manage_sidebar_department',
  },
];

const activeTab = computed(() => route.name);
const departmentLabel = computed(() => auth.user?.department?.name || 'Chưa gắn phòng ban');

const visibleTabs = computed(() =>
  TABS.filter((tab) => {
    if (tab.permission && !auth.can(tab.permission)) return false;
    if (auth.hiddenMenuKeys.includes(tab.name)) return false;
    return true;
  }),
);

const reloadChild = ref(null);
const reloading = ref(false);
const primaryAction = ref(null);

const headerPrimaryAction = computed(() => {
  if (activeTab.value !== 'manager.workspace-config.members') return null;
  return primaryAction.value;
});

provide('workspaceConfigHub', {
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
  if (!reloadChild.value || reloading.value) return;
  reloading.value = true;
  try {
    await reloadChild.value();
  } finally {
    reloading.value = false;
  }
}

watch(
  [visibleTabs, activeTab],
  () => {
    if (visibleTabs.value.some((tab) => tab.name === activeTab.value)) return;
    const fallback = visibleTabs.value[0];
    if (fallback) {
      router.replace({ name: fallback.name });
    }
  },
  { immediate: true },
);
</script>

<template>
  <section class="wc-hub">
    <PageHeader
      title="Cấu hình phòng ban"
      :subtitle="departmentLabel"
      icon="settings"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình phòng ban' },
      ]"
      :primary-action="headerPrimaryAction"
    >
      <template #actions>
        <button type="button" class="wc-hub__header-btn" :disabled="reloading" @click="reload">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-hub__spin': reloading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <nav v-if="visibleTabs.length" class="wc-hub__tabs hide-scrollbar" aria-label="Mục cấu hình phòng ban">
      <router-link
        v-for="tab in visibleTabs"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="wc-hub__tab"
        :class="{ 'wc-hub__tab--active': activeTab === tab.name }"
        :aria-current="activeTab === tab.name ? 'page' : undefined"
      >
        {{ tab.label }}
      </router-link>
    </nav>

    <div class="wc-hub__body">
      <router-view />
    </div>
  </section>
</template>

<style scoped>
.wc-hub {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.wc-hub__header-btn {
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

.wc-hub__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-hub__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-hub__spin {
  animation: wc-hub-spin 0.8s linear infinite;
}

@keyframes wc-hub-spin {
  to {
    transform: rotate(360deg);
  }
}

.wc-hub__tabs {
  position: relative;
  z-index: 8;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.25rem;
  margin-bottom: var(--space-3);
  padding: 0.25rem 0;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.wc-hub__tab {
  padding: var(--space-2) var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  box-shadow: 0 2px 0 transparent;
}

.wc-hub__tab:hover {
  color: var(--color-text);
}

.wc-hub__tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.wc-hub__body {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

@media (max-width: 768px) {
  .wc-hub {
    padding: var(--space-4);
  }

  .wc-hub__tabs {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-hub__spin {
    animation: none;
  }
}
</style>
