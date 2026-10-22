<script setup>
import { onMounted, ref } from 'vue';

const birthdays = ref([]);
const loading = ref(true);

function formatDay(isoDate) {
  if (!isoDate) return '';
  const parts = isoDate.split('-');
  if (parts.length !== 3) return isoDate;
  return `${parts[2]}/${parts[1]}`;
}

onMounted(async () => {
  try {
    const { data } = await window.axios.get('/api/social/birthdays', { params: { days: 14 } });
    birthdays.value = data.birthdays ?? [];
  } catch {
    birthdays.value = [];
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <section v-if="!loading && birthdays.length > 0" class="birthday-panel">
    <h2 class="birthday-panel__title">Sinh nhật</h2>
    <ul class="birthday-panel__list">
      <li v-for="(item, index) in birthdays" :key="`${item.next_birthday}-${index}`" class="birthday-panel__item">
        <span class="birthday-panel__date">{{ formatDay(item.next_birthday) }}</span>
        <span class="birthday-panel__name">{{ item.name }}</span>
        <span v-if="item.department_name" class="birthday-panel__dept">{{ item.department_name }}</span>
      </li>
    </ul>
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
  margin: 0 0 var(--space-3);
}

.birthday-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.birthday-panel__item {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: var(--space-2);
  font-size: 0.875rem;
}

.birthday-panel__date {
  font-weight: 600;
  color: var(--color-primary);
  min-width: 3rem;
}

.birthday-panel__name {
  color: var(--color-text);
}

.birthday-panel__dept {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}
</style>
