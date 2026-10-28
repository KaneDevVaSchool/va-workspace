<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { formatPeerMeta } from '../lib/chatPeerMeta';
import { useColleagueSearch } from '../lib/useColleagueSearch';
import { useChatStore } from '../store/chatStore';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const store = useChatStore();
const auth = useAuthStore();
const title = ref('');
const query = ref('');
const selected = ref([]);
const saving = ref(false);
const error = ref('');
const { results, searching, loadingMore, failed, setQuery, onScroll, reset } = useColleagueSearch({
  exclude: (user) => user.id === auth.user?.id || selected.value.some((item) => item.id === user.id),
});

const canSubmit = computed(
  () => !saving.value && title.value.trim() !== '' && selected.value.length > 0,
);

watch(
  () => props.open,
  (value) => {
    if (!value) return;
    title.value = '';
    query.value = '';
    reset();
    selected.value = [];
    error.value = '';
    window.addEventListener('keydown', onKeydown);
  },
);

watch(query, setQuery);

onBeforeUnmount(() => {
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
          <h2 id="chat-group-title" class="chat-group__heading">Tạo nhóm</h2>
          <button type="button" class="chat-group__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="chat-group__body hide-scrollbar">
          <form id="chat-group-form" class="chat-group__form" @submit.prevent="submit">
            <label class="chat-group__field">
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

            <div v-if="selected.length > 0" class="chat-group__chips hide-scrollbar" aria-label="Thành viên đã chọn">
              <span v-for="user in selected" :key="user.id" class="chat-group__chip">
                <span class="chat-group__chip-name">{{ user.name }}</span>
                <button
                  type="button"
                  class="chat-group__chip-remove"
                  :aria-label="`Bỏ ${user.name}`"
                  @click="removeUser(user.id)"
                >
                  <AppIcon name="close" :size="12" />
                </button>
              </span>
            </div>
            <p v-else class="chat-group__hint">Chọn ít nhất một thành viên.</p>

            <label class="chat-group__field">
              <span class="chat-group__label">Thêm thành viên</span>
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

            <div class="chat-group__list hide-scrollbar" @scroll.passive="onScroll">
              <p v-if="query.trim() === ''" class="chat-group__empty">Nhập tên để tìm đồng nghiệp.</p>
              <p v-else-if="searching" class="chat-group__empty">Đang tìm…</p>
              <p v-else-if="failed && results.length === 0" class="chat-group__empty">
                Chưa tải được danh sách đồng nghiệp. Bạn thử lại sau ít phút.
              </p>
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
              <p v-if="loadingMore" class="chat-group__empty">Đang tải thêm…</p>
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
  align-items: flex-end;
  justify-content: center;
  padding: 0;
  background: var(--color-sidebar-overlay);
}

.chat-group__panel {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 28rem;
  max-height: min(88vh, 36rem);
  overflow: hidden;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  animation: chat-group-sheet-in 220ms ease;
}

@keyframes chat-group-sheet-in {
  from {
    transform: translateY(12px);
    opacity: 0.6;
  }
}

.chat-group__head,
.chat-group__actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
}

.chat-group__head {
  box-shadow: 0 1px 0 var(--color-border);
}

.chat-group__head::before {
  content: '';
  position: absolute;
  left: 50%;
  top: 0.45rem;
  width: 2.25rem;
  height: 0.25rem;
  margin-left: -1.125rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-border) 85%, var(--color-text-muted));
}

.chat-group__head {
  position: relative;
  padding-top: 0.85rem;
}

.chat-group__heading {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
}

.chat-group__close {
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
  padding: var(--space-3) var(--space-4);
}

.chat-group__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
}

.chat-group__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.chat-group__label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.chat-group__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.chat-group__chips {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.35rem;
  overflow-x: auto;
  padding-bottom: 0.1rem;
}

.chat-group__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  max-width: 12rem;
  padding: 0.25rem 0.35rem 0.25rem 0.55rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  flex-shrink: 0;
}

.chat-group__chip-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.75rem;
  font-weight: 600;
}

.chat-group__chip-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  color: var(--color-primary);
  cursor: pointer;
  flex-shrink: 0;
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

.chat-group__input:not(.chat-group__input--plain):focus,
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

.chat-group__list {
  flex: 1;
  min-height: 8rem;
  max-height: 14rem;
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

@media (min-width: 640px) {
  .chat-group {
    align-items: center;
    padding: var(--space-5);
  }

  .chat-group__panel {
    width: min(28rem, calc(100vw - 2.5rem));
    max-height: min(36rem, calc(100vh - 2.5rem));
    border-radius: var(--radius-lg);
  }

  .chat-group__head::before {
    display: none;
  }

  .chat-group__head {
    padding-top: var(--space-3);
  }
}
</style>
