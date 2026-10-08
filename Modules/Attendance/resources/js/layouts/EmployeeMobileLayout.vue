<script setup>
//
// Shell mobile riêng cho nhân viên thường — KHÔNG dùng AppSidebar/AppHeader.
// Header ngang tối giản (lời chào) + bottom tab bar cố định 4 mục. Dùng cho
// nhóm route meta.employeeMobile khi useEmployeeMobileView() = true.
//
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { usePwaInstall } from '@/composables/usePwaInstall';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();
const auth = useAuthStore();
const { canInstall, promptInstall, dismiss } = usePwaInstall();

const TABS = [
  { name: 'dashboard.me', icon: 'home', label: 'Trang chủ' },
  { name: 'employee.attendance', icon: 'clock', label: 'Chấm công' },
  { name: 'employee.leave', icon: 'calendar', label: 'Nghỉ phép' },
  { name: 'employee.requests', icon: 'fileText', label: 'Đơn của tôi' },
];

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 11) return 'Chào buổi sáng';
  if (hour < 14) return 'Chào buổi trưa';
  if (hour < 18) return 'Chào buổi chiều';
  return 'Chào buổi tối';
});

const firstName = computed(() => {
  const name = auth.user?.name || '';
  const parts = name.trim().split(/\s+/);
  return parts[parts.length - 1] || name;
});

const todayLabel = computed(() => new Intl.DateTimeFormat('vi-VN', {
  weekday: 'long',
  day: '2-digit',
  month: '2-digit',
}).format(new Date()));
</script>

<template>
  <div class="employee-shell">
    <header class="employee-shell__header">
      <div class="employee-shell__avatar" aria-hidden="true">
        <AppIcon name="user" :size="20" />
      </div>
      <div class="employee-shell__greeting">
        <p class="employee-shell__hello">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</p>
        <p class="employee-shell__date">{{ todayLabel }}</p>
      </div>
    </header>

    <main class="employee-shell__content">
      <div v-if="canInstall" class="install-banner">
        <span class="install-banner__icon" aria-hidden="true">
          <AppIcon name="download" :size="18" :stroke-width="1.9" />
        </span>
        <span class="install-banner__text">
          <span class="install-banner__title">Cài ứng dụng vào máy</span>
          <span class="install-banner__hint">Mở nhanh hơn, dùng như một app riêng</span>
        </span>
        <button type="button" class="install-banner__action" @click="promptInstall">Cài đặt</button>
        <button type="button" class="install-banner__close" aria-label="Đóng gợi ý cài đặt" @click="dismiss">
          <AppIcon name="close" :size="14" />
        </button>
      </div>

      <slot />
    </main>

    <nav class="employee-tabbar" aria-label="Điều hướng chính">
      <router-link
        v-for="tab in TABS"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="employee-tabbar__item"
        :class="{ 'employee-tabbar__item--active': route.name === tab.name }"
      >
        <span class="employee-tabbar__icon">
          <AppIcon :name="tab.icon" :size="20" :stroke-width="1.9" />
        </span>
        <span class="employee-tabbar__label">{{ tab.label }}</span>
      </router-link>
    </nav>
  </div>
</template>

<style scoped>
.employee-shell {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-bg);
}

.employee-shell__header {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-5);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  background: linear-gradient(135deg, var(--color-primary-900), var(--color-primary-700));
  color: var(--color-on-primary);
}

.employee-shell__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  background: var(--color-sidebar-well-strong);
  color: var(--color-on-primary);
}

.employee-shell__greeting {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.employee-shell__hello {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.3;
}

.employee-shell__date {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-sidebar-text);
  text-transform: capitalize;
}

.employee-shell__content {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4) var(--space-3) calc(var(--space-6) + env(safe-area-inset-bottom));
}

.install-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
}

.install-banner__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-primary);
}

.install-banner__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.install-banner__title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.install-banner__hint {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.install-banner__action {
  flex-shrink: 0;
  padding: 0.375rem var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.install-banner__close {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.employee-tabbar {
  flex: 0 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  padding: var(--space-2) var(--space-2) calc(var(--space-2) + env(safe-area-inset-bottom));
  background: var(--color-surface);
  box-shadow: 0 -1px 0 var(--color-border), var(--shadow-md);
}

.employee-tabbar__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: var(--space-1) 0;
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  text-decoration: none;
}

.employee-tabbar__icon {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
}

.employee-tabbar__label {
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.2;
}

.employee-tabbar__item--active {
  color: var(--color-primary);
}

.employee-tabbar__item--active .employee-tabbar__icon {
  background: var(--color-primary-surface);
}

@media (max-width: 360px) {
  .employee-shell__header {
    padding: var(--space-3) var(--space-3) var(--space-4);
  }

  .employee-shell__hello {
    font-size: 1rem;
  }
}
</style>
