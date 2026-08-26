<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatSocialTime } from '../lib/formatSocialTime.js';
import { sanitizeSocialHtml } from '../lib/sanitizeSocialHtml.js';
import { vSocialStickers } from '../lib/socialStickers.js';

const props = defineProps({
  open: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  versions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'content-click']);

function close() {
  emit('close');
}

function onContentClick(event) {
  emit('content-click', event);
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function bindPage() {
  document.addEventListener('keydown', onKey);
  document.body.style.overflow = 'hidden';
}

function unbindPage() {
  document.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      bindPage();
      return;
    }
    unbindPage();
  },
);

onBeforeUnmount(unbindPage);
</script>

<template>
  <Teleport to="body">
    <Transition name="history-dialog-fade">
      <div
        v-if="open"
        class="history-dialog"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="history-dialog__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="history-dialog-title"
        >
          <div class="history-dialog__head">
            <span class="history-dialog__icon" aria-hidden="true">
              <AppIcon name="clock" :size="16" :stroke-width="1.75" />
            </span>
            <h2 id="history-dialog-title" class="history-dialog__title">Lịch sử chỉnh sửa</h2>
            <button
              type="button"
              class="history-dialog__close"
              aria-label="Đóng"
              @click="close"
            >
              <AppIcon name="close" :size="15" />
            </button>
          </div>

          <div class="history-dialog__body hide-scrollbar">
            <p v-if="loading" class="history-dialog__empty">Đang tải lịch sử...</p>
            <ol v-else class="history-dialog__list">
              <li v-for="version in versions" :key="version.id ?? 'current'" class="history-dialog__item">
                <div class="history-dialog__meta">
                  <span class="history-dialog__label">
                    {{ version.is_current ? 'Hiện tại' : 'Phiên bản trước' }}
                  </span>
                  <time :datetime="version.published_at">{{ formatSocialTime(version.published_at) }}</time>
                </div>
                <div
                  v-if="version.content"
                  class="history-dialog__content"
                  v-html="sanitizeSocialHtml(version.content)"
                  v-social-stickers
                  @click="onContentClick"
                ></div>
                <p v-else class="history-dialog__empty">Không có nội dung.</p>
              </li>
            </ol>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.history-dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.history-dialog__panel {
  width: min(64rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.history-dialog__head {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.history-dialog__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.85rem;
  height: 1.85rem;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.history-dialog__title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.25;
}

.history-dialog__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.history-dialog__close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.history-dialog__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

.history-dialog__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.history-dialog__item {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.history-dialog__meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.history-dialog__label {
  font-weight: 700;
  color: var(--color-text);
}

.history-dialog__content {
  color: var(--color-text);
  font-size: 0.875rem;
  word-break: break-word;
}

.history-dialog__empty {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.history-dialog__content :deep(p) {
  margin: 0 0 var(--space-2) 0;
}

.history-dialog__content :deep(p:last-child) {
  margin-bottom: 0;
}

.history-dialog__content :deep(ul),
.history-dialog__content :deep(ol) {
  margin: 0 0 var(--space-2) 0;
  padding-left: 1.25rem;
}

.history-dialog__content :deep(a) {
  color: var(--color-info);
}

.history-dialog__content :deep(.mention),
.history-dialog__content :deep(.hashtag) {
  color: var(--color-primary);
  font-weight: 600;
  cursor: pointer;
}

.history-dialog-fade-enter-active,
.history-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.history-dialog-fade-enter-from,
.history-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .history-dialog {
    padding: var(--space-3);
  }

  .history-dialog__panel {
    width: 100%;
    height: calc(100vh - 1.5rem);
  }
}

@media (prefers-reduced-motion: reduce) {
  .history-dialog-fade-enter-active,
  .history-dialog-fade-leave-active {
    transition: none;
  }
}
</style>
