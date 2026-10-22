<script setup>
import { statusColor } from '../utils/chartColors';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  acceptingTaskId: { type: Number, default: null },
});

const emit = defineEmits(['accept-task']);

function dotStyle(status) {
  return { background: statusColor(status) };
}

function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('vi-VN');
}

function formatProgress(value) {
  return value === null || value === undefined ? '—' : `${value}%`;
}

function formatTimeRange(task) {
  if (!task.start_time && !task.due_time) return '—';
  if (task.start_time && task.due_time) return `${task.start_time} – ${task.due_time}`;
  return task.start_time || task.due_time;
}

function onAccept(task) {
  if (props.acceptingTaskId) return;
  emit('accept-task', task);
}
</script>

<template>
  <div class="my-task-table">
    <div v-if="!loading && rows.length === 0" class="my-task-table__empty">Bạn chưa có công việc nào được giao.</div>

    <div v-else class="my-task-table__wrap hide-scrollbar">
      <table class="my-task-table__table">
        <thead>
          <tr>
            <th class="my-task-table__col-title">Công việc</th>
            <th class="my-task-table__col-project">Dự án</th>
            <th class="my-task-table__col-status">Trạng thái</th>
            <th class="my-task-table__col-progress">Tiến độ</th>
            <th class="my-task-table__col-assigner">Người giao</th>
            <th class="my-task-table__col-time">Giờ làm</th>
            <th class="my-task-table__col-date">Hạn</th>
            <th class="my-task-table__col-confirm">Xác nhận</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in rows" :key="task.id">
            <td class="my-task-table__col-title">{{ task.title }}</td>
            <td class="my-task-table__col-project">{{ task.project_name ?? '—' }}</td>
            <td class="my-task-table__col-status">
              <span class="my-task-table__dot" :style="dotStyle(task.status)" aria-hidden="true"></span>
              {{ task.status_label }}
            </td>
            <td class="my-task-table__col-progress">{{ formatProgress(task.progress_percent) }}</td>
            <td class="my-task-table__col-assigner">{{ task.creator_name ?? '—' }}</td>
            <td class="my-task-table__col-time">{{ formatTimeRange(task) }}</td>
            <td class="my-task-table__col-date" :class="{ 'my-task-table__col-date--overdue': task.is_overdue }">
              {{ formatDate(task.end_date) }}
            </td>
            <td class="my-task-table__col-confirm">
              <button
                v-if="task.can_accept"
                type="button"
                class="my-task-table__confirm-btn"
                :disabled="acceptingTaskId === task.id"
                @click="onAccept(task)"
              >
                Xác nhận
              </button>
              <span v-else class="my-task-table__confirm-done">Đã xác nhận</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.my-task-table {
  min-width: 0;
}

.my-task-table__empty {
  padding: var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.my-task-table__wrap {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 26rem;
}

.my-task-table__table {
  width: 100%;
  min-width: 32rem;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.my-task-table__table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--color-surface);
  text-align: left;
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text-muted);
  font-weight: 600;
  white-space: nowrap;
}

.my-task-table__table td {
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  color: var(--color-text);
  vertical-align: middle;
}

.my-task-table__col-progress,
.my-task-table__col-assigner,
.my-task-table__col-time,
.my-task-table__col-date,
.my-task-table__col-confirm {
  white-space: nowrap;
}

.my-task-table__col-date--overdue {
  color: var(--color-danger);
  font-weight: 600;
}

.my-task-table__confirm-btn {
  display: inline-flex;
  align-items: center;
  height: 1.75rem;
  padding: 0 var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.my-task-table__confirm-btn:hover:not(:disabled) {
  background: var(--color-primary-700);
}

.my-task-table__confirm-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.my-task-table__confirm-done {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.my-task-table__dot {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  margin-right: var(--space-1);
}

@media (max-width: 480px) {
  .my-task-table__wrap {
    max-height: 20rem;
  }
}
</style>
