<script setup>
//
// Nút + menu chế độ xem (Danh sách / Kanban / Lịch). Dùng trên hàng tab
// hoặc thanh lịch (toolbar) — chỉ một instance được mount tại một thời điểm.
// Option Kanban nằm trong collapse, chỉ bung khi chọn mục Kanban.
//
import { ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  toolbar: { type: Boolean, default: false },
  isList: { type: Boolean, default: false },
  isKanban: { type: Boolean, default: false },
  isCalendar: { type: Boolean, default: false },
  kanbanGroupBy: { type: String, default: 'status' },
  triggerLabel: { type: String, required: true },
  triggerIcon: { type: String, default: 'layoutList' },
});

const emit = defineEmits(['toggle', 'select-list', 'select-calendar', 'select-kanban']);

const kanbanOpen = ref(false);

const kanbanItems = [
  { value: 'status', icon: 'layoutGrid', label: 'Theo trạng thái' },
  { value: 'assignees', icon: 'users', label: 'Theo người thực hiện' },
  { value: 'project', icon: 'layers', label: 'Theo dự án' },
  { value: 'priority', icon: 'bookmark', label: 'Theo mức độ ưu tiên' },
  { value: 'type', icon: 'gitBranch', label: 'Theo loại' },
];

watch(
  () => props.open,
  (isOpen) => {
    kanbanOpen.value = Boolean(isOpen && props.isKanban);
  },
);

function toggleKanban() {
  kanbanOpen.value = !kanbanOpen.value;
}
</script>

<template>
  <div
    id="task-view-mode-root"
    class="task-view-mode"
    :class="{ 'task-view-mode--toolbar': props.toolbar }"
  >
    <button
      type="button"
      class="task-view-mode__trigger"
      aria-haspopup="menu"
      :aria-expanded="open"
      aria-label="Chế độ xem"
      @click.stop="emit('toggle')"
    >
      <AppIcon :name="triggerIcon" :size="15" />
      <span>{{ triggerLabel }}</span>
      <AppIcon name="chevronDown" :size="14" />
    </button>
    <div v-if="open" class="task-view-mode__menu" role="menu" @click.stop>
      <button
        type="button"
        class="task-view-mode__item"
        :class="{ 'task-view-mode__item--on': isList }"
        role="menuitem"
        @click="emit('select-list')"
      >
        <AppIcon name="layoutList" :size="15" />
        <span>Danh sách</span>
        <AppIcon v-if="isList" name="check" :size="14" />
      </button>

      <div class="task-view-mode__kanban">
        <button
          type="button"
          class="task-view-mode__item"
          :class="{
            'task-view-mode__item--on': isKanban,
            'task-view-mode__item--open': kanbanOpen,
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
            class="task-view-mode__chevron"
            :class="{ 'task-view-mode__chevron--open': kanbanOpen }"
          />
        </button>
        <div class="task-view-mode__collapse" :class="{ 'task-view-mode__collapse--open': kanbanOpen }">
          <div class="task-view-mode__collapse-inner">
            <div class="task-view-mode__sub" role="menu">
              <button
                v-for="item in kanbanItems"
                :key="item.value"
                type="button"
                class="task-view-mode__item task-view-mode__item--sub"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === item.value }"
                role="menuitem"
                @click="emit('select-kanban', item.value)"
              >
                <AppIcon :name="item.icon" :size="15" />
                <span>{{ item.label }}</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === item.value" name="check" :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <button
        type="button"
        class="task-view-mode__item"
        :class="{ 'task-view-mode__item--on': isCalendar }"
        role="menuitem"
        @click="emit('select-calendar')"
      >
        <AppIcon name="calendar" :size="15" />
        <span>Lịch</span>
        <AppIcon v-if="isCalendar" name="check" :size="14" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.task-view-mode {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
}

.task-view-mode__trigger {
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

.task-view-mode--toolbar .task-view-mode__trigger {
  height: 1.875rem;
  padding: 0 0.7rem;
  border-radius: var(--radius-sm);
  background: transparent;
  box-shadow: none;
}

.task-view-mode--toolbar .task-view-mode__trigger:hover,
.task-view-mode--toolbar .task-view-mode__trigger[aria-expanded='true'] {
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-primary);
}

.task-view-mode--toolbar .task-view-mode__menu {
  right: 0;
  left: auto;
}

.task-view-mode__trigger:hover,
.task-view-mode__trigger[aria-expanded='true'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.task-view-mode__menu {
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

.task-view-mode__kanban {
  display: flex;
  flex-direction: column;
}

.task-view-mode__item {
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

.task-view-mode__item span {
  flex: 1;
  min-width: 0;
}

.task-view-mode__item:hover,
.task-view-mode__item--open {
  background: var(--color-surface-muted);
}

.task-view-mode__item--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.task-view-mode__item--on:hover,
.task-view-mode__item--on.task-view-mode__item--open {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-view-mode__chevron {
  flex-shrink: 0;
  color: var(--color-text-muted);
  transition: transform 200ms ease;
}

.task-view-mode__item--on .task-view-mode__chevron,
.task-view-mode__item--open .task-view-mode__chevron {
  color: currentColor;
}

.task-view-mode__chevron--open {
  transform: rotate(-180deg);
}

.task-view-mode__collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 200ms ease;
}

.task-view-mode__collapse--open {
  grid-template-rows: 1fr;
}

.task-view-mode__collapse-inner {
  min-height: 0;
  overflow: hidden;
}

.task-view-mode__sub {
  display: flex;
  flex-direction: column;
  margin: 0.125rem 0.375rem 0.375rem 1.125rem;
  padding: 0.125rem 0 0.125rem 0.25rem;
  box-shadow: inset 2px 0 0 var(--color-border);
}

.task-view-mode__item--sub {
  padding: 0.4375rem 0.5rem;
  font-weight: 400;
}

.task-view-mode__item--sub.task-view-mode__item--on {
  font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
  .task-view-mode__chevron,
  .task-view-mode__collapse {
    transition: none;
  }
}
</style>
