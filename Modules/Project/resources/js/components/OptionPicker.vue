<script setup>
//
// Select có mô tả dưới mỗi option — dùng cho cách tính tiến độ (tiêu đề
// + ví dụ in nghiêng), không dùng native <select> vì option không hiện
// được 2 dòng.
//
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Chọn…' },
  clearable: { type: Boolean, default: false },
  labelledBy: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const highlighted = ref(0);
const root = ref(null);
const listRef = ref(null);
const listStyle = ref({});

const selected = computed(
  () => props.options.find((opt) => String(opt.value) === String(props.modelValue)) || null,
);

watch(
  () => props.modelValue,
  (value) => {
    const idx = props.options.findIndex((opt) => String(opt.value) === String(value));
    highlighted.value = idx >= 0 ? idx : 0;
  },
  { immediate: true },
);

watch(open, async (isOpen) => {
  if (!isOpen) return;
  await nextTick();
  placeList();
});

function placeList() {
  const el = root.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const pad = 8;
  const gap = 4;
  const maxH = Math.min(22 * 16, window.innerHeight - pad * 2);
  const spaceBelow = window.innerHeight - rect.bottom - pad;
  const openUp = spaceBelow < 12 * 16 && rect.top > spaceBelow;
  listStyle.value = {
    position: 'fixed',
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    maxHeight: `${maxH}px`,
    zIndex: 80,
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
  open.value = false;
}

function clear(event) {
  event.preventDefault();
  event.stopPropagation();
  if (props.disabled) return;
  emit('update:modelValue', '');
  open.value = false;
}

function onDocPointer(event) {
  if (root.value?.contains(event.target) || listRef.value?.contains(event.target)) return;
  open.value = false;
}

function onKeydown(event) {
  if (props.disabled) return;
  if (event.key === 'Escape') {
    open.value = false;
    return;
  }
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    open.value = true;
    const n = props.options.length;
    if (!n) return;
    const dir = event.key === 'ArrowDown' ? 1 : -1;
    highlighted.value = (highlighted.value + dir + n) % n;
    return;
  }
  if (event.key === 'Enter' && open.value) {
    event.preventDefault();
    const item = props.options[highlighted.value];
    if (item) pick(item);
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onDocPointer);
  window.addEventListener('resize', placeList);
  window.addEventListener('scroll', placeList, true);
});
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocPointer);
  window.removeEventListener('resize', placeList);
  window.removeEventListener('scroll', placeList, true);
});
</script>

<template>
  <div
    ref="root"
    class="opt-picker"
    :class="{ 'opt-picker--open': open, 'opt-picker--disabled': disabled }"
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
        @click="clear"
      >
        <AppIcon name="close" :size="14" :stroke-width="2.25" />
      </button>
      <AppIcon name="chevronDown" :size="16" :stroke-width="2" class="opt-picker__chevron" />
    </div>

    <Teleport to="body">
      <ul
        v-if="open"
        ref="listRef"
        class="opt-picker__list hide-scrollbar"
        role="listbox"
        :aria-labelledby="labelledBy || undefined"
        :style="listStyle"
      >
        <li
          v-for="(opt, index) in options"
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
    </Teleport>
  </div>
</template>

<style scoped>
.opt-picker {
  position: relative;
  min-width: 0;
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

.opt-picker__list {
  margin: 0;
  padding: var(--space-1);
  overflow-y: auto;
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
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
