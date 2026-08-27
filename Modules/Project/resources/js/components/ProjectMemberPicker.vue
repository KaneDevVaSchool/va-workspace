<script setup>
//
// Picker "Người thực hiện" cho form Dự án — phỏng theo cấu trúc
// EvaluationPositionPicker.vue (Modules/Evaluation) nhưng đơn giản hơn:
// chỉ 1 nhóm người dùng (không chia "Chức danh"/"Phòng ban" như Position).
// Gõ để tìm trong danh sách nội bộ đã load sẵn (props.users), chọn xong
// hiện thành chip có nút xoá bên dưới ô input.
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // user ids
  users: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Gõ tên hoặc email để tìm người dùng…' },
  emptyText: { type: String, default: 'Chưa chọn người thực hiện nào.' },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const queryFocused = ref(false);

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];
  return props.users.filter(
    (u) =>
      !selectedIds.value.has(String(u.id)) &&
      (u.name.toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)),
  );
});

const selectedUsers = computed(() => props.users.filter((u) => selectedIds.value.has(String(u.id))));

function select(userId) {
  const id = String(userId);
  if (!selectedIds.value.has(id)) {
    emit('update:modelValue', [...props.modelValue, userId]);
  }
}

function remove(userId) {
  const id = String(userId);
  emit(
    'update:modelValue',
    props.modelValue.filter((uid) => String(uid) !== id),
  );
}

function pick(item) {
  select(item.id);
  query.value = '';
}
</script>

<template>
  <div class="proj-member-picker">
    <div class="proj-member-picker__autocomplete">
      <input
        v-model="query"
        type="search"
        class="proj-page__input"
        :placeholder="placeholder"
        :disabled="disabled"
        @focus="queryFocused = true"
        @blur="queryFocused = false"
      />
      <ul v-if="queryFocused && query.trim()" class="proj-member-picker__list hide-scrollbar" role="listbox">
        <li
          v-for="item in matches"
          :key="item.id"
          class="proj-member-picker__option"
          @mousedown.prevent="pick(item)"
        >
          <span>{{ item.name }}</span>
          <span v-if="item.email" class="proj-member-picker__option-email">{{ item.email }}</span>
        </li>
        <li v-if="matches.length === 0" class="proj-member-picker__empty">
          Không tìm thấy người dùng khớp «{{ query }}».
        </li>
      </ul>
    </div>

    <div class="proj-member-picker__chips">
      <span v-for="item in selectedUsers" :key="item.id" class="proj-member-picker__chip">
        <span>{{ item.name }}</span>
        <button
          type="button"
          class="proj-member-picker__chip-remove"
          aria-label="Bỏ người thực hiện này"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="11" />
        </button>
      </span>
      <p v-if="selectedUsers.length === 0 && emptyText" class="proj-member-picker__empty-state">{{ emptyText }}</p>
    </div>
  </div>
</template>

<style scoped>
.proj-member-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-member-picker__autocomplete {
  position: relative;
}

.proj-member-picker__list {
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

.proj-member-picker__option {
  display: flex;
  flex-direction: column;
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-member-picker__option:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-member-picker__option-email {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-member-picker__empty {
  padding: 0.4375rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.proj-member-picker__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.proj-member-picker__chip {
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

.proj-member-picker__chip-remove {
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

.proj-member-picker__chip-remove:hover {
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.proj-member-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-member-picker__empty-state {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
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
