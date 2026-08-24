<script setup>
//
// 1 ô trong ma trận phân quyền — 3 trạng thái (cấp/không cấp/khoá) + dấu ◆
// khi có override (khác "effective khác default", xem PermissionMatrix.vue).
// Dùng box-shadow cho dấu hiệu override, KHÔNG dùng border theo hướng.
//
const props = defineProps({
  cell: { type: Object, required: true }, // { default, effective, reserved, global_override, scoped_override, effective_source }
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle', 'inspect']);

function hasOverride(cell) {
  return cell.global_override !== null || cell.scoped_override !== null;
}

function onClick() {
  if (props.cell.reserved || props.loading) return;
  emit('toggle');
}

function onInspect(event) {
  if (!hasOverride(props.cell)) return;
  event.stopPropagation();
  emit('inspect');
}
</script>

<template>
  <button
    type="button"
    class="perm-cell"
    :class="{
      'perm-cell--granted': cell.effective,
      'perm-cell--denied': !cell.effective,
      'perm-cell--reserved': cell.reserved,
      'perm-cell--override': hasOverride(cell),
      'perm-cell--loading': loading,
    }"
    :disabled="cell.reserved || loading"
    :aria-label="cell.reserved ? 'Quyền hệ thống, đã khoá' : (cell.effective ? 'Đang được cấp, bấm để thu hồi' : 'Không được cấp, bấm để cấp')"
    @click="onClick"
  >
    <span class="perm-cell__mark">
      <template v-if="loading">…</template>
      <template v-else-if="cell.reserved">🔒</template>
      <template v-else-if="cell.effective">●</template>
      <template v-else>○</template>
    </span>
    <span
      v-if="hasOverride(cell) && !cell.reserved"
      class="perm-cell__override-dot"
      role="button"
      aria-label="Xem nguồn gốc override"
      @click="onInspect"
    >◆</span>
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
  font-size: 1rem;
  line-height: 1;
}

.perm-cell--granted .perm-cell__mark {
  color: var(--color-success);
}

.perm-cell--denied .perm-cell__mark {
  color: var(--color-text-muted);
}

.perm-cell--reserved .perm-cell__mark {
  color: var(--color-text-muted);
  opacity: 0.7;
}

.perm-cell--override {
  box-shadow: inset 0 0 0 1.5px var(--color-primary-300);
  border-radius: var(--radius-sm);
}

.perm-cell__override-dot {
  position: absolute;
  top: 0.125rem;
  right: 0.125rem;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-size: 0.5625rem;
  line-height: 1;
  padding: 0;
  cursor: pointer;
}

.perm-cell--loading {
  opacity: 0.6;
}

.perm-cell:hover:not(:disabled) {
  background: var(--color-surface-muted);
}
</style>
