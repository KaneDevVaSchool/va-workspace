<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { searchUsers } from '../api/chatApi';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const query = ref('');
const results = ref([]);
const searching = ref(false);
let debounceTimer = null;

watch(query, (value) => {
  clearTimeout(debounceTimer);
  const trimmed = value.trim();
  if (trimmed === '') {
    results.value = [];
    return;
  }
  debounceTimer = window.setTimeout(() => runSearch(trimmed), 250);
});

async function runSearch(value) {
  searching.value = true;
  try {
    results.value = await searchUsers(value);
  } finally {
    searching.value = false;
  }
}

async function pick(user) {
  query.value = '';
  results.value = [];
  await store.openWithUser(user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <div class="chat-search">
    <div class="chat-search__field">
      <AppIcon name="search" :size="16" :stroke-width="1.75" />
      <input
        v-model="query"
        type="text"
        class="chat-search__input"
        placeholder="Tìm tên đồng nghiệp..."
        aria-label="Tìm người để trò chuyện"
      />
    </div>

    <div v-if="query.trim() !== ''" class="chat-search__results">
      <p v-if="searching" class="chat-search__empty">Đang tìm…</p>
      <p v-else-if="results.length === 0" class="chat-search__empty">Không tìm thấy ai phù hợp.</p>
      <button
        v-for="user in results"
        v-else
        :key="user.id"
        type="button"
        class="chat-search__item"
        @click="pick(user)"
      >
        <img
          v-if="user.avatar_url"
          class="chat-search__avatar"
          :src="user.avatar_url"
          :alt="`Ảnh đại diện của ${user.name}`"
        />
        <span v-else class="chat-search__avatar chat-search__avatar--placeholder">{{ initial(user.name) }}</span>
        <span class="chat-search__item-body">
          <span class="chat-search__item-name">{{ user.name }}</span>
          <span v-if="user.department" class="chat-search__item-dept">{{ user.department }}</span>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.chat-search {
  flex-shrink: 0;
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-search__field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.chat-search__input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.chat-search__input:focus {
  outline: none;
}

.chat-search__results {
  max-height: 14rem;
  overflow-y: auto;
  margin-top: var(--space-2);
}

.chat-search__empty {
  margin: 0;
  padding: var(--space-3);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.chat-search__item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  padding: 0.5rem var(--space-2);
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-family: inherit;
}

.chat-search__item:hover {
  background: var(--color-surface-muted);
}

.chat-search__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.chat-search__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-search__item-body {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.chat-search__item-name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.chat-search__item-dept {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}
</style>
