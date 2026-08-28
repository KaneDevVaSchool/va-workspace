<script setup>
//
// Autocomplete chọn phòng ban — một hoặc nhiều. Mở danh sách khi focus
// (không bắt buộc gõ), hỗ trợ mũi tên / Enter. Mục đã chọn hiện thành
// hàng (chấm màu + tên), không dùng pill.
//
import { computed, nextTick, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  departments: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  multiple: { type: Boolean, default: true },
  searchLabel: { type: String, default: 'Tìm phòng ban' },
  placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const inputRef = ref(null);

const selectedDepartments = computed(() =>
  props.departments.filter((d) => selectedIds.value.has(String(d.id))),
);

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  const list = props.departments.filter((d) => {
    if (selectedIds.value.has(String(d.id))) return false;
    if (!q) return true;
    return (d.name || '').toLowerCase().includes(q) || (d.code || '').toLowerCase().includes(q);
  });
  if (q) return list;
  return list.slice(0, 40);
});

const showSearch = computed(() => props.multiple || selectedDepartments.value.length === 0);

watch(matches, () => {
  highlighted.value = 0;
});

function pick(item) {
  if (props.multiple) {
    if (!selectedIds.value.has(String(item.id))) {
      emit('update:modelValue', [...props.modelValue, item.id]);
    }
  } else {
    emit('update:modelValue', [item.id]);
  }
  query.value = '';
  open.value = props.multiple;
  highlighted.value = 0;
}

function remove(departmentId) {
  emit(
    'update:modelValue',
    props.modelValue.filter((id) => String(id) !== String(departmentId)),
  );
  if (!props.multiple) {
    nextTick(() => inputRef.value?.focus());
  }
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
</script>

<template>
  <div class="proj-dept-picker">
    <ul v-if="selectedDepartments.length" class="proj-dept-picker__picked">
      <li v-for="item in selectedDepartments" :key="item.id" class="proj-dept-picker__row">
        <span class="proj-dept-picker__dot" aria-hidden="true" />
        <span class="proj-dept-picker__name">{{ item.name }}</span>
        <span v-if="item.code" class="proj-dept-picker__meta">{{ item.code }}</span>
        <button
          type="button"
          class="proj-dept-picker__remove"
          aria-label="Bỏ phòng ban này"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="12" :stroke-width="2.25" />
        </button>
      </li>
    </ul>

    <div v-if="showSearch" class="proj-dept-picker__search-wrap">
      <span class="proj-dept-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="proj-dept-picker__search"
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
        class="proj-dept-picker__list hide-scrollbar"
        role="listbox"
        :aria-label="searchLabel"
      >
        <li
          v-for="(item, index) in matches"
          :key="item.id"
          class="proj-dept-picker__option"
          :class="{ 'proj-dept-picker__option--on': index === highlighted }"
          role="option"
          :aria-selected="index === highlighted ? 'true' : 'false'"
          @mousedown.prevent="pick(item)"
        >
          <span>{{ item.name }}</span>
          <span v-if="item.code" class="proj-dept-picker__option-meta">{{ item.code }}</span>
        </li>
        <li v-if="matches.length === 0" class="proj-dept-picker__empty">Không tìm thấy.</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.proj-dept-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.proj-dept-picker__picked {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-dept-picker__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.5rem 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-dept-picker__dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-gold-600);
}

.proj-dept-picker__name {
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.proj-dept-picker__meta {
  margin-left: auto;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-dept-picker__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  margin-left: var(--space-1);
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.proj-dept-picker__remove:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.proj-dept-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-dept-picker__search-wrap {
  position: relative;
}

.proj-dept-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.proj-dept-picker__search {
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

.proj-dept-picker__search::placeholder {
  color: var(--color-text-muted);
}

.proj-dept-picker__search::-webkit-search-decoration,
.proj-dept-picker__search::-webkit-search-cancel-button {
  -webkit-appearance: none;
}

.proj-dept-picker__search:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-dept-picker__search:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-dept-picker__search:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-dept-picker__list {
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

.proj-dept-picker__option {
  display: flex;
  flex-direction: column;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-dept-picker__option:hover,
.proj-dept-picker__option--on {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}

.proj-dept-picker__option-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-dept-picker__empty {
  padding: 0.5rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}
</style>
