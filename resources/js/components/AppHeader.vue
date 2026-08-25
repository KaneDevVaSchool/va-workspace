<script setup>
//
// Header khung kiểu 1Office: một hàng thấp — menu + (PageHeader teleport)
// + lối tắt / nhật ký / avatar tài khoản.
//
import AppIcon from './AppIcon.vue';
import HeaderShortcuts from './HeaderShortcuts.vue';
import HeaderNotifications from './HeaderNotifications.vue';
import HeaderActivityLog from './HeaderActivityLog.vue';
import HeaderAccountMenu from './HeaderAccountMenu.vue';
import { usePageHeaderTarget } from '../composables/usePageHeaderTarget';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

defineProps({
  collapsed: { type: Boolean, default: false },
});

defineEmits(['toggle-sidebar']);

const pageHeaderTarget = usePageHeaderTarget();
const auth = useAuthStore();

function setPageHeaderEl(el) {
  if (pageHeaderTarget) {
    pageHeaderTarget.value = el;
  }
}
</script>

<template>
  <header class="app-header">
    <button
      type="button"
      class="app-header__icon-btn"
      :aria-label="collapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
      :aria-expanded="!collapsed"
      data-tour="header-menu-toggle"
      @click="$emit('toggle-sidebar')"
    >
      <AppIcon name="menu" :size="20" />
    </button>

    <div id="app-content-header" class="app-header__page" :ref="setPageHeaderEl" />

    <div class="app-header__actions">
      <HeaderShortcuts />
      <HeaderNotifications />
      <HeaderActivityLog v-if="auth.canViewActivityLog" />
      <HeaderAccountMenu />
    </div>
  </header>
</template>

<style scoped>
.app-header {
  position: relative;
  flex-shrink: 0;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  height: 3.25rem;
  padding: 0 var(--space-3);
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.app-header__icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.app-header__icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.app-header__icon-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.app-header__page {
  flex: 1;
  min-width: 0;
  height: 100%;
}

.app-header__actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
}

@media (min-width: 640px) {
  .app-header {
    padding: 0 var(--space-4);
  }
}

@media (min-width: 1280px) {
  .app-header {
    padding: 0 var(--space-5);
  }
}
</style>
