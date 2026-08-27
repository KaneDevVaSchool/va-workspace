<script setup>
//
// Chọn "Loại dự án" (mục A) — select từ danh mục có sẵn (project_types) +
// nút "+" mở modal nhỏ để tự tạo loại mới ngay trong luồng, tương tự cách
// ProjectLabelPicker cho tạo nhãn mới, nhưng gọn hơn (chỉ 1 field tên) nên
// dùng modal riêng thay vì panel 2 cột.
//
import { nextTick, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, required: true }, // [{ value, label }]
  disabled: { type: Boolean, default: false },
  inputId: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'created']);

const dialogOpen = ref(false);
const newName = ref('');
const creating = ref(false);
const nameInput = ref(null);

function openDialog() {
  if (props.disabled) return;
  newName.value = '';
  dialogOpen.value = true;
  nextTick(() => nameInput.value?.focus());
}

function closeDialog() {
  if (creating.value) return;
  dialogOpen.value = false;
}

async function createType() {
  const name = newName.value.trim();
  if (!name || creating.value) return;

  creating.value = true;
  try {
    const { data } = await window.axios.post('/api/project/types', { name });
    emit('created', data.type);
    emit('update:modelValue', data.type.value);
    dialogOpen.value = false;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tạo được loại dự án.');
  } finally {
    creating.value = false;
  }
}
</script>

<template>
  <div class="proj-type-select">
    <select
      :id="inputId"
      :value="modelValue"
      class="proj-type-select__select"
      :disabled="disabled"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <option value="" disabled>Chọn loại dự án…</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
    <button
      type="button"
      class="proj-type-select__add"
      aria-label="Thêm loại dự án mới"
      :disabled="disabled"
      @click="openDialog"
    >
      <AppIcon name="plus" :size="16" :stroke-width="2" />
    </button>

    <Teleport to="body">
      <Transition name="proj-type-select-fade">
        <div v-if="dialogOpen" class="proj-type-select__overlay" @click.self="closeDialog">
          <Transition name="proj-type-select-pop" appear>
            <div
              class="proj-type-select__panel"
              role="dialog"
              aria-modal="true"
              aria-labelledby="proj-type-select-title"
            >
              <div class="proj-type-select__head">
                <span class="proj-type-select__icon" aria-hidden="true">
                  <AppIcon name="plus" :size="17" :stroke-width="1.75" />
                </span>
                <h2 id="proj-type-select-title" class="proj-type-select__title">Thêm loại dự án</h2>
                <button
                  type="button"
                  class="proj-type-select__close"
                  aria-label="Đóng"
                  :disabled="creating"
                  @click="closeDialog"
                >
                  <AppIcon name="close" :size="16" />
                </button>
              </div>

              <div class="proj-type-select__body">
                <label class="proj-type-select__label" for="proj-type-select-name">Tên loại dự án</label>
                <input
                  id="proj-type-select-name"
                  ref="nameInput"
                  v-model="newName"
                  type="text"
                  class="proj-type-select__text-input"
                  maxlength="100"
                  placeholder="Ví dụ: Nghiên cứu phát triển"
                  :disabled="creating"
                  @keydown.enter.prevent="createType"
                  @keydown.esc="closeDialog"
                />
              </div>

              <div class="proj-type-select__actions">
                <button type="button" class="proj-type-select__btn proj-type-select__btn--ghost" :disabled="creating" @click="closeDialog">
                  Huỷ
                </button>
                <button
                  type="button"
                  class="proj-type-select__btn proj-type-select__btn--primary"
                  :disabled="creating || !newName.trim()"
                  @click="createType"
                >
                  <AppIcon v-if="!creating" name="check" :size="15" :stroke-width="2.25" />
                  {{ creating ? 'Đang tạo…' : 'Tạo loại dự án' }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.proj-type-select {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.proj-type-select__select {
  flex: 1;
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.proj-type-select__select:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-type-select__select:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-type-select__select:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-type-select__text-input {
  width: 100%;
  min-width: 0;
  padding: 0.75rem 0.9375rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 1rem;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.proj-type-select__text-input:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-type-select__text-input:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-type-select__text-input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-type-select__add {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-primary);
  cursor: pointer;
  transition: background-color 0.12s ease, border-color 0.12s ease;
}

.proj-type-select__add:hover:not(:disabled) {
  border-color: var(--color-primary-300);
  background: var(--color-primary-surface);
}

.proj-type-select__add:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-type-select__overlay {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
  backdrop-filter: blur(2px);
}

.proj-type-select__panel {
  width: min(26rem, calc(100vw - 2.5rem));
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  padding: 1.5rem 1.625rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg), 0 0 0 1px var(--color-border);
}

.proj-type-select__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-4);
}

.proj-type-select__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-type-select__title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.3;
}

.proj-type-select__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.875rem;
  height: 1.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.proj-type-select__close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.proj-type-select__body {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  min-width: 0;
}

.proj-type-select__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-type-select__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding-top: var(--space-1);
}

.proj-type-select__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4375rem;
  padding: 0.6875rem 1.25rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.12s ease, border-color 0.12s ease, box-shadow 0.12s ease, transform 0.1s ease;
}

.proj-type-select__btn--primary:active:not(:disabled) {
  transform: translateY(1px);
}

.proj-type-select__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
  box-shadow: var(--shadow-sm);
}

.proj-type-select__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.proj-type-select__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.proj-type-select__btn--ghost:hover:not(:disabled) {
  border-color: var(--color-border-strong);
  background: var(--color-surface-muted);
}

.proj-type-select__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

/* Hiệu ứng mở/đóng modal: overlay mờ dần, panel phóng nhẹ từ giữa. */
.proj-type-select-fade-enter-active,
.proj-type-select-fade-leave-active {
  transition: opacity 0.15s ease;
}

.proj-type-select-fade-enter-from,
.proj-type-select-fade-leave-to {
  opacity: 0;
}

.proj-type-select-pop-enter-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}

.proj-type-select-pop-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}

.proj-type-select-pop-enter-from,
.proj-type-select-pop-leave-to {
  opacity: 0;
  transform: translateY(0.5rem) scale(0.97);
}
</style>
