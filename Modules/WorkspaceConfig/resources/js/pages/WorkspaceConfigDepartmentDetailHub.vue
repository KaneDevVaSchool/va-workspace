<script setup>
//
// superadmin/workspace-config/departments/:departmentId — hub workspace của
// 1 phòng ban, mở từ WorkspaceConfigOverviewSuperadmin. Load 1 lần dữ liệu
// phòng ban (menu hiển thị + thành viên + tiêu chí đánh giá) rồi cấp cho 3
// tab con qua provide/inject — tránh mỗi tab tự gọi lại API. Chỉ xem — super_admin
// không sửa thay department_director (không có thao tác ghi ở tab nào).
// Tab "Menu hiển thị" luôn đứng đầu.
//
import { computed, provide, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';

const TABS = [
  { name: 'superadmin.workspace-config.department-detail.sidebar', label: 'Menu hiển thị', icon: 'layoutList' },
  { name: 'superadmin.workspace-config.department-detail.members', label: 'Thành viên', icon: 'users' },
  { name: 'superadmin.workspace-config.department-detail.evaluation', label: 'Tiêu chí đánh giá', icon: 'clipboardCheck' },
];

const route = useRoute();
const router = useRouter();

const department = ref(null);
const members = ref([]);
const sidebarMenus = ref([]);
const evaluationCriteria = ref([]);
const loading = ref(false);

const activeTab = computed(() => route.name);

const directorSubtitle = computed(() => {
  const director = department.value?.director;
  if (!director?.name) return '';
  return director.email ? `${director.name} · ${director.email}` : director.name;
});

provide('workspaceConfigDeptDetailHub', {
  department,
  members,
  sidebarMenus,
  evaluationCriteria,
  loading,
});

async function loadDetail() {
  loading.value = true;
  try {
    const { data } = await window.axios.get(
      `/api/workspace-config/departments/${route.params.departmentId}`,
    );
    department.value = data.department;
    members.value = data.members ?? [];
    sidebarMenus.value = data.sidebar_menus ?? [];
    evaluationCriteria.value = data.evaluation_criteria ?? [];
  } catch (error) {
    department.value = null;
    members.value = [];
    sidebarMenus.value = [];
    evaluationCriteria.value = [];
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được chi tiết phòng ban.');
  } finally {
    loading.value = false;
  }
}

watch(
  () => route.params.departmentId,
  () => loadDetail(),
  { immediate: true },
);

watch(
  activeTab,
  () => {
    if (TABS.some((tab) => tab.name === activeTab.value)) return;
    router.replace({
      name: 'superadmin.workspace-config.department-detail.sidebar',
      params: route.params,
    });
  },
  { immediate: true },
);
</script>

<template>
  <section class="wc-dept-hub">
    <PageHeader
      :title="department ? department.name : 'Chi tiết phòng ban'"
      :subtitle="directorSubtitle"
      icon="building"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: department ? department.name : '' },
      ]"
    >
      <template #actions>
        <button type="button" class="wc-dept-hub__header-btn" :disabled="loading" @click="loadDetail">
          <AppIcon name="refresh" :size="16" :class="{ 'wc-dept-hub__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <nav class="wc-dept-hub__tabs hide-scrollbar" aria-label="Mục chi tiết phòng ban">
      <router-link
        v-for="tab in TABS"
        :key="tab.name"
        :to="{ name: tab.name, params: route.params }"
        class="wc-dept-hub__tab"
        :class="{ 'wc-dept-hub__tab--active': activeTab === tab.name }"
        :aria-current="activeTab === tab.name ? 'page' : undefined"
      >
        <AppIcon :name="tab.icon" :size="15" :stroke-width="1.75" />
        {{ tab.label }}
      </router-link>
    </nav>

    <div class="wc-dept-hub__body">
      <router-view />
    </div>
  </section>
</template>

<style scoped>
.wc-dept-hub {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.wc-dept-hub__header-btn {
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

.wc-dept-hub__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-dept-hub__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-dept-hub__spin {
  animation: wc-dept-hub-spin 0.8s linear infinite;
}

@keyframes wc-dept-hub-spin {
  to {
    transform: rotate(360deg);
  }
}

.wc-dept-hub__tabs {
  position: relative;
  z-index: 8;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-1);
  min-height: 2.5rem;
  margin-bottom: var(--space-3);
  padding: 0;
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.wc-dept-hub__tab {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-2) var(--space-3);
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

.wc-dept-hub__tab:hover {
  color: var(--color-text);
}

.wc-dept-hub__tab--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.wc-dept-hub__body {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

@media (max-width: 768px) {
  .wc-dept-hub {
    padding: var(--space-4);
  }

  .wc-dept-hub__tabs {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}

@media (max-width: 480px) {
  .wc-dept-hub {
    padding: var(--space-3);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-dept-hub__spin {
    animation: none;
  }
}
</style>
