<script setup>
//
// Nhãn trạng thái on/off dạng chữ nhật (không pill/badge).
// "Đang hiện" / "Đang hoạt động" luôn xanh lá (--color-success*),
// không dùng màu brand đỏ.
//
defineProps({
  on: { type: Boolean, required: true },
  label: { type: String, required: true },
});
</script>

<template>
  <span class="status-mark" :class="on ? 'status-mark--on' : 'status-mark--off'">
    {{ label }}
  </span>
</template>

<style scoped>
.status-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
  padding: 0.125rem 0.5rem;
  border-radius: 0;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}

.status-mark--on {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.status-mark--off {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}
</style>
