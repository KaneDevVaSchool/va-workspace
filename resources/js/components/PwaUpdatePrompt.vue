<script setup>
//
// Banner mảnh báo "có bản cập nhật mới" — khác PwaPushPrompt (xin quyền OS,
// che toàn màn hình). Ở đây không cần xác nhận gì nguy hiểm, chỉ 1 nút bấm
// để tải lại khi người dùng rảnh tay, tránh mất dữ liệu form đang nhập dở
// do tự động reload ngay khi có bản mới.
//
import { usePwaUpdate } from '../composables/usePwaUpdate';

const { updateAvailable, applyUpdate } = usePwaUpdate();
</script>

<template>
  <Teleport to="body">
    <Transition name="pwa-update-bar">
      <div v-if="updateAvailable" class="pwa-update-bar" role="status">
        <span class="pwa-update-bar__text">Có bản cập nhật mới</span>
        <button type="button" class="pwa-update-bar__btn" @click="applyUpdate">
          Tải lại
        </button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.pwa-update-bar {
  position: fixed;
  left: 50%;
  bottom: calc(var(--space-4) + var(--toast-shell-bottom-inset, 0px));
  transform: translateX(-50%);
  z-index: 1950;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) var(--space-4);
  border-radius: var(--radius-full);
  background: var(--color-text);
  color: #fff;
  box-shadow: var(--shadow-lg);
  max-width: calc(100vw - 2rem);
}

.pwa-update-bar__text {
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
}

.pwa-update-bar__btn {
  appearance: none;
  border: none;
  border-radius: var(--radius-full);
  padding: 0.5rem 1rem;
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
}

.pwa-update-bar__btn:active {
  opacity: 0.85;
}

.pwa-update-bar-enter-active,
.pwa-update-bar-leave-active {
  transition: opacity 0.22s ease, transform 0.26s cubic-bezier(0.32, 0.72, 0, 1);
}

.pwa-update-bar-enter-from,
.pwa-update-bar-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(0.5rem);
}
</style>
