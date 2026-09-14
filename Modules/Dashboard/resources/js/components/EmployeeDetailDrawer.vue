<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  employee: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const taskStatusLabels = {
  not_started: 'Chưa bắt đầu',
  in_progress: 'Đang thực hiện',
  under_review: 'Đang đánh giá',
  on_hold: 'Tạm dừng',
  completed: 'Hoàn thành',
  cancelled: 'Đã huỷ',
};
</script>

<template>
  <aside class="employee-drawer" aria-label="Chi tiết nhân viên">
    <div class="employee-drawer__head">
      <h2 class="employee-drawer__title">Chi tiết nhân viên</h2>
      <button type="button" class="employee-drawer__close" aria-label="Đóng" @click="emit('close')">
        <AppIcon name="close" :size="16" />
      </button>
    </div>

    <div v-if="loading || !employee" class="employee-drawer__loading">Đang tải…</div>

    <template v-else>
      <div class="employee-drawer__lead">
        <img v-if="employee.avatar_url" :src="employee.avatar_url" alt="" class="employee-drawer__avatar" />
        <span v-else class="employee-drawer__avatar employee-drawer__avatar--placeholder">{{ employee.name?.charAt(0) }}</span>
        <div>
          <span class="employee-drawer__lead-name">{{ employee.name }}</span>
          <p class="employee-drawer__lead-team">{{ employee.team_name ?? employee.department_name ?? '—' }}</p>
        </div>
      </div>

      <div class="employee-drawer__rows">
        <div class="employee-drawer__row">
          <span class="employee-drawer__row-label">Tiến độ trung bình</span>
          <span class="employee-drawer__row-value">{{ employee.average_progress_percent === null ? 'Chưa có dữ liệu' : employee.average_progress_percent + '%' }}</span>
        </div>
        <div v-if="employee.average_progress_percent !== null" class="employee-drawer__progress-bar">
          <div class="employee-drawer__progress-fill" :style="{ width: employee.average_progress_percent + '%' }"></div>
        </div>
        <div class="employee-drawer__row">
          <span class="employee-drawer__row-label">Dự án đang tham gia</span>
          <span class="employee-drawer__row-value">{{ (employee.projects ?? []).map((p) => p.name).join(', ') || '—' }}</span>
        </div>
      </div>

      <h3 class="employee-drawer__section-title">Công việc đang làm ({{ employee.tasks?.length ?? 0 }})</h3>
      <div class="employee-drawer__tasks">
        <p v-if="!employee.tasks?.length" class="employee-drawer__empty">Chưa có công việc nào.</p>
        <div v-for="task in employee.tasks" v-else :key="task.id" class="employee-drawer__task">
          <span class="employee-drawer__task-title">{{ task.title }}</span>
          <span class="employee-drawer__task-meta">
            {{ taskStatusLabels[task.status] ?? task.status }}
            <template v-if="task.progress_percent !== null"> · {{ task.progress_percent }}%</template>
            <template v-if="task.project_name"> · {{ task.project_name }}</template>
          </span>
        </div>
      </div>
    </template>
  </aside>
</template>

<style scoped>
.employee-drawer {
  flex-shrink: 0;
  width: 28rem;
  max-width: 100%;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.employee-drawer__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

.employee-drawer__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.employee-drawer__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.employee-drawer__close:hover {
  background: var(--color-surface-muted);
}

.employee-drawer__loading {
  color: var(--color-text-muted);
  padding: var(--space-5) 0;
  text-align: center;
}

.employee-drawer__lead {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  margin-bottom: var(--space-3);
}

.employee-drawer__avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-full);
  object-fit: cover;
  flex-shrink: 0;
}

.employee-drawer__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 700;
}

.employee-drawer__lead-name {
  font-weight: 700;
  color: var(--color-text);
}

.employee-drawer__lead-team {
  margin: 2px 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.employee-drawer__rows {
  display: flex;
  flex-direction: column;
  margin-bottom: var(--space-4);
}

.employee-drawer__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.employee-drawer__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.employee-drawer__row-label::after {
  content: ':';
}

.employee-drawer__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.employee-drawer__progress-bar {
  height: 6px;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
  margin: 0 0 var(--space-1);
}

.employee-drawer__progress-fill {
  height: 100%;
  background: var(--color-primary);
  border-radius: var(--radius-full);
  transition: width 0.5s ease;
}

.employee-drawer__section-title {
  margin: 0 0 var(--space-2);
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.employee-drawer__tasks {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.employee-drawer__task {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-2);
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
}

.employee-drawer__task-title {
  font-size: 0.8125rem;
  color: var(--color-text);
}

.employee-drawer__task-meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.employee-drawer__empty {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

@media (max-width: 1024px) {
  .employee-drawer {
    width: 100%;
    max-height: 42%;
  }
}
</style>
