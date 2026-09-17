<script setup>
//
// 1 hàng <tr> của bảng công việc — dùng chung giữa bảng phẳng (Tất cả/Việc
// cha) và bảng nhóm theo giai đoạn trong ProjectTasksTab.vue, tránh lặp lại
// toàn bộ markup cell (pill trạng thái/ưu tiên, avatar, tiến độ...).
//
import AppIcon from '@/components/AppIcon.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { computeExpectedProgress } from '@/lib/progress';
import {
  formatTaskDate,
  taskCellText as cellText,
  taskPriorityLabel as priorityLabel,
  taskPriorityTone as priorityTone,
  taskStatusLabel as statusLabel,
  taskStatusTone as statusTone,
  taskTypeLabel as typeLabel,
  taskTypeTone as typeTone,
} from '../constants/task.js';

const props = defineProps({
  task: { type: Object, required: true },
  shownColumns: { type: Array, required: true },
  collapsedIds: { type: Set, required: true },
});

const emit = defineEmits(['open', 'context-menu', 'toggle-collapse']);
const formatDate = formatTaskDate;
</script>

<template>
  <tr class="ptasks__row" @dblclick="emit('open', task)" @contextmenu="emit('context-menu', $event, task)">
    <td
      v-for="col in shownColumns"
      :key="col.key"
      :class="{
        'ptasks__td--name': col.key === 'title',
        'ptasks__td--avatar': col.key === 'assignee' || col.key === 'creator' || col.key === 'manager',
      }"
    >
      <span v-if="col.key === 'code'" class="ptasks__pill ptasks__pill--code">{{ task.code || '—' }}</span>
      <span v-else-if="col.key === 'title'" class="ptasks__name">
        <span class="ptasks__name-row" :style="task.depth ? { paddingLeft: `${task.depth * 20}px` } : undefined">
          <button
            v-if="task.hasChildren"
            type="button"
            class="ptasks__tree"
            :aria-label="collapsedIds.has(task.id) ? 'Mở rộng công việc con' : 'Thu gọn công việc con'"
            @click.stop="emit('toggle-collapse', task.id)"
          >
            <AppIcon
              name="chevronRight"
              :size="12"
              class="ptasks__tree-icon"
              :class="{ 'ptasks__tree-icon--open': !collapsedIds.has(task.id) }"
            />
          </button>
          <button type="button" class="ptasks__link" @click.stop="emit('open', task)">{{ task.title }}</button>
        </span>
      </span>
      <span v-else-if="col.key === 'assignee'">
        <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
        <span v-else>—</span>
      </span>
      <span v-else-if="col.key === 'status'" class="ptasks__pill" :class="`ptasks__pill--${statusTone(task.status)}`">
        <span class="ptasks__dot" :class="`ptasks__dot--${statusTone(task.status)}`" />
        {{ statusLabel(task.status) }}
      </span>
      <span v-else-if="col.key === 'priority'" class="ptasks__pill" :class="`ptasks__pill--${priorityTone(task.priority)}`">
        <span class="ptasks__dot" :class="`ptasks__dot--${priorityTone(task.priority)}`" />
        {{ priorityLabel(task.priority) }}
      </span>
      <span v-else-if="col.key === 'start_date'" class="ptasks__pill ptasks__pill--date">{{ formatDate(task.start_date) }}</span>
      <span v-else-if="col.key === 'end_date'" class="ptasks__pill ptasks__pill--date">{{ formatDate(task.end_date) }}</span>
      <span v-else-if="col.key === 'actual_start_date'" class="ptasks__pill ptasks__pill--date">{{ formatDate(task.actual_start_date) }}</span>
      <span v-else-if="col.key === 'actual_end_date'" class="ptasks__pill ptasks__pill--date">{{ formatDate(task.actual_end_date) }}</span>
      <span v-else-if="col.key === 'progress_percent'" class="ptasks__progress">
        <DualProgressBar
          v-if="task.progress_percent != null"
          :actual="task.progress_percent"
          :expected="computeExpectedProgress(task.start_date, task.end_date)"
          size="sm"
        />
        <span v-else>—</span>
      </span>
      <span v-else-if="col.key === 'type'" class="ptasks__pill" :class="`ptasks__pill--${typeTone(task.type)}`">
        {{ typeLabel(task.type) }}
      </span>
      <span v-else-if="col.key === 'creator'">
        <UserAvatarTip v-if="task.creator" :user="task.creator" label="Người tạo" />
        <span v-else>—</span>
      </span>
      <span v-else-if="col.key === 'created_at'">{{ formatDateTime(task.created_at) }}</span>
      <span v-else-if="col.key === 'updated_at'">{{ formatDateTime(task.updated_at) }}</span>
      <span v-else-if="col.key === 'parent'">{{ task.parent?.title || '—' }}</span>
      <span v-else-if="col.key === 'attachments_count'">{{ task.attachments_count || 0 }}</span>
      <span v-else-if="col.key === 'estimated_hours'">{{ task.estimated_hours ?? '—' }}</span>
      <span v-else-if="col.key === 'worklog_hours'">{{ task.worklog_hours || 0 }}</span>
      <span v-else-if="col.key === 'manager'">
        <UserAvatarTip v-if="task.manager" :user="task.manager" label="Người quản lý" />
        <span v-else>—</span>
      </span>
      <span v-else-if="col.key === 'accepted_by'">{{ task.accepted_by_user?.name || '—' }}</span>
      <span v-else-if="col.key === 'weight'">{{ task.weight != null ? `${task.weight}%` : '—' }}</span>
      <span v-else-if="col.key === 'is_overdue'" class="ptasks__pill" :class="`ptasks__pill--${task.is_overdue ? 'danger' : 'success'}`">
        <span class="ptasks__dot" :class="`ptasks__dot--${task.is_overdue ? 'danger' : 'success'}`" />
        {{ task.is_overdue ? 'Quá hạn' : 'Đúng hạn' }}
      </span>
      <span v-else-if="col.key === 'variance_days'">{{ formatVarianceDays(task.variance_days) }}</span>
      <span v-else>{{ cellText(task, col.key) }}</span>
    </td>
  </tr>
</template>

<style scoped>
.ptasks__row {
  cursor: pointer;
}

.ptasks__row:hover td {
  filter: brightness(0.97);
}

.ptasks__row td {
  text-align: center;
}

.ptasks__td--name {
  overflow: visible;
  white-space: normal;
  text-align: left;
}

.ptasks__td--avatar {
  overflow: visible;
}

.ptasks__name-row {
  display: flex;
  align-items: flex-start;
  gap: 0.25rem;
}

.ptasks__tree {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.125rem;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.ptasks__tree-icon {
  transition: transform 0.15s ease;
}

.ptasks__tree-icon--open {
  transform: rotate(90deg);
}

.ptasks__link {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.ptasks__link:hover {
  color: var(--color-primary);
}

.ptasks__pill {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.5625rem;
  background: var(--pill-bg, var(--color-surface-muted));
  color: var(--pill-fg, var(--color-text));
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 600;
}

.ptasks__pill--primary { --pill-bg: var(--color-primary-50); --pill-fg: var(--color-primary-900); }
.ptasks__pill--tertiary { --pill-bg: var(--color-tertiary-50); --pill-fg: var(--color-tertiary-800); }
.ptasks__pill--gold { --pill-bg: var(--color-gold-50); --pill-fg: var(--color-gold-800); }
.ptasks__pill--success { --pill-bg: var(--color-success-tint-bg); --pill-fg: var(--color-success-tint-fg); }
.ptasks__pill--info { --pill-bg: var(--color-info-tint-bg); --pill-fg: var(--color-info-tint-fg); }
.ptasks__pill--warning { --pill-bg: var(--color-warning-tint-bg); --pill-fg: var(--color-warning-tint-fg); }
.ptasks__pill--danger { --pill-bg: var(--color-danger-tint-bg); --pill-fg: var(--color-danger-tint-fg); }
.ptasks__pill--violet { --pill-bg: var(--color-tertiary-surface); --pill-fg: var(--color-tertiary); }
.ptasks__pill--umber { --pill-bg: var(--color-umber-surface); --pill-fg: var(--color-umber-700); }
.ptasks__pill--neutral,
.ptasks__pill--code,
.ptasks__pill--date {
  --pill-bg: var(--color-surface-muted);
  --pill-fg: var(--color-text);
}

.ptasks__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}
.ptasks__dot--primary { background: var(--color-primary); }
.ptasks__dot--success { background: var(--color-success); }
.ptasks__dot--info { background: var(--color-info); }
.ptasks__dot--gold,
.ptasks__dot--warning { background: var(--color-gold); }
.ptasks__dot--danger { background: var(--color-danger); }
.ptasks__dot--tertiary { background: var(--color-tertiary); }
.ptasks__dot--umber { background: var(--color-umber); }

.ptasks__progress {
  display: inline-flex;
  align-items: center;
}
</style>
