<script setup>
//
// Combobox tìm theo tên / email — dùng trong modal gán vai trò và thêm/sửa nhóm.
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { FALLBACK_AVATAR_SRC, FALLBACK_AVATAR_SRCSET } from '../constants/members.js';

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: [String, Number], default: '' },
  items: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Tìm theo tên hoặc email…' },
  emptyText: { type: String, default: 'Không có kết quả.' },
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: false },
  showAvatar: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const highlight = ref(0);
const brokenIds = ref(new Set());
const root = ref(null);
const inputEl = ref(null);
const listEl = ref(null);
const listId = useId();
const listStyle = ref({});

function positionList() {
  const rect = root.value?.getBoundingClientRect();
  if (!rect) return;
  const gap = 6;
  const maxH = 264;
  const spaceBelow = window.innerHeight - rect.bottom - gap - 12;
  const openUp = spaceBelow < 140 && rect.top > spaceBelow;
  listStyle.value = {
    position: 'fixed',
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    zIndex: 400,
    maxHeight: `${Math.min(maxH, openUp ? rect.top - 12 : spaceBelow)}px`,
    ...(openUp
      ? { bottom: `${window.innerHeight - rect.top + gap}px`, top: 'auto' }
      : { top: `${rect.bottom + gap}px`, bottom: 'auto' }),
  };
}

const selected = computed(
  () => props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null,
);

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  const selectedLabel = selected.value ? String(selected.value.label || '').toLowerCase() : '';
  const needle = q && q !== selectedLabel ? q : '';
  if (!needle) return props.items;
  return props.items.filter((item) => {
    const hay = `${item.label ?? ''} ${item.sublabel ?? ''} ${item.meta ?? ''}`.toLowerCase();
    return hay.includes(needle);
  });
});

function displayQueryFor(item) {
  return item?.label ?? '';
}

function syncQueryFromValue() {
  query.value = selected.value ? displayQueryFor(selected.value) : '';
}

function optionId(index) {
  return `${listId}-opt-${index}`;
}

function usesPhoto(item) {
  return Boolean(item?.avatar_url) && !brokenIds.value.has(item.id);
}

function onAvatarError(id) {
  if (brokenIds.value.has(id)) return;
  const next = new Set(brokenIds.value);
  next.add(id);
  brokenIds.value = next;
}

function openList() {
  if (props.disabled) return;
  open.value = true;
  highlight.value = Math.max(
    0,
    filtered.value.findIndex((item) => String(item.id) === String(props.modelValue)),
  );
  nextTick(() => {
    positionList();
    scrollHighlightIntoView();
  });
}

function closeList() {
  open.value = false;
  syncQueryFromValue();
}

function pick(item) {
  emit('update:modelValue', item.id);
  query.value = displayQueryFor(item);
  open.value = false;
}

function clear() {
  emit('update:modelValue', '');
  query.value = '';
  highlight.value = 0;
  nextTick(() => inputEl.value?.focus());
}

function onFocus() {
  openList();
  nextTick(() => inputEl.value?.select());
}

function onInput() {
  if (!open.value) openList();
  if (selected.value && query.value !== displayQueryFor(selected.value)) {
    emit('update:modelValue', '');
  }
  highlight.value = 0;
}

function moveHighlight(delta) {
  if (!open.value) {
    openList();
    return;
  }
  const count = filtered.value.length;
  if (count === 0) return;
  highlight.value = (highlight.value + delta + count) % count;
  nextTick(scrollHighlightIntoView);
}

function scrollHighlightIntoView() {
  const option = listEl.value?.querySelector(`[data-index="${highlight.value}"]`);
  option?.scrollIntoView({ block: 'nearest' });
}

function onKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveHighlight(1);
    return;
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveHighlight(-1);
    return;
  }
  if (event.key === 'Enter') {
    if (!open.value) return;
    event.preventDefault();
    const item = filtered.value[highlight.value];
    if (item) pick(item);
    return;
  }
  if (event.key === 'Escape' && open.value) {
    event.preventDefault();
    event.stopPropagation();
    closeList();
  }
}

function onDocumentPointer(event) {
  if (!open.value) return;
  if (root.value?.contains(event.target) || listEl.value?.contains(event.target)) return;
  closeList();
}

function onDocumentKeydown(event) {
  if (event.key !== 'Escape' || !open.value) return;
  event.preventDefault();
  event.stopPropagation();
  closeList();
}

watch(
  () => props.modelValue,
  () => {
    if (!open.value) syncQueryFromValue();
  },
);

watch(
  () => props.items,
  () => {
    if (!open.value) syncQueryFromValue();
  },
);

watch(open, (isOpen) => {
  if (isOpen) {
    nextTick(positionList);
    window.addEventListener('resize', positionList);
    window.addEventListener('scroll', positionList, true);
    return;
  }
  window.removeEventListener('resize', positionList);
  window.removeEventListener('scroll', positionList, true);
});

onMounted(() => {
  syncQueryFromValue();
  document.addEventListener('mousedown', onDocumentPointer);
  document.addEventListener('keydown', onDocumentKeydown, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentPointer);
  document.removeEventListener('keydown', onDocumentKeydown, true);
  window.removeEventListener('resize', positionList);
  window.removeEventListener('scroll', positionList, true);
});
</script>

<template>
  <div ref="root" class="wc-picker">
    <div class="wc-picker__control" :class="{ 'wc-picker__control--open': open }">
      <AppIcon class="wc-picker__search-icon" name="search" :size="16" :stroke-width="1.75" />
      <input
        :id="id"
        ref="inputEl"
        v-model="query"
        type="text"
        class="wc-picker__input"
        role="combobox"
        autocomplete="off"
        spellcheck="false"
        aria-autocomplete="list"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-controls="listId"
        :aria-activedescendant="open && filtered[highlight] ? optionId(highlight) : undefined"
        :placeholder="placeholder"
        :disabled="disabled"
        @focus="onFocus"
        @input="onInput"
        @keydown="onKeydown"
      />
      <button
        v-if="clearable && modelValue !== '' && modelValue != null && !disabled"
        type="button"
        class="wc-picker__icon-btn"
        aria-label="Xoá lựa chọn"
        @click="clear"
      >
        <AppIcon name="close" :size="14" :stroke-width="1.75" />
      </button>
      <button
        type="button"
        class="wc-picker__icon-btn"
        tabindex="-1"
        aria-hidden="true"
        :disabled="disabled"
        @mousedown.prevent="open ? closeList() : openList()"
      >
        <AppIcon name="chevronDown" :size="16" :stroke-width="1.75" />
      </button>
    </div>

    <Teleport to="body">
      <ul
        v-show="open"
        :id="listId"
        ref="listEl"
        class="wc-picker__list hide-scrollbar"
        role="listbox"
        :style="listStyle"
      >
        <li v-if="filtered.length === 0" class="wc-picker__empty" role="presentation">
          {{ emptyText }}
        </li>
        <li
          v-for="(item, index) in filtered"
          v-else
          :id="optionId(index)"
          :key="item.id"
          :data-index="index"
          class="wc-picker__option"
          :class="{
            'wc-picker__option--active': index === highlight,
            'wc-picker__option--selected': String(item.id) === String(modelValue),
          }"
          role="option"
          :aria-selected="String(item.id) === String(modelValue) ? 'true' : 'false'"
          @mousedown.prevent="pick(item)"
          @mouseenter="highlight = index"
        >
          <span v-if="showAvatar" class="wc-picker__avatar" aria-hidden="true">
            <img
              v-if="usesPhoto(item)"
              :src="item.avatar_url"
              alt=""
              class="wc-picker__avatar-img"
              referrerpolicy="no-referrer"
              @error="onAvatarError(item.id)"
            />
            <img
              v-else
              :src="FALLBACK_AVATAR_SRC"
              :srcset="FALLBACK_AVATAR_SRCSET"
              alt=""
              class="wc-picker__avatar-fallback"
            />
          </span>
          <span class="wc-picker__copy">
            <span class="wc-picker__label">{{ item.label }}</span>
            <span v-if="item.sublabel" class="wc-picker__sub">{{ item.sublabel }}</span>
          </span>
          <span v-if="item.meta" class="wc-picker__meta">{{ item.meta }}</span>
        </li>
      </ul>
    </Teleport>
  </div>
</template>

<style scoped>
.wc-picker {
  position: relative;
}

.wc-picker__control {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  min-height: 2.75rem;
  padding: 0 0.5rem 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.wc-picker__control--open,
.wc-picker__control:focus-within {
  border-color: var(--color-primary);
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
}

.wc-picker__search-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.wc-picker__input {
  flex: 1;
  min-width: 0;
  height: 2.625rem;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.9375rem;
}

.wc-picker__input:focus {
  outline: none;
}

.wc-picker__input::placeholder {
  color: var(--color-text-muted);
}

.wc-picker__input:disabled {
  cursor: not-allowed;
}

.wc-picker__icon-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.wc-picker__icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.wc-picker__icon-btn:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.wc-picker__list {
  z-index: 400;
  max-height: 16.5rem;
  overflow: auto;
  margin: 0;
  padding: 0.375rem;
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.wc-picker__empty {
  padding: 0.875rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-picker__option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.wc-picker__option--active {
  background: var(--color-surface-muted);
}

.wc-picker__option--selected {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.wc-picker__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}

.wc-picker__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.wc-picker__avatar-fallback {
  box-sizing: border-box;
  width: 100%;
  height: 100%;
  padding: 4%;
  object-fit: contain;
}

.wc-picker__copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
}

.wc-picker__label {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.wc-picker__sub {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.wc-picker__meta {
  flex-shrink: 0;
  max-width: 8.5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: right;
}
</style>
