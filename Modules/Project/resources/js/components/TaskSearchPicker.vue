<script setup>
//
// Autocomplete chọn 1 mục (dự án / công việc cha). Danh sách absolute
// ngay dưới ô nhập để không đè lên chính input.
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  disabled: { type: Boolean, default: false },
  searchLabel: { type: String, default: 'Tìm' },
  placeholder: { type: String, default: '' },
  emptyText: { type: String, default: 'Không tìm thấy.' },
  removeAriaLabel: { type: String, default: 'Bỏ chọn' },
  loadOptions: { type: Function, required: true },
  loadById: { type: Function, required: true },
  itemLabel: { type: Function, required: true },
  itemCode: { type: Function, default: () => () => '' },
  itemMeta: { type: Function, required: true },
});

const emit = defineEmits(['update:modelValue', 'update:item']);

const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const loading = ref(false);
const matches = ref([]);
const selected = ref(null);
const inputRef = ref(null);
const wrapRef = ref(null);
const listRef = ref(null);
let searchTimer = null;
let requestSeq = 0;
let blurTimer = 0;

const hasValue = computed(() => props.modelValue !== '' && props.modelValue != null);

const showList = computed(
  () => open.value && !hasValue.value && (matches.value.length || query.value.trim() || loading.value),
);

watch(
  () => props.modelValue,
  async (id) => {
    if (id === '' || id == null) {
      selected.value = null;
      emit('update:item', null);
      return;
    }
    if (selected.value && String(selected.value.id) === String(id)) return;
    try {
      const item = await props.loadById(id);
      selected.value = item;
      emit('update:item', item);
      if (!item) emit('update:modelValue', '');
    } catch {
      selected.value = null;
      emit('update:item', null);
      emit('update:modelValue', '');
    }
  },
  { immediate: true },
);

async function fetchMatches(q) {
  const seq = ++requestSeq;
  loading.value = true;
  try {
    const rows = await props.loadOptions(q);
    if (seq !== requestSeq) return;
    matches.value = Array.isArray(rows) ? rows : [];
    highlighted.value = 0;
  } catch {
    if (seq !== requestSeq) return;
    matches.value = [];
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
}

function scheduleFetch(q) {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchMatches(q), 250);
}

function pick(item) {
  selected.value = item;
  query.value = '';
  open.value = false;
  matches.value = [];
  emit('update:modelValue', item.id);
  emit('update:item', item);
}

function clear() {
  selected.value = null;
  query.value = '';
  matches.value = [];
  emit('update:modelValue', '');
  emit('update:item', null);
  nextTick(() => inputRef.value?.focus());
}

function onFocus() {
  if (props.disabled) return;
  window.clearTimeout(blurTimer);
  open.value = true;
  fetchMatches(query.value);
}

function onBlur() {
  blurTimer = window.setTimeout(() => {
    if (listRef.value?.contains(document.activeElement)) return;
    open.value = false;
    highlighted.value = 0;
  }, 0);
}

function onInput() {
  open.value = true;
  scheduleFetch(query.value);
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

function onDocPointerDown(event) {
  if (!open.value) return;
  const wrap = wrapRef.value;
  const list = listRef.value;
  if (wrap?.contains(event.target) || list?.contains(event.target)) return;
  open.value = false;
}

onMounted(() => {
  document.addEventListener('pointerdown', onDocPointerDown, true);
});

onBeforeUnmount(() => {
  clearTimeout(searchTimer);
  window.clearTimeout(blurTimer);
  document.removeEventListener('pointerdown', onDocPointerDown, true);
});
</script>

<template>
  <div class="task-search-picker">
    <div v-if="hasValue && selected" class="task-search-picker__search-wrap">
      <span class="task-search-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <span class="task-search-picker__value">
        <span class="task-search-picker__value-name">{{ itemLabel(selected) }}</span>
        <span v-if="itemCode(selected)" class="task-search-picker__value-code">{{ itemCode(selected) }}</span>
      </span>
      <button
        type="button"
        class="task-search-picker__remove"
        :aria-label="removeAriaLabel"
        :disabled="disabled"
        @click="clear"
      >
        <AppIcon name="close" :size="12" :stroke-width="2.25" />
      </button>
    </div>

    <div v-else-if="hasValue && !selected" class="task-search-picker__search-wrap">
      <span class="task-search-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <span class="task-search-picker__value-name task-search-picker__value-name--muted">Đang tải…</span>
    </div>

    <div v-else ref="wrapRef" class="task-search-picker__search-wrap">
      <span class="task-search-picker__search-icon" aria-hidden="true">
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="task-search-picker__search"
        :aria-label="searchLabel"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
        @blur="onBlur"
        @input="onInput"
        @keydown="onKeydown"
      />
      <ul
        v-if="showList"
        ref="listRef"
        class="task-search-picker__list hide-scrollbar"
        role="listbox"
        :aria-label="searchLabel"
        @mousedown.prevent
      >
        <li
          v-for="(item, index) in matches"
          :key="item.id"
          class="task-search-picker__option"
          :class="{ 'task-search-picker__option--on': index === highlighted }"
          role="option"
          :aria-selected="index === highlighted ? 'true' : 'false'"
          @mousedown.prevent="pick(item)"
        >
          <span class="task-search-picker__option-name">{{ itemLabel(item) }}</span>
          <span v-if="itemCode(item) || itemMeta(item)" class="task-search-picker__option-meta">
            <span v-if="itemCode(item)">{{ itemCode(item) }}</span>
            <span v-if="itemCode(item) && itemMeta(item)" aria-hidden="true">·</span>
            <span v-if="itemMeta(item)">{{ itemMeta(item) }}</span>
          </span>
        </li>
        <li v-if="loading && matches.length === 0" class="task-search-picker__empty">Đang tìm…</li>
        <li v-else-if="matches.length === 0" class="task-search-picker__empty">{{ emptyText }}</li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.task-search-picker {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.task-search-picker:focus-within {
  z-index: 8;
}

.task-search-picker__search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
  min-height: 2.375rem;
  padding-right: 0.375rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.task-search-picker__search-wrap:hover {
  border-color: var(--color-border-strong);
}

.task-search-picker__search-wrap:focus-within {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.task-search-picker__search-icon {
  position: absolute;
  top: 50%;
  left: 0.75rem;
  z-index: 1;
  display: inline-flex;
  color: var(--color-text-muted);
  transform: translateY(-50%);
  pointer-events: none;
}

.task-search-picker__search {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 0;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  outline: none;
}

.task-search-picker__search::placeholder {
  color: var(--color-text-muted);
}

.task-search-picker__search:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.task-search-picker__value {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  min-width: 0;
  flex: 1;
  padding: 0.5rem 0.5rem 0.5rem 2.25rem;
}

.task-search-picker__value-name {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-search-picker__value-name--muted {
  flex: 1;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  color: var(--color-text-muted);
}

.task-search-picker__value-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
  white-space: nowrap;
}

.task-search-picker__remove {
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

.task-search-picker__remove:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-danger);
}

.task-search-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.task-search-picker__list {
  position: absolute;
  z-index: 20;
  top: calc(100% + 4px);
  right: 0;
  left: 0;
  overflow-y: auto;
  max-height: 14rem;
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12)), inset 0 0 0 1px var(--color-border);
}

.task-search-picker__option {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.task-search-picker__option:hover,
.task-search-picker__option--on {
  background: var(--color-surface-muted);
}

.task-search-picker__option-name {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-search-picker__option-meta {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  min-width: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-search-picker__empty {
  padding: 0.625rem 0.75rem;
  font-size: 0.8125rem;
  font-style: italic;
  color: var(--color-text-muted);
}
</style>
