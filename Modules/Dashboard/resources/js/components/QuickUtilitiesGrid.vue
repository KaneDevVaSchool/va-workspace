<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  /** Chỉ hiển thị (xem trước cấu hình), không điều hướng */
  preview: { type: Boolean, default: false },
  /** utility_key — viền nhấn ô đang chọn */
  activeKey: { type: String, default: null },
});

const emit = defineEmits(['select']);
</script>

<template>
  <ul class="quick-utilities" :class="{ 'quick-utilities--compact': compact }">
    <template v-if="loading">
      <li v-for="n in compact ? 4 : 8" :key="`sk-${n}`" class="quick-utilities__sk" aria-hidden="true" />
    </template>
    <template v-else>
      <li v-for="item in items" :key="item.utility_key">
        <component
          :is="preview ? 'div' : 'button'"
          :type="preview ? undefined : 'button'"
          class="quick-utilities__tile"
          :class="{
            'quick-utilities__tile--preview': preview,
            'quick-utilities__tile--active': activeKey && item.utility_key === activeKey,
            'quick-utilities__tile--maintenance': item.is_under_maintenance,
          }"
          :aria-disabled="!preview && item.is_under_maintenance ? true : undefined"
          @click="!preview && emit('select', item)"
        >
          <span v-if="item.is_under_maintenance" class="quick-utilities__badge">Bảo trì</span>
          <span class="quick-utilities__icon" :class="`quick-utilities__icon--${item.tone || 'primary'}`">
            <AppIcon :name="item.icon || 'layoutGrid'" :size="compact ? 20 : 22" :stroke-width="1.75" />
          </span>
          <span class="quick-utilities__label">{{ item.label }}</span>
        </component>
      </li>
    </template>
  </ul>
</template>

<style scoped>
.quick-utilities {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(5.75rem, 1fr));
  gap: var(--space-3);
  list-style: none;
  margin: 0;
  padding: 0;
}

.quick-utilities--compact {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-2);
  /* Trang chủ PWA: tối đa 2 hàng — giữ chỗ khi đang tải lối tắt, tránh giật layout */
  min-height: calc(2 * 4.75rem + var(--space-2));
}

.quick-utilities__tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-height: 5.5rem;
  padding: var(--space-3) var(--space-2);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  font-family: inherit;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.quick-utilities--compact .quick-utilities__tile {
  min-height: 4.75rem;
  padding: var(--space-2);
}

.quick-utilities__tile:not(.quick-utilities__tile--preview):not(.quick-utilities__tile--maintenance):hover {
  transform: translateY(-2px);
  box-shadow: inset 0 0 0 1px var(--color-border-strong, var(--color-border)), var(--shadow-md);
}

.quick-utilities__tile:not(.quick-utilities__tile--preview):not(.quick-utilities__tile--maintenance):active {
  transform: scale(0.96);
}

.quick-utilities__tile--preview {
  cursor: default;
}

.quick-utilities__tile--active {
  box-shadow:
    inset 0 0 0 2px var(--color-primary),
    var(--shadow-md);
}

.quick-utilities__tile--maintenance {
  opacity: 0.82;
}

.quick-utilities__tile--maintenance:not(.quick-utilities__tile--preview) {
  cursor: not-allowed;
}

.quick-utilities__badge {
  position: absolute;
  top: var(--space-1);
  right: var(--space-1);
  padding: 0.125rem 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-warning-tint-bg, #fef3c7);
  color: var(--color-warning, #b45309);
  font-size: 0.5625rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  line-height: 1.2;
}

.quick-utilities__icon {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
}

.quick-utilities--compact .quick-utilities__icon {
  width: 2.35rem;
  height: 2.35rem;
}

.quick-utilities__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.quick-utilities__icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.quick-utilities__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.quick-utilities__icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}

.quick-utilities__icon--umber {
  background: var(--color-umber-surface);
  color: var(--color-umber);
}

.quick-utilities__icon--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.quick-utilities__label {
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.25;
  text-align: center;
  color: var(--color-text);
}

.quick-utilities__sk {
  min-height: 5.5rem;
  border-radius: var(--radius-lg);
  background: linear-gradient(
    90deg,
    var(--color-surface-muted) 0%,
    var(--color-surface) 50%,
    var(--color-surface-muted) 100%
  );
  background-size: 200% 100%;
  animation: quick-util-shimmer 1.2s ease-in-out infinite;
}

@keyframes quick-util-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}

@media (max-width: 640px) {
  .quick-utilities:not(.quick-utilities--compact) {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
</style>
