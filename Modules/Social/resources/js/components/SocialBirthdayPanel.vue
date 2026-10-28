<script setup>
import { computed, onMounted, ref } from 'vue';

const birthdays = ref([]);
const loading = ref(true);

function formatMonth(isoDate) {
  if (!isoDate) return '';
  const parts = String(isoDate).split('-');
  if (parts.length < 2) return '';
  const month = Number(parts[1]);
  if (!Number.isInteger(month) || month < 1 || month > 12) return '';
  return `Tháng ${month}`;
}

const groups = computed(() => {
  const buckets = [];

  for (const item of birthdays.value) {
    const label = formatMonth(item.next_birthday);
    if (!label) continue;

    const last = buckets.at(-1);
    if (last && last.label === label) {
      last.people.push(item);
    } else {
      buckets.push({ label, people: [item] });
    }
  }

  return buckets;
});

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
  <section v-if="!loading && groups.length > 0" class="birthday-panel">
    <h2 class="birthday-panel__title">Sinh nhật</h2>
    <div class="birthday-panel__groups">
      <section v-for="group in groups" :key="group.label" class="birthday-panel__group">
        <h3 class="birthday-panel__month">{{ group.label }}</h3>
        <ul class="birthday-panel__list">
          <li
            v-for="(item, index) in group.people"
            :key="`${item.next_birthday}-${item.name}-${index}`"
            class="birthday-panel__person"
          >
            <span class="birthday-panel__name">{{ item.name }}</span>
            <span v-if="item.department_name" class="birthday-panel__dept">{{ item.department_name }}</span>
          </li>
        </ul>
      </section>
    </div>
  </section>
</template>

<style scoped>
.birthday-panel {
  flex-shrink: 0;
  min-width: 0;
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

.birthday-panel__groups {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
}

.birthday-panel__group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.birthday-panel__month {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--color-primary);
}

.birthday-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.birthday-panel__person {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0.125rem;
  min-width: 0;
}

.birthday-panel__name,
.birthday-panel__dept {
  display: block;
  min-width: 0;
  max-width: 100%;
  overflow-wrap: break-word;
}

.birthday-panel__name {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--color-text);
}

.birthday-panel__dept {
  font-size: 0.75rem;
  line-height: 1.35;
  color: var(--color-text-muted);
}
</style>
