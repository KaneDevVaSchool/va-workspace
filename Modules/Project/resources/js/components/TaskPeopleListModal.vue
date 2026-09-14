<script setup>
import { onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, required: true },
  title: { type: String, default: 'Người thực hiện' },
  people: { type: Array, default: () => [] },
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
    <Transition name="task-people-list-fade">
      <div
        v-if="open"
        class="task-people-list"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="task-people-list__panel"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="task-people-list__header">
            <h2 class="task-people-list__title">{{ title }}</h2>
            <button type="button" class="task-people-list__close" aria-label="Đóng" @click="close">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <div class="task-people-list__body hide-scrollbar">
            <p v-if="!people.length" class="task-people-list__empty">Chưa có người thực hiện.</p>
            <ul v-else class="task-people-list__people">
              <li v-for="user in people" :key="user.id" class="task-people-list__person">
                <img
                  v-if="user.avatar_url"
                  class="task-people-list__avatar"
                  :src="user.avatar_url"
                  :alt="`Ảnh đại diện của ${user.name}`"
                />
                <div v-else class="task-people-list__avatar task-people-list__avatar--placeholder">
                  {{ user.name?.charAt(0)?.toUpperCase() ?? '?' }}
                </div>
                <div class="task-people-list__person-info">
                  <div class="task-people-list__person-name">{{ user.name }}</div>
                  <div v-if="user.department?.name" class="task-people-list__person-meta">
                    {{ user.department.name }}
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.task-people-list {
  position: fixed;
  inset: 0;
  z-index: 310;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, #000000 45%, transparent);
}

.task-people-list__panel {
  width: min(26rem, calc(100vw - 2rem));
  height: min(32rem, calc(100vh - 2.5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.task-people-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.task-people-list__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.task-people-list__close {
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

.task-people-list__close:hover {
  background: var(--color-border);
}

.task-people-list__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.task-people-list__empty {
  margin: 0;
  padding: var(--space-5);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.task-people-list__people {
  list-style: none;
  margin: 0;
  padding: var(--space-2) 0;
}

.task-people-list__person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-4);
}

.task-people-list__avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.task-people-list__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.task-people-list__person-info {
  flex: 1;
  min-width: 0;
}

.task-people-list__person-name {
  font-weight: 600;
  color: var(--color-text);
}

.task-people-list__person-meta {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.task-people-list-fade-enter-active,
.task-people-list-fade-leave-active {
  transition: opacity 0.15s ease;
}

.task-people-list-fade-enter-from,
.task-people-list-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .task-people-list-fade-enter-active,
  .task-people-list-fade-leave-active {
    transition: none;
  }
}
</style>
