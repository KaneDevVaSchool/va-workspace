<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { searchUsers } from '@modules/Chat/resources/js/api/chatApi';

const emit = defineEmits(['select']);

const query = ref('');
const results = ref([]);
const searching = ref(false);
const inputRef = ref(null);
let timer = null;

watch(query, (value) => {
  clearTimeout(timer);
  const trimmed = value.trim();
  if (trimmed === '') {
    results.value = [];
    searching.value = false;
    return;
  }
  timer = window.setTimeout(() => runSearch(trimmed), 250);
});

async function runSearch(value) {
  searching.value = true;
  try {
    results.value = await searchUsers(value);
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}

function pick(user) {
  query.value = '';
  results.value = [];
  emit('select', user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function focus() {
  inputRef.value?.focus();
}

defineExpose({ focus });
</script>

<template>
  <div class="colleague-search">
    <label class="colleague-search__field">
      <AppIcon name="search" :size="16" />
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="colleague-search__input"
        placeholder="Tìm đồng nghiệp..."
        aria-label="Tìm đồng nghiệp để xem tường"
      />
    </label>
    <div v-if="query.trim() !== ''" class="colleague-search__results" role="listbox">
      <p v-if="searching" class="colleague-search__empty">Đang tìm…</p>
      <p v-else-if="results.length === 0" class="colleague-search__empty">Không tìm thấy ai phù hợp.</p>
      <button
        v-for="user in results"
        v-else
        :key="user.id"
        type="button"
        class="colleague-search__item"
        role="option"
        @click="pick(user)"
      >
        <img
          v-if="user.avatar_url"
          class="colleague-search__avatar"
          :src="user.avatar_url"
          :alt="`Ảnh đại diện của ${user.name}`"
        />
        <span v-else class="colleague-search__avatar colleague-search__avatar--placeholder">{{ initial(user.name) }}</span>
        <span class="colleague-search__copy">
          <span class="colleague-search__name">{{ user.name }}</span>
          <span v-if="user.department" class="colleague-search__dept">{{ user.department }}</span>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.colleague-search {
  position: relative;
}

.colleague-search__field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.4rem 0.7rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.colleague-search__input {
  width: 100%;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  outline: none;
}

.colleague-search__results {
  position: absolute;
  z-index: 5;
  top: calc(100% + 0.35rem);
  left: 0;
  right: 0;
  max-height: 16rem;
  overflow: auto;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg), inset 0 0 0 1px var(--color-border);
}

.colleague-search__empty {
  margin: 0;
  padding: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.colleague-search__item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.55rem 0.75rem;
  cursor: pointer;
  font-family: inherit;
  box-shadow: 0 1px 0 var(--color-border);
}

.colleague-search__item:hover {
  background: var(--color-surface-muted);
}

.colleague-search__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.colleague-search__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.colleague-search__copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.colleague-search__name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.colleague-search__dept {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}
</style>
