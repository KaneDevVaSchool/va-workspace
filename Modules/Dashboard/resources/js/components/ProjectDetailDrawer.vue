<script setup>
import AppIcon from '@/components/AppIcon.vue';

defineProps({
  project: { type: Object, default: null }, // null = đang tải
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const healthLabel = { good: 'Tốt', warning: 'Cần chú ý', risk: 'Nguy cơ' };
</script>

<template>
  <aside class="project-drawer" aria-label="Chi tiết dự án">
    <div class="project-drawer__head">
      <h2 class="project-drawer__title">Chi tiết dự án</h2>
      <button type="button" class="project-drawer__close" aria-label="Đóng" @click="emit('close')">
        <AppIcon name="close" :size="16" />
      </button>
    </div>

    <div v-if="loading || !project" class="project-drawer__loading">Đang tải…</div>

    <template v-else>
      <div class="project-drawer__lead">
        <span :class="`project-drawer__dot project-drawer__dot--${project.health}`" aria-hidden="true"></span>
        <div>
          <span class="project-drawer__lead-name">{{ project.name }}</span>
          <p class="project-drawer__lead-code">{{ project.code }}</p>
        </div>
      </div>

      <div class="project-drawer__rows">
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Trạng thái</span>
          <span class="project-drawer__row-value">{{ project.status_label }}</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Sức khoẻ</span>
          <span class="project-drawer__row-value">{{ healthLabel[project.health] }}</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Tiến độ</span>
          <span class="project-drawer__row-value">{{ project.progress_percent === null ? 'Chưa có dữ liệu' : project.progress_percent + '%' }}</span>
        </div>
        <div v-if="project.progress_percent !== null" class="project-drawer__progress-bar">
          <div class="project-drawer__progress-fill" :style="{ width: project.progress_percent + '%' }"></div>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Ngày bắt đầu</span>
          <span class="project-drawer__row-value">{{ project.start_date ?? '—' }}</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Hạn hoàn thành</span>
          <span class="project-drawer__row-value">{{ project.end_date ?? '—' }}</span>
        </div>
        <div v-if="project.days_overdue > 0" class="project-drawer__row">
          <span class="project-drawer__row-label">Số ngày trễ</span>
          <span class="project-drawer__row-value">{{ project.days_overdue }} ngày</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Công việc</span>
          <span class="project-drawer__row-value">{{ project.tasks_completed }}/{{ project.tasks_total }} hoàn thành</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Người phụ trách</span>
          <span class="project-drawer__row-value">{{ project.lead_name ?? '—' }}</span>
        </div>
        <div class="project-drawer__row">
          <span class="project-drawer__row-label">Phòng ban sở hữu</span>
          <span class="project-drawer__row-value">{{ project.owner_department_name ?? '—' }}</span>
        </div>
        <div v-if="project.executing_departments?.length" class="project-drawer__row">
          <span class="project-drawer__row-label">Phòng ban thực hiện</span>
          <span class="project-drawer__row-value">{{ project.executing_departments.join(', ') }}</span>
        </div>
      </div>
    </template>
  </aside>
</template>

<style scoped>
.project-drawer {
  flex-shrink: 0;
  width: 28rem;
  max-width: 100%;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.project-drawer__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

.project-drawer__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.project-drawer__close {
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

.project-drawer__close:hover {
  background: var(--color-surface-muted);
}

.project-drawer__loading {
  color: var(--color-text-muted);
  padding: var(--space-5) 0;
  text-align: center;
}

.project-drawer__lead {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  margin-bottom: var(--space-3);
}

.project-drawer__dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
  margin-top: 6px;
  flex-shrink: 0;
}

.project-drawer__dot--good { background: var(--color-success); }
.project-drawer__dot--warning { background: var(--color-warning); }
.project-drawer__dot--risk { background: var(--color-danger); }

.project-drawer__lead-name {
  font-weight: 700;
  color: var(--color-text);
}

.project-drawer__lead-code {
  margin: 2px 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.project-drawer__rows {
  display: flex;
  flex-direction: column;
}

.project-drawer__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.project-drawer__row:last-child {
  box-shadow: none;
}

.project-drawer__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.project-drawer__row-label::after {
  content: ':';
}

.project-drawer__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.project-drawer__progress-bar {
  height: 6px;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
  margin: 0 0 var(--space-1);
}

.project-drawer__progress-fill {
  height: 100%;
  background: var(--color-primary);
  border-radius: var(--radius-full);
  transition: width 0.5s ease;
}

@media (max-width: 1024px) {
  .project-drawer {
    width: 100%;
    max-height: 42%;
  }
}
</style>
