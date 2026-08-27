<script setup>
//
// Autocomplete chọn phòng ban — một hoặc nhiều. Cùng cấu trúc chip +
// gõ-tìm như ProjectMemberPicker.vue.
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  departments: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  multiple: { type: Boolean, default: true },
  placeholder: { type: String, default: 'Gõ tên phòng ban để tìm…' },
  emptyText: { type: String, default: 'Chưa chọn phòng ban nào.' },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const queryFocused = ref(false);

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];
  return props.departments.filter(
    (d) =>
      !selectedIds.value.has(String(d.id)) &&
      ((d.name || '').toLowerCase().includes(q) || (d.code || '').toLowerCase().includes(q)),
  );
});

const selectedDepartments = computed(() =>
  props.departments.filter((d) => selectedIds.value.has(String(d.id))),
);

function pick(item) {
  if (props.multiple) {
    if (!selectedIds.value.has(String(item.id))) {
      emit('update:modelValue', [...props.modelValue, item.id]);
    }
  } else {
    emit('update:modelValue', [item.id]);
  }
  query.value = '';
}

function remove(departmentId) {
  emit(
    'update:modelValue',
    props.modelValue.filter((id) => String(id) !== String(departmentId)),
  );
}
</script>

<template>
  <div class="proj-dept-picker">
    <div class="proj-dept-picker__autocomplete">
      <input
        v-model="query"
        type="search"
        class="proj-page__input"
        :placeholder="placeholder"
        :disabled="disabled"
        @focus="queryFocused = true"
        @blur="queryFocused = false"
      />
      <ul v-if="queryFocused && query.trim()" class="proj-dept-picker__list hide-scrollbar" role="listbox">
        <li
          v-for="item in matches"
          :key="item.id"
          class="proj-dept-picker__option"
          @mousedown.prevent="pick(item)"
        >
          <span>{{ item.name }}</span>
          <span v-if="item.code" class="proj-dept-picker__option-meta">{{ item.code }}</span>
        </li>
        <li v-if="matches.length === 0" class="proj-dept-picker__empty">
          Không tìm thấy phòng ban khớp «{{ query }}».
        </li>
      </ul>
    </div>

    <div class="proj-dept-picker__chips">
      <span v-for="item in selectedDepartments" :key="item.id" class="proj-dept-picker__chip">
        <span>{{ item.name }}</span>
        <button
          type="button"
          class="proj-dept-picker__chip-remove"
          aria-label="Bỏ phòng ban này"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="11" />
        </button>
      </span>
      <p v-if="selectedDepartments.length === 0 && emptyText" class="proj-dept-picker__empty-state">{{ emptyText }}</p>
    </div>
  </div>
</template>

<style scoped>
.proj-dept-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-dept-picker__autocomplete {
  position: relative;
}

.proj-dept-picker__list {
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

.proj-dept-picker__option {
  display: flex;
  flex-direction: column;
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-dept-picker__option:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-dept-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-dept-picker__empty {
  padding: 0.4375rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.proj-dept-picker__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.proj-dept-picker__chip {
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

.proj-dept-picker__chip-remove {
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

.proj-dept-picker__chip-remove:hover {
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.proj-dept-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-dept-picker__empty-state {
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
