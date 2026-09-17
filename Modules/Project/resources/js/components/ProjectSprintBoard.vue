<script setup>
//
// Chế độ xem "Theo Sprint" trên tab Công việc — mỗi Sprint là 1 khối gập/mở,
// bên trong là bảng công việc (nhóm phụ theo danh mục cha gần nhất nếu có,
// ví dụ "Development"). Sprint là bảng riêng (Modules/Project/App/Models/
// Sprint.php), tự fetch qua API, không nằm trong cây WBS như phase.
//
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { computeExpectedProgress } from '@/lib/progress';
import { showClientToast } from '@/lib/clientToast';
import {
  groupProjectTasksBySprint,
  groupSprintTasksByCategory,
  sprintStatusLabel,
  sprintStatusTone,
} from '../constants/sprint.js';
import {
  TASK_STATUSES,
  formatTaskDate,
  taskPriorityLabel,
  taskPriorityTone,
  taskStatusLabel,
  taskStatusTone,
  taskTypeLabel,
} from '../constants/task.js';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
  filter: { type: String, default: 'all' },
  filterLabel: { type: String, default: 'Tổng công việc' },
  query: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['add-tasks']);
const router = useRouter();
const sprints = ref([]);
const sprintsLoading = ref(false);
const collapsedSprints = ref(new Set());
const statusUpdatingIds = ref(new Set());
const statusOptions = TASK_STATUSES.filter((item) => item.value);

async function loadSprints() {
  if (!props.project?.id) return;
  sprintsLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/project/${props.project.id}/sprints`);
    sprints.value = data.sprints ?? [];
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách sprint.');
  } finally {
    sprintsLoading.value = false;
  }
}

defineExpose({ reload: loadSprints });

onMounted(loadSprints);
watch(() => props.project?.id, loadSprints);
// Cây WBS được tải lại sau mỗi lần thêm/sửa sprint (ProjectDetail.vue
// onTasksChanged) — nhân tiện làm mới luôn danh sách sprint để board cập
// nhật cột mới mà không cần props riêng báo hiệu.
watch(() => props.tree, loadSprints);

const columns = computed(() =>
  groupProjectTasksBySprint(props.tree, sprints.value, { filter: props.filter, query: props.query }),
);

function isCollapsed(key) {
  return collapsedSprints.value.has(key);
}

function toggleSprint(key) {
  const next = new Set(collapsedSprints.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedSprints.value = next;
}

function categoryGroups(col) {
  return groupSprintTasksByCategory(col.tasks);
}

function openTask(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

function formatDate(value) {
  return formatTaskDate(value) === '—' ? '' : formatTaskDate(value);
}

function dateRangeLabel(item) {
  if (!item.start_date && !item.end_date) return '';
  return `${formatDate(item.start_date) || '—'} – ${formatDate(item.end_date) || '—'}`;
}

async function changeTaskStatus(task, status) {
  if (!status || status === task.status) return;
  const previous = task.status;
  task.status = status;
  const busy = new Set(statusUpdatingIds.value);
  busy.add(task.id);
  statusUpdatingIds.value = busy;
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${task.id}`, { status });
    Object.assign(task, data.task);
    showClientToast('success', `Đã chuyển sang ${taskStatusLabel(status)}.`);
  } catch (error) {
    task.status = previous;
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được trạng thái.');
  } finally {
    const next = new Set(statusUpdatingIds.value);
    next.delete(task.id);
    statusUpdatingIds.value = next;
  }
}
</script>

<template>
  <div class="psb hide-scrollbar">
    <p v-if="loading || sprintsLoading" class="psb__empty">Đang tải…</p>
    <p v-else-if="!columns.length" class="psb__empty">Dự án chưa có sprint nào.</p>
    <section v-else v-for="col in columns" :key="col.id ?? 'no-sprint'" class="psb__sprint">
      <header class="psb__head" @click="toggleSprint(col.id ?? 'no-sprint')">
        <button
          type="button"
          class="psb__toggle"
          :aria-label="isCollapsed(col.id ?? 'no-sprint') ? 'Mở rộng sprint' : 'Thu gọn sprint'"
          @click.stop="toggleSprint(col.id ?? 'no-sprint')"
        >
          <AppIcon
            name="chevronRight"
            :size="16"
            class="psb__toggle-icon"
            :class="{ 'psb__toggle-icon--open': !isCollapsed(col.id ?? 'no-sprint') }"
          />
        </button>

        <div class="psb__head-main">
          <span v-if="col.phase?.title" class="psb__phase">{{ col.phase.title }}</span>
          <span class="psb__title">{{ col.name }}</span>
        </div>

        <span v-if="col.status" class="psb__status">
          <span class="psb__dot" :class="`psb__dot--${sprintStatusTone(col.status)}`" />
          {{ sprintStatusLabel(col.status) }}
        </span>

        <span v-if="dateRangeLabel(col)" class="psb__range">{{ dateRangeLabel(col) }}</span>

        <span class="psb__count">{{ col.tasks.length }} công việc</span>

        <span v-if="col.estimatedHours || col.worklogHours" class="psb__hours">
          Kế hoạch {{ col.estimatedHours || 0 }}h · Thực tế {{ col.worklogHours || 0 }}h
        </span>

        <span v-if="col.avgProgress != null" class="psb__progress">
          <span class="psb__mini">
            <span class="psb__mini-fill" :style="{ width: `${col.avgProgress}%` }" />
          </span>
          <span class="psb__progress-value">{{ col.avgProgress }}%</span>
        </span>

        <button
          v-if="canEdit && col.id"
          type="button"
          class="psb__add"
          @click.stop="emit('add-tasks', { sprintId: col.id })"
        >
          <AppIcon name="plus" :size="14" />
          Thêm việc
        </button>
      </header>

      <div v-show="!isCollapsed(col.id ?? 'no-sprint')" class="psb__body">
        <p v-if="!col.tasks.length" class="psb__col-empty">
          Không có công việc nào.
          <button
            v-if="canEdit && col.id"
            type="button"
            class="psb__add psb__add--inline"
            @click="emit('add-tasks', { sprintId: col.id })"
          >
            <AppIcon name="plus" :size="14" />
            Thêm việc
          </button>
        </p>
        <div v-else class="psb__table-wrap hide-scrollbar">
          <table class="psb__table">
            <thead>
              <tr>
                <th class="psb__th--name">Công việc</th>
                <th>Người làm</th>
                <th>Trạng thái</th>
                <th>Ưu tiên</th>
                <th>Bắt đầu</th>
                <th>Hạn</th>
                <th>Giờ KH</th>
                <th>Giờ TT</th>
                <th>Tiến độ</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in categoryGroups(col)" :key="group.key">
                <tr v-if="group.title" class="psb__group-row">
                  <td colspan="9">{{ group.title }}</td>
                </tr>
                <tr
                  v-for="task in group.tasks"
                  :key="task.id"
                  class="psb__row"
                  @dblclick="openTask(task)"
                >
                  <td class="psb__td--name">
                    <span v-if="task.code" class="psb__code">{{ task.code }}</span>
                    <button type="button" class="psb__link" @click="openTask(task)">{{ task.title }}</button>
                  </td>
                  <td>
                    <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                    <span v-else>—</span>
                  </td>
                  <td>
                    <select
                      v-if="canEdit"
                      class="psb__status-select"
                      :class="`psb__status-select--${taskStatusTone(task.status)}`"
                      :disabled="statusUpdatingIds.has(task.id)"
                      :value="task.status"
                      @click.stop
                      @change="changeTaskStatus(task, $event.target.value)"
                    >
                      <option v-for="item in statusOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <span v-else class="psb__status-text">
                      <span class="psb__dot" :class="`psb__dot--${taskStatusTone(task.status)}`" />
                      {{ taskStatusLabel(task.status) }}
                    </span>
                  </td>
                  <td>
                    <span v-if="task.priority" class="psb__status-text">
                      <span class="psb__dot" :class="`psb__dot--${taskPriorityTone(task.priority)}`" />
                      {{ taskPriorityLabel(task.priority) }}
                    </span>
                    <span v-else>—</span>
                  </td>
                  <td>{{ formatDate(task.start_date) || '—' }}</td>
                  <td>{{ formatDate(task.end_date) || '—' }}</td>
                  <td>{{ task.estimated_hours ?? '—' }}</td>
                  <td>{{ task.worklog_hours || 0 }}</td>
                  <td>
                    <DualProgressBar
                      v-if="task.progress_percent != null"
                      :actual="task.progress_percent"
                      :expected="computeExpectedProgress(task.start_date, task.end_date)"
                      size="sm"
                    />
                    <span v-else>—</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.psb {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.75rem;
  overflow-y: auto;
  background: var(--color-surface-muted, var(--color-surface));
}

.psb__empty {
  margin: 2rem auto;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.psb__sprint {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border-radius: var(--radius-lg, 0.75rem);
  box-shadow: 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.psb__head {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.875rem;
  cursor: pointer;
}

.psb__toggle {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.psb__toggle-icon {
  transition: transform 0.15s ease;
}

.psb__toggle-icon--open {
  transform: rotate(90deg);
}

.psb__head-main {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 8rem;
}

.psb__phase {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.psb__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.psb__status,
.psb__status-text {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: var(--color-text);
  white-space: nowrap;
}

.psb__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}
.psb__dot--primary { background: var(--color-primary); }
.psb__dot--success { background: var(--color-success); }
.psb__dot--info { background: var(--color-info); }
.psb__dot--gold,
.psb__dot--warning { background: var(--color-gold); }
.psb__dot--danger { background: var(--color-danger); }
.psb__dot--tertiary { background: var(--color-tertiary); }
.psb__dot--umber { background: var(--color-umber); }
.psb__dot--neutral { background: var(--color-text-muted); }

.psb__range {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.psb__count {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.psb__hours {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.psb__progress {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}

.psb__mini {
  width: 6rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-border);
  overflow: hidden;
}

.psb__mini-fill {
  display: block;
  height: 100%;
  background: var(--color-primary);
}

.psb__progress-value {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.psb__add {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.25rem;
  padding: 0.3125rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.psb__add:hover {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.psb__add--inline {
  margin-left: 0.5rem;
}

.psb__body {
  flex-shrink: 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__col-empty {
  margin: 0;
  padding: 0.875rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  text-align: center;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.psb__table-wrap {
  overflow-x: auto;
}

.psb__table {
  width: 100%;
  min-width: 52rem;
  border-collapse: collapse;
}

.psb__table thead th {
  padding: 0.5rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__th--name {
  min-width: 16rem;
}

.psb__group-row td {
  padding: 0.375rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--color-surface-muted);
}

.psb__row {
  cursor: pointer;
}

.psb__row:hover td {
  filter: brightness(0.97);
}

.psb__row td {
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  color: var(--color-text);
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__td--name {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: normal;
}

.psb__code {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.psb__link {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.psb__link:hover {
  color: var(--color-primary);
}

.psb__status-select {
  padding: 0.25rem 0.5rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  cursor: pointer;
}

.psb__status-select:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .psb {
    padding: 0.5rem;
  }
  .psb__head {
    gap: 0.5rem;
  }
  .psb__progress {
    margin-left: 0;
  }
}

@media (max-width: 480px) {
  .psb__head {
    flex-direction: column;
    align-items: flex-start;
  }
  .psb__progress {
    width: 100%;
  }
  .psb__mini {
    flex: 1;
  }
}
</style>
