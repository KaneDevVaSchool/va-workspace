<script setup>
//
// Nhãn trạng thái on/off: chữ thường + 1 chấm màu nhỏ đứng trước, KHÔNG
// dùng badge/pill nền màu bo tròn (mục 14 CLAUDE.md). Mẫu theo
// PermissionMatrix.vue (.perm-page__dot). "Đang hiện" / "Đang hoạt động"
// luôn chấm xanh lá (--color-success), không dùng màu brand đỏ.
//
defineProps({
  on: { type: Boolean, required: true },
  label: { type: String, required: true },
});
</script>

<template>
  <span class="status-mark">
    <span class="status-mark__dot" :class="on ? 'status-mark__dot--on' : 'status-mark__dot--off'" aria-hidden="true" />
    {{ label }}
  </span>
</template>

<style scoped>
.status-mark {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  color: var(--color-text);
  font-size: inherit;
  font-weight: 400;
  line-height: 1.4;
  white-space: nowrap;
}

.status-mark__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.status-mark__dot--on {
  background: var(--color-success);
}

.status-mark__dot--off {
  background: var(--color-text-muted);
}
</style>
