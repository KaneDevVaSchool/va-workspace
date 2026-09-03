<script setup>
//
// Select có mô tả dưới mỗi option — dùng cho cách tính tiến độ (tiêu đề
// + ví dụ in nghiêng), không dùng native <select> vì option không hiện
// được 2 dòng.
//
// autocomplete: gõ mới hiện gợi ý ngay dưới input (không teleport, phù hợp modal).
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Chọn…' },
  clearable: { type: Boolean, default: false },
  labelledBy: { type: String, default: '' },
  searchable: { type: Boolean, default: false },
  autocomplete: { type: Boolean, default: false },
  minQuery: { type: Number, default: 1 },
  listZIndex: { type: Number, default: 1600 },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const highlighted = ref(0);
const query = ref('');
const root = ref(null);
const panelRef = ref(null);
const filterRef = ref(null);
const inputRef = ref(null);
const listRef = ref(null);
const panelStyle = ref({});
const listId = useId();

const selected = computed(
  () => props.options.find((opt) => String(opt.value) === String(props.modelValue)) || null,
);

function foldSearch(text) {
  return String(text ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase();
}

const autocompleteNeedle = computed(() => {
  const q = foldSearch(query.value).trim();
  if (!q) return '';
  const sel = selected.value?.label ?? '';
  if (q === foldSearch(sel).trim()) return '';
  return q;
});

const autocompleteActive = computed(
  () => props.autocomplete && autocompleteNeedle.value.length >= props.minQuery,
);

const autocompleteOptions = computed(() => {
  if (!autocompleteActive.value) return [];
  const needle = autocompleteNeedle.value;
  return props.options.filter((opt) => {
    const hay = `${opt.label ?? ''} ${opt.description ?? ''}`;
    return foldSearch(hay).includes(needle);
  });
});

const visibleOptions = computed(() => {
  if (props.autocomplete) return autocompleteOptions.value;
  if (!props.searchable) return props.options;
  const needle = foldSearch(query.value).trim();
  if (!needle) return props.options;
  return props.options.filter((opt) => {
    const hay = `${opt.label ?? ''} ${opt.description ?? ''}`;
    return foldSearch(hay).includes(needle);
  });
});

function syncQueryFromValue() {
  query.value = selected.value?.label ?? '';
}

watch(
  () => props.modelValue,
  () => {
    if (props.autocomplete) {
      if (!autocompleteActive.value) syncQueryFromValue();
      return;
    }
    const idx = visibleOptions.value.findIndex((opt) => String(opt.value) === String(props.modelValue));
    highlighted.value = idx >= 0 ? idx : 0;
  },
  { immediate: true },
);

watch(
  () => props.options,
  () => {
    if (props.autocomplete && !autocompleteActive.value) syncQueryFromValue();
  },
);

watch(visibleOptions, (list) => {
  if (highlighted.value >= list.length) highlighted.value = Math.max(0, list.length - 1);
});

watch(open, async (isOpen) => {
  if (props.autocomplete) return;
  if (!isOpen) {
    query.value = '';
    return;
  }
  await nextTick();
  placePanel();
  if (props.searchable) filterRef.value?.focus();
});

function placePanel() {
  const el = root.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const pad = 8;
  const gap = 4;
  const maxH = Math.min(22 * 16, window.innerHeight - pad * 2);
  const spaceBelow = window.innerHeight - rect.bottom - pad;
  const openUp = spaceBelow < 12 * 16 && rect.top > spaceBelow;
  panelStyle.value = {
    position: 'fixed',
    left: `${rect.left}px`,
    width: `${Math.max(rect.width, 12 * 16)}px`,
    maxHeight: `${maxH}px`,
    zIndex: props.listZIndex,
    ...(openUp
      ? { bottom: `${window.innerHeight - rect.top + gap}px`, top: 'auto' }
      : { top: `${rect.bottom + gap}px`, bottom: 'auto' }),
  };
}

function toggle() {
  if (props.disabled) return;
  open.value = !open.value;
}

function pick(opt) {
  emit('update:modelValue', opt.value);
  if (props.autocomplete) {
    query.value = opt.label ?? '';
    highlighted.value = 0;
    return;
  }
  open.value = false;
}

function clearSelection(event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  if (props.disabled) return;
  emit('update:modelValue', '');
  query.value = '';
  highlighted.value = 0;
  if (!props.autocomplete) open.value = false;
  else nextTick(() => inputRef.value?.focus());
}

function onAutocompleteInput() {
  if (props.disabled) return;
  if (selected.value && query.value !== (selected.value.label ?? '')) {
    emit('update:modelValue', '');
  }
  highlighted.value = 0;
}

function scrollHighlightIntoView() {
  const option = listRef.value?.querySelector(`[data-index="${highlighted.value}"]`);
  option?.scrollIntoView({ block: 'nearest' });
}

function onDocPointer(event) {
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
  if (path.includes(root.value) || path.includes(panelRef.value)) return;
  if (root.value?.contains(event.target) || panelRef.value?.contains(event.target)) return;
  open.value = false;
}

function onFilterInput(event) {
  if (props.disabled) return;
  query.value = event.target.value;
  highlighted.value = 0;
}

function onKeydown(event) {
  if (props.disabled) return;

  if (props.autocomplete) {
    if (!autocompleteActive.value) return;
    const list = autocompleteOptions.value;
    if (event.key === 'Escape') {
      syncQueryFromValue();
      highlighted.value = 0;
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const n = list.length;
      if (!n) return;
      const dir = event.key === 'ArrowDown' ? 1 : -1;
      highlighted.value = (highlighted.value + dir + n) % n;
      nextTick(scrollHighlightIntoView);
      return;
    }
    if (event.key === 'Enter') {
      const item = list[highlighted.value];
      if (!item) return;
      event.preventDefault();
      pick(item);
    }
    return;
  }

  if (event.key === 'Escape') {
    open.value = false;
    return;
  }
  const list = visibleOptions.value;
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    open.value = true;
    const n = list.length;
    if (!n) return;
    const dir = event.key === 'ArrowDown' ? 1 : -1;
    highlighted.value = (highlighted.value + dir + n) % n;
    return;
  }
  if (event.key === 'Enter' && open.value) {
    event.preventDefault();
    const item = list[highlighted.value];
    if (item) pick(item);
  }
}

onMounted(() => {
  if (props.autocomplete) {
    syncQueryFromValue();
    return;
  }
  document.addEventListener('mousedown', onDocPointer);
  window.addEventListener('resize', placePanel);
  window.addEventListener('scroll', placePanel, true);
});
onBeforeUnmount(() => {
  if (props.autocomplete) return;
  document.removeEventListener('mousedown', onDocPointer);
  window.removeEventListener('resize', placePanel);
  window.removeEventListener('scroll', placePanel, true);
});
</script>

<template>
  <div
    v-if="autocomplete"
    ref="root"
    class="opt-picker opt-picker--autocomplete"
    :class="{
      'opt-picker--open': autocompleteActive,
      'opt-picker--disabled': disabled,
    }"
  >
    <div class="opt-picker__ac-control" :class="{ 'opt-picker__ac-control--open': autocompleteActive }">
      <AppIcon name="search" :size="16" :stroke-width="2" class="opt-picker__ac-icon" aria-hidden="true" />
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="opt-picker__ac-input"
        role="combobox"
        autocomplete="off"
        spellcheck="false"
        aria-autocomplete="list"
        :aria-labelledby="labelledBy || undefined"
        :aria-expanded="autocompleteActive ? 'true' : 'false'"
        :aria-controls="listId"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="onAutocompleteInput"
        @keydown="onKeydown"
      />
      <button
        v-if="clearable && modelValue !== '' && modelValue != null && !disabled"
        type="button"
        class="opt-picker__clear"
        aria-label="Bỏ chọn"
        @click="clearSelection"
      >
        <AppIcon name="close" :size="14" :stroke-width="2.25" />
      </button>
    </div>

    <ul
      v-if="autocompleteActive"
      :id="listId"
      ref="listRef"
      class="opt-picker__ac-list hide-scrollbar"
      role="listbox"
      :aria-labelledby="labelledBy || undefined"
    >
      <li v-if="autocompleteOptions.length === 0" class="opt-picker__empty" role="presentation">
        Không có kết quả phù hợp.
      </li>
      <template v-else>
        <li
          v-for="(opt, index) in autocompleteOptions"
          :key="opt.value"
          :data-index="index"
          class="opt-picker__option"
          :class="{
            'opt-picker__option--on': index === highlighted,
            'opt-picker__option--selected': String(opt.value) === String(modelValue),
          }"
          role="option"
          :aria-selected="String(opt.value) === String(modelValue) ? 'true' : 'false'"
          @mousedown.prevent="pick(opt)"
          @mouseenter="highlighted = index"
        >
          <span class="opt-picker__option-label">{{ opt.label }}</span>
          <span v-if="opt.description" class="opt-picker__option-desc">{{ opt.description }}</span>
        </li>
      </template>
    </ul>
  </div>

  <div
    v-else
    ref="root"
    class="opt-picker"
    :class="{
      'opt-picker--open': open,
      'opt-picker--disabled': disabled,
      'opt-picker--searchable': searchable,
    }"
  >
    <div
      class="opt-picker__trigger"
      role="combobox"
      :aria-labelledby="labelledBy || undefined"
      :aria-expanded="open ? 'true' : 'false'"
      aria-haspopup="listbox"
      :aria-disabled="disabled ? 'true' : 'false'"
      :tabindex="disabled ? -1 : 0"
      @click="toggle"
      @keydown="onKeydown"
    >
      <span class="opt-picker__value" :class="{ 'opt-picker__value--empty': !selected }">
        {{ selected?.label || placeholder }}
      </span>
      <button
        v-if="clearable && selected"
        type="button"
        class="opt-picker__clear"
        aria-label="Bỏ chọn"
        :disabled="disabled"
        @click="clearSelection"
      >
        <AppIcon name="close" :size="14" :stroke-width="2.25" />
      </button>
      <AppIcon name="chevronDown" :size="16" :stroke-width="2" class="opt-picker__chevron" />
    </div>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        class="opt-picker__panel"
        :style="panelStyle"
        @mousedown.stop
      >
        <div v-if="searchable" class="opt-picker__filter-wrap">
          <AppIcon name="search" :size="16" :stroke-width="2" class="opt-picker__filter-icon" aria-hidden="true" />
          <input
            ref="filterRef"
            class="opt-picker__filter"
            type="text"
            :value="query"
            placeholder="Gõ để tìm…"
            autocomplete="off"
            spellcheck="false"
            @input="onFilterInput"
            @keydown="onKeydown"
          />
        </div>
        <ul
          class="opt-picker__list hide-scrollbar"
          role="listbox"
          :aria-labelledby="labelledBy || undefined"
        >
          <li v-if="visibleOptions.length === 0" class="opt-picker__empty" role="presentation">
            Không có kết quả phù hợp.
          </li>
          <li
            v-for="(opt, index) in visibleOptions"
            :key="opt.value"
            class="opt-picker__option"
            :class="{
              'opt-picker__option--on': index === highlighted,
              'opt-picker__option--selected': String(opt.value) === String(modelValue),
            }"
            role="option"
            :aria-selected="String(opt.value) === String(modelValue) ? 'true' : 'false'"
            @mousedown.prevent="pick(opt)"
            @mouseenter="highlighted = index"
          >
            <span class="opt-picker__option-label">{{ opt.label }}</span>
            <span v-if="opt.description" class="opt-picker__option-desc">{{ opt.description }}</span>
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.opt-picker {
  position: relative;
  min-width: 0;
  z-index: 0;
}

.opt-picker--open {
  z-index: 1;
}

.opt-picker__trigger {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  text-align: left;
  cursor: pointer;
}

.opt-picker--open .opt-picker__trigger,
.opt-picker__trigger:focus-visible {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.opt-picker--disabled .opt-picker__trigger {
  opacity: 0.6;
  cursor: not-allowed;
}

.opt-picker__value {
  flex: 1;
  min-width: 0;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.opt-picker__value--empty {
  font-weight: 500;
  color: var(--color-text-muted);
}

.opt-picker__clear {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.opt-picker__clear:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.opt-picker__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  transition: transform 0.16s ease;
}

.opt-picker--open .opt-picker__chevron {
  transform: rotate(180deg);
}

.opt-picker__ac-control {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.opt-picker__ac-control--open,
.opt-picker__ac-control:focus-within {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.opt-picker--disabled .opt-picker__ac-control {
  opacity: 0.6;
}

.opt-picker__ac-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.opt-picker__ac-input {
  flex: 1;
  min-width: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  outline: none;
}

.opt-picker__ac-input::placeholder {
  font-weight: 500;
  color: var(--color-text-muted);
}

.opt-picker__ac-input:disabled {
  cursor: not-allowed;
}

.opt-picker__ac-list {
  position: relative;
  z-index: 2;
  max-height: 14rem;
  margin: 0.375rem 0 0;
  padding: var(--space-1);
  overflow-y: auto;
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.opt-picker__panel {
  display: flex;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.opt-picker__filter-wrap {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 0.625rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.opt-picker__filter-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.opt-picker__filter {
  flex: 1;
  min-width: 0;
  padding: 0.25rem 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  outline: none;
}

.opt-picker__filter::placeholder {
  color: var(--color-text-muted);
}

.opt-picker__list {
  margin: 0;
  padding: var(--space-1);
  overflow-y: auto;
  list-style: none;
}

.opt-picker__empty {
  padding: 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.opt-picker__option {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.opt-picker__option--on,
.opt-picker__option:hover {
  background: var(--color-surface-muted);
}

.opt-picker__option--selected {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.opt-picker__option-label {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.35;
}

.opt-picker__option-desc {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  font-weight: 400;
  line-height: 1.45;
}
</style>
