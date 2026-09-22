<script setup>
//
// Chế độ xem "Bảng giai đoạn" — mỗi giai đoạn (phase) là 1 cột, công việc là
// thẻ kéo-thả được giữa các cột để đổi giai đoạn (parent_id). Task chưa gán
// giai đoạn nằm ở cột đầu "Chưa thuộc giai đoạn nào".
//
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import {
  groupProjectTasksByPhase,
  taskPriorityLabel,
  taskPriorityTone,
  taskStatusLabel,
} from '../constants/task.js';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  filter: { type: String, default: 'all' },
  query: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'context-menu', 'task-moved']);

const movingIds = ref(new Set());

const columns = computed(() => {
  const groups = groupProjectTasksByPhase(props.tree, { filter: props.filter, query: props.query });
  return groups.map((group) => {
    const idsInGroup = new Set(group.tasks.map((task) => task.id));
    return {
      key: group.key,
      phaseId: group.id,
      title: group.title,
      code: group.code,
      // Chỉ hiện việc gốc của giai đoạn (không thụt cấp) — việc con đi theo
      // việc cha khi kéo, tránh cột dài rối vì lẫn cả cây con.
      tasks: group.tasks.filter((task) => !idsInGroup.has(task.parent_id)),
    };
  });
});

async function onDrop(event, column) {
  const { added } = event;
  if (!added) return;
  const task = added.element;
  if (!task?.id) return;
  const newParentId = column.phaseId || null;
  if (task.parent_id === newParentId) return;

  movingIds.value = new Set([...movingIds.value, task.id]);
  try {
    await window.axios.put(`/api/project/tasks/${task.id}`, { parent_id: newParentId });
    showClientToast('success', `Đã chuyển "${task.title}" sang ${column.title}.`);
    emit('task-moved');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không chuyển được công việc sang giai đoạn này.');
    emit('task-moved');
  } finally {
    const next = new Set(movingIds.value);
    next.delete(task.id);
    movingIds.value = next;
  }
}
</script>

<template>
  <div class="pboard hide-scrollbar">
    <p v-if="loading" class="pboard__empty">Đang tải công việc…</p>
    <p v-else-if="!columns.length" class="pboard__empty">Dự án chưa có giai đoạn hoặc công việc nào.</p>
    <div v-else class="pboard__cols">
      <div v-for="column in columns" :key="column.key" class="pboard__col">
        <header class="pboard__col-head">
          <span class="pboard__col-title">
            <span v-if="column.code" class="pboard__col-code">{{ column.code }}</span>
            {{ column.title }}
          </span>
          <span class="pboard__col-count">{{ column.tasks.length }}</span>
        </header>
        <draggable
          class="pboard__col-body hide-scrollbar"
          :list="column.tasks"
          group="phase-tasks"
          item-key="id"
          :animation="150"
          ghost-class="pboard__ghost"
          drag-class="pboard__dragging"
          @change="onDrop($event, column)"
        >
          <template #item="{ element: task }">
            <article
              class="pboard__card"
              :class="{ 'pboard__card--busy': movingIds.has(task.id) }"
              @click="emit('open', task)"
              @contextmenu.prevent.stop="emit('context-menu', $event, task)"
            >
              <span class="pboard__grip" aria-hidden="true">
                <AppIcon name="gripVertical" :size="14" />
              </span>
              <header class="pboard__card-head">
                <span v-if="task.code" class="pboard__card-code">{{ task.code }}</span>
              </header>
              <h3 class="pboard__card-title">{{ task.title }}</h3>
              <div v-if="task.priority" class="pboard__card-labels">
                <span class="pboard__prio" :class="`pboard__prio--${taskPriorityTone(task.priority)}`">
                  {{ taskPriorityLabel(task.priority) }}
                </span>
              </div>
              <footer class="pboard__card-foot">
                <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                <span class="pboard__card-status">{{ taskStatusLabel(task.status) }}</span>
              </footer>
            </article>
          </template>
        </draggable>
        <p v-if="!column.tasks.length" class="pboard__col-empty">Kéo công việc vào đây.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pboard {
  flex: 1;
  min-height: 0;
  display: flex;
  overflow-x: auto;
  overflow-y: hidden;
  background: var(--color-surface-muted);
}

.pboard__empty {
  margin: auto;
  padding: 2rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.pboard__cols {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  height: 100%;
  padding: 0.75rem;
}

.pboard__col {
  flex: 0 0 18rem;
  width: 18rem;
  display: flex;
  flex-direction: column;
  max-height: 100%;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.pboard__col-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.75rem 0.875rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.pboard__col-title {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
  min-width: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.pboard__col-code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-weight: 600;
}

.pboard__col-count {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.pboard__col-body {
  flex: 1;
  min-height: 4rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.625rem;
  overflow-y: auto;
}

.pboard__col-empty {
  margin: 0 0.625rem 0.625rem;
  padding: 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-align: center;
}

.pboard__card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.625rem 0.625rem 0.625rem 1.625rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.pboard__card--busy {
  opacity: 0.6;
  pointer-events: none;
}

.pboard__grip {
  position: absolute;
  top: 0.625rem;
  left: 0.375rem;
  color: var(--color-text-muted);
  cursor: var(--cursor-grab);
}

.pboard__card-head {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.pboard__card-code {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.pboard__card-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
}

.pboard__card-labels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.pboard__prio {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.pboard__prio::before {
  content: '';
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.pboard__prio--danger::before {
  background: var(--color-danger);
}

.pboard__prio--info::before {
  background: var(--color-info);
}

.pboard__prio--tertiary::before {
  background: var(--color-tertiary);
}

.pboard__prio--gold::before {
  background: var(--color-gold);
}

.pboard__card-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.pboard__card-status {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.pboard__ghost {
  opacity: 0.5;
}

.pboard__dragging {
  cursor: var(--cursor-grabbing);
}
</style>
