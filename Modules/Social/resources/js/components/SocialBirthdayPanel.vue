<script setup>
// Panel "Sinh nhật" — chờ nguồn dữ liệu ngày sinh nhân sự từ API HRM
// (chưa có cột date_of_birth trong bảng users, xem plans/2026-08-25-mang-noi-bo-social.md).
// Danh sách luôn rỗng ở giai đoạn này nên panel tự ẩn qua v-if trên chính
// phần tử gốc (CLAUDE.md mục 14 — không giữ chỗ rỗng chiếm layout).
import { ref } from 'vue';

const birthdays = ref([]);
</script>

<template>
  <section v-if="birthdays.length > 0" class="birthday-panel">
    <h2 class="birthday-panel__title">Sinh nhật</h2>
  </section>
</template>

<style scoped>
.birthday-panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
}

.birthday-panel__title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0;
}
</style>
