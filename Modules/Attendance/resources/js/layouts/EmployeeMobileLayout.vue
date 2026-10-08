<script setup>
//
// Shell mobile dùng chung cho MỌI tài khoản trên viewport ≤768px — KHÔNG
// dùng AppSidebar/AppHeader. Header ngang tối giản (lời chào) + bottom tab
// bar cố định 5 mục. Dùng cho nhóm route meta.employeeMobile khi
// useEmployeeMobileView() = true.
//
// CSS: resources/css/employee-mobile-shell.css
//   .employee-shell--browser  → tab trình duyệt
//   .employee-shell--pwa      → PWA cài màn hình
//
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useEmployeeMobileShell } from '@/composables/useEmployeeMobileShell';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();
const auth = useAuthStore();
const { canInstall, promptInstall, dismiss } = usePwaInstall();
const { shellMode } = useEmployeeMobileShell();

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
  <div
    class="employee-shell"
    :class="[
      `employee-shell--${shellMode}`,
      { 'employee-shell--home': route.name === 'dashboard.me' },
    ]"
  >
    <header
      class="employee-shell__header"
      :class="{ 'employee-shell__header--compact': route.name !== 'dashboard.me' }"
    >
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

      <label v-if="route.name === 'dashboard.me'" class="employee-shell__search">
        <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
        <input
          type="search"
          class="employee-shell__search-input"
          placeholder="Tìm kiếm tiện ích, đồng nghiệp..."
          autocomplete="off"
        />
      </label>
    </header>

    <main
      class="employee-shell__content"
      :class="{ 'employee-shell__content--home': route.name === 'dashboard.me' }"
    >
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
