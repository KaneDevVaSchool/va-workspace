<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import { DON_TU_GROUPS } from '../nav.js';

const route = useRoute();

const currentTitle = computed(() => route.meta.screenTitle || 'Đơn từ');
</script>

<template>
  <section class="don-tu">
    <PageHeader
      :title="currentTitle"
      subtitle="Xem trước — chưa có dữ liệu"
      icon="fileText"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Đơn từ', to: { name: 'manager.don-tu.hub' } },
        { label: currentTitle },
      ]"
    />

    <div class="don-tu__body">
      <nav class="don-tu__nav" aria-label="Mục đơn từ">
        <div v-for="group in DON_TU_GROUPS" :key="group.id" class="don-tu__group">
          <p class="don-tu__group-label">{{ group.label }}</p>
          <router-link
            v-for="item in group.items"
            :key="item.name"
            :to="{ name: item.name }"
            class="don-tu__link"
            :aria-current="route.name === item.name ? 'page' : undefined"
          >
            <span>{{ item.title }}</span>
            <span class="don-tu__meta">{{ item.phase }}</span>
          </router-link>
        </div>
      </nav>
      <div class="don-tu__main">
        <router-view />
      </div>
    </div>
  </section>
</template>

<style scoped>
.don-tu {
  height: 100%;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: var(--space-5);
}

.don-tu__body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: 16rem minmax(0, 1fr);
  gap: var(--space-5);
}

.don-tu__nav {
  min-height: 0;
  overflow: auto;
  padding-right: var(--space-2);
  box-shadow: inset -1px 0 0 var(--color-border);
}

.don-tu__group + .don-tu__group {
  margin-top: var(--space-4);
}

.don-tu__group-label {
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.don-tu__link {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.4rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  text-decoration: none;
  font-size: 0.875rem;
}

.don-tu__link:hover {
  background: var(--color-surface-muted);
}

.don-tu__link[aria-current='page'] {
  background: var(--color-surface-muted);
  font-weight: 600;
}

.don-tu__meta {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
}

.don-tu__main {
  min-width: 0;
  min-height: 0;
  overflow: auto;
}

@media (max-width: 768px) {
  .don-tu__body {
    grid-template-columns: 1fr;
  }

  .don-tu__nav {
    box-shadow: none;
    overflow: visible;
  }
}
</style>
