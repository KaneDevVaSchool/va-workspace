<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';

defineProps({
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
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

const tabs = [
  { icon: 'home', label: 'Trang chủ', active: true },
  { icon: 'megaphone', label: 'Bảng tin', active: false },
  { icon: 'clock', label: 'Chấm công', active: false },
  { icon: 'calendar', label: 'Nghỉ phép', active: false },
  { icon: 'fileText', label: 'Đơn', active: false },
];
</script>

<template>
  <div class="emp-preview" aria-hidden="true">
    <div class="employee-shell employee-shell--browser employee-shell--home emp-preview__shell">
      <header class="employee-shell__header">
        <div class="employee-shell__header-deco" aria-hidden="true"></div>

        <div class="employee-shell__header-top">
          <div class="employee-shell__greeting">
            <p class="employee-shell__hello">{{ greeting }}{{ firstName ? `, ${firstName}` : '' }}</p>
            <p class="employee-shell__date">{{ todayLabel }}</p>
          </div>
          <div class="employee-shell__header-actions">
            <span class="employee-shell__icon-btn" aria-hidden="true">
              <AppIcon name="bell" :size="20" :stroke-width="1.8" />
              <span class="employee-shell__notif-dot" aria-hidden="true"></span>
            </span>
            <div class="employee-shell__avatar" aria-hidden="true">
              <span class="employee-shell__avatar-initial">{{ avatarInitial }}</span>
            </div>
          </div>
        </div>

        <label class="employee-shell__search">
          <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
          <input
            type="search"
            class="employee-shell__search-input"
            placeholder="Tìm kiếm tiện ích, đồng nghiệp..."
            tabindex="-1"
            readonly
          />
        </label>
      </header>

      <main class="employee-shell__content employee-shell__content--home">
        <div class="employee-home">
          <div class="employee-home__stat-row">
            <div class="stat-card stat-card--tasks">
              <span class="stat-card__icon stat-card__icon--tasks" aria-hidden="true">
                <AppIcon name="listChecks" :size="20" :stroke-width="1.8" />
              </span>
              <div class="stat-card__body">
                <p class="stat-card__label">Việc hôm nay</p>
                <p class="stat-card__value">1/4 đã xong</p>
              </div>
              <div class="stat-card__progress" aria-hidden="true">
                <div class="stat-card__progress-fill" style="width: 25%"></div>
              </div>
            </div>

            <div class="stat-card stat-card--attendance">
              <span class="stat-card__icon stat-card__icon--attendance" aria-hidden="true">
                <AppIcon name="clock" :size="20" :stroke-width="1.8" />
              </span>
              <div class="stat-card__body">
                <p class="stat-card__label">Chấm công hôm nay</p>
                <p class="stat-card__value stat-card__value--inline">
                  <AppIcon name="check" :size="14" :stroke-width="2.5" class="stat-card__check" />
                  Vào lúc 07:52
                </p>
              </div>
            </div>
          </div>

          <section class="employee-home__section">
            <div class="section-head">
              <h2 class="section-head__title">Tiện ích nhanh</h2>
              <span class="section-head__link">Xem tất cả</span>
            </div>
            <QuickUtilitiesGrid
              :items="utilities"
              :loading="loading"
              compact
              preview
              :active-key="activeUtilityKey"
            />
          </section>

          <section class="employee-home__section">
            <div class="section-head">
              <h2 class="section-head__title">Việc thường ngày</h2>
              <span class="section-head__link">Xem tất cả</span>
            </div>
            <ul class="task-list">
              <li class="task-card task-card--upcoming">
                <span class="task-card__check task-card__check--preview" aria-hidden="true"></span>
                <div class="task-card__main">
                  <p class="task-card__title">Cập nhật tiến độ việc được giao</p>
                  <p class="task-card__project">Dự án triển khai hệ thống</p>
                </div>
                <span class="task-card__time">16:30</span>
              </li>
            </ul>
          </section>

          <section class="employee-home__section employee-home__section--last">
            <div class="section-head">
              <h2 class="section-head__title">Thông báo mới</h2>
              <span class="section-head__link">Xem tất cả</span>
            </div>
            <ul class="notice-list">
              <li>
                <span class="notice-card notice-card--preview">
                  <span class="notice-card__icon notice-card__icon--tertiary" aria-hidden="true">
                    <AppIcon name="megaphone" :size="20" :stroke-width="1.8" />
                  </span>
                  <span class="notice-card__body">
                    <span class="notice-card__title">Thông báo nghỉ lễ</span>
                    <span class="notice-card__meta">Phòng Nhân sự • Hôm nay</span>
                  </span>
                </span>
              </li>
            </ul>
          </section>
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
.emp-preview {
  display: flex;
  justify-content: center;
  min-width: 0;
}

.emp-preview__shell {
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

.emp-preview__shell .employee-shell__icon-btn {
  pointer-events: none;
}

.employee-home {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  padding-bottom: var(--space-2);
}

.employee-home__stat-row {
  position: relative;
  z-index: 4;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  margin: calc(-1 * var(--employee-stat-overlap, 3.25rem)) 0 var(--space-2);
}

.stat-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 6.5rem;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 4px 14px rgba(9, 36, 78, 0.08);
  text-align: left;
  font-family: inherit;
}

.stat-card__icon {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
}

.stat-card__icon--tasks {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.stat-card__icon--attendance {
  background: var(--color-success-tint-bg);
  color: var(--color-success);
}

.stat-card__body {
  flex: 1;
  min-width: 0;
}

.stat-card__label {
  margin: 0 0 2px;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.stat-card__value {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.3;
}

.stat-card__value--inline {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.stat-card__check {
  color: var(--color-success);
}

.stat-card__progress {
  position: absolute;
  right: var(--space-3);
  bottom: var(--space-3);
  left: var(--space-3);
  height: 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface-strong);
  overflow: hidden;
}

.stat-card__progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.stat-card--tasks {
  padding-bottom: calc(var(--space-3) + 0.5rem);
}

.employee-home__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.employee-home__section--last {
  padding-bottom: var(--space-2);
}

.task-list,
.notice-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-card--upcoming {
  background: var(--color-tertiary-surface);
  box-shadow: inset 0 0 0 1px var(--color-tertiary-200);
}

.task-card__check--preview {
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 2.5px var(--color-tertiary);
}

.task-card__main {
  flex: 1;
  min-width: 0;
}

.task-card__title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text);
}

.task-card__project {
  margin: 2px 0 0;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.task-card__time {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-tertiary);
}

.notice-card--preview {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.notice-card__icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  color: var(--color-on-tertiary);
}

.notice-card__icon--tertiary {
  background: var(--color-tertiary);
}

.notice-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.notice-card__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.notice-card__meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.section-head__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.section-head__link {
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-tertiary);
}
</style>
