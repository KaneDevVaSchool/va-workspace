<script setup>
//
// Picker "Nhãn" cho form Dự án (mục E) — tìm nhãn có sẵn (load 1 lần toàn bộ,
// filter phía client vì số lượng nhỏ) và tạo nhãn mới ngay trong luồng.
// Danh sách + chọn màu nằm trong luồng (không dropdown absolute) để không bị
// khung overflow của bảng cắt mất.
//
import { computed, ref } from 'vue';
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
  modelValue: { type: Array, required: true }, // label ids
  labels: { type: Array, required: true }, // [{ id, name, color }] — toàn bộ nhãn hệ thống
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'created']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));
const query = ref('');
const createName = ref('');
const newColor = ref('primary');
const creating = ref(false);

const unusedLabels = computed(() => props.labels.filter((l) => !selectedIds.value.has(String(l.id))));

const matches = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return unusedLabels.value;
  return unusedLabels.value.filter((l) => l.name.toLowerCase().includes(q));
});

const exactCreateMatch = computed(() => {
  const q = createName.value.trim().toLowerCase();
  if (!q) return null;
  return props.labels.find((l) => l.name.toLowerCase() === q) || null;
});

const canCreateNew = computed(() => createName.value.trim() !== '' && !exactCreateMatch.value);

const selectedLabels = computed(() => props.labels.filter((l) => selectedIds.value.has(String(l.id))));

const previewName = computed(() => createName.value.trim() || 'Tên nhãn');

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
}

async function createAndSelect() {
  const name = createName.value.trim();
  if (!name || creating.value || !canCreateNew.value) return;

  creating.value = true;
  try {
    const { data } = await window.axios.post('/api/project/labels', { name, color: newColor.value });
    emit('created', data.label);
    select(data.label.id);
    createName.value = '';
    newColor.value = 'primary';
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tạo được nhãn mới.');
  } finally {
    creating.value = false;
  }
}

function onSearchEnter() {
  if (matches.value.length > 0) {
    pick(matches.value[0]);
  }
}
</script>

<template>
  <div class="proj-label-picker">
    <div class="proj-label-picker__grid">
      <section class="proj-label-picker__pane">
        <h3 class="proj-label-picker__pane-title">Nhãn có sẵn</h3>

        <label class="proj-label-picker__field">
          <span class="proj-label-picker__label">Tìm nhãn</span>
          <span class="proj-label-picker__search">
            <AppIcon name="search" :size="15" />
            <input
              v-model="query"
              type="search"
              class="proj-label-picker__input"
              placeholder="Gõ để lọc…"
              :disabled="disabled"
              @keydown.enter.prevent="onSearchEnter"
            />
          </span>
        </label>

        <ul v-if="matches.length" class="proj-label-picker__list hide-scrollbar" role="listbox">
          <li
            v-for="item in matches"
            :key="item.id"
            class="proj-label-picker__option"
            role="option"
            @click="pick(item)"
          >
            <span class="proj-label-picker__chip" :class="`proj-label-picker__chip--${item.color}`">
              <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${item.color}`" />
              <span>{{ item.name }}</span>
            </span>
          </li>
        </ul>
        <p v-else class="proj-label-picker__empty">
          {{ query.trim() ? 'Không có nhãn khớp.' : 'Không còn nhãn để gắn.' }}
        </p>
      </section>

      <section class="proj-label-picker__pane proj-label-picker__pane--create" :class="`proj-label-picker__pane--${newColor}`">
        <h3 class="proj-label-picker__pane-title">Tạo nhãn mới</h3>

        <div class="proj-label-picker__create-top">
          <label class="proj-label-picker__field">
            <span class="proj-label-picker__label">Tên nhãn</span>
            <input
              v-model="createName"
              type="text"
              class="proj-label-picker__input"
              maxlength="100"
              :disabled="disabled || creating"
              @keydown.enter.prevent="createAndSelect"
            />
          </label>
          <div class="proj-label-picker__preview">
            <span class="proj-label-picker__label">Xem trước</span>
            <span class="proj-label-picker__chip" :class="`proj-label-picker__chip--${newColor}`">
              <span class="proj-label-picker__dot" :class="`proj-label-picker__dot--${newColor}`" />
              <span>{{ previewName }}</span>
            </span>
          </div>
        </div>

        <div class="proj-label-picker__field">
          <span class="proj-label-picker__label">Màu nhãn</span>
          <div class="proj-label-picker__swatches">
            <button
              v-for="color in COLORS"
              :key="color.key"
              type="button"
              class="proj-label-picker__swatch"
              :class="[
                `proj-label-picker__swatch--${color.key}`,
                { 'proj-label-picker__swatch--active': newColor === color.key },
              ]"
              :aria-label="`Chọn màu ${color.label}`"
              :aria-pressed="newColor === color.key ? 'true' : 'false'"
              :disabled="disabled || creating"
              @click="newColor = color.key"
            >
              <span class="proj-label-picker__swatch-dot" />
              <span class="proj-label-picker__swatch-name">{{ color.label }}</span>
            </button>
          </div>
        </div>

        <button
          type="button"
          class="proj-label-picker__create"
          :disabled="disabled || creating || !canCreateNew"
          @click="createAndSelect"
        >
          <AppIcon name="plus" :size="15" :stroke-width="1.75" />
          Tạo nhãn
        </button>
      </section>

      <div class="proj-label-picker__selected">
        <span class="proj-label-picker__label">Đã gắn</span>
        <div class="proj-label-picker__chips">
          <span
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
          </span>
          <p v-if="selectedLabels.length === 0" class="proj-label-picker__empty">Chưa gán nhãn nào.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.proj-label-picker {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 22rem;
  height: 100%;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  container-type: inline-size;
}

.proj-label-picker.proj-page__dialog-picker {
  padding: 0;
  background: transparent;
  min-height: 0;
  overflow: hidden;
  container-type: normal;
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__grid {
  grid-template-columns: minmax(0, 1fr);
  grid-template-rows: minmax(0, 1fr) min-content min-content;
  gap: var(--space-2);
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__pane--create {
  max-height: none;
  min-height: min-content;
  overflow: visible;
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__create-top {
  grid-template-columns: minmax(0, 1fr) minmax(0, 8.5rem);
  align-items: end;
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__preview {
  max-width: 10rem;
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__swatches {
  max-height: none;
  overflow: visible;
}

.proj-label-picker.proj-page__dialog-picker .proj-label-picker__selected {
  max-height: 4.25rem;
}

@media (max-width: 40rem) {
  .proj-label-picker.proj-page__dialog-picker .proj-label-picker__create-top {
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
  }

  .proj-label-picker.proj-page__dialog-picker .proj-label-picker__preview {
    max-width: none;
  }
}

.proj-label-picker__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  grid-template-rows: minmax(min-content, 1fr) auto;
  gap: var(--space-3);
  flex: 1;
  min-width: 0;
  min-height: 0;
  height: 100%;
  align-content: stretch;
  overflow: hidden;
}

.proj-label-picker__pane {
  position: relative;
  z-index: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  min-height: 0;
  overflow: hidden;
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-label-picker__pane::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-tertiary);
}

.proj-label-picker__pane--create {
  min-height: min-content;
  overflow: visible;
}

.proj-label-picker__pane--create::before {
  background: var(--pane-accent, var(--color-primary));
}

.proj-label-picker__pane--primary {
  --pane-accent: var(--color-primary);
}
.proj-label-picker__pane--secondary {
  --pane-accent: var(--color-secondary);
}
.proj-label-picker__pane--tertiary {
  --pane-accent: var(--color-tertiary);
}
.proj-label-picker__pane--gold {
  --pane-accent: var(--color-gold);
}
.proj-label-picker__pane--success {
  --pane-accent: var(--color-success);
}
.proj-label-picker__pane--info {
  --pane-accent: var(--color-info);
}
.proj-label-picker__pane--warning {
  --pane-accent: var(--color-warning, var(--color-primary));
}
.proj-label-picker__pane--danger {
  --pane-accent: var(--color-danger);
}
.proj-label-picker__pane--violet {
  --pane-accent: #7c3aed;
}

.proj-label-picker__pane-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.35;
}

.proj-label-picker__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.proj-label-picker__label {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
}

.proj-label-picker__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.25rem;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.proj-label-picker__search .proj-label-picker__input {
  min-height: 0;
  padding: 0;
  border: none;
  background: transparent;
}

.proj-label-picker__search .proj-label-picker__input:focus {
  outline: none;
  border-color: transparent;
}

.proj-label-picker__search:focus-within {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.proj-label-picker__input {
  width: 100%;
  min-width: 0;
  min-height: 2.25rem;
  padding: 0.4375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.proj-label-picker__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.proj-label-picker__input::placeholder {
  color: var(--color-text-muted);
}

.proj-label-picker__input:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  background: var(--color-surface-muted);
}

.proj-label-picker__create-top {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 8.5rem);
  gap: var(--space-2);
  align-items: end;
}

.proj-label-picker__preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  max-width: 10rem;
}

.proj-label-picker__preview .proj-label-picker__chip {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.proj-label-picker__swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.proj-label-picker__swatch {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-height: 1.75rem;
  padding: 0.1875rem 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.proj-label-picker__swatch:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.proj-label-picker__swatch:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.proj-label-picker__swatch--active {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--swatch-accent, var(--color-primary));
  color: var(--color-text);
}

.proj-label-picker__swatch-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--swatch-accent, var(--color-text-muted));
}

.proj-label-picker__swatch-name {
  min-width: 0;
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

.proj-label-picker__create {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  align-self: flex-end;
  flex-shrink: 0;
  padding: 0.4375rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-label-picker__create:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.proj-label-picker__create:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-label-picker__list {
  flex: 1;
  display: flex;
  flex-wrap: wrap;
  align-content: flex-start;
  gap: 0.375rem;
  min-height: 0;
  margin: 0;
  padding: 0;
  overflow: auto;
  list-style: none;
}

.proj-label-picker__option {
  cursor: pointer;
}

.proj-label-picker__option:hover .proj-label-picker__chip {
  filter: brightness(0.97);
}

.proj-label-picker__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.proj-label-picker__selected {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  min-height: 0;
  max-height: 5.75rem;
  overflow: auto;
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
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.625rem;
  border: none;
  border-radius: 0;
  background: var(--chip-bg, var(--color-surface-muted));
  color: var(--chip-fg, var(--color-text));
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.proj-label-picker__chip--sm {
  padding: 0.0625rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 600;
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

@container (max-width: 40rem) {
  .proj-label-picker__grid {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: minmax(7.5rem, 1fr) min-content auto;
  }

  .proj-label-picker__create-top {
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
  }

  .proj-label-picker__preview {
    max-width: none;
  }

  .proj-label-picker__pane--create {
    min-height: min-content;
    overflow: visible;
  }

  .proj-label-picker__list {
    max-height: none;
  }
}
</style>
