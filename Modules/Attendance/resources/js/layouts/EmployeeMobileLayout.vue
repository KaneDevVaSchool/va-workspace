<script setup>
//
// Shell mobile dùng chung cho MỌI tài khoản trên viewport ≤768px — KHÔNG
// dùng AppSidebar/AppHeader. Header ngang tối giản (lời chào) + bottom tab
// bar cố định 5 mục. Dùng cho nhóm route meta.employeeMobile khi
// useEmployeeMobileView() = true.
//
// CSS: resources/css/employee-mobile-shell.css
//   .employee-shell--browser / --pwa  → header
//   .employee-tabbar--dock (Teleport body, fixed) → bottom nav
//
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useEmployeeMobileShell } from '@/composables/useEmployeeMobileShell';
import { useEmployeeTabbarDock } from '@/composables/useEmployeeTabbarDock';
import AppIcon from '@/components/AppIcon.vue';

const route = useRoute();
const auth = useAuthStore();
const { canInstall, promptInstall, dismiss } = usePwaInstall();
const { shellMode, isMobilePwa } = useEmployeeMobileShell();
const tabbarRef = ref(null);
useEmployeeTabbarDock(tabbarRef);

// PWA: style gắn thẳng trên thẻ — không dùng class .employee-tabbar
// (bản CSS cache vẫn có padding-bottom / safe-area kéo khoảng trống dưới icon).
const pwaNavStyle = {
  position: 'fixed',
  left: '0',
  right: '0',
  bottom: '0',
  zIndex: '500',
  display: 'grid',
  gridTemplateColumns: 'repeat(5, 1fr)',
  alignItems: 'center',
  height: '48px',
  margin: '0',
  padding: '0',
  background: 'var(--color-surface, #fff)',
  boxShadow: '0 -1px 0 var(--color-border, #e6e8ee)',
  boxSizing: 'border-box',
};

const pwaItemStyle = {
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  justifyContent: 'center',
  gap: '1px',
  height: '48px',
  margin: '0',
  padding: '0',
  color: 'var(--color-text-muted, #5c6570)',
  textDecoration: 'none',
  fontSize: '10px',
  fontWeight: '600',
  lineHeight: '1.1',
};

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

const displayName = computed(() => auth.user?.name?.trim() || '');
const departmentName = computed(() => auth.user?.department?.name?.trim() || '');
const jobTitleName = computed(() => auth.user?.job_title_name?.trim() || '');

const avatarBroken = ref(false);
const hasAvatarPhoto = computed(
  () => Boolean(auth.user?.avatar_url) && !avatarBroken.value,
);
const avatarInitial = computed(() => {
  const name = displayName.value || auth.user?.email || '?';
  return name.trim().charAt(0).toUpperCase() || '?';
});

const isHomeHeader = computed(() => route.name === 'dashboard.me');

onMounted(() => {
  if (auth.isAuthenticated) {
    auth.fetchMe();
  }
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
          <template v-if="isHomeHeader">
            <p class="employee-shell__hello">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</p>
            <p class="employee-shell__date">{{ todayLabel }}</p>
          </template>
          <template v-else>
            <p class="employee-shell__hello">{{ displayName || 'Nhân sự' }}</p>
            <p v-if="departmentName" class="employee-shell__date">{{ departmentName }}</p>
            <p v-else-if="jobTitleName" class="employee-shell__date">{{ jobTitleName }}</p>
          </template>
        </div>
        <div class="employee-shell__header-actions">
          <button type="button" class="employee-shell__icon-btn" aria-label="Thông báo">
            <AppIcon name="bell" :size="20" :stroke-width="1.8" />
            <span class="employee-shell__notif-dot" aria-hidden="true"></span>
          </button>
          <div class="employee-shell__avatar" :aria-label="displayName || 'Ảnh đại diện'">
            <img
              v-if="hasAvatarPhoto"
              class="employee-shell__avatar-img"
              :src="auth.user.avatar_url"
              alt=""
              @error="avatarBroken = true"
            />
            <span v-else class="employee-shell__avatar-initial">{{ avatarInitial }}</span>
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

    <Teleport to="body">
      <nav
        v-if="isMobilePwa"
        ref="tabbarRef"
        class="pwa-bottom-nav"
        :style="pwaNavStyle"
        aria-label="Điều hướng chính"
      >
        <router-link
          v-for="tab in TABS"
          :key="tab.name"
          :to="{ name: tab.name }"
          :style="{
            ...pwaItemStyle,
            color: route.name === tab.name ? 'var(--color-tertiary, #0e336f)' : pwaItemStyle.color,
          }"
        >
          <AppIcon :name="tab.icon" :size="18" :stroke-width="1.9" />
          <span>{{ tab.label }}</span>
        </router-link>
      </nav>
      <nav
        v-else
        ref="tabbarRef"
        class="employee-tabbar employee-tabbar--dock employee-tabbar--browser"
        aria-label="Điều hướng chính"
      >
        <div class="employee-tabbar__bar">
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
        </div>
        <div class="employee-tabbar__safe" aria-hidden="true"></div>
      </nav>
    </Teleport>
  </div>
</template>
