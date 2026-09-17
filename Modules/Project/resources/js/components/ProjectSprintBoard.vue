<script setup>
//
// Chế độ xem "Theo Sprint" trên tab Công việc — board, mỗi cột là 1 sprint
// (tên giai đoạn cha hiện nhỏ phía trên tên sprint). Sprint là bảng riêng
// (Modules/Project/App/Models/Sprint.php), tự fetch qua API, không nằm
// trong cây WBS như phase. Chưa hỗ trợ kéo-thả — đổi sprint của 1 công
// việc qua form sửa công việc.
//
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import { groupProjectTasksBySprint, sprintStatusLabel, sprintStatusTone } from '../constants/sprint.js';
import { taskPriorityLabel, taskTypeLabel } from '../constants/task.js';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
  filter: { type: String, default: 'all' },
  filterLabel: { type: String, default: 'Tổng công việc' },
  query: { type: String, default: '' },
});

const router = useRouter();
const sprints = ref([]);
const sprintsLoading = ref(false);

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

function openTask(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function dateRangeLabel(item) {
  if (!item.start_date && !item.end_date) return '';
  return `${formatDate(item.start_date)} – ${formatDate(item.end_date)}`;
}
</script>

<template>
  <div class="psb hide-scrollbar">
    <p v-if="loading || sprintsLoading" class="psb__empty">Đang tải…</p>
    <p v-else-if="!columns.length" class="psb__empty">Dự án chưa có sprint nào.</p>
    <div v-else v-for="col in columns" :key="col.id ?? 'no-sprint'" class="psb__col">
      <header class="psb__head">
        <div class="psb__head-main">
          <span v-if="col.phase?.title" class="psb__phase">{{ col.phase.title }}</span>
          <span class="psb__title">{{ col.name }}</span>
          <span v-if="col.status" class="psb__status">
            <span class="psb__dot" :class="`psb__dot--${sprintStatusTone(col.status)}`" />
            {{ sprintStatusLabel(col.status) }}
          </span>
        </div>
        <span class="psb__count">{{ col.tasks.length }}</span>
      </header>
      <p v-if="dateRangeLabel(col)" class="psb__range">{{ dateRangeLabel(col) }}</p>
      <div v-if="col.avgProgress != null" class="psb__progress">
        <span class="psb__mini">
          <span class="psb__mini-fill" :style="{ width: `${col.avgProgress}%` }" />
        </span>
        <span class="psb__progress-value">{{ col.avgProgress }}%</span>
      </div>
      <div class="psb__body hide-scrollbar">
        <article
          v-for="task in col.tasks"
          :key="task.id"
          class="psb__card"
          @click="openTask(task)"
        >
          <header class="psb__card-head">
            <span v-if="task.code" class="psb__code">{{ task.code }}</span>
            <span class="psb__type">{{ taskTypeLabel(task.type) }}</span>
          </header>
          <h3 class="psb__card-title">{{ task.title }}</h3>
          <div v-if="task.priority && task.priority !== 'low'" class="psb__labels">
            <span class="psb__prio">{{ taskPriorityLabel(task.priority) }}</span>
          </div>
          <div v-if="task.assignee?.name" class="psb__card-foot">
            <UserAvatarTip :user="task.assignee" label="Người thực hiện" />
            <span>{{ task.assignee.name }}</span>
          </div>
        </article>
        <p v-if="!col.tasks.length" class="psb__col-empty">Không có công việc nào.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.psb {
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem;
  overflow-x: auto;
  overflow-y: hidden;
  background: var(--color-surface-muted, var(--color-surface));
}

.psb__empty {
  margin: 2rem auto;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.psb__col {
  flex: 0 0 17rem;
  width: 17rem;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg, 0.75rem);
  overflow: hidden;
}

.psb__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.75rem 0.875rem 0.5rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__head-main {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.psb__phase {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.psb__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.psb__status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
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
.psb__dot--umber { background: var(--color-umber); }
.psb__dot--neutral { background: var(--color-text-muted); }

.psb__count {
  flex-shrink: 0;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.psb__range {
  margin: 0;
  padding: 0 0.875rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.psb__progress {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.875rem;
}

.psb__mini {
  flex: 1;
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
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.psb__body {
  flex: 1;
  min-height: 4rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.5rem 0.625rem 0.75rem;
  overflow-y: auto;
}

.psb__col-empty {
  margin: 0.5rem 0;
  text-align: center;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.psb__card {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.625rem 0.75rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  cursor: pointer;
}

.psb__card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.psb__card-head {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.psb__code {
  font-weight: 700;
}

.psb__type {
  margin-left: auto;
}

.psb__card-title {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
}

.psb__labels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.psb__prio {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.psb__card-foot {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

@media (max-width: 768px) {
  .psb__col {
    flex-basis: 15rem;
    width: 15rem;
  }
}

@media (max-width: 480px) {
  .psb__col {
    flex-basis: 13rem;
    width: 13rem;
  }
}
</style>
