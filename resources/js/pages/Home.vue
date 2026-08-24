<script setup>
//
// Trang mẫu — minh hoạ dùng CSS var theme thay vì màu cứng, và border
// dùng shorthand thay vì border-left/right/top/bottom (quy tắc 1).
//
</script>

<template>
  <section class="home">
    <h1 class="home__title">VA Workspace</h1>
    <p class="home__subtitle">Frontend Vue đã sẵn sàng, dùng theme CSS var chung.</p>
  </section>
</template>

<style scoped>
.home {
  padding: var(--space-6);
  max-width: 720px;
  margin: 0 auto;
}

.home__title {
  color: var(--color-primary);
  font-weight: 700;
  /* Đúng quy tắc: border shorthand, không dùng border-bottom riêng lẻ */
  border: 0 solid var(--color-primary-200);
  box-shadow: 0 2px 0 var(--color-primary-200);
  padding-bottom: var(--space-2);
}

.home__subtitle {
  color: var(--color-text-muted);
}

@media (max-width: 768px) {
  .home {
    padding: var(--space-4);
  }
}
</style>
