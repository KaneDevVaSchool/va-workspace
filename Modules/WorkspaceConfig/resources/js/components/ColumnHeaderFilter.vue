<script setup>
//
// Tiêu đề cột bảng có nút sắp xếp + nút lọc "chứa" mở popover (mẫu
// ColumnHeaderFilter bên va-hrm). Popover dùng Teleport ra body + toạ độ
// tuyệt đối để không bị cắt bởi overflow của vùng bảng. Nút chỉ có icon nên
// có aria-label, KHÔNG dùng title/tooltip (mục 13 CLAUDE.md).
//
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: String, default: '' },
  filterable: { type: Boolean, default: false },
  sortable: { type: Boolean, default: false },
  sortDir: { type: String, default: '' },
  placeholder: { type: String, default: '' },
});

const emit = defineEmits(['apply', 'clear', 'toggle-sort']);

const open = ref(false);
const draft = ref('');
const coords = ref(null);
const trigger = ref(null);
const panel = ref(null);
const input = ref(null);

function place() {
  const el = trigger.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const width = 236;
  coords.value = {
    top: rect.bottom + 6,
    left: Math.min(Math.max(8, rect.left), window.innerWidth - width - 8),
  };
}

function onDocumentPointerDown(event) {
  if (trigger.value?.contains(event.target)) return;
  if (panel.value?.contains(event.target)) return;
  open.value = false;
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape') {
    open.value = false;
  }
}

function bind() {
  document.addEventListener('mousedown', onDocumentPointerDown);
  document.addEventListener('keydown', onDocumentKeydown);
  window.addEventListener('scroll', place, true);
  window.addEventListener('resize', place);
}

function unbind() {
  document.removeEventListener('mousedown', onDocumentPointerDown);
  document.removeEventListener('keydown', onDocumentKeydown);
  window.removeEventListener('scroll', place, true);
  window.removeEventListener('resize', place);
}

function toggleOpen() {
  open.value = !open.value;
}

function apply() {
  emit('apply', draft.value.trim());
  open.value = false;
}

function clear() {
  draft.value = '';
  emit('clear');
  open.value = false;
}

watch(open, (isOpen) => {
  if (isOpen) {
    draft.value = props.value;
    place();
    bind();
    nextTick(() => input.value?.focus());
  } else {
    unbind();
    coords.value = null;
  }
});

onBeforeUnmount(unbind);
</script>

<template>
  <span class="col-head">
    <span class="col-head__label">{{ label }}</span>
    <span class="col-head__tools">
      <button
        v-if="sortable"
        type="button"
        class="col-head__btn"
        :class="{ 'col-head__btn--on': Boolean(sortDir) }"
        :aria-label="`Sắp xếp theo ${label}`"
        @click.stop="emit('toggle-sort')"
      >
        <AppIcon :name="sortDir === 'desc' ? 'chevronsDown' : 'chevronsUp'" :size="13" />
      </button>
      <button
        v-if="filterable"
        ref="trigger"
        type="button"
        class="col-head__btn"
        :class="{ 'col-head__btn--on': Boolean(value) }"
        :aria-label="`Lọc theo ${label}`"
        :aria-expanded="open"
        @click.stop="toggleOpen"
      >
        <AppIcon name="search" :size="13" />
      </button>
    </span>

    <Teleport v-if="open && coords" to="body">
      <div
        ref="panel"
        class="col-head__panel"
        :style="{ top: `${coords.top}px`, left: `${coords.left}px` }"
      >
        <p class="col-head__panel-title">Lọc theo {{ label.toLowerCase() }}</p>
        <input
          ref="input"
          v-model="draft"
          type="search"
          class="col-head__panel-input"
          :placeholder="placeholder || 'Nhập nội dung cần tìm'"
          @keydown.enter.prevent="apply"
          @keydown.stop
          @click.stop
        />
        <div class="col-head__panel-actions">
          <button
            type="button"
            class="col-head__panel-btn"
            :disabled="!value && !draft"
            @click.stop="clear"
          >
            Bỏ lọc
          </button>
          <button
            type="button"
            class="col-head__panel-btn col-head__panel-btn--primary"
            @click.stop="apply"
          >
            Áp dụng
          </button>
        </div>
      </div>
    </Teleport>
  </span>
</template>

<style scoped>
.col-head {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  min-width: 0;
}

.col-head__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.col-head__tools {
  display: inline-flex;
  align-items: center;
  gap: 0.0625rem;
  flex-shrink: 0;
}

.col-head__btn {
  display: grid;
  place-items: center;
  width: 1.125rem;
  height: 1.125rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.col-head__btn:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.col-head__btn--on {
  background: var(--color-primary-50);
  color: var(--color-primary-900);
}

.col-head__panel {
  position: fixed;
  z-index: 60;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  width: 236px;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-lg);
}

.col-head__panel-title {
  margin: 0;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
}

.col-head__panel-input {
  width: 100%;
  height: 2rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.col-head__panel-input:focus {
  outline: none;
  box-shadow: inset 0 0 0 2px var(--color-primary-900);
}

.col-head__panel-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.col-head__panel-btn {
  height: 1.75rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.col-head__panel-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.col-head__panel-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.col-head__panel-btn--primary {
  background: var(--color-primary-900);
  color: var(--color-on-primary);
  box-shadow: none;
}

.col-head__panel-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}
</style>
