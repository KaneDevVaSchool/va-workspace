<script setup>
//
// Picker "Nhãn" cho form Dự án (mục E) — gõ để tìm nhãn có sẵn (load 1 lần
// toàn bộ, filter phía client vì số lượng nhỏ, không phân trang), nếu gõ
// tên MỚI + Enter thì tạo nhãn mới (chọn màu bằng 5 chấm tròn nhỏ hiện cạnh
// ô input khi tên gõ chưa khớp gợi ý nào) rồi gán luôn vào dự án.
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const COLORS = ['primary', 'success', 'info', 'warning', 'danger'];

const props = defineProps({
  modelValue: { type: Array, required: true }, // label ids
  labels: { type: Array, required: true }, // [{ id, name, color }] — toàn bộ nhãn hệ thống
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'created']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const queryFocused = ref(false);
const newColor = ref('primary');
const creating = ref(false);

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return [];
  return props.labels.filter(
    (l) => !selectedIds.value.has(String(l.id)) && l.name.toLowerCase().includes(q),
  );
});

const exactMatch = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return null;
  return props.labels.find((l) => l.name.toLowerCase() === q) || null;
});

const canCreateNew = computed(() => query.value.trim() !== '' && !exactMatch.value);

const selectedLabels = computed(() => props.labels.filter((l) => selectedIds.value.has(String(l.id))));

function select(labelId) {
  const id = String(labelId);
  if (!selectedIds.value.has(id)) {
    emit('update:modelValue', [...props.modelValue, labelId]);
  }
}

function remove(labelId) {
  const id = String(labelId);
  emit(
    'update:modelValue',
    props.modelValue.filter((lid) => String(lid) !== id),
  );
}

function pick(item) {
  select(item.id);
  query.value = '';
  newColor.value = 'primary';
}

async function createAndSelect() {
  const name = query.value.trim();
  if (!name || creating.value) return;

  creating.value = true;
  try {
    const { data } = await window.axios.post('/api/project/labels', { name, color: newColor.value });
    emit('created', data.label);
    select(data.label.id);
    query.value = '';
    newColor.value = 'primary';
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tạo được nhãn mới.');
  } finally {
    creating.value = false;
  }
}

function onEnter() {
  if (matches.value.length > 0) {
    pick(matches.value[0]);
  } else if (canCreateNew.value) {
    createAndSelect();
  }
}
</script>

<template>
  <div class="proj-label-picker">
    <div class="proj-label-picker__autocomplete">
      <input
        v-model="query"
        type="search"
        class="proj-page__input"
        placeholder="Gõ tên nhãn — Enter để chọn hoặc tạo mới…"
        :disabled="disabled || creating"
        @focus="queryFocused = true"
        @blur="queryFocused = false"
        @keydown.enter.prevent="onEnter"
      />

      <div v-if="queryFocused && canCreateNew" class="proj-label-picker__colors">
        <button
          v-for="color in COLORS"
          :key="color"
          type="button"
          class="proj-label-picker__color-dot"
          :class="[`proj-label-picker__color-dot--${color}`, { 'proj-label-picker__color-dot--active': newColor === color }]"
          :aria-label="`Chọn màu ${color}`"
          @mousedown.prevent="newColor = color"
        />
      </div>

      <ul v-if="queryFocused && query.trim()" class="proj-label-picker__list hide-scrollbar" role="listbox">
        <li v-for="item in matches" :key="item.id" class="proj-label-picker__option" @mousedown.prevent="pick(item)">
          <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${item.color}`" />
          <span>{{ item.name }}</span>
        </li>
        <li v-if="canCreateNew" class="proj-label-picker__option proj-label-picker__option--create" @mousedown.prevent="createAndSelect">
          <AppIcon name="plus" :size="14" />
          <span>Tạo nhãn mới «{{ query.trim() }}»</span>
        </li>
      </ul>
    </div>

    <div class="proj-label-picker__chips">
      <span v-for="item in selectedLabels" :key="item.id" class="proj-label-picker__chip">
        <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${item.color}`" />
        <span>{{ item.name }}</span>
        <button
          type="button"
          class="proj-label-picker__chip-remove"
          aria-label="Bỏ nhãn này"
          :disabled="disabled"
          @click="remove(item.id)"
        >
          <AppIcon name="close" :size="11" />
        </button>
      </span>
      <p v-if="selectedLabels.length === 0" class="proj-label-picker__hint">Chưa gán nhãn nào.</p>
    </div>
  </div>
</template>

<style scoped>
.proj-label-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-label-picker__autocomplete {
  position: relative;
}

.proj-label-picker__colors {
  position: absolute;
  z-index: 11;
  top: calc(100% + 0.25rem);
  left: 0;
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12));
}

.proj-label-picker__color-dot {
  width: 1rem;
  height: 1rem;
  padding: 0;
  border: 2px solid transparent;
  border-radius: var(--radius-full);
  cursor: pointer;
}

.proj-label-picker__color-dot--active {
  box-shadow: 0 0 0 2px var(--color-surface), 0 0 0 3px var(--color-text);
}

.proj-label-picker__color-dot--primary {
  background: var(--color-primary);
}

.proj-label-picker__color-dot--success {
  background: var(--color-success);
}

.proj-label-picker__color-dot--info {
  background: var(--color-info);
}

.proj-label-picker__color-dot--warning {
  background: var(--color-warning, var(--color-primary));
}

.proj-label-picker__color-dot--danger {
  background: var(--color-danger);
}

.proj-label-picker__list {
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

.proj-label-picker__option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.proj-label-picker__option:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-label-picker__option--create {
  color: var(--color-primary);
  font-weight: 600;
}

.proj-label-picker__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.proj-label-picker__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
  padding: 0.3125rem 0.5rem 0.3125rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-label-picker__chip-remove {
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

.proj-label-picker__chip-remove:hover {
  background: var(--color-surface-muted);
}

.proj-label-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-label-picker__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.proj-label-picker__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.proj-label-picker__dot--primary {
  background: var(--color-primary);
}

.proj-label-picker__dot--success {
  background: var(--color-success);
}

.proj-label-picker__dot--info {
  background: var(--color-info);
}

.proj-label-picker__dot--warning {
  background: var(--color-warning, var(--color-primary));
}

.proj-label-picker__dot--danger {
  background: var(--color-danger);
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
