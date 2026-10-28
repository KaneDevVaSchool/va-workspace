<script setup>
import AppIcon from '@/components/AppIcon.vue';
import DashboardEmployeeShellPreview from './DashboardEmployeeShellPreview.vue';
import QuickUtilitiesGrid from './QuickUtilitiesGrid.vue';

defineProps({
  utilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  activeUtilityKey: { type: String, default: null },
});
</script>

<template>
  <DashboardEmployeeShellPreview active-route="dashboard.me" page-title="Trang chủ">
    <div class="employee-home">
          <div class="employee-home__stat-row employee-home__stat-row--single">
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

          <section class="employee-home__section employee-home__section--last">
            <div class="section-head">
              <h2 class="section-head__title">Thông báo mới</h2>
              <span class="section-head__link">Xem tất cả</span>
            </div>
            <ul class="notice-list">
              <li>
                <span class="notice-card notice-card--preview notice-card--new">
                  <span class="notice-card__icon notice-card__icon--tertiary" aria-hidden="true">
                    <AppIcon name="megaphone" :size="18" :stroke-width="1.75" />
                  </span>
                  <span class="notice-card__body">
                    <span class="notice-card__top">
                      <span class="notice-card__author">Phòng Nhân sự</span>
                      <span class="notice-card__badge">Mới</span>
                    </span>
                    <span class="notice-card__title">Thông báo nghỉ lễ 30/4 và 1/5</span>
                    <span class="notice-card__meta">Hôm nay</span>
                  </span>
                </span>
              </li>
            </ul>
          </section>
    </div>
  </DashboardEmployeeShellPreview>
</template>

<style scoped>
.employee-home {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  padding: var(--space-4) var(--space-4) var(--space-2);
}

.employee-home__stat-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
  margin: 0;
}

.employee-home__stat-row--single {
  grid-template-columns: 1fr;
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

.notice-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.notice-card--preview {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.notice-card--preview::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.notice-card--preview.notice-card--new::before {
  background: var(--color-tertiary);
}

.notice-card__icon {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  margin-top: 1px;
  border-radius: var(--radius-md);
}

.notice-card__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.notice-card__body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.notice-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
}

.notice-card__author {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.notice-card__badge {
  flex-shrink: 0;
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface-strong, var(--color-tertiary-surface));
  color: var(--color-tertiary);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.notice-card__title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text);
  line-height: 1.4;
}

.notice-card__meta {
  font-size: 0.75rem;
  font-weight: 400;
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
