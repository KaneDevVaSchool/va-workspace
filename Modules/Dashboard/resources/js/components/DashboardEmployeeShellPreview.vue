<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import EmployeeMobileTabbar from '@modules/Attendance/resources/js/components/employee-mobile-shell/EmployeeMobileTabbar.vue';
import { EMPLOYEE_MOBILE_TABS } from '@modules/Attendance/resources/js/constants/employeeMobileTabs.js';

const props = defineProps({
  activeRoute: { type: String, default: 'dashboard.me' },
  home: { type: Boolean, default: false },
  pageTitle: { type: String, default: '' },
});

const auth = useAuthStore();

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

const avatarInitial = computed(() => {
  const name = displayName.value || auth.user?.email || '?';
  return name.trim().charAt(0).toUpperCase() || '?';
});

const todayLabel = computed(() =>
  new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
  }).format(new Date()),
);

const headerTitle = computed(() => {
  if (props.home) return '';
  return props.pageTitle.trim() || displayName.value || 'Nhân sự';
});

</script>

<template>
  <div class="emp-shell-preview" aria-hidden="true">
    <div class="employee-shell employee-shell--browser employee-shell--unified emp-shell-preview__shell">
      <header
        class="employee-shell__header employee-shell__header--brand"
        :class="{ 'employee-shell__header--home': home }"
      >
        <div class="employee-shell__header-deco" aria-hidden="true"></div>
        <div class="employee-shell__header-top">
          <span class="employee-shell__brand-mark" aria-hidden="true">
            <img
              src="/images/pwa/icon-192.png"
              alt=""
              class="employee-shell__brand-logo employee-shell__brand-logo--pwa"
              width="40"
              height="40"
            />
          </span>
          <div class="employee-shell__greeting">
              <template v-if="home">
                <p class="employee-shell__kicker">{{ greeting }}</p>
                <h1 class="employee-shell__hello">
                  {{ firstName || displayName || 'bạn' }}
                </h1>
                <div class="employee-shell__meta employee-shell__meta--reserve employee-shell__meta--home">
                  <span class="employee-shell__date">{{ todayLabel }}</span>
                  <span
                    v-if="departmentName || jobTitleName"
                    class="employee-shell__meta-sep"
                    aria-hidden="true"
                  >·</span>
                  <span v-if="departmentName" class="employee-shell__meta-extra">{{ departmentName }}</span>
                  <span v-else-if="jobTitleName" class="employee-shell__meta-extra">{{ jobTitleName }}</span>
                </div>
              </template>
              <template v-else>
                <p class="employee-shell__kicker">VA Workspace</p>
                <h1 class="employee-shell__hello employee-shell__hello--page">
                  {{ headerTitle }}
                </h1>
                <div v-if="pageTitle && (departmentName || jobTitleName)" class="employee-shell__meta">
                  <span class="employee-shell__meta-extra">{{ departmentName || jobTitleName }}</span>
                </div>
              </template>
            </div>
          <div class="employee-shell__header-actions">
            <div class="employee-shell__tool-cluster">
              <span class="employee-shell__icon-btn" aria-hidden="true">
                <AppIcon name="refresh" :size="20" :stroke-width="1.8" />
              </span>
              <span class="employee-shell__icon-btn employee-shell__icon-btn--chat" aria-hidden="true">
                <AppIcon name="messageCircle" :size="20" :stroke-width="1.8" />
              </span>
            </div>
            <span
              class="employee-shell__avatar employee-shell__avatar-btn"
              :class="{ 'employee-shell__avatar--home': home }"
              aria-hidden="true"
            >
              <span class="employee-shell__avatar-initial">{{ avatarInitial }}</span>
            </span>
          </div>
        </div>

        <label v-if="home" class="employee-shell__search employee-shell__search--elevated">
          <span class="employee-shell__search-leading" aria-hidden="true">
            <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
          </span>
          <input
            type="search"
            class="employee-shell__search-input"
            placeholder="Tìm tiện ích nhanh..."
            tabindex="-1"
            readonly
          />
        </label>
      </header>

      <main
        class="employee-shell__content employee-shell__content--unified"
        :class="{ 'employee-shell__content--home': home }"
      >
        <slot />
      </main>

      <EmployeeMobileTabbar
        preview
        :tabs="EMPLOYEE_MOBILE_TABS"
        :active-name="activeRoute"
        :shell-page-key="activeRoute"
      />
    </div>
  </div>
</template>

<style scoped>
.emp-shell-preview {
  display: flex;
  justify-content: center;
  min-width: 0;
}

.emp-shell-preview__shell {
  position: relative !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  inset: auto !important;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 24rem;
  height: min(36rem, 72vh) !important;
  min-height: 28rem;
  max-height: min(36rem, 72vh) !important;
  overflow: hidden;
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.emp-shell-preview__shell .employee-shell__header {
  flex-shrink: 0;
}

.emp-shell-preview__shell .employee-shell__content {
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
}

.emp-shell-preview__shell :deep(.employee-tabbar--shell) {
  flex-shrink: 0;
  margin-top: auto;
}

.emp-shell-preview__shell :deep(.employee-tabbar__item) {
  pointer-events: none;
}

.emp-shell-preview__shell :is(.employee-shell__icon-btn, .employee-shell__avatar-btn, .employee-shell__brand-mark) {
  pointer-events: none;
}

.emp-shell-preview__shell .employee-shell__search-input {
  pointer-events: none;
}
</style>
