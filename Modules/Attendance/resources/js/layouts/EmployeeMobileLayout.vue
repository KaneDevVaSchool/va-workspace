<script setup>
//
// Shell mobile dùng chung cho MỌI tài khoản trên viewport ≤768px — KHÔNG
// dùng AppSidebar/AppHeader. Header ngang tối giản (lời chào) + bottom tab
// bar cố định 5 mục. Dùng cho nhóm route meta.employeeMobile khi
// useEmployeeMobileView() = true.
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
  { name: 'employee.feed', icon: 'megaphone', label: 'Bảng tin' },
  { name: 'employee.attendance', icon: 'clock', label: 'Chấm công' },
  { name: 'employee.leave', icon: 'calendar', label: 'Nghỉ phép' },
  { name: 'employee.requests', icon: 'fileText', label: 'Đơn' },
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
      <div class="employee-shell__header-deco" aria-hidden="true"></div>

      <div class="employee-shell__header-top">
        <div class="employee-shell__greeting">
          <p class="employee-shell__hello">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</p>
          <p class="employee-shell__date">{{ todayLabel }}</p>
        </div>
        <div class="employee-shell__header-actions">
          <button type="button" class="employee-shell__icon-btn" aria-label="Thông báo">
            <AppIcon name="bell" :size="20" :stroke-width="1.8" />
            <span class="employee-shell__notif-dot" aria-hidden="true"></span>
          </button>
          <div class="employee-shell__avatar" aria-hidden="true">
            <AppIcon name="user" :size="20" />
          </div>
        </div>
      </div>

      <label class="employee-shell__search">
        <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
        <input
          type="search"
          class="employee-shell__search-input"
          placeholder="Tìm kiếm tiện ích, đồng nghiệp..."
          autocomplete="off"
        />
      </label>
    </header>

    <main class="employee-shell__content" :class="{ 'employee-shell__content--home': route.name === 'dashboard.me' }">
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
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-tertiary-surface);
}

.employee-shell__header {
  position: relative;
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: calc(var(--space-4) + env(safe-area-inset-top, 0px))
    calc(var(--space-4) + env(safe-area-inset-right, 0px)) var(--space-5)
    calc(var(--space-4) + env(safe-area-inset-left, 0px));
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  background: linear-gradient(145deg, var(--color-tertiary-800), var(--color-tertiary-600));
  color: var(--color-on-tertiary);
  overflow: hidden;
}

.employee-shell__header-deco {
  position: absolute;
  top: -3rem;
  right: -2.5rem;
  width: 9rem;
  height: 9rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.08);
  pointer-events: none;
}

.employee-shell__header-deco::after {
  content: '';
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.06);
}

.employee-shell__header-top {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}

.employee-shell__greeting {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.employee-shell__hello {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.01em;
}

.employee-shell__date {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 400;
  color: rgba(255, 255, 255, 0.78);
  text-transform: capitalize;
}

.employee-shell__header-actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.employee-shell__icon-btn {
  position: relative;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.14);
  color: var(--color-on-tertiary);
  cursor: pointer;
}

.employee-shell__notif-dot {
  position: absolute;
  top: 0.5rem;
  right: 0.55rem;
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-500);
  box-shadow: 0 0 0 2px var(--color-tertiary-700);
}

.employee-shell__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.22);
  color: var(--color-on-tertiary);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.25);
}

.employee-shell__search {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 var(--space-3);
  height: 2.75rem;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.16);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.12);
}

.employee-shell__search-icon {
  flex-shrink: 0;
  color: rgba(255, 255, 255, 0.85);
}

.employee-shell__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-on-tertiary);
  font-family: inherit;
  font-size: 0.875rem;
  outline: none;
}

.employee-shell__search-input::placeholder {
  color: rgba(255, 255, 255, 0.65);
}

.employee-shell__content {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4) var(--space-3) var(--space-6);
}

.employee-shell__content--home {
  padding-top: 0;
}

.install-banner {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.install-banner__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
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
  background: var(--color-tertiary);
  color: var(--color-on-tertiary);
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
  grid-template-columns: repeat(5, 1fr);
  padding: var(--space-2) calc(var(--space-2) + env(safe-area-inset-right, 0px))
    calc(var(--space-2) + env(safe-area-inset-bottom, 0px))
    calc(var(--space-2) + env(safe-area-inset-left, 0px));
  background: var(--color-surface);
  box-shadow: 0 -1px 0 var(--color-border), var(--shadow-md);
}

.employee-tabbar__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 0;
  padding: var(--space-1) 2px;
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
  max-width: 100%;
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.employee-tabbar__item--active {
  color: var(--color-tertiary);
}

.employee-tabbar__item--active .employee-tabbar__icon {
  background: var(--color-tertiary-surface);
}

@media (max-width: 360px) {
  .employee-shell__header {
    padding-top: calc(var(--space-3) + env(safe-area-inset-top, 0px));
    padding-right: calc(var(--space-3) + env(safe-area-inset-right, 0px));
    padding-bottom: var(--space-4);
    padding-left: calc(var(--space-3) + env(safe-area-inset-left, 0px));
  }

  .employee-shell__hello {
    font-size: 1.0625rem;
  }

  .employee-tabbar {
    padding-left: 2px;
    padding-right: 2px;
  }

  .employee-tabbar__icon {
    width: 1.5rem;
    height: 1.5rem;
  }

  .employee-tabbar__label {
    font-size: 0.625rem;
  }
}
</style>
