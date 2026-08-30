<script setup>
//
// Card công việc trên lịch: nền tint theo trạng thái; tiêu đề tối đa
// hai dòng; thời gian và phòng ban tách dòng, phòng ban là pill nhẹ.
//
import UserAvatarTip from '@/components/UserAvatarTip.vue';

defineProps({
  task: { type: Object, required: true },
  time: { type: String, default: '' },
  department: { type: String, default: '' },
  toneClass: { type: String, default: '' },
});

const emit = defineEmits(['open']);
</script>

<template>
  <div
    class="task-cal__chip"
    :class="toneClass"
    role="button"
    tabindex="0"
    @click="emit('open', $event)"
    @keydown.enter.prevent="emit('open', $event)"
  >
    <span class="task-cal__chip-head">
      <span class="task-cal__chip-title">{{ task.title }}</span>
      <span v-if="task.assignee" class="task-cal__chip-who" @click.stop>
        <UserAvatarTip :user="task.assignee" label="Người thực hiện" />
      </span>
    </span>
    <span v-if="time" class="task-cal__chip-time">{{ time }}</span>
    <span v-if="department" class="task-cal__chip-dept">{{ department }}</span>
  </div>
</template>

<style scoped>
.task-cal__chip {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  flex-shrink: 0;
  gap: 0.28rem;
  width: 100%;
  min-width: 0;
  overflow: hidden;
  padding: 0.55rem 0.55rem 0.5rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  transition: box-shadow 0.12s ease, background-color 0.12s ease;
}

.task-cal__chip:hover,
.task-cal__chip:focus-visible {
  box-shadow: var(--shadow-md);
}

.task-cal__chip-head {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  min-width: 0;
}

.task-cal__chip-title {
  flex: 1;
  min-width: 0;
  display: -webkit-box;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.task-cal__chip-who {
  flex-shrink: 0;
  width: 1.375rem;
  height: 1.375rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  line-height: 0;
}

.task-cal__chip-who :deep(.user-avatar-tip) {
  display: flex;
  width: 1.375rem;
  height: 1.375rem;
}

.task-cal__chip-who :deep(.user-avatar-tip__btn),
.task-cal__chip-who :deep(.user-avatar-tip__avatar) {
  width: 1.375rem;
  height: 1.375rem;
}

.task-cal__chip-who :deep(.user-avatar-tip__avatar) {
  font-size: 0.5625rem;
}

.task-cal__chip-who :deep(.user-avatar-tip__btn:hover),
.task-cal__chip-who :deep(.user-avatar-tip__btn--open),
.task-cal__chip-who :deep(.user-avatar-tip__btn:focus-visible) {
  outline: 1px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 0;
}

.task-cal__chip-time {
  display: block;
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.task-cal__chip-dept {
  display: block;
  align-self: flex-start;
  max-width: 100%;
  padding: 0.12rem 0.4rem;
  border-radius: var(--radius-sm);
  background: var(--color-secondary-50);
  color: var(--color-secondary-700);
  font-size: 0.625rem;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.task-cal__chip--tertiary {
  background: var(--color-tertiary-surface);
}

.task-cal__chip--info {
  background: var(--color-info-tint-bg);
}

.task-cal__chip--gold {
  background: var(--color-gold-50);
}

.task-cal__chip--success {
  background: var(--color-success-tint-bg);
}

.task-cal__chip--umber {
  background: var(--color-umber-tint-bg);
}

.task-cal__chip--neutral {
  background: var(--color-surface);
}
</style>
