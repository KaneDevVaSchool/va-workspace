<script setup>
//
// Autocomplete chọn 1 người — dùng cho Phụ trách chính. Mở danh sách khi
// focus, ưu tiên người thuộc phòng ban thực hiện. Mục đã chọn hiện thành
// hàng (chấm màu + tên + phòng ban), không dùng pill.
//
import { computed, nextTick, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  users: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  searchLabel: { type: String, default: 'Tìm người phụ trách' },
  placeholder: { type: String, default: '' },
  preferredDepartmentIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const inputRef = ref(null);

const selectedUser = computed(() => {
  if (props.modelValue === '' || props.modelValue == null) return null;
  return props.users.find((u) => String(u.id) === String(props.modelValue)) || null;
});

const preferredSet = computed(
  () => new Set((props.preferredDepartmentIds || []).map((id) => String(id))),
);

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  const list = props.users.filter((u) => {
    if (String(u.id) === String(props.modelValue)) return false;
    if (!q) return true;
    const dept = (u.department?.name || '').toLowerCase();
    return (
      u.name.toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q) || dept.includes(q)
    );
  });

  const preferred = [];
  const rest = [];
  for (const u of list) {
    if (u.department?.id && preferredSet.value.has(String(u.department.id))) {
      preferred.push(u);
    } else {
      rest.push(u);
    }
  }
  const ordered = [...preferred, ...rest];
  if (q) return ordered;
  return ordered.slice(0, 40);
});

watch(matches, () => {
  highlighted.value = 0;
});

function pick(user) {
  emit('update:modelValue', user.id);
  query.value = '';
  open.value = false;
}

function clear() {
  emit('update:modelValue', '');
  query.value = '';
  nextTick(() => inputRef.value?.focus());
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
  <div class="proj-user-picker">
    <div v-if="selectedUser" class="proj-user-picker__row">
      <span class="proj-user-picker__avatar" aria-hidden="true">{{ initial(selectedUser.name) }}</span>
      <span class="proj-user-picker__copy">
        <span class="proj-user-picker__name">{{ selectedUser.name }}</span>
        <span v-if="selectedUser.department?.name || selectedUser.email" class="proj-user-picker__meta">
          {{ selectedUser.department?.name || selectedUser.email }}
        </span>
      </span>
      <button
        type="button"
        class="proj-user-picker__remove"
        aria-label="Bỏ phụ trách chính"
        :disabled="disabled"
        @click="clear"
      >
        <AppIcon name="close" :size="12" :stroke-width="2.25" />
      </button>
    </div>

    <div v-else class="proj-user-picker__search-wrap">
      <span class="proj-user-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="proj-user-picker__search"
        :aria-label="searchLabel"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
      />
      <ul
        v-if="open && (matches.length || query.trim())"
        class="proj-user-picker__list hide-scrollbar"
        role="listbox"
        :aria-label="searchLabel"
      >
        <li
          v-for="(item, index) in matches"
          :key="item.id"
          class="proj-user-picker__option"
          :class="{ 'proj-user-picker__option--on': index === highlighted }"
          role="option"
          :aria-selected="index === highlighted ? 'true' : 'false'"
          @mousedown.prevent="pick(item)"
        >
          <span class="proj-user-picker__option-avatar" aria-hidden="true">{{ initial(item.name) }}</span>
          <span class="proj-user-picker__option-copy">
            <span>{{ item.name }}</span>
            <span class="proj-user-picker__option-meta">
              {{ item.department?.name || item.email || '' }}
            </span>
          </span>
        </li>
        <li v-if="matches.length === 0" class="proj-user-picker__empty">Không tìm thấy.</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.proj-user-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.proj-user-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-user-picker__avatar,
.proj-user-picker__option-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.proj-user-picker__copy,
.proj-user-picker__option-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.proj-user-picker__name {
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.proj-user-picker__meta {
  overflow-wrap: anywhere;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.proj-user-picker__remove {
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

.proj-user-picker__remove:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.proj-user-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-user-picker__search-wrap {
  position: relative;
}

.proj-user-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.proj-user-picker__search {
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

.proj-user-picker__search::placeholder {
  color: var(--color-text-muted);
}

.proj-user-picker__search::-webkit-search-decoration,
.proj-user-picker__search::-webkit-search-cancel-button {
  -webkit-appearance: none;
}

.proj-user-picker__search:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-user-picker__search:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-user-picker__search:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-user-picker__list {
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

.proj-user-picker__option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-user-picker__option:hover,
.proj-user-picker__option--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-user-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-user-picker__empty {
  padding: 0.5rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
