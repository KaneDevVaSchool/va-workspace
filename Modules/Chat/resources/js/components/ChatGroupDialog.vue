<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { searchUsers } from '../api/chatApi';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const store = useChatStore();
const auth = useAuthStore();
const title = ref('');
const query = ref('');
const results = ref([]);
const selected = ref([]);
const searching = ref(false);
const saving = ref(false);
const error = ref('');
let debounceTimer = null;

const canSubmit = computed(
  () => !saving.value && title.value.trim() !== '' && selected.value.length > 0,
);

watch(
  () => props.open,
  (value) => {
    if (!value) return;
    title.value = '';
    query.value = '';
    results.value = [];
    selected.value = [];
    error.value = '';
    window.addEventListener('keydown', onKeydown);
  },
);

watch(query, (value) => {
  clearTimeout(debounceTimer);
  const trimmed = value.trim();
  if (trimmed === '') {
    results.value = [];
    return;
  }
  debounceTimer = window.setTimeout(() => runSearch(trimmed), 250);
});

onBeforeUnmount(() => {
  clearTimeout(debounceTimer);
  window.removeEventListener('keydown', onKeydown);
});

function close() {
  if (saving.value) return;
  window.removeEventListener('keydown', onKeydown);
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape') close();
}

async function runSearch(value) {
  searching.value = true;
  try {
    const users = await searchUsers(value);
    const taken = new Set(selected.value.map((user) => user.id));
    results.value = users.filter((user) => user.id !== auth.user?.id && !taken.has(user.id));
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}

function addUser(user) {
  if (selected.value.some((item) => item.id === user.id)) return;
  if (selected.value.length >= 49) {
    error.value = 'Nhóm chỉ có tối đa 50 thành viên.';
    return;
  }
  selected.value = [...selected.value, user];
  results.value = results.value.filter((item) => item.id !== user.id);
  error.value = '';
}

function removeUser(id) {
  selected.value = selected.value.filter((user) => user.id !== id);
}

async function submit() {
  if (!canSubmit.value) return;
  saving.value = true;
  error.value = '';
  try {
    await store.createGroup(
      title.value.trim(),
      selected.value.map((user) => user.id),
    );
    saving.value = false;
    close();
  } catch (err) {
    const data = err?.response?.data;
    error.value = data?.errors?.title?.[0]
      || data?.errors?.user_ids?.[0]
      || data?.message
      || 'Không tạo được nhóm.';
  } finally {
    saving.value = false;
  }
}

function initial(name) {
  return name?.trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="chat-group" role="presentation" @mousedown.self="close">
      <div
        class="chat-group__panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="chat-group-title"
      >
        <div class="chat-group__head">
          <span class="chat-group__icon" aria-hidden="true">
            <AppIcon name="users" :size="22" :stroke-width="1.75" />
          </span>
          <h2 id="chat-group-title" class="chat-group__heading">Tạo nhóm</h2>
          <button type="button" class="chat-group__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="chat-group__body hide-scrollbar">
          <form id="chat-group-form" class="chat-group__grid" @submit.prevent="submit">
            <label class="chat-group__field chat-group__field--wide">
              <span class="chat-group__label">Tên nhóm</span>
              <input
                v-model="title"
                class="chat-group__input"
                type="text"
                maxlength="80"
                autocomplete="off"
                placeholder="Ví dụ: Tổ chuyên môn"
                aria-label="Tên nhóm"
              />
            </label>

            <label class="chat-group__field chat-group__field--wide">
              <span class="chat-group__label">Thành viên</span>
              <span class="chat-group__search">
                <AppIcon name="search" :size="16" :stroke-width="1.75" />
                <input
                  v-model="query"
                  class="chat-group__input chat-group__input--plain"
                  type="text"
                  placeholder="Tìm tên, mã hoặc email"
                  aria-label="Tìm thành viên"
                />
              </span>
            </label>

            <div class="chat-group__column">
              <p class="chat-group__column-label">Kết quả</p>
              <div class="chat-group__list hide-scrollbar">
                <p v-if="query.trim() === ''" class="chat-group__empty">Nhập tên để tìm đồng nghiệp.</p>
                <p v-else-if="searching" class="chat-group__empty">Đang tìm…</p>
                <p v-else-if="results.length === 0" class="chat-group__empty">Không tìm thấy ai phù hợp.</p>
                <button
                  v-for="user in results"
                  :key="user.id"
                  type="button"
                  class="chat-group__person"
                  @click="addUser(user)"
                >
                  <img
                    v-if="user.avatar_url"
                    class="chat-group__avatar"
                    :src="user.avatar_url"
                    :alt="`Ảnh đại diện của ${user.name}`"
                  />
                  <span v-else class="chat-group__avatar">{{ initial(user.name) }}</span>
                  <span class="chat-group__person-copy">
                    <span class="chat-group__person-name">{{ user.name }}</span>
                    <span v-if="formatPeerMeta(user)" class="chat-group__person-meta">{{ formatPeerMeta(user) }}</span>
                  </span>
                  <AppIcon name="plus" :size="16" :stroke-width="1.75" />
                </button>
              </div>
            </div>

            <div class="chat-group__column">
              <p class="chat-group__column-label">Đã chọn ({{ selected.length }})</p>
              <div class="chat-group__list hide-scrollbar">
                <p v-if="selected.length === 0" class="chat-group__empty">Chưa chọn thành viên.</p>
                <div v-for="user in selected" :key="user.id" class="chat-group__person chat-group__person--static">
                  <img
                    v-if="user.avatar_url"
                    class="chat-group__avatar"
                    :src="user.avatar_url"
                    :alt="`Ảnh đại diện của ${user.name}`"
                  />
                  <span v-else class="chat-group__avatar">{{ initial(user.name) }}</span>
                  <span class="chat-group__person-copy">
                    <span class="chat-group__person-name">{{ user.name }}</span>
                    <span v-if="formatPeerMeta(user)" class="chat-group__person-meta">{{ formatPeerMeta(user) }}</span>
                  </span>
                  <button type="button" class="chat-group__remove" :aria-label="`Bỏ ${user.name}`" @click="removeUser(user.id)">
                    <AppIcon name="close" :size="14" />
                  </button>
                </div>
              </div>
            </div>
          </form>
          <p v-if="error" class="chat-group__error" role="alert">{{ error }}</p>
        </div>

        <div class="chat-group__actions">
          <button type="button" class="chat-group__btn chat-group__btn--ghost" :disabled="saving" @click="close">
            Huỷ
          </button>
          <button type="submit" form="chat-group-form" class="chat-group__btn chat-group__btn--primary" :disabled="!canSubmit">
            {{ saving ? 'Đang tạo…' : 'Tạo nhóm' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.chat-group {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.625rem;
  background: var(--color-sidebar-overlay);
}

.chat-group__panel {
  display: flex;
  flex-direction: column;
  width: calc(100vw - 2.5rem);
  max-width: calc(100vw - 2.5rem);
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.chat-group__head,
.chat-group__actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
}

.chat-group__head {
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-group__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.chat-group__heading {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
}

.chat-group__close,
.chat-group__remove {
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.chat-group__close {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
}

.chat-group__close:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.chat-group__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-4) var(--space-5);
}

.chat-group__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.chat-group__field--wide {
  grid-column: 1 / -1;
}

.chat-group__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.chat-group__label,
.chat-group__column-label {
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-group__column-label {
  margin: 0 0 0.35rem;
}

.chat-group__input,
.chat-group__search {
  width: 100%;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 16px;
}

.chat-group__input {
  padding: 0.65rem 0.75rem;
}

.chat-group__input:focus,
.chat-group__search:focus-within {
  outline: 2px solid var(--color-primary);
  outline-offset: -1px;
}

.chat-group__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0 0.75rem;
  color: var(--color-text-muted);
}

.chat-group__input--plain {
  flex: 1;
  min-width: 0;
  padding: 0.65rem 0;
  background: transparent;
  outline: none;
}

.chat-group__column {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.chat-group__list {
  min-height: 12rem;
  max-height: 22rem;
  overflow: auto;
  padding: 0.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.chat-group__empty {
  margin: 0;
  padding: 0.875rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.chat-group__person {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  text-align: left;
  padding: 0.45rem 0.5rem;
  cursor: pointer;
  font-family: inherit;
}

.chat-group__person:hover {
  background: var(--color-surface-muted);
}

.chat-group__person--static {
  cursor: default;
}

.chat-group__person--static:hover {
  background: transparent;
}

.chat-group__avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.chat-group__person-copy {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chat-group__person-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  font-weight: 600;
}

.chat-group__person-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.chat-group__remove {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.chat-group__error {
  margin: var(--space-3) 0 0;
  color: var(--color-danger-tint-fg);
  font-size: 0.8125rem;
}

.chat-group__actions {
  justify-content: flex-end;
  box-shadow: 0 -1px 0 var(--color-border);
}

.chat-group__btn {
  border: none;
  border-radius: var(--radius-md);
  padding: 0.6rem 1rem;
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}

.chat-group__btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.chat-group__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.chat-group__btn--primary {
  background: linear-gradient(145deg, var(--color-primary), var(--color-tertiary-600));
  color: var(--color-on-primary);
}

@media (max-width: 720px) {
  .chat-group__panel {
    width: calc(100vw - 1.25rem);
    max-width: calc(100vw - 1.25rem);
    height: calc(100vh - 1.25rem);
    max-height: calc(100vh - 1.25rem);
  }

  .chat-group__grid {
    grid-template-columns: 1fr;
  }

  .chat-group__head,
  .chat-group__actions,
  .chat-group__body {
    padding-left: var(--space-3);
    padding-right: var(--space-3);
  }
}
</style>
