<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const screen = computed(() => ({
  title: route.meta.screenTitle || 'Đơn từ',
  ui: route.meta.ui || '',
  phase: route.meta.phase || '',
}));

/** Liên kết xem trước luồng con — không có trên sidebar. */
const QUICK_LINKS = {
  'manager.don-tu.start': [{ label: 'Soạn đơn mới', to: { name: 'manager.don-tu.compose' } }],
  'manager.don-tu.mine': [{ label: 'Mở chi tiết hồ sơ mẫu', to: { name: 'manager.don-tu.show' } }],
  'manager.don-tu.forms': [{ label: 'Cấu hình mẫu mẫu', to: { name: 'manager.don-tu.form' } }],
  'manager.don-tu.workflows': [{ label: 'Cấu hình quy trình mẫu', to: { name: 'manager.don-tu.workflow' } }],
};

const quickLinks = computed(() => QUICK_LINKS[route.name] ?? []);
</script>

<template>
  <article class="don-tu-placeholder">
    <p class="don-tu-placeholder__kicker">{{ screen.ui }} · {{ screen.phase }}</p>
    <h2 class="don-tu-placeholder__title">{{ screen.title }}</h2>
    <p class="don-tu-placeholder__text">
      Màn xem trước để đi luồng menu. Chưa có biểu mẫu, danh sách hay thao tác duyệt.
    </p>
    <p v-if="quickLinks.length" class="don-tu-placeholder__links">
      <router-link
        v-for="link in quickLinks"
        :key="link.label"
        :to="link.to"
        class="don-tu-placeholder__link"
      >
        {{ link.label }}
      </router-link>
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

.don-tu-placeholder__links {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin: var(--space-4) 0 0;
}

.don-tu-placeholder__link {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-primary);
  text-decoration: none;
}

.don-tu-placeholder__link:hover {
  text-decoration: underline;
}
</style>
