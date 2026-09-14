<script setup>
import { statusColor } from '../utils/chartColors';

defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

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
            <th class="my-task-table__col-date">Hạn</th>
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
            <td class="my-task-table__col-date" :class="{ 'my-task-table__col-date--overdue': task.is_overdue }">
              {{ formatDate(task.end_date) }}
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
.my-task-table__col-date {
  white-space: nowrap;
}

.my-task-table__col-date--overdue {
  color: var(--color-danger);
  font-weight: 600;
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
