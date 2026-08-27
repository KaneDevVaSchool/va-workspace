<script setup>
//
// Autocomplete chọn 1 người — dùng cho Phụ trách chính. Hiển thị
// "Tên — Phòng ban" trong danh sách gợi ý và chip đã chọn.
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  users: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Gõ tên hoặc email để tìm người phụ trách…' },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const queryFocused = ref(false);

const selectedUser = computed(() => {
  if (props.modelValue === '' || props.modelValue == null) return null;
  return props.users.find((u) => String(u.id) === String(props.modelValue)) || null;
});

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];
  return props.users.filter((u) => {
    if (String(u.id) === String(props.modelValue)) return false;
    const dept = (u.department?.name || '').toLowerCase();
    return (
      u.name.toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      dept.includes(q)
    );
  });
});

function userLine(user) {
  const dept = user.department?.name;
  return dept ? `${user.name} — ${dept}` : user.name;
}

function pick(user) {
  emit('update:modelValue', user.id);
  query.value = '';
}

function clear() {
  emit('update:modelValue', '');
}
</script>

<template>
  <div class="proj-user-picker">
    <div class="proj-user-picker__autocomplete">
      <input
        v-model="query"
        type="search"
        class="proj-page__input"
        :placeholder="placeholder"
        :disabled="disabled"
        @focus="queryFocused = true"
        @blur="queryFocused = false"
      />
      <ul v-if="queryFocused && query.trim()" class="proj-user-picker__list hide-scrollbar" role="listbox">
        <li
          v-for="item in matches"
          :key="item.id"
          class="proj-user-picker__option"
          @mousedown.prevent="pick(item)"
        >
          <span>{{ item.name }}</span>
          <span class="proj-user-picker__option-meta">
            {{ item.department?.name || item.email || '' }}
          </span>
        </li>
        <li v-if="matches.length === 0" class="proj-user-picker__empty">
          Không tìm thấy người dùng khớp «{{ query }}».
        </li>
      </ul>
    </div>

    <div v-if="selectedUser" class="proj-user-picker__chips">
      <span class="proj-user-picker__chip">
        <span>{{ userLine(selectedUser) }}</span>
        <button
          type="button"
          class="proj-user-picker__chip-remove"
          aria-label="Bỏ phụ trách chính"
          :disabled="disabled"
          @click="clear"
        >
          <AppIcon name="close" :size="11" />
        </button>
      </span>
    </div>
  </div>
</template>

<style scoped>
.proj-user-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-user-picker__autocomplete {
  position: relative;
}

.proj-user-picker__list {
  position: absolute;
  z-index: 10;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  max-height: 12rem;
  overflow-y: auto;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12));
}

.proj-user-picker__option {
  display: flex;
  flex-direction: column;
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-user-picker__option:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-user-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-user-picker__empty {
  padding: 0.4375rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.proj-user-picker__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.proj-user-picker__chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.375rem 0.625rem 0.375rem 0.75rem;
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-user-picker__chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.proj-user-picker__chip-remove:hover {
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.proj-user-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-page__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  width: 100%;
}

.proj-page__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}
</style>
