<script setup>
//
// Trang mẫu — minh hoạ dùng CSS var theme thay vì màu cứng, và border
// dùng shorthand thay vì border-left/right/top/bottom (quy tắc 1).
//
import PageHeader from '../components/PageHeader.vue';
</script>

<template>
  <section class="home">
    <PageHeader
      title="VA Workspace"
      icon="dashboard"
      description="Frontend Vue đã sẵn sàng, dùng theme CSS var chung."
      :breadcrumbs="[{ label: 'Trang chủ' }]"
    />
  </section>
</template>

<style scoped>
.home {
  padding: var(--space-6);
  max-width: 960px;
}

@media (max-width: 768px) {
  .home {
    padding: var(--space-4);
  }
}
</style>
