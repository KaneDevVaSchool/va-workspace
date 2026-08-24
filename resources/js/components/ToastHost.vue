<script setup>
//
// Toast nổi góc trên-phải, thay cho các <p class="...error"> tĩnh rải rác
// trong page (VD Login.vue). Lắng nghe event 'va-client-toast' qua
// showClientToast()/subscribeClientToast() (resources/js/lib/clientToast.js)
// — gọi được từ bất kỳ component/store nào, không cần provide/inject.
//
// UI dạng "soft-tint": nền nhạt pha màu trạng thái + chữ/icon đậm cùng tông,
// thay vì nền đặc bão hòa cao (dịu mắt hơn, đỡ chói) — xem resources/css/theme.css
// (--color-*-tint-bg/fg/border).
//
// Hành vi: lỗi ở lại tới khi người dùng đóng tay (cần đọc/xử lý), các variant
// khác tự đóng sau một khoảng thời gian, có progress-bar đếm ngược và tạm dừng
// khi hover/focus để không mất toast lúc đang đọc. Âm thanh qua Web Audio API
// — xem lib/toastSound.js.
//
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { subscribeClientToast } from '../lib/clientToast';
import { playToastSound } from '../lib/toastSound';

const DEFAULT_DISMISS_MS = {
  success: 5_000,
  warning: 7_000,
  info: 6_000,
};
const MAX_VISIBLE_TOASTS = 4;

const VARIANT_META = {
  success: { label: 'Thành công', role: 'status' },
  error: { label: 'Lỗi', role: 'alert' },
  warning: { label: 'Cảnh báo', role: 'alert' },
  info: { label: 'Thông tin', role: 'status' },
};

const toasts = ref([]);
let seq = 0;
// Mỗi toast tự đóng giữ 1 bản ghi { duration, remaining, startedAt, timer, raf, progress }
// để hỗ trợ pause/resume khi hover và progress-bar mượt.
const autoClose = reactive(new Map());
let unsubscribe = null;

function clearAutoClose(id) {
  const entry = autoClose.get(id);
  if (!entry) return;
  if (entry.timer != null) window.clearTimeout(entry.timer);
  if (entry.raf != null) window.cancelAnimationFrame(entry.raf);
  autoClose.delete(id);
}

function dismiss(id) {
  clearAutoClose(id);
  toasts.value = toasts.value.filter((t) => t.id !== id);
}

function tickProgress(id) {
  const entry = autoClose.get(id);
  if (!entry) return;
  const elapsed = performance.now() - entry.startedAt;
  const ratio = Math.max(0, 1 - elapsed / entry.duration);
  entry.progress = ratio * 100;
  if (ratio > 0) {
    entry.raf = window.requestAnimationFrame(() => tickProgress(id));
  }
}

function startAutoClose(id, duration) {
  const entry = autoClose.get(id);
  if (!entry) return;
  entry.startedAt = performance.now();
  entry.timer = window.setTimeout(() => dismiss(id), entry.remaining);
  entry.raf = window.requestAnimationFrame(() => tickProgress(id));
}

function pauseAutoClose(id) {
  const entry = autoClose.get(id);
  if (!entry || entry.timer == null) return;
  window.clearTimeout(entry.timer);
  if (entry.raf != null) window.cancelAnimationFrame(entry.raf);
  entry.timer = null;
  entry.raf = null;
  entry.remaining = Math.max(0, entry.remaining - (performance.now() - entry.startedAt));
}

function resumeAutoClose(id) {
  const entry = autoClose.get(id);
  if (!entry || entry.timer != null || entry.remaining <= 0) return;
  startAutoClose(id, entry.remaining);
}

function push(variant, message, duration) {
  seq += 1;
  const id = seq;
  const next = [...toasts.value, { id, variant, message }];
  const removed = next.slice(0, Math.max(0, next.length - MAX_VISIBLE_TOASTS));
  for (const toast of removed) clearAutoClose(toast.id);
  toasts.value = next.slice(-MAX_VISIBLE_TOASTS);

  playToastSound(variant === 'success' ? 'success' : variant === 'error' ? 'error' : 'neutral');

  // Lỗi cần người dùng đọc/xử lý nên chỉ đóng thủ công. Các variant khác tự
  // đóng, nhưng đủ lâu để đọc nội dung — và tạm dừng khi hover/focus.
  if (variant !== 'error') {
    const finalDuration = duration ?? DEFAULT_DISMISS_MS[variant] ?? DEFAULT_DISMISS_MS.info;
    autoClose.set(id, { duration: finalDuration, remaining: finalDuration, startedAt: 0, timer: null, raf: null, progress: 100 });
    startAutoClose(id, finalDuration);
  }
}

function progressOf(id) {
  return autoClose.get(id)?.progress ?? null;
}

onMounted(() => {
  unsubscribe = subscribeClientToast((detail) => push(detail.variant, detail.message, detail.duration));
});

onBeforeUnmount(() => {
  unsubscribe?.();
  toasts.value.forEach((t) => clearAutoClose(t.id));
});
</script>

<template>
  <Teleport to="body">
    <div class="toast-host" aria-live="polite" aria-relevant="additions">
      <TransitionGroup name="toast-host-item" tag="div" class="toast-host__stack">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :role="VARIANT_META[toast.variant]?.role ?? 'status'"
          class="toast-host__item"
          :class="`toast-host__item--${toast.variant}`"
          tabindex="0"
          @mouseenter="pauseAutoClose(toast.id)"
          @mouseleave="resumeAutoClose(toast.id)"
          @focusin="pauseAutoClose(toast.id)"
          @focusout="resumeAutoClose(toast.id)"
        >
          <div class="toast-host__row">
            <span class="toast-host__icon" aria-hidden="true">
              <svg v-if="toast.variant === 'success'" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              <svg v-else-if="toast.variant === 'error'" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <svg v-else-if="toast.variant === 'warning'" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
            </span>
            <div class="toast-host__body">
              <p class="toast-host__label">{{ VARIANT_META[toast.variant]?.label ?? 'Thông báo' }}</p>
              <p class="toast-host__message">{{ toast.message }}</p>
            </div>
            <button
              type="button"
              class="toast-host__close"
              aria-label="Đóng thông báo"
              @click="dismiss(toast.id)"
            >
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
          <div v-if="progressOf(toast.id) !== null" class="toast-host__progress-track" aria-hidden="true">
            <div class="toast-host__progress-bar" :style="{ width: progressOf(toast.id) + '%' }" />
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-host {
  position: fixed;
  inset: 0 0 auto 0;
  z-index: 400;
  display: flex;
  justify-content: flex-end;
  padding: var(--space-3);
  pointer-events: none;
}

.toast-host__stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
  width: 100%;
  max-width: 24rem;
}

.toast-host__item {
  pointer-events: auto;
  position: relative;
  width: 100%;
  overflow: hidden;
  border: 1px solid transparent;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  outline: none;
}

.toast-host__item:focus-visible {
  box-shadow: var(--shadow-lg), 0 0 0 2px var(--color-primary-300);
}

.toast-host__row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-3);
}

.toast-host__item--success {
  background: var(--color-success-tint-bg);
  border: 1px solid var(--color-success-tint-border);
  color: var(--color-success-tint-fg);
}

.toast-host__item--error {
  background: var(--color-danger-tint-bg);
  border: 1px solid var(--color-danger-tint-border);
  color: var(--color-danger-tint-fg);
}

.toast-host__item--warning {
  background: var(--color-warning-tint-bg);
  border: 1px solid var(--color-warning-tint-border);
  color: var(--color-warning-tint-fg);
}

.toast-host__item--info {
  background: var(--color-info-tint-bg);
  border: 1px solid var(--color-info-tint-border);
  color: var(--color-info-tint-fg);
}

.toast-host__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  margin-top: 0.125rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, currentColor 14%, transparent);
}

.toast-host__body {
  flex: 1;
  min-width: 0;
  padding-top: 0.0625rem;
}

.toast-host__label {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.85;
}

.toast-host__message {
  margin: var(--space-1) 0 0;
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.45;
  color: var(--color-text);
  /* Giới hạn nội dung dài để toast không phình quá cao. */
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.toast-host__close {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-1);
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: currentColor;
  opacity: 0.65;
  cursor: pointer;
  transition: background-color 0.15s ease, opacity 0.15s ease;
}

.toast-host__close:hover {
  background: color-mix(in srgb, currentColor 14%, transparent);
  opacity: 1;
}

.toast-host__progress-track {
  height: 3px;
  background: color-mix(in srgb, currentColor 12%, transparent);
}

.toast-host__progress-bar {
  height: 100%;
  background: currentColor;
  opacity: 0.55;
  /* Cập nhật liên tục qua rAF (inline style), không cần transition riêng —
     tránh giật khi tạm dừng/tiếp tục lúc hover. */
}

/* Transition khi thêm/xóa/di chuyển toast trong stack. */
.toast-host-item-enter-active {
  transition: transform 0.24s ease-out, opacity 0.24s ease-out;
}

.toast-host-item-leave-active {
  transition: transform 0.18s ease-in, opacity 0.18s ease-in;
  position: absolute;
}

.toast-host-item-move {
  transition: transform 0.2s ease;
}

.toast-host-item-enter-from {
  transform: translateY(-10px) scale(0.98);
  opacity: 0;
}

.toast-host-item-leave-to {
  transform: translateX(12px);
  opacity: 0;
}

@media (max-width: 480px) {
  .toast-host {
    padding: var(--space-2);
  }

  .toast-host__stack {
    max-width: 100%;
  }
}
</style>
