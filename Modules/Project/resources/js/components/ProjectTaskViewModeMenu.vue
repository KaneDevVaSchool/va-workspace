<script setup>
//
// Menu chế độ xem tab Công việc (chi tiết dự án): Tất cả / Việc cha / Kanban / Gantt.
// Cùng chrome với TaskViewModeMenu trên danh sách công việc.
//
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { PROJECT_TASK_VIEWS } from '../constants/task.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  mode: { type: String, default: 'all' },
  kanbanGroupBy: { type: String, default: 'status' },
  triggerLabel: { type: String, required: true },
  triggerIcon: { type: String, default: 'layoutList' },
});

const emit = defineEmits(['toggle', 'select', 'select-kanban']);

const kanbanOpen = ref(false);

const kanbanItems = [
  { value: 'status', icon: 'layoutGrid', label: 'Theo trạng thái' },
  { value: 'assignees', icon: 'users', label: 'Theo người thực hiện' },
  { value: 'priority', icon: 'bookmark', label: 'Theo mức độ ưu tiên' },
  { value: 'type', icon: 'gitBranch', label: 'Theo loại' },
];

watch(
  () => props.open,
  (isOpen) => {
    kanbanOpen.value = Boolean(isOpen && props.mode === 'kanban');
  },
);

function toggleKanban() {
  kanbanOpen.value = !kanbanOpen.value;
}
</script>

<template>
  <div id="project-task-view-mode" class="ptvm">
    <button
      type="button"
      class="ptvm__trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      aria-label="Chế độ xem"
      @click.stop="emit('toggle')"
    >
      <AppIcon :name="triggerIcon" :size="15" />
      <span>{{ triggerLabel }}</span>
      <AppIcon name="chevronDown" :size="14" />
    </button>
    <div v-if="open" class="ptvm__menu" role="menu" @click.stop>
      <template v-for="item in PROJECT_TASK_VIEWS" :key="item.key">
        <div v-if="item.key === 'kanban'" class="ptvm__kanban">
          <button
            type="button"
            class="ptvm__item"
            :class="{
              'ptvm__item--on': mode === 'kanban',
              'ptvm__item--open': kanbanOpen,
            }"
            role="menuitem"
            aria-haspopup="menu"
            :aria-expanded="kanbanOpen"
            @click="toggleKanban"
          >
            <AppIcon name="layoutGrid" :size="15" />
            <span>Kanban</span>
            <AppIcon
              name="chevronDown"
              :size="14"
              class="ptvm__chevron"
              :class="{ 'ptvm__chevron--open': kanbanOpen }"
            />
          </button>
          <div class="ptvm__collapse" :class="{ 'ptvm__collapse--open': kanbanOpen }">
            <div class="ptvm__collapse-inner">
              <div class="ptvm__sub" role="menu">
                <button
                  v-for="sub in kanbanItems"
                  :key="sub.value"
                  type="button"
                  class="ptvm__item ptvm__item--sub"
                  :class="{ 'ptvm__item--on': mode === 'kanban' && kanbanGroupBy === sub.value }"
                  role="menuitem"
                  @click="emit('select-kanban', sub.value)"
                >
                  <AppIcon :name="sub.icon" :size="15" />
                  <span>{{ sub.label }}</span>
                  <AppIcon v-if="mode === 'kanban' && kanbanGroupBy === sub.value" name="check" :size="14" />
                </button>
              </div>
            </div>
          </div>
        </div>
        <button
          v-else
          type="button"
          class="ptvm__item"
          :class="{ 'ptvm__item--on': mode === item.key }"
          role="menuitem"
          @click="emit('select', item.key)"
        >
          <AppIcon :name="item.icon" :size="15" />
          <span>{{ item.label }}</span>
          <AppIcon v-if="mode === item.key" name="check" :size="14" />
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.ptvm {
  position: relative;
  z-index: 12;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
}

.ptvm__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 100%;
  padding: 0.5625rem 0.75rem 0.5rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 1px 0 0 var(--color-border);
}

.ptvm__trigger:hover,
.ptvm__trigger[aria-expanded='true'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.ptvm__menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 16.5rem;
  padding: 0.25rem;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.ptvm__kanban {
  display: flex;
  flex-direction: column;
}

.ptvm__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.ptvm__item span {
  flex: 1;
  min-width: 0;
}

.ptvm__item:hover,
.ptvm__item--open {
  background: var(--color-surface-muted);
}

.ptvm__item--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.ptvm__item--on:hover,
.ptvm__item--on.ptvm__item--open {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.ptvm__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  transition: transform 200ms ease;
}

.ptvm__item--on .ptvm__chevron,
.ptvm__item--open .ptvm__chevron {
  color: currentColor;
}

.ptvm__chevron--open {
  transform: rotate(-180deg);
}

.ptvm__collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 200ms ease;
}

.ptvm__collapse--open {
  grid-template-rows: 1fr;
}

.ptvm__collapse-inner {
  min-height: 0;
  overflow: hidden;
}

.ptvm__sub {
  display: flex;
  flex-direction: column;
  margin: 0.125rem 0.375rem 0.375rem 1.125rem;
  padding: 0.125rem 0 0.125rem 0.25rem;
  box-shadow: inset 2px 0 0 var(--color-border);
}

.ptvm__item--sub {
  padding: 0.4375rem 0.5rem;
  font-weight: 400;
}

.ptvm__item--sub.ptvm__item--on {
  font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
  .ptvm__chevron,
  .ptvm__collapse {
    transition: none;
  }
}
</style>
