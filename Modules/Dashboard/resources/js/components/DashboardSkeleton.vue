<script setup>
defineProps({
  height: { type: String, default: '220px' },
  rows: { type: Number, default: 0 }, // >0 = vẽ skeleton dạng bảng (số dòng)
});
</script>

<template>
  <div v-if="rows > 0" class="skeleton skeleton--table">
    <div v-for="i in rows" :key="i" class="skeleton__row"></div>
  </div>
  <div v-else class="skeleton" :style="{ height }"></div>
</template>

<style scoped>
.skeleton {
  width: 100%;
  border-radius: var(--radius-md);
  background: linear-gradient(90deg, var(--color-surface-muted) 25%, var(--color-border) 37%, var(--color-surface-muted) 63%);
  background-size: 400% 100%;
  animation: skeleton-pulse 1.4s ease infinite;
}

.skeleton--table {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  background: none;
  animation: none;
}

.skeleton__row {
  height: 2.25rem;
  border-radius: var(--radius-sm);
  background: linear-gradient(90deg, var(--color-surface-muted) 25%, var(--color-border) 37%, var(--color-surface-muted) 63%);
  background-size: 400% 100%;
  animation: skeleton-pulse 1.4s ease infinite;
}

@keyframes skeleton-pulse {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton,
  .skeleton__row {
    animation: none;
  }
}
</style>
