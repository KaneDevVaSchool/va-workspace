<script setup>
import AppIcon from '@/components/AppIcon.vue';
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';

defineProps({
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});

const tabs = [
  { icon: 'home', label: 'Trang chủ', active: false },
  { icon: 'megaphone', label: 'Bảng tin', active: false },
  { icon: 'clock', label: 'Chấm công', active: false },
  { icon: 'calendar', label: 'Nghỉ phép', active: false },
  { icon: 'fileText', label: 'Đơn', active: false },
];
</script>

<template>
  <div class="emp-util-all-preview" aria-hidden="true">
    <div class="employee-shell employee-shell--browser emp-util-all-preview__shell">
      <header class="employee-shell__header employee-shell__header--compact">
        <div class="employee-shell__header-deco" aria-hidden="true"></div>
        <div class="employee-shell__header-top">
          <div class="employee-shell__greeting">
            <p class="employee-shell__hello">Tiện ích nhanh</p>
            <p class="employee-shell__date">Chạm vào lối tắt để mở</p>
          </div>
          <div class="employee-shell__header-actions">
            <span class="employee-shell__icon-btn" aria-hidden="true">
              <AppIcon name="bell" :size="20" :stroke-width="1.8" />
            </span>
          </div>
        </div>
      </header>

      <main class="employee-shell__content">
        <div class="emp-util-all-preview__body">
          <p class="emp-util-all-preview__hint">
            Trang này mở khi nhân viên chọn <strong>Xem tất cả</strong> ở trang chủ điện thoại.
          </p>
          <QuickUtilitiesGrid
            :items="utilities"
            :loading="loading"
            preview
            :active-key="activeUtilityKey"
          />
        </div>
      </main>

      <nav class="employee-tabbar employee-tabbar--shell employee-tabbar--browser" aria-hidden="true">
        <div class="employee-tabbar__bar">
          <span
            v-for="tab in tabs"
            :key="tab.label"
            class="employee-tabbar__item"
            :class="{ 'employee-tabbar__item--active': tab.active }"
          >
            <span class="employee-tabbar__icon">
              <AppIcon :name="tab.icon" :size="20" :stroke-width="1.9" />
            </span>
            <span class="employee-tabbar__label">{{ tab.label }}</span>
          </span>
        </div>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.emp-util-all-preview {
  display: flex;
  justify-content: center;
  min-width: 0;
}

.emp-util-all-preview__shell {
  position: relative !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  width: 100%;
  max-width: 24rem;
  height: auto !important;
  max-height: none !important;
  overflow: hidden;
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
}

.emp-util-all-preview__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-6);
}

.emp-util-all-preview__hint {
  margin: 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.emp-util-all-preview__hint strong {
  font-weight: 700;
  color: var(--color-text);
}
</style>
