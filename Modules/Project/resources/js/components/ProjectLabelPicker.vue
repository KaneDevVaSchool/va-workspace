<script setup>
//
// Combobox nhãn gọn — cùng mẫu ProjectMemberPicker: chip đã gắn + một ô
// tìm (placeholder) + danh sách khi focus. Gõ tên chưa có thì tạo ngay
// trong list (chọn màu trên cùng hàng), không panel phụ / nút Tạo mới.
//
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const COLORS = [
  { key: 'primary', label: 'Đỏ đô' },
  { key: 'secondary', label: 'Xanh ngọc' },
  { key: 'tertiary', label: 'Xanh dương' },
  { key: 'gold', label: 'Vàng đồng' },
  { key: 'success', label: 'Xanh lá' },
  { key: 'info', label: 'Xanh biển' },
  { key: 'warning', label: 'Cam' },
  { key: 'danger', label: 'Đỏ' },
  { key: 'violet', label: 'Tím' },
];

const props = defineProps({
  modelValue: { type: Array, required: true },
  labels: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Tìm hoặc tạo nhãn…' },
  searchLabel: { type: String, default: 'Tìm nhãn' },
  autofocus: { type: Boolean, default: false },
  alwaysOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'created']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const newColor = ref('primary');
const creating = ref(false);
const inputRef = ref(null);

const unusedLabels = computed(() => props.labels.filter((l) => !selectedIds.value.has(String(l.id))));

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return unusedLabels.value;
  return unusedLabels.value.filter((l) => l.name.toLowerCase().includes(q));
});

const selectedLabels = computed(() => {
  const byId = new Map(props.labels.map((l) => [String(l.id), l]));
  return props.modelValue.map((id) => byId.get(String(id))).filter(Boolean);
});

const queryTrimmed = computed(() => query.value.trim());

const exactMatch = computed(() => {
  const q = queryTrimmed.value.toLowerCase();
  if (!q) return null;
  return props.labels.find((l) => l.name.toLowerCase() === q) || null;
});

const canCreateNew = computed(() => queryTrimmed.value !== '' && !exactMatch.value);

const showList = computed(() => (props.alwaysOpen || open.value) && !props.disabled);

const optionCount = computed(() => matches.value.length + (canCreateNew.value ? 1 : 0));

const emptyHint = computed(() => {
  if (queryTrimmed.value && exactMatch.value && selectedIds.value.has(String(exactMatch.value.id))) {
    return 'Nhãn này đã gắn.';
  }
  if (props.labels.length === 0) return 'Chưa có nhãn. Gõ tên để tạo mới.';
  if (unusedLabels.value.length === 0) return 'Đã gắn hết nhãn. Gõ tên để tạo mới.';
  return 'Không có nhãn khớp.';
});

watch([matches, canCreateNew], () => {
  if (highlighted.value >= optionCount.value) {
    highlighted.value = Math.max(0, optionCount.value - 1);
  }
});

function select(labelId) {
  const id = String(labelId);
  if (!selectedIds.value.has(id)) {
    emit('update:modelValue', [...props.modelValue, labelId]);
  }
}

function remove(labelId) {
  emit(
    'update:modelValue',
    props.modelValue.filter((lid) => String(lid) !== String(labelId)),
  );
}

function pick(item) {
  select(item.id);
  query.value = '';
  highlighted.value = 0;
  nextTick(() => inputRef.value?.focus());
}

function onFocus() {
  if (props.disabled) return;
  open.value = true;
}

function onBlur() {
  open.value = false;
}

function activateHighlighted() {
  if (creating.value) return;
  const item = matches.value[highlighted.value];
  if (item) {
    pick(item);
    return;
  }
  if (canCreateNew.value) createAndSelect();
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    open.value = true;
    const n = optionCount.value;
    if (!n) return;
    highlighted.value = (highlighted.value + 1) % n;
    return;
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    open.value = true;
    const n = optionCount.value;
    if (!n) return;
    highlighted.value = (highlighted.value - 1 + n) % n;
    return;
  }
  if (event.key === 'Enter') {
    event.preventDefault();
    activateHighlighted();
    return;
  }
  if (event.key === 'Escape') {
    if (query.value) {
      event.stopPropagation();
      query.value = '';
      highlighted.value = 0;
      return;
    }
    open.value = false;
    return;
  }
  if (event.key === 'Backspace' && query.value === '' && selectedLabels.value.length) {
    remove(selectedLabels.value[selectedLabels.value.length - 1].id);
  }
}

async function createAndSelect() {
  const name = queryTrimmed.value;
  if (!name || creating.value || !canCreateNew.value) return;

  creating.value = true;
  try {
    const { data } = await window.axios.post('/api/project/labels', { name, color: newColor.value });
    emit('created', data.label);
    select(data.label.id);
    query.value = '';
    newColor.value = 'primary';
    highlighted.value = 0;
    await nextTick();
    inputRef.value?.focus();
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tạo được nhãn mới.');
  } finally {
    creating.value = false;
  }
}

onMounted(() => {
  if (!props.autofocus || props.disabled) return;
  open.value = true;
  nextTick(() => inputRef.value?.focus());
});
</script>

<template>
  <div class="proj-label-picker">
    <ul v-if="selectedLabels.length" class="proj-label-picker__picked">
      <li
        v-for="item in selectedLabels"
        :key="item.id"
        class="proj-label-picker__chip"
        :class="`proj-label-picker__chip--${item.color}`"
      >
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
      </li>
    </ul>

    <div class="proj-label-picker__search-wrap">
      <span class="proj-label-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        class="proj-label-picker__search"
        :aria-label="searchLabel"
        :placeholder="placeholder"
        :disabled="disabled"
        maxlength="100"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
      />
    </div>

    <ul
      v-if="showList && (optionCount || queryTrimmed || alwaysOpen)"
      class="proj-label-picker__list hide-scrollbar"
      role="listbox"
      :aria-label="searchLabel"
    >
      <li
        v-for="(item, index) in matches"
        :key="item.id"
        class="proj-label-picker__option"
        :class="{ 'proj-label-picker__option--on': index === highlighted }"
        role="option"
        :aria-selected="index === highlighted ? 'true' : 'false'"
        @mousedown.prevent="pick(item)"
      >
        <span class="proj-label-picker__chip" :class="`proj-label-picker__chip--${item.color}`">
          <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${item.color}`" />
          <span>{{ item.name }}</span>
        </span>
      </li>
      <li
        v-if="canCreateNew"
        class="proj-label-picker__create"
        :class="[
          `proj-label-picker__create--${newColor}`,
          { 'proj-label-picker__option--on': highlighted === matches.length },
          { 'proj-label-picker__create--busy': creating },
        ]"
        role="option"
        :aria-selected="highlighted === matches.length ? 'true' : 'false'"
        :aria-busy="creating ? 'true' : 'false'"
        @mousedown.prevent="createAndSelect"
      >
        <span class="proj-label-picker__create-copy">
          <AppIcon name="plus" :size="13" :stroke-width="2.25" />
          <span>{{ creating ? 'Đang tạo…' : `Tạo «${queryTrimmed}»` }}</span>
        </span>
        <div
          class="proj-label-picker__swatches"
          role="radiogroup"
          aria-label="Màu nhãn"
          @mousedown.prevent.stop
        >
          <button
            v-for="color in COLORS"
            :key="color.key"
            type="button"
            class="proj-label-picker__swatch"
            :class="[
              `proj-label-picker__swatch--${color.key}`,
              { 'proj-label-picker__swatch--on': newColor === color.key },
            ]"
            role="radio"
            :aria-label="color.label"
            :aria-checked="newColor === color.key ? 'true' : 'false'"
            :disabled="disabled || creating"
            @click.stop="newColor = color.key"
          />
        </div>
      </li>
      <li v-else-if="!matches.length" class="proj-label-picker__empty">{{ emptyHint }}</li>
    </ul>
  </div>
</template>

<style scoped>
.proj-label-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
}

.proj-label-picker__picked {
  display: flex;
  flex-wrap: wrap;
  align-content: flex-start;
  gap: 0.375rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-label-picker__search-wrap {
  position: relative;
}

.proj-label-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.proj-label-picker__search {
  width: 100%;
  min-width: 0;
  height: 2.25rem;
  padding: 0 0.75rem 0 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.proj-label-picker__search::placeholder {
  color: var(--color-text-muted);
}

.proj-label-picker__search::-webkit-search-decoration,
.proj-label-picker__search::-webkit-search-cancel-button {
  -webkit-appearance: none;
}

.proj-label-picker__search:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-label-picker__search:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-label-picker__search:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.proj-label-picker__list {
  max-height: 12rem;
  overflow-y: auto;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-label-picker__option {
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 0.3125rem 0.5rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.proj-label-picker__option:hover,
.proj-label-picker__create:hover,
.proj-label-picker__option--on {
  background: var(--color-surface-muted);
}

.proj-label-picker__empty {
  margin: 0;
  padding: 0.5rem 0.625rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.proj-label-picker__create {
  --pane-accent: var(--color-primary);

  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.625rem;
  min-width: 0;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.proj-label-picker__create-copy {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-width: 0;
  color: var(--pane-accent);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-label-picker__create--primary {
  --pane-accent: var(--color-primary);
}
.proj-label-picker__create--secondary {
  --pane-accent: var(--color-secondary);
}
.proj-label-picker__create--tertiary {
  --pane-accent: var(--color-tertiary);
}
.proj-label-picker__create--gold {
  --pane-accent: var(--color-gold);
}
.proj-label-picker__create--success {
  --pane-accent: var(--color-success);
}
.proj-label-picker__create--info {
  --pane-accent: var(--color-info);
}
.proj-label-picker__create--warning {
  --pane-accent: var(--color-warning, var(--color-primary));
}
.proj-label-picker__create--danger {
  --pane-accent: var(--color-danger);
}
.proj-label-picker__create--violet {
  --pane-accent: #7c3aed;
}

.proj-label-picker__create--busy {
  opacity: 0.7;
  pointer-events: none;
}

.proj-label-picker__swatches {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  margin-left: auto;
}

.proj-label-picker__swatch {
  width: 0.875rem;
  height: 0.875rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--swatch-accent, var(--color-text-muted));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-text) 12%, transparent);
  cursor: pointer;
}

.proj-label-picker__swatch:hover:not(:disabled) {
  transform: scale(1.12);
}

.proj-label-picker__swatch:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.proj-label-picker__swatch--on {
  box-shadow: 0 0 0 2px var(--color-surface), 0 0 0 3.5px var(--swatch-accent, var(--color-primary));
}

.proj-label-picker__swatch--primary {
  --swatch-accent: var(--color-primary);
}
.proj-label-picker__swatch--secondary {
  --swatch-accent: var(--color-secondary);
}
.proj-label-picker__swatch--tertiary {
  --swatch-accent: var(--color-tertiary);
}
.proj-label-picker__swatch--gold {
  --swatch-accent: var(--color-gold);
}
.proj-label-picker__swatch--success {
  --swatch-accent: var(--color-success);
}
.proj-label-picker__swatch--info {
  --swatch-accent: var(--color-info);
}
.proj-label-picker__swatch--warning {
  --swatch-accent: var(--color-warning, var(--color-primary));
}
.proj-label-picker__swatch--danger {
  --swatch-accent: var(--color-danger);
}
.proj-label-picker__swatch--violet {
  --swatch-accent: #7c3aed;
}

.proj-label-picker__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.5rem;
  border: none;
  border-radius: 0;
  background: var(--chip-bg, var(--color-surface-muted));
  color: var(--chip-fg, var(--color-text));
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.proj-label-picker__chip--primary {
  --chip-bg: var(--color-primary-50);
  --chip-fg: var(--color-primary-900);
  --chip-line: var(--color-primary-400);
}
.proj-label-picker__chip--secondary {
  --chip-bg: var(--color-secondary-50);
  --chip-fg: var(--color-secondary-800);
  --chip-line: var(--color-secondary-400);
}
.proj-label-picker__chip--tertiary {
  --chip-bg: var(--color-tertiary-50);
  --chip-fg: var(--color-tertiary-800);
  --chip-line: var(--color-tertiary-400);
}
.proj-label-picker__chip--gold {
  --chip-bg: var(--color-gold-50);
  --chip-fg: var(--color-gold-800);
  --chip-line: var(--color-gold-400);
}
.proj-label-picker__chip--success {
  --chip-bg: var(--color-success-tint-bg);
  --chip-fg: var(--color-success-tint-fg);
  --chip-line: var(--color-success);
}
.proj-label-picker__chip--info {
  --chip-bg: var(--color-info-tint-bg);
  --chip-fg: var(--color-info-tint-fg);
  --chip-line: var(--color-info);
}
.proj-label-picker__chip--warning {
  --chip-bg: var(--color-warning-tint-bg);
  --chip-fg: var(--color-warning-tint-fg);
  --chip-line: var(--color-warning);
}
.proj-label-picker__chip--danger {
  --chip-bg: var(--color-danger-tint-bg);
  --chip-fg: var(--color-danger-tint-fg);
  --chip-line: var(--color-danger);
}
.proj-label-picker__chip--violet {
  --chip-bg: #f3e8ff;
  --chip-fg: #5b21b6;
  --chip-line: #7c3aed;
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
  background: color-mix(in srgb, var(--chip-fg, var(--color-text)) 12%, transparent);
}

.proj-label-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-label-picker__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--chip-line, var(--color-text-muted));
}

.proj-label-picker__dot--primary {
  background: var(--color-primary);
}
.proj-label-picker__dot--secondary {
  background: var(--color-secondary);
}
.proj-label-picker__dot--tertiary {
  background: var(--color-tertiary);
}
.proj-label-picker__dot--gold {
  background: var(--color-gold);
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
.proj-label-picker__dot--violet {
  background: #7c3aed;
}
</style>
