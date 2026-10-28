<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { useColleagueSearch } from '../lib/useColleagueSearch';
import { useChatStore } from '../store/chatStore';

const store = useChatStore();
const query = ref('');
const focused = ref(false);
const { results, searching, loadingMore, setQuery, onScroll, reset } = useColleagueSearch();

watch(query, setQuery);

async function pick(user) {
  query.value = '';
  reset();
  await store.openWithUser(user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <div class="chat-search">
    <div class="chat-search__field" :class="{ 'chat-search__field--focus': focused || query.trim() !== '' }">
      <AppIcon name="search" :size="16" :stroke-width="1.75" />
      <input
        v-model="query"
        type="text"
        class="chat-search__input"
        placeholder="Tìm tên, mã hoặc email đồng nghiệp…"
        aria-label="Tìm người để trò chuyện"
        @focus="focused = true"
        @blur="focused = false"
      />
      <button
        v-if="query.trim() !== ''"
        type="button"
        class="chat-search__clear"
        aria-label="Xoá từ khoá tìm kiếm"
        @click="query = ''"
      >
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
    </div>

    <Transition name="chat-picker">
      <div v-if="query.trim() !== ''" class="chat-search__results hide-scrollbar" @scroll.passive="onScroll">
        <p v-if="searching" class="chat-search__empty">Đang tìm…</p>
        <p v-else-if="results.length === 0" class="chat-search__empty">Không tìm thấy ai phù hợp.</p>
        <button
          v-for="(user, index) in results"
          v-else
          :key="user.id"
          type="button"
          class="chat-search__item"
          :style="{ animationDelay: `${index * 40}ms` }"
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
            <span v-if="formatPeerMeta(user)" class="chat-search__item-meta">{{ formatPeerMeta(user) }}</span>
          </span>
          <AppIcon class="chat-search__chevron" name="chevronRight" :size="16" :stroke-width="1.75" />
        </button>
        <p v-if="loadingMore" class="chat-search__empty">Đang tải thêm…</p>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.chat-search {
  flex-shrink: 0;
  padding: 0 var(--space-3) var(--space-2);
}

.chat-search__field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.55rem var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    0 2px 10px color-mix(in srgb, var(--color-tertiary-900) 4%, transparent);
  transition:
    box-shadow 180ms ease,
    background 180ms ease;
}

.chat-search__field--focus {
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 45%, var(--color-border)),
    0 4px 14px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.chat-search__input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 16px;
}

.chat-search__input:focus {
  outline: none;
}

.chat-search__clear {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-search__results {
  max-height: min(24rem, 55vh);
  overflow-y: auto;
  margin-top: var(--space-2);
  padding: 0.25rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
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
  padding: 0.55rem var(--space-2);
  border-radius: var(--radius-md);
  cursor: pointer;
  font-family: inherit;
  animation: chat-list-item-in 260ms ease both;
  transition: background 120ms ease;
}

.chat-search__item:hover {
  background: var(--color-primary-surface);
}

.chat-search__avatar {
  width: 2.125rem;
  height: 2.125rem;
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
  flex: 1;
}

.chat-search__item-name {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.chat-search__item-meta {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.chat-search__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  opacity: 0.6;
}
</style>
