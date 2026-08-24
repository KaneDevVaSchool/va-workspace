<script setup>
//
// Dialog xác nhận dùng chung (generic, không riêng cho logout) — port ý
// tưởng từ va-hrm (components/ui/Modal.tsx + LogoutConfirmModal.tsx) nhưng
// gộp lại thành 1 component tái dùng được cho mọi hành động cần xác nhận
// ("Bạn có chắc muốn...?"), viết bằng CSS thuần theo theme.css.
//
// Dùng qua v-model:open + props title/description/confirmLabel, lắng nghe
// @confirm. Component tự đóng khi bấm nút hủy/backdrop/Esc — cha chỉ cần
// xử lý @confirm (đóng dialog là việc của v-model, không phải của cha).
//
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Xác nhận' },
  cancelLabel: { type: String, default: 'Huỷ' },
  danger: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'confirm']);

function close() {
  if (props.loading) return;
  emit('update:open', false);
}

function confirm() {
  if (props.loading) return;
  emit('confirm');
}

function onKeydown(e) {
  if (e.key === 'Escape') close();
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown);
    } else {
      document.removeEventListener('keydown', onKeydown);
    }
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-dialog-fade">
      <div v-if="open" class="confirm-dialog" role="presentation" @mousedown.self="close">
        <div
          class="confirm-dialog__panel"
          role="alertdialog"
          aria-modal="true"
          :aria-label="title"
        >
          <h2 class="confirm-dialog__title">{{ title }}</h2>
          <p v-if="description" class="confirm-dialog__description">{{ description }}</p>

          <div class="confirm-dialog__actions">
            <button
              type="button"
              class="confirm-dialog__btn confirm-dialog__btn--ghost"
              :disabled="loading"
              @click="close"
            >
              {{ cancelLabel }}
            </button>
            <button
              type="button"
              class="confirm-dialog__btn"
              :class="danger ? 'confirm-dialog__btn--danger' : 'confirm-dialog__btn--primary'"
              :disabled="loading"
              @click="confirm"
            >
              {{ loading ? 'Đang xử lý…' : confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.confirm-dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.confirm-dialog__panel {
  width: 100%;
  max-width: 24rem;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  padding: var(--space-5);
}

.confirm-dialog__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.confirm-dialog__description {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.confirm-dialog__actions {
  margin-top: var(--space-5);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.confirm-dialog__btn {
  padding: var(--space-2) var(--space-4);
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, opacity 0.15s ease;
}

.confirm-dialog__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.confirm-dialog__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.confirm-dialog__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.confirm-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.confirm-dialog__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.confirm-dialog__btn--danger {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.confirm-dialog__btn--danger:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.confirm-dialog-fade-enter-active,
.confirm-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.confirm-dialog-fade-enter-from,
.confirm-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 480px) {
  .confirm-dialog__panel {
    padding: var(--space-4);
  }
}
</style>
