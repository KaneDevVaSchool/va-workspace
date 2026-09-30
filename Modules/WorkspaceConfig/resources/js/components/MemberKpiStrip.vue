<script setup>
//
// Dải thẻ tóm tắt đầu trang nhân sự (mẫu KpiSummaryStrip bên va-hrm, dựng
// lại theo token dự án này): số lớn + nhãn + chấm icon nền nhạt. Thẻ có
// `filter` là nút bấm được để lọc nhanh; thẻ không có chỉ là số hiển thị.
// Dải màu nhận diện vẽ bằng ::before (position absolute + width), KHÔNG
// dùng border-left (mục 2 CLAUDE.md).
//
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  cards: { type: Array, required: true },
  activeFilter: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

function activate(card) {
  if (!card.filter) return;
  emit('quick-filter', card.filter);
}
</script>

<template>
  <div class="kpi-strip" aria-label="Thống kê tổng quan nhân sự workspace">
    <component
      v-for="card in cards"
      :is="card.filter ? 'button' : 'div'"
      :key="card.key"
      :type="card.filter ? 'button' : undefined"
      class="kpi-strip__card"
      :class="[
        `kpi-strip__card--${card.tone}`,
        {
          'kpi-strip__card--interactive': Boolean(card.filter),
          'kpi-strip__card--active': Boolean(card.filter) && activeFilter === card.filter,
        },
      ]"
      :aria-pressed="card.filter ? activeFilter === card.filter : undefined"
      @click="activate(card)"
    >
      <span class="kpi-strip__copy">
        <span class="kpi-strip__label">{{ card.label }}</span>
        <span class="kpi-strip__value">{{ card.value }}</span>
        <span v-if="card.sub" class="kpi-strip__sub">{{ card.sub }}</span>
      </span>
      <span class="kpi-strip__icon" aria-hidden="true">
        <AppIcon :name="card.icon" :size="18" :stroke-width="1.75" />
      </span>
    </component>
  </div>
</template>

<style scoped>
.kpi-strip {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
}

.kpi-strip__card {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  min-width: 0;
  min-height: 5.25rem;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-3) + 3px);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  overflow: hidden;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}

/* Dải màu nhận diện bên trái + vệt nền nhạt — không dùng border-left. */
.kpi-strip__card::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 3px;
  background: var(--kpi-accent, var(--color-border));
}

.kpi-strip__card::after {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 38%;
  max-width: 7rem;
  background: linear-gradient(90deg, var(--kpi-wash, transparent), transparent);
  pointer-events: none;
}

.kpi-strip__card--interactive {
  cursor: pointer;
}

.kpi-strip__card--interactive:hover {
  box-shadow: inset 0 0 0 1px var(--color-border-strong), var(--shadow-md);
}

.kpi-strip__card--active {
  box-shadow: inset 0 0 0 2px var(--kpi-accent, var(--color-primary-900)), var(--shadow-md);
}

.kpi-strip__card--brand {
  --kpi-accent: var(--color-primary-900);
  --kpi-wash: var(--color-primary-50);
}

.kpi-strip__card--info {
  --kpi-accent: var(--color-tertiary);
  --kpi-wash: var(--color-tertiary-50);
}

.kpi-strip__card--warning {
  --kpi-accent: var(--color-warning);
  --kpi-wash: var(--color-warning-tint-bg);
}

.kpi-strip__card--success {
  --kpi-accent: var(--color-success);
  --kpi-wash: var(--color-success-tint-bg);
}

.kpi-strip__card--teal {
  --kpi-accent: var(--color-secondary);
  --kpi-wash: var(--color-secondary-50);
}

.kpi-strip__copy {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.kpi-strip__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
}

.kpi-strip__value {
  color: var(--color-text);
  font-size: 1.625rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}

.kpi-strip__card--brand .kpi-strip__value {
  color: var(--color-primary-900);
}

.kpi-strip__sub {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.kpi-strip__icon {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--kpi-wash, var(--color-surface-muted));
  color: var(--kpi-accent, var(--color-text-muted));
  box-shadow: inset 0 0 0 1px
    color-mix(in srgb, var(--kpi-accent, var(--color-border)) 22%, transparent);
}

@media (max-width: 1280px) {
  .kpi-strip {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .kpi-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-2);
  }

  .kpi-strip__value {
    font-size: 1.375rem;
  }
}

@media (max-width: 480px) {
  .kpi-strip__card {
    min-height: 4.5rem;
    padding: var(--space-2) var(--space-2) var(--space-2) calc(var(--space-2) + 3px);
  }

  .kpi-strip__icon {
    width: 1.875rem;
    height: 1.875rem;
  }
}
</style>
