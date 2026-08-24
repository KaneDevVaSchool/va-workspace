<script setup>
//
// Autocomplete: gõ mới hiện list kết quả ngay dưới input. Không mở sẵn
// danh sách khi focus, không teleport (tránh lệch vị trí trong modal).
//
import { computed, nextTick, ref, useId, watch } from 'vue';
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
  minQuery: { type: Number, default: 1 },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const highlight = ref(0);
const brokenIds = ref(new Set());
const inputEl = ref(null);
const listEl = ref(null);
const listId = useId();

const selected = computed(
  () => props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null,
);

const needle = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return '';
  const selectedLabel = selected.value ? String(selected.value.label || '').toLowerCase() : '';
  if (q === selectedLabel) return '';
  return q;
});

const searching = computed(() => needle.value.length >= props.minQuery);

const filtered = computed(() => {
  if (!searching.value) return [];
  return props.items.filter((item) => {
    const hay = `${item.label ?? ''} ${item.sublabel ?? ''} ${item.meta ?? ''}`.toLowerCase();
    return hay.includes(needle.value);
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

function pick(item) {
  emit('update:modelValue', item.id);
  query.value = displayQueryFor(item);
  highlight.value = 0;
}

function clear() {
  emit('update:modelValue', '');
  query.value = '';
  highlight.value = 0;
  nextTick(() => inputEl.value?.focus());
}

function onInput() {
  if (selected.value && query.value !== displayQueryFor(selected.value)) {
    emit('update:modelValue', '');
  }
  highlight.value = 0;
}

function scrollHighlightIntoView() {
  const option = listEl.value?.querySelector(`[data-index="${highlight.value}"]`);
  option?.scrollIntoView({ block: 'nearest' });
}

function moveHighlight(delta) {
  const count = filtered.value.length;
  if (count === 0) return;
  highlight.value = (highlight.value + delta + count) % count;
  nextTick(scrollHighlightIntoView);
}

function onKeydown(event) {
  if (!searching.value) return;
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
    const item = filtered.value[highlight.value];
    if (!item) return;
    event.preventDefault();
    pick(item);
  }
}

watch(
  () => props.modelValue,
  () => {
    if (!searching.value) syncQueryFromValue();
  },
);

watch(
  () => props.items,
  () => {
    if (!searching.value) syncQueryFromValue();
  },
);

syncQueryFromValue();
</script>

<template>
  <div class="wc-picker">
    <div class="wc-picker__control" :class="{ 'wc-picker__control--open': searching }">
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
        :aria-expanded="searching ? 'true' : 'false'"
        :aria-controls="listId"
        :aria-activedescendant="searching && filtered[highlight] ? optionId(highlight) : undefined"
        :placeholder="placeholder"
        :disabled="disabled"
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
    </div>

    <ul
      v-if="searching"
      :id="listId"
      ref="listEl"
      class="wc-picker__list hide-scrollbar"
      role="listbox"
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
  </div>
</template>

<style scoped>
.wc-picker {
  position: relative;
  min-width: 0;
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
  height: 2.375rem;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
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

.wc-picker__list {
  z-index: 2;
  max-height: 14rem;
  overflow: auto;
  margin: 0.375rem 0 0;
  padding: 0.25rem;
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.wc-picker__empty {
  padding: 0.75rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-picker__option {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.wc-picker__option--active,
.wc-picker__option:hover {
  background: var(--color-surface-muted);
}

.wc-picker__option--selected {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}

.wc-picker__avatar {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
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

.wc-picker__label,
.wc-picker__sub,
.wc-picker__meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wc-picker__label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
}

.wc-picker__sub {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.35;
}

.wc-picker__meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}
</style>
