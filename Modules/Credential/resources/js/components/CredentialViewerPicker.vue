<script setup>
//
// Autocomplete chọn nhiều người được cấp quyền xem thông tin đăng nhập —
// copy/rút gọn từ Modules/Project/resources/js/components/ProjectMemberPicker.vue
// (bỏ phần department grouping — Credential không cần nhóm theo phòng
// ban, allUsers() ở đây chỉ trả {id,name,email}, không có
// department/avatar_url). Namespace CSS riêng `cred-viewer-picker`,
// không import chéo module.
//
import { computed, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  users: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Tìm tên hoặc email' },
  emptyText: { type: String, default: '' },
  searchLabel: { type: String, default: 'Tìm người' },
  removeAriaLabel: { type: String, default: 'Bỏ người này' },
  tone: { type: String, default: 'primary' },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const inputRef = ref(null);

const selectedUsers = computed(() => {
  const byId = new Map(props.users.map((u) => [String(u.id), u]));
  return props.modelValue.map((id) => byId.get(String(id))).filter(Boolean);
});

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  const list = props.users.filter((u) => {
    if (selectedIds.value.has(String(u.id))) return false;
    if (!q) return true;
    return u.name.toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q);
  });
  return q ? list : list.slice(0, 40);
});

watch(matches, () => {
  highlighted.value = 0;
});

function pick(user) {
  if (!selectedIds.value.has(String(user.id))) {
    emit('update:modelValue', [...props.modelValue, user.id]);
  }
  query.value = '';
  open.value = true;
  highlighted.value = 0;
}

function remove(userId) {
  emit(
    'update:modelValue',
    props.modelValue.filter((id) => String(id) !== String(userId)),
  );
}

function onFocus() {
  if (props.disabled) return;
  open.value = true;
}

function onBlur() {
  open.value = false;
  highlighted.value = 0;
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    open.value = true;
    const n = matches.value.length;
    if (!n) return;
    highlighted.value = (highlighted.value + 1) % n;
    return;
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    open.value = true;
    const n = matches.value.length;
    if (!n) return;
    highlighted.value = (highlighted.value - 1 + n) % n;
    return;
  }
  if (event.key === 'Enter') {
    const item = matches.value[highlighted.value];
    if (open.value && item) {
      event.preventDefault();
      pick(item);
    }
    return;
  }
  if (event.key === 'Escape') {
    open.value = false;
  }
}

function initial(name) {
  return (name || '').trim().charAt(0).toUpperCase() || '?';
}
</script>

<template>
  <div class="cred-viewer-picker" :class="`cred-viewer-picker--${tone}`">
    <ul v-if="selectedUsers.length" class="cred-viewer-picker__picked hide-scrollbar">
      <li v-for="item in selectedUsers" :key="item.id" class="cred-viewer-picker__row">
        <span class="cred-viewer-picker__avatar" aria-hidden="true">{{ initial(item.name) }}</span>
        <span class="cred-viewer-picker__copy">
          <span class="cred-viewer-picker__name">{{ item.name }}</span>
          <span v-if="item.email" class="cred-viewer-picker__meta">{{ item.email }}</span>
        </span>
        <button
          type="button"
          class="cred-viewer-picker__remove"
          :aria-label="removeAriaLabel"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="12" :stroke-width="2.25" />
        </button>
      </li>
    </ul>
    <p v-else-if="emptyText" class="cred-viewer-picker__empty-state">{{ emptyText }}</p>

    <div class="cred-viewer-picker__search-wrap">
      <span class="cred-viewer-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="cred-viewer-picker__search"
        :aria-label="searchLabel"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
      />
      <ul v-if="open && (matches.length || query.trim())" class="cred-viewer-picker__list hide-scrollbar" role="listbox" :aria-label="searchLabel">
        <li
          v-for="(item, index) in matches"
          :key="item.id"
          class="cred-viewer-picker__option"
          :class="{ 'cred-viewer-picker__option--on': index === highlighted }"
          role="option"
          :aria-selected="index === highlighted ? 'true' : 'false'"
          @mousedown.prevent="pick(item)"
        >
          <span class="cred-viewer-picker__option-avatar" aria-hidden="true">{{ initial(item.name) }}</span>
          <span class="cred-viewer-picker__option-copy">
            <span>{{ item.name }}</span>
            <span class="cred-viewer-picker__option-meta">{{ item.email || '' }}</span>
          </span>
        </li>
        <li v-if="matches.length === 0" class="cred-viewer-picker__empty">Không tìm thấy.</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.cred-viewer-picker {
  --picker-accent: var(--color-primary);
  --picker-surface: var(--color-primary-surface);

  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.cred-viewer-picker--secondary {
  --picker-accent: var(--color-secondary);
  --picker-surface: var(--color-secondary-surface);
}

.cred-viewer-picker--tertiary {
  --picker-accent: var(--color-tertiary);
  --picker-surface: var(--color-tertiary-surface);
}

.cred-viewer-picker--gold {
  --picker-accent: var(--color-gold-600);
  --picker-surface: var(--color-gold-surface);
}

.cred-viewer-picker__picked {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  max-height: 14rem;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.cred-viewer-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.cred-viewer-picker__avatar,
.cred-viewer-picker__option-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--picker-surface);
  color: var(--picker-accent);
  font-size: 0.75rem;
  font-weight: 700;
}

.cred-viewer-picker__copy,
.cred-viewer-picker__option-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.cred-viewer-picker__name {
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.cred-viewer-picker__meta {
  overflow-wrap: anywhere;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.cred-viewer-picker__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.cred-viewer-picker__remove:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.cred-viewer-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cred-viewer-picker__empty-state {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.cred-viewer-picker__search-wrap {
  position: relative;
}

.cred-viewer-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.cred-viewer-picker__search {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.cred-viewer-picker__search::placeholder {
  color: var(--color-text-muted);
}

.cred-viewer-picker__search::-webkit-search-decoration,
.cred-viewer-picker__search::-webkit-search-cancel-button {
  -webkit-appearance: none;
}

.cred-viewer-picker__search:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.cred-viewer-picker__search:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.cred-viewer-picker__search:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.cred-viewer-picker__list {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  max-height: 14rem;
  overflow-y: auto;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12)), inset 0 0 0 1px var(--color-border);
}

.cred-viewer-picker__option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.cred-viewer-picker__option:hover,
.cred-viewer-picker__option--on {
  background: var(--picker-surface);
  color: var(--picker-accent);
}

.cred-viewer-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.cred-viewer-picker__empty {
  padding: 0.5rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
