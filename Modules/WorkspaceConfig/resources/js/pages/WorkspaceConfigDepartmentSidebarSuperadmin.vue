<script setup>
//
// superadmin/workspace-config/departments/:departmentId/sidebar — tab "Menu
// hiển thị" trong hub chi tiết phòng ban. Chỉ xem — bật/tắt menu là việc của
// department_director tại manager.workspace-config.sidebar, super_admin
// không sửa thay. Dữ liệu lấy từ WorkspaceConfigDepartmentDetailHub qua inject.
//
import { computed, inject } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import StatusBadge from '../components/StatusBadge.vue';

const MENU_ICONS = {
  home: 'dashboard',
};

const hub = inject('workspaceConfigDeptDetailHub', null);
const menus = computed(() => hub?.sidebarMenus?.value ?? []);
const loading = computed(() => hub?.loading?.value ?? false);

const visibleCount = computed(() => menus.value.filter((item) => item.is_visible).length);

function menuIcon(menuKey) {
  return MENU_ICONS[menuKey] || 'layoutList';
}
</script>

<template>
  <div class="wc-dept-sidebar">
    <div class="wc-dept-sidebar__list-wrap hide-scrollbar">
      <p v-if="loading" class="wc-dept-sidebar__empty">Đang tải…</p>
      <p v-else-if="menus.length === 0" class="wc-dept-sidebar__empty">
        Chưa có mục menu nào có thể cấu hình.
      </p>

      <ul v-else class="wc-dept-sidebar__list" role="list">
        <li
          v-for="menu in menus"
          :key="menu.menu_key"
          class="wc-dept-sidebar__item"
          :class="{ 'wc-dept-sidebar__item--on': menu.is_visible }"
        >
          <span class="wc-dept-sidebar__item-icon" aria-hidden="true">
            <AppIcon :name="menuIcon(menu.menu_key)" :size="18" :stroke-width="1.75" />
          </span>

          <div class="wc-dept-sidebar__item-copy">
            <p class="wc-dept-sidebar__item-label">{{ menu.label }}</p>
            <StatusBadge
              :on="menu.is_visible"
              :label="menu.is_visible ? 'Đang hiện' : 'Đang ẩn'"
            />
          </div>
        </li>
      </ul>
    </div>

    <p v-if="!loading && menus.length > 0" class="wc-dept-sidebar__meta">
      {{ visibleCount }}/{{ menus.length }} đang hiện
    </p>
  </div>
</template>

<style scoped>
.wc-dept-sidebar {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow: hidden;
}

.wc-dept-sidebar__list-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.wc-dept-sidebar__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.wc-dept-sidebar__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wc-dept-sidebar__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-dept-sidebar__item:last-child {
  box-shadow: none;
}

.wc-dept-sidebar__item-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.wc-dept-sidebar__item--on .wc-dept-sidebar__item-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.wc-dept-sidebar__item-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
}

.wc-dept-sidebar__item-label {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.wc-dept-sidebar__meta {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}
</style>
