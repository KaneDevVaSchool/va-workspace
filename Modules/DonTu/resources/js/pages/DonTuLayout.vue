<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';

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
        { label: 'Đơn từ', to: { name: 'manager.don-tu.start' } },
        { label: currentTitle },
      ]"
    />

    <div class="don-tu__main">
      <router-view />
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

.don-tu__main {
  flex: 1;
  min-height: 0;
  overflow: auto;
}
</style>
