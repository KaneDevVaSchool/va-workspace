<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { useColleagueSearch } from '../lib/useColleagueSearch';
import { useChatStore } from '../store/chatStore';

const listQuery = defineModel('listQuery', { type: String, default: '' });

const emit = defineEmits(['create-group']);

const store = useChatStore();
const peerQuery = ref('');
const { results, searching, loadingMore, failed, setQuery, onScroll, reset } = useColleagueSearch();

watch(peerQuery, (value) => {
  listQuery.value = value.trim();
  setQuery(value);
});

async function pick(user) {
  peerQuery.value = '';
  listQuery.value = '';
  reset();
  await store.openWithUser(user.id);
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}

function onCreateGroup() {
  emit('create-group');
}
</script>

<template>
  <div class="employee-chat-toolbar">
    <label class="employee-shell__search employee-shell__search--elevated employee-chat-toolbar__search">
      <span class="employee-shell__search-leading" aria-hidden="true">
        <AppIcon name="search" :size="18" class="employee-shell__search-icon" />
      </span>
      <input
        v-model="peerQuery"
        type="search"
        class="employee-shell__search-input"
        placeholder="Tìm hội thoại, đồng nghiệp…"
        autocomplete="off"
        enterkeyhint="search"
        aria-label="Tìm hội thoại hoặc đồng nghiệp"
      />
      <button
        v-if="peerQuery.trim() !== ''"
        type="button"
        class="employee-chat-toolbar__clear"
        aria-label="Xoá từ khoá"
        @click="peerQuery = ''"
      >
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
      <button
        type="button"
        class="employee-chat-toolbar__group"
        aria-label="Tạo nhóm"
        @click="onCreateGroup"
      >
        <AppIcon name="userPlus" :size="18" :stroke-width="1.75" />
      </button>
    </label>

    <Transition name="employee-chat-toolbar-drop">
      <div
        v-if="peerQuery.trim() !== ''"
        class="employee-chat-toolbar__drop hide-scrollbar"
        role="listbox"
        aria-label="Kết quả tìm đồng nghiệp"
        @scroll.passive="onScroll"
      >
        <p v-if="searching" class="employee-chat-toolbar__empty">Đang tìm…</p>
        <p v-else-if="failed && results.length === 0" class="employee-chat-toolbar__empty">
          Chưa tải được danh sách đồng nghiệp. Bạn thử lại sau ít phút.
        </p>
        <p v-else-if="results.length === 0" class="employee-chat-toolbar__empty">
          Không tìm thấy đồng nghiệp — danh sách hội thoại vẫn được lọc theo từ khoá.
        </p>
        <button
          v-for="(user, index) in results"
          v-else
          :key="user.id"
          type="button"
          class="employee-chat-toolbar__person"
          role="option"
          :style="{ animationDelay: `${index * 35}ms` }"
          @click="pick(user)"
        >
          <img
            v-if="user.avatar_url"
            class="employee-chat-toolbar__avatar"
            :src="user.avatar_url"
            :alt="`Ảnh đại diện của ${user.name}`"
          />
          <span v-else class="employee-chat-toolbar__avatar employee-chat-toolbar__avatar--ph">
            {{ initial(user.name) }}
          </span>
          <span class="employee-chat-toolbar__person-copy">
            <span class="employee-chat-toolbar__person-name">{{ user.name }}</span>
            <span v-if="formatPeerMeta(user)" class="employee-chat-toolbar__person-meta">
              {{ formatPeerMeta(user) }}
            </span>
          </span>
          <AppIcon name="messageCircle" :size="16" :stroke-width="1.75" />
        </button>
        <p v-if="loadingMore" class="employee-chat-toolbar__empty">Đang tải thêm…</p>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.employee-chat-toolbar {
  position: relative;
  z-index: 2;
  margin-top: 0.35rem;
}

.employee-chat-toolbar__search {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  height: 2.75rem;
  margin-top: 0;
  padding: 0 0.35rem 0 1rem;
}

.employee-chat-toolbar__clear,
.employee-chat-toolbar__group {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: inherit;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.employee-chat-toolbar__clear {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
}

.employee-chat-toolbar__group {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.employee-chat-toolbar__drop {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 0.35rem);
  max-height: min(24rem, 55vh);
  overflow-y: auto;
  padding: 0.25rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    0 12px 28px color-mix(in srgb, var(--color-tertiary-900) 14%, transparent);
}

.employee-chat-toolbar__empty {
  margin: 0;
  padding: 0.75rem 0.65rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.employee-chat-toolbar__person {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  padding: 0.45rem 0.5rem;
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  color: var(--color-text);
  animation: chat-list-item-in 260ms ease both;
}

.employee-chat-toolbar__person:hover {
  background: var(--color-primary-surface);
}

.employee-chat-toolbar__avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.employee-chat-toolbar__avatar--ph {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.employee-chat-toolbar__person-copy {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.employee-chat-toolbar__person-name {
  font-size: 0.8125rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.employee-chat-toolbar__person-meta {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.employee-chat-toolbar-drop-enter-active,
.employee-chat-toolbar-drop-leave-active {
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.employee-chat-toolbar-drop-enter-from,
.employee-chat-toolbar-drop-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
