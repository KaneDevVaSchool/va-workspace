<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const screen = computed(() => ({
  title: route.meta.screenTitle || 'Đơn từ',
  ui: route.meta.ui || '',
  phase: route.meta.phase || '',
}));
</script>

<template>
  <article class="don-tu-placeholder">
    <p class="don-tu-placeholder__kicker">{{ screen.ui }} · {{ screen.phase }}</p>
    <h2 class="don-tu-placeholder__title">{{ screen.title }}</h2>
    <p class="don-tu-placeholder__text">
      Màn xem trước để đi luồng menu. Chưa có biểu mẫu, danh sách hay thao tác duyệt.
    </p>
  </article>
</template>

<style scoped>
.don-tu-placeholder {
  max-width: 36rem;
}

.don-tu-placeholder__kicker {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.don-tu-placeholder__title {
  margin: 0 0 var(--space-3);
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 600;
}

.don-tu-placeholder__text {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.9375rem;
  line-height: 1.5;
}
</style>
