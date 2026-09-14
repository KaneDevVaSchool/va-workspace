<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { animateCount } from '../composables/useCountUp';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: Number, default: null }, // null = đang tải / chưa có dữ liệu
  suffix: { type: String, default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'primary' }, // primary | secondary | tertiary | gold | danger
  clickable: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
  decimals: { type: Number, default: 0 },
});

const emit = defineEmits(['click']);

const displayValue = ref(0);

function playAnimation(target) {
  if (target === null || Number.isNaN(target)) return;
  animateCount((v) => { displayValue.value = v; }, target, 700);
}

onMounted(() => playAnimation(props.value));
watch(() => props.value, (v) => playAnimation(v));

const formatted = computed(() => {
  if (props.value === null) return '—';
  return displayValue.value.toLocaleString('vi-VN', {
    minimumFractionDigits: props.decimals,
    maximumFractionDigits: props.decimals,
  });
});
</script>

<template>
  <button
    type="button"
    class="kpi-card"
    :class="[
      `kpi-card--${tone === 'danger' && !(value > 0) ? 'gold' : tone}`,
      { 'kpi-card--active': active, 'kpi-card--static': !clickable },
    ]"
    :disabled="!clickable"
    @click="clickable && emit('click')"
  >
    <span v-if="icon" class="kpi-card__icon" aria-hidden="true"><AppIcon :name="icon" /></span>
    <span class="kpi-card__body">
      <span class="kpi-card__value">{{ formatted }}<span v-if="suffix" class="kpi-card__suffix">{{ suffix }}</span></span>
      <span class="kpi-card__label">{{ label }}</span>
    </span>
  </button>
</template>

<style scoped>
.kpi-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-4) calc(var(--space-4) + 0.75rem);
  background: var(--kpi-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
  font-family: inherit;
  width: 100%;
  overflow: hidden;
  clip-path: polygon(0.9rem 0%, 100% 0%, calc(100% - 0.9rem) 100%, 0% 100%);
  animation: kpi-card-reveal 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* Dải màu đậm bên trái, mô phỏng "viền trái" mà không dùng border-left (cấm — xem CLAUDE.md mục 2). */
.kpi-card::before {
  content: '';
  position: absolute;
  inset: 0;
  width: 0.5rem;
  background: var(--kpi-accent);
}

.kpi-card:nth-child(2) { animation-delay: 0.06s; }
.kpi-card:nth-child(3) { animation-delay: 0.12s; }
.kpi-card:nth-child(4) { animation-delay: 0.18s; }
.kpi-card:nth-child(n+5) { animation-delay: 0.24s; }

@keyframes kpi-card-reveal {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .kpi-card {
    animation: none;
  }
}

.kpi-card--static {
  cursor: default;
}

.kpi-card:not(.kpi-card--static):hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.kpi-card--active {
  outline: 2px solid var(--kpi-accent);
  outline-offset: -2px;
}

.kpi-card--primary { --kpi-surface: var(--color-primary-surface); --kpi-accent: var(--color-primary); --kpi-on: var(--color-primary-900); }
.kpi-card--secondary { --kpi-surface: var(--color-secondary-surface); --kpi-accent: var(--color-secondary); --kpi-on: var(--color-secondary-800); }
.kpi-card--tertiary { --kpi-surface: var(--color-tertiary-surface); --kpi-accent: var(--color-tertiary); --kpi-on: var(--color-tertiary-800); }
.kpi-card--gold { --kpi-surface: var(--color-gold-surface); --kpi-accent: var(--color-gold); --kpi-on: var(--color-gold-800); }
.kpi-card--danger { --kpi-surface: var(--color-danger-tint-bg); --kpi-accent: var(--color-danger); --kpi-on: var(--color-danger); }

.kpi-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--kpi-accent);
  box-shadow: var(--shadow-sm);
  flex-shrink: 0;
}

.kpi-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.kpi-card__value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--kpi-on);
  line-height: 1.2;
}

.kpi-card__suffix {
  font-size: 1rem;
  font-weight: 500;
  margin-left: 2px;
}

.kpi-card__label {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

@media (max-width: 480px) {
  .kpi-card__value {
    font-size: 1.25rem;
  }
}
</style>
