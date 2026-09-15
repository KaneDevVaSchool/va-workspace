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
