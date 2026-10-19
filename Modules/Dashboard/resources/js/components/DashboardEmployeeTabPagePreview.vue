<script setup>
import AppIcon from '@/components/AppIcon.vue';
import { EMPLOYEE_PWA_TABBAR } from '../constants/employeePwaPreview.js';

const props = defineProps({
  activeRoute: { type: String, required: true },
  kicker: { type: String, default: 'VA Workspace' },
  title: { type: String, required: true },
  hint: { type: String, default: '' },
});

const tabs = EMPLOYEE_PWA_TABBAR.map((tab) => ({
  ...tab,
  active: tab.routeName === props.activeRoute,
}));
</script>

<template>
  <div class="emp-tab-preview" aria-hidden="true">
    <div class="employee-shell employee-shell--browser emp-tab-preview__shell">
      <header class="employee-shell__header employee-shell__header--brand">
        <div class="employee-shell__header-deco" aria-hidden="true"></div>
        <div class="employee-shell__header-top">
          <div class="employee-shell__greeting">
            <p class="employee-shell__kicker">{{ kicker }}</p>
            <h2 class="employee-shell__hello employee-shell__hello--page">{{ title }}</h2>
          </div>
          <div class="employee-shell__header-actions">
            <span class="employee-shell__icon-btn" aria-hidden="true">
              <AppIcon name="refresh" :size="20" :stroke-width="1.8" />
            </span>
            <span class="employee-shell__icon-btn" aria-hidden="true">
              <AppIcon name="bell" :size="20" :stroke-width="1.8" />
            </span>
            <span class="employee-shell__avatar" aria-hidden="true">
              <span class="employee-shell__avatar-initial">N</span>
            </span>
          </div>
        </div>
      </header>

      <main class="employee-shell__content">
        <div class="emp-tab-preview__body">
          <p v-if="hint" class="emp-tab-preview__hint">{{ hint }}</p>
          <div class="emp-tab-preview__placeholder" aria-hidden="true">
            <span v-for="n in 4" :key="n" class="emp-tab-preview__sk">
              <span class="emp-tab-preview__sk-line emp-tab-preview__sk-line--title" />
              <span class="emp-tab-preview__sk-line" />
              <span class="emp-tab-preview__sk-line emp-tab-preview__sk-line--short" />
            </span>
          </div>
        </div>
      </main>

      <nav class="employee-tabbar employee-tabbar--shell employee-tabbar--browser" aria-hidden="true">
        <div class="employee-tabbar__bar">
          <span
            v-for="tab in tabs"
            :key="tab.routeName"
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
.emp-tab-preview {
  display: flex;
  justify-content: center;
  min-width: 0;
}

.emp-tab-preview__shell {
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

.emp-tab-preview__body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-6);
}

.emp-tab-preview__hint {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.emp-tab-preview__placeholder {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.emp-tab-preview__sk {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.emp-tab-preview__sk-line {
  display: block;
  height: 0.625rem;
  border-radius: var(--radius-sm);
  background: linear-gradient(
    90deg,
    var(--color-border) 0%,
    color-mix(in srgb, var(--color-border) 55%, transparent) 50%,
    var(--color-border) 100%
  );
  background-size: 200% 100%;
  animation: emp-tab-preview-shimmer 1.4s ease-in-out infinite;
}

.emp-tab-preview__sk-line--title {
  width: 55%;
  height: 0.75rem;
}

.emp-tab-preview__sk-line--short {
  width: 40%;
}

@keyframes emp-tab-preview-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
</style>
