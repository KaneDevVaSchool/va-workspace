<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, required: true },
  title: { type: String, default: 'Dự án' },
  projects: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

function close() {
  emit('close');
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
    <Transition name="employee-projects-modal-fade">
      <div
        v-if="open"
        class="employee-projects-modal"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="employee-projects-modal__panel"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="employee-projects-modal__header">
            <h2 class="employee-projects-modal__title">{{ title }}</h2>
            <button type="button" class="employee-projects-modal__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <div class="employee-projects-modal__body hide-scrollbar">
            <p v-if="!projects.length" class="employee-projects-modal__empty">Chưa có dự án nào.</p>
            <ul v-else class="employee-projects-modal__list">
              <li v-for="(project, index) in projects" :key="project.id ?? index" class="employee-projects-modal__item">
                <span class="employee-projects-modal__dot" aria-hidden="true"></span>
                <span class="employee-projects-modal__name">{{ project.name }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.employee-projects-modal {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.employee-projects-modal__panel {
  width: min(26rem, calc(100vw - 2rem));
  height: min(28rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.employee-projects-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.employee-projects-modal__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.employee-projects-modal__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text);
  cursor: pointer;
}

.employee-projects-modal__close:hover {
  background: var(--color-border);
}

.employee-projects-modal__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.employee-projects-modal__empty {
  margin: 0;
  padding: var(--space-5);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.employee-projects-modal__list {
  list-style: none;
  margin: 0;
  padding: var(--space-2) 0;
}

.employee-projects-modal__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.employee-projects-modal__item:last-child {
  box-shadow: none;
}

.employee-projects-modal__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
}

.employee-projects-modal__name {
  color: var(--color-text);
  font-size: 0.875rem;
}

.employee-projects-modal-fade-enter-active,
.employee-projects-modal-fade-leave-active {
  transition: opacity 0.15s ease;
}

.employee-projects-modal-fade-enter-from,
.employee-projects-modal-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .employee-projects-modal-fade-enter-active,
  .employee-projects-modal-fade-leave-active {
    transition: none;
  }
}
</style>
