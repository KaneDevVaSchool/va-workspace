<script setup>
//
// Trang Vue của module Example — nằm cạnh backend của module, giữ code
// module tự chứa (self-contained). Được import từ resources/js/router
// hoặc auto-registered theo glob khi cấu hình module routing thật.
//
</script>

<template>
  <section class="example-index">
    <h2 class="example-index__title">Example module</h2>
  </section>
</template>

<style scoped>
.example-index {
  padding: var(--space-4);
}

.example-index__title {
  color: var(--color-primary);
}
</style>
