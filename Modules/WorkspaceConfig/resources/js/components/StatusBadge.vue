<script setup>
//
// Nhãn trạng thái on/off. "Đang hiện" / "Đang hoạt động" luôn xanh lá
// (--color-success*), không dùng màu brand đỏ.
//
defineProps({
  on: { type: Boolean, required: true },
  label: { type: String, required: true },
});
</script>

<template>
  <span class="status-badge" :class="on ? 'status-badge--on' : 'status-badge--off'">
    <span class="status-badge__dot" aria-hidden="true" />
    {{ label }}
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.125rem 0.625rem 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
}

.status-badge__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.status-badge--on {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.status-badge--on .status-badge__dot {
  background: var(--color-success);
}

.status-badge--off {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.status-badge--off .status-badge__dot {
  background: var(--color-text-muted);
}
</style>
