<script setup>
//
// 1 ô trong ma trận phân quyền — 3 trạng thái (cấp/không cấp/khoá) + chấm nhỏ
// khi có chỉnh riêng (override tại scope hiện tại hoặc toàn hệ thống).
// Click ô thường = yêu cầu đổi cấp/thu hồi (cha hiện hộp thoại xác nhận).
// Click ô khoá = mở "Chi tiết quyền". Không đổi ngay, không double-click.
// Dùng box-shadow cho dấu hiệu đang chọn, KHÔNG dùng border theo hướng.
//
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  cell: { type: Object, required: true }, // { default, effective, reserved, global_override, scoped_override, effective_source }
  loading: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
});
const emit = defineEmits(['toggle', 'inspect']);

function hasOverride(cell) {
  return cell.global_override !== null || cell.scoped_override !== null;
}

function onClick() {
  if (props.cell.reserved) {
    emit('inspect');
    return;
  }
  if (props.loading) return;
  emit('toggle');
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
    :aria-label="cell.reserved ? 'Quyền hệ thống, không thể đổi. Bấm để xem chi tiết' : (cell.effective ? 'Đang được cấp. Bấm để thu hồi' : 'Không được cấp. Bấm để cấp')"
    @click="onClick"
  >
    <span class="perm-cell__mark">
      <template v-if="loading">
        <span class="perm-cell__spinner" />
      </template>
      <AppIcon v-else-if="cell.reserved" name="lock" :size="16" />
      <AppIcon v-else-if="cell.effective" name="check" :size="18" />
      <AppIcon v-else name="minus" :size="18" />
    </span>
    <span v-if="hasOverride(cell) && !cell.reserved" class="perm-cell__override-dot" aria-hidden="true" />
  </button>
</template>

<style scoped>
.perm-cell {
  position: relative;
  width: 100%;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
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
  line-height: 1;
}

.perm-cell--granted .perm-cell__mark {
  color: var(--color-success);
}

.perm-cell--denied .perm-cell__mark {
  color: var(--color-danger);
  opacity: 0.85;
}

.perm-cell--reserved .perm-cell__mark {
  color: var(--color-text-muted);
}

.perm-cell__override-dot {
  position: absolute;
  top: 0.3125rem;
  right: 0.3125rem;
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
  /* Vòng xoay dùng conic-gradient thay vì border-top-color (cấm border theo hướng, xem CLAUDE.md mục 2) */
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
</style>
