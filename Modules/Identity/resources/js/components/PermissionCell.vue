<script setup>
//
// 1 ô trong ma trận phân quyền — cấp / chưa cấp / khoá. Chấm nhỏ khi có
// chỉnh riêng. Click mở chi tiết (cha xử lý cấp/thu hồi từ panel).
//
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  cell: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
});
const emit = defineEmits(['inspect']);

function hasOverride(cell) {
  return cell.global_override !== null || cell.scoped_override !== null;
}

function cellLabel(cell) {
  if (cell.reserved) return 'Quyền hệ thống';
  if (cell.effective) return hasOverride(cell) ? 'Được cấp, có sửa riêng' : 'Được cấp';
  return hasOverride(cell) ? 'Chưa cấp, có sửa riêng' : 'Chưa cấp';
}

function onClick() {
  if (props.loading) return;
  emit('inspect');
}
</script>

<template>
  <button
    type="button"
    class="perm-cell"
    :class="{
      'perm-cell--granted': cell.effective && !cell.reserved,
      'perm-cell--denied': !cell.effective && !cell.reserved,
      'perm-cell--reserved': cell.reserved,
      'perm-cell--loading': loading,
      'perm-cell--active': active,
    }"
    :disabled="loading"
    :aria-label="cellLabel(cell)"
    :aria-pressed="active"
    @click.stop="onClick"
  >
    <span class="perm-cell__mark">
      <template v-if="loading">
        <span class="perm-cell__spinner" />
      </template>
      <AppIcon v-else-if="cell.reserved" name="lock" :size="15" />
      <AppIcon v-else-if="cell.effective" name="check" :size="16" />
    </span>
    <span v-if="hasOverride(cell) && !cell.reserved" class="perm-cell__override-dot" aria-hidden="true" />
  </button>
</template>

<style scoped>
.perm-cell {
  position: relative;
  width: 100%;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
  font-family: var(--font-family-base);
}

.perm-cell:disabled {
  cursor: not-allowed;
}

.perm-cell__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  line-height: 1;
}

.perm-cell--granted .perm-cell__mark {
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 12%, transparent);
  border-radius: var(--radius-full);
}

.perm-cell--denied .perm-cell__mark {
  box-shadow: inset 0 0 0 1.5px var(--color-border);
  border-radius: var(--radius-full);
}

.perm-cell--reserved .perm-cell__mark {
  color: var(--color-text-muted);
}

.perm-cell__override-dot {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  width: 0.375rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-info);
}

.perm-cell--active {
  box-shadow: inset 0 0 0 1.5px var(--color-primary-400);
  background: var(--color-primary-surface);
}

.perm-cell--loading {
  opacity: 0.6;
}

.perm-cell__spinner {
  width: 0.875rem;
  height: 0.875rem;
  border-radius: var(--radius-full);
  background: conic-gradient(var(--color-primary) 0deg, var(--color-primary) 90deg, var(--color-border) 90deg 360deg);
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
  animation: perm-cell-spin 0.6s linear infinite;
}

@keyframes perm-cell-spin {
  to {
    transform: rotate(360deg);
  }
}

.perm-cell:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

@media (prefers-reduced-motion: reduce) {
  .perm-cell__spinner {
    animation: none;
  }
}
</style>
