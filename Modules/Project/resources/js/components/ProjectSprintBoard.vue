<script setup>
//
// Chế độ xem "Theo Sprint" trên tab Công việc — mỗi Sprint là 1 khối gập/mở,
// bên trong là bảng công việc (nhóm phụ theo danh mục cha gần nhất nếu có).
// Việc con thụt lề dưới cha, sắp theo lịch; mã nằm trên tên.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { computeExpectedProgress } from '@/lib/progress';
import { showClientToast } from '@/lib/clientToast';
import {
  formatTaskScheduleRange,
  groupProjectTasksBySprint,
  groupSprintsByPhase,
  groupSprintTasksByCategory,
  sprintStatusLabel,
  sprintStatusTone,
  taskRangeInSprint,
} from '../constants/sprint.js';
import {
  TASK_STATUSES,
  taskPriorityDisplay,
  taskPriorityTone,
  taskStatusLabel,
  taskStatusTone,
} from '../constants/task.js';

const COL_COUNT = 8;

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
  filter: { type: String, default: 'all' },
  filterLabel: { type: String, default: 'Tổng công việc' },
  query: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['add-tasks', 'context-menu']);
const router = useRouter();
const boardRef = ref(null);
const sprints = ref([]);
const sprintsLoading = ref(false);
const collapsedSprints = ref(new Set());
const collapsedIds = ref(new Set());
const statusUpdatingIds = ref(new Set());
const statusOptions = TASK_STATUSES.filter((item) => item.value);
const actionMenu = reactive({ id: null, top: 0, left: 0 });

useDragScroll(boardRef, { axis: 'x', closest: '.psb__table-wrap' });

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

onMounted(() => {
  loadSprints();
  document.addEventListener('pointerdown', onDocPointerDown, true);
  document.addEventListener('keydown', onDocKeydown);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointerDown, true);
  document.removeEventListener('keydown', onDocKeydown);
});
watch(() => props.project?.id, loadSprints);
watch(() => props.tree, loadSprints);

const columns = computed(() =>
  groupProjectTasksBySprint(props.tree, sprints.value, { filter: props.filter, query: props.query }),
);

const phaseLayout = computed(() => groupSprintsByPhase(props.tree, columns.value));
const boardSections = computed(() => {
  const searching = Boolean(String(props.query || '').trim()) || (props.filter && props.filter !== 'all');
  let sections = phaseLayout.value.bundles.map((bundle) => ({ ...bundle, kind: 'phase' }));
  if (searching) {
    sections = sections.filter((section) => section.sprints.length > 0);
  }
  if (phaseLayout.value.unphased.length) {
    sections.push({
      kind: 'loose',
      key: '__unphased__',
      id: null,
      title: null,
      sprints: phaseLayout.value.unphased,
    });
  }
  return sections;
});
const hasBoard = computed(() => boardSections.value.length > 0);

function sprintKey(col) {
  return col.id ?? 'no-sprint';
}

function isCollapsed(col) {
  return collapsedSprints.value.has(sprintKey(col));
}

function toggleSprint(col) {
  const key = sprintKey(col);
  const next = new Set(collapsedSprints.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedSprints.value = next;
}

function categoryGroups(col) {
  return groupSprintTasksByCategory(col.tasks, collapsedIds.value);
}

function toggleTaskCollapse(id) {
  const next = new Set(collapsedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  collapsedIds.value = next;
}

function openTask(task) {
  if (!task?.id) return;
  closeActionMenu();
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

function addTasks(col, parent = null) {
  if (!col?.id) return;
  closeActionMenu();
  emit('add-tasks', {
    sprintId: col.id,
    parentId: parent?.id || null,
    parent: parent || null,
  });
}

function addTasksBySprint() {
  closeActionMenu();
  emit('add-tasks', { sprintId: null, parentId: null, parent: null });
}

function findActionContext() {
  const id = actionMenu.id;
  if (!id) return null;
  const col = columns.value.find((item) => item.tasks.some((task) => task.id === id));
  const task = col?.tasks.find((item) => item.id === id);
  if (!col || !task) return null;
  return { col, task };
}

const actionCanAddChild = computed(() => Boolean(props.canEdit && findActionContext()?.col?.id));

function addChildFromMenu() {
  const ctx = findActionContext();
  if (!ctx?.col?.id) return;
  addTasks(ctx.col, ctx.task);
}

function editTaskFromMenu() {
  const ctx = findActionContext();
  if (!ctx?.task?.id) return;
  closeActionMenu();
  router.push({ name: 'manager.project.tasks.edit', params: { id: ctx.task.id } });
}

function dateRangeLabel(item) {
  return formatTaskScheduleRange(item);
}

function hoursPlan(task) {
  return task.estimated_hours == null || task.estimated_hours === '' ? '' : `${task.estimated_hours} giờ`;
}

function hoursActual(task) {
  return `${task.worklog_hours || 0} giờ`;
}

function onRowClick(event, task) {
  if (event.target.closest('button, a, input, select, textarea, .user-avatar-tip')) return;
  openTask(task);
}

function onRowContextMenu(event, task) {
  closeActionMenu();
  emit('context-menu', event, task);
}

function scheduleLabel(task) {
  return formatTaskScheduleRange(task);
}

function scheduleSpan(task, col) {
  return taskRangeInSprint(task, col);
}

function findTreeTask(nodes, id) {
  for (const node of nodes || []) {
    if (node.id === id) return node;
    const found = findTreeTask(node.children, id);
    if (found) return found;
  }
  return null;
}

function applyPresentedTask(node, presented) {
  if (!node || !presented) return;
  const children = node.children;
  Object.assign(node, presented);
  if (children) node.children = children;
}

async function changeTaskStatus(task, status) {
  if (!status || status === task.status) return;
  const node = findTreeTask(props.tree, task.id) || task;
  const previous = { ...node, children: node.children };
  applyPresentedTask(node, { status });
  const busy = new Set(statusUpdatingIds.value);
  busy.add(task.id);
  statusUpdatingIds.value = busy;
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${task.id}`, { status });
    applyPresentedTask(node, data.task);
    showClientToast('success', `Đã chuyển sang ${taskStatusLabel(status)}.`);
  } catch (error) {
    applyPresentedTask(node, previous);
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được trạng thái.');
  } finally {
    const next = new Set(statusUpdatingIds.value);
    next.delete(task.id);
    statusUpdatingIds.value = next;
  }
}

function closeActionMenu() {
  actionMenu.id = null;
}

function toggleActionMenu(task, event) {
  event.stopPropagation();
  if (actionMenu.id === task.id) {
    closeActionMenu();
    return;
  }
  const rect = event.currentTarget.getBoundingClientRect();
  const width = 176;
  const left = Math.min(Math.max(8, rect.right - width), window.innerWidth - width - 8);
  actionMenu.id = task.id;
  actionMenu.left = left;
  actionMenu.top = rect.bottom + 4;
  nextTick(() => {
    const menu = document.querySelector('.psb__menu');
    if (!menu) return;
    if (rect.bottom + 4 + menu.offsetHeight > window.innerHeight - 8) {
      actionMenu.top = Math.max(8, rect.top - menu.offsetHeight - 4);
    }
  });
}

function onDocPointerDown(event) {
  if (!actionMenu.id) return;
  if (event.target?.closest?.('.psb__menu, .psb__more')) return;
  closeActionMenu();
}

function onDocKeydown(event) {
  if (event.key === 'Escape' && actionMenu.id) closeActionMenu();
}
</script>

<template>
  <div ref="boardRef" class="psb hide-scrollbar">
    <p v-if="(loading || sprintsLoading) && !columns.length" class="psb__empty">Đang tải danh sách đợt làm việc…</p>
    <div v-else-if="!loading && !sprintsLoading && !hasBoard" class="psb__empty-box">
      <p class="psb__empty-title">Chưa có đợt làm việc</p>
      <p class="psb__empty-copy">Bấm nút Thêm đợt làm việc ở thanh trên để tạo đợt đầu tiên, rồi thêm việc vào đợt đó.</p>
    </div>
    <template v-else>
    <div
      v-for="section in boardSections"
      :key="section.key"
      class="psb__phase"
      :class="{ 'psb__phase--loose': section.kind === 'loose' }"
    >
      <header v-if="section.title" class="psb__phase-head">
        <div class="psb__phase-copy">
          <span v-if="section.code" class="psb__phase-code">{{ section.code }}</span>
          <span class="psb__phase-title">{{ section.title }}</span>
        </div>
        <span class="psb__phase-meta">
          {{ section.sprints.length ? `${section.sprints.length} đợt làm việc` : 'Chưa có đợt làm việc' }}
        </span>
      </header>

      <div v-if="!section.sprints.length" class="psb__col-empty psb__col-empty--phase">
        <p class="psb__empty-title">Giai đoạn này chưa có đợt làm việc</p>
        <p class="psb__empty-copy">Tạo đợt làm việc cho giai đoạn này để xếp việc vào lịch.</p>
        <button
          v-if="canEdit"
          type="button"
          class="psb__add"
          @click="addTasksBySprint"
        >
          <AppIcon name="plus" :size="14" />
          Thêm việc vào đợt làm việc
        </button>
      </div>

    <section
      v-for="col in section.sprints"
      :key="sprintKey(col)"
      class="psb__sprint"
      :class="`psb__sprint--${col.status ? sprintStatusTone(col.status) : 'neutral'}`"
    >
      <header
        class="psb__head"
        :class="{ 'psb__head--collapsed': isCollapsed(col) }"
        @click="toggleSprint(col)"
      >
        <button
          type="button"
          class="psb__toggle"
          :aria-expanded="isCollapsed(col) ? 'false' : 'true'"
          @click.stop="toggleSprint(col)"
        >
          <AppIcon
            name="chevronRight"
            :size="16"
            class="psb__toggle-icon"
            :class="{ 'psb__toggle-icon--open': !isCollapsed(col) }"
          />
          <span>{{ isCollapsed(col) ? 'Hiện việc' : 'Ẩn việc' }}</span>
        </button>

        <div class="psb__head-main">
          <span v-if="section.kind === 'loose' && col.phase?.title" class="psb__sprint-phase">{{ col.phase.title }}</span>
          <span class="psb__title">{{ col.name }}</span>
          <div class="psb__meta">
            <span v-if="col.status" class="psb__status">
              <span class="psb__dot" :class="`psb__dot--${sprintStatusTone(col.status)}`" />
              {{ sprintStatusLabel(col.status) }}
            </span>
            <span v-if="dateRangeLabel(col)" class="psb__range">{{ dateRangeLabel(col) }}</span>
            <span class="psb__count">{{ col.tasks.length }} việc</span>
            <span v-if="col.childCount" class="psb__count">{{ col.childCount }} việc nhỏ</span>
            <span v-if="col.estimatedHours || col.worklogHours" class="psb__hours">
              Dự kiến {{ col.estimatedHours || 0 }} giờ · Đã làm {{ col.worklogHours || 0 }} giờ
            </span>
          </div>
        </div>

        <div class="psb__head-end">
          <span v-if="col.avgProgress != null" class="psb__progress-value">{{ col.avgProgress }}%</span>
          <button
            v-if="canEdit && col.id"
            type="button"
            class="psb__add"
            @click.stop="addTasks(col)"
          >
            <AppIcon name="plus" :size="14" />
            Thêm việc
          </button>
        </div>

        <span
          v-if="col.avgProgress != null"
          class="psb__rail"
          aria-hidden="true"
        >
          <span class="psb__rail-fill" :style="{ width: `${col.avgProgress}%` }" />
        </span>
      </header>

      <div v-show="!isCollapsed(col)" class="psb__body">
        <div v-if="!col.tasks.length" class="psb__col-empty">
          <p class="psb__empty-title">Đợt này chưa có việc</p>
          <p class="psb__empty-copy">
            {{ canEdit ? 'Dùng nút Thêm việc trên đầu đợt để giao việc cho đợt này.' : 'Chưa có việc nào trong đợt này.' }}
          </p>
        </div>
        <div v-else class="psb__table-wrap hide-scrollbar">
          <table class="psb__table">
            <thead>
              <tr>
                <th class="psb__th--name">Công việc</th>
                <th class="psb__th--person">Người làm</th>
                <th class="psb__th--status">Trạng thái</th>
                <th>Ưu tiên</th>
                <th class="psb__th--time">Thời gian</th>
                <th>Giờ làm</th>
                <th>Tiến độ</th>
                <th class="psb__th--action">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in categoryGroups(col)" :key="group.key">
                <tr v-if="group.title" class="psb__group-row">
                  <td :colspan="COL_COUNT">Nhóm: {{ group.title }}</td>
                </tr>
                <tr
                  v-for="task in group.tasks"
                  :key="task.id"
                  class="psb__row"
                  :class="{ 'psb__row--child': task.depth > 0 }"
                  @click="onRowClick($event, task)"
                  @contextmenu.prevent.stop="onRowContextMenu($event, task)"
                >
                  <td class="psb__td--name">
                    <div class="psb__task" :style="task.depth ? { paddingLeft: `${task.depth * 1.25}rem` } : undefined">
                      <button
                        v-if="task.hasChildren"
                        type="button"
                        class="psb__tree"
                        :aria-label="collapsedIds.has(task.id) ? 'Hiện việc nhỏ' : 'Ẩn việc nhỏ'"
                        @click.stop="toggleTaskCollapse(task.id)"
                      >
                        <AppIcon
                          name="chevronRight"
                          :size="12"
                          class="psb__tree-icon"
                          :class="{ 'psb__tree-icon--open': !collapsedIds.has(task.id) }"
                        />
                      </button>
                      <span v-else class="psb__tree-spacer" />
                      <div class="psb__task-copy">
                        <span v-if="task.code" class="psb__code">{{ task.code }}</span>
                        <button type="button" class="psb__link" @click.stop="openTask(task)">{{ task.title }}</button>
                        <button
                          v-if="task.hasChildren"
                          type="button"
                          class="psb__child-n"
                          @click.stop="toggleTaskCollapse(task.id)"
                        >
                          {{ collapsedIds.has(task.id) ? `Hiện ${task.childCount} việc nhỏ` : 'Ẩn việc nhỏ' }}
                        </button>
                        <span v-else-if="task.depth > 0" class="psb__child-n">Việc nhỏ</span>
                      </div>
                    </div>
                  </td>
                  <td class="psb__td--person">
                    <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                    <span v-else class="psb__muted">—</span>
                  </td>
                  <td class="psb__td--status">
                    <select
                      v-if="canEdit"
                      class="psb__status-select"
                      :class="`psb__status-select--${taskStatusTone(task.status)}`"
                      :disabled="statusUpdatingIds.has(task.id)"
                      :value="task.status"
                      aria-label="Đổi trạng thái"
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
                      {{ taskPriorityDisplay(task) }}
                    </span>
                    <span v-else class="psb__muted">Chưa chọn</span>
                  </td>
                  <td class="psb__td--time">
                    <div v-if="scheduleLabel(task)" class="psb__time">
                      <span class="psb__time-label" :class="{ 'psb__time-label--overdue': task.is_overdue }">
                        {{ scheduleLabel(task) }}
                      </span>
                      <span v-if="task.is_overdue" class="psb__overdue">Quá hạn</span>
                      <span v-if="scheduleSpan(task, col)" class="psb__track" aria-hidden="true">
                        <span
                          class="psb__track-fill"
                          :class="{ 'psb__track-fill--overdue': task.is_overdue }"
                          :style="{
                            marginLeft: `${scheduleSpan(task, col).left}%`,
                            width: `${scheduleSpan(task, col).width}%`,
                          }"
                        />
                      </span>
                    </div>
                    <span v-else class="psb__muted">Chưa đặt ngày</span>
                  </td>
                  <td class="psb__td--hours">
                    <span class="psb__hours-stack">
                      <span>Dự kiến: {{ hoursPlan(task) || 'chưa nhập' }}</span>
                      <span>Đã làm: {{ hoursActual(task) }}</span>
                    </span>
                  </td>
                  <td>
                    <DualProgressBar
                      v-if="task.progress_percent != null"
                      :actual="task.progress_percent"
                      :expected="computeExpectedProgress(task.start_date, task.end_date)"
                      size="sm"
                    />
                    <span v-else class="psb__muted">Chưa có</span>
                  </td>
                  <td class="psb__td--action" @click.stop>
                    <button
                      type="button"
                      class="psb__more"
                      :class="{ 'psb__more--open': actionMenu.id === task.id }"
                      aria-haspopup="menu"
                      aria-label="Thao tác"
                      :aria-expanded="actionMenu.id === task.id ? 'true' : 'false'"
                      @click="toggleActionMenu(task, $event)"
                    >
                      <AppIcon name="moreVertical" :size="16" />
                    </button>
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

    <Teleport to="body">
      <div
        v-if="actionMenu.id"
        class="psb__menu"
        role="menu"
        aria-label="Thao tác công việc"
        :style="{ top: `${actionMenu.top}px`, left: `${actionMenu.left}px` }"
      >
        <button type="button" class="psb__menu-item" role="menuitem" @click="openTask({ id: actionMenu.id })">
          <AppIcon name="eye" :size="15" />
          <span>Xem chi tiết</span>
        </button>
        <button
          v-if="canEdit && actionMenu.id"
          type="button"
          class="psb__menu-item"
          role="menuitem"
          @click="editTaskFromMenu"
        >
          <AppIcon name="pencil" :size="15" />
          <span>Sửa công việc</span>
        </button>
        <button
          v-if="actionCanAddChild"
          type="button"
          class="psb__menu-item"
          role="menuitem"
          @click="addChildFromMenu"
        >
          <AppIcon name="gitBranch" :size="15" />
          <span>Thêm việc nhỏ</span>
        </button>
      </div>
    </Teleport>
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
  background: var(--color-surface-muted);
}

.psb__empty {
  margin: 2rem auto;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.psb__empty-box,
.psb__col-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  margin: 0;
  padding: 1.5rem 1rem;
  text-align: center;
}

.psb__col-empty--phase {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.psb__phase {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.psb__phase-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  min-width: 0;
  padding: 0.125rem 0.25rem;
}

.psb__phase-copy {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  min-width: 0;
}

.psb__phase-code {
  color: var(--color-text-muted);
  font-family: var(--font-family-mono, ui-monospace, monospace);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.psb__phase-title {
  min-width: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  line-height: 1.3;
}

.psb__phase-meta {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.psb__empty-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.psb__empty-copy {
  margin: 0 0 0.5rem;
  max-width: 28rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
}

.psb__sprint {
  --psb-accent: var(--color-text-muted);
  --psb-accent-soft: var(--color-surface-muted);
  position: relative;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.psb__sprint::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--psb-accent);
}

.psb__sprint--primary {
  --psb-accent: var(--color-primary);
  --psb-accent-soft: var(--color-primary-surface);
}
.psb__sprint--success {
  --psb-accent: var(--color-success);
  --psb-accent-soft: var(--color-success-tint-bg);
}
.psb__sprint--info {
  --psb-accent: var(--color-info);
  --psb-accent-soft: var(--color-info-tint-bg);
}
.psb__sprint--neutral {
  --psb-accent: var(--color-text-muted);
  --psb-accent-soft: var(--color-surface-muted);
}
.psb__sprint--umber {
  --psb-accent: var(--color-umber);
  --psb-accent-soft: var(--color-umber-tint-bg);
}

.psb__head {
  position: relative;
  flex-shrink: 0;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  column-gap: 0.875rem;
  row-gap: 0.375rem;
  padding: 0.875rem 1rem 1rem 0.5rem;
  background: color-mix(in srgb, var(--psb-accent) 8%, var(--color-surface));
  cursor: pointer;
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__head:hover {
  background: color-mix(in srgb, var(--psb-accent) 12%, var(--color-surface));
}

.psb__head--collapsed {
  padding-top: 1rem;
  padding-bottom: 1.125rem;
}

.psb__toggle {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  align-self: start;
  min-height: 2rem;
  margin-top: 0.125rem;
  padding: 0.25rem 0.5rem 0.25rem 0.25rem;
  border: 0;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-surface) 72%, transparent);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--psb-accent) 18%, var(--color-border));
}

.psb__toggle:hover {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--psb-accent) 40%, var(--color-border));
}

.psb__toggle-icon {
  transition: transform 0.18s ease;
}

.psb__toggle-icon--open {
  transform: rotate(90deg);
}

.psb__head-main {
  display: flex;
  flex-direction: column;
  gap: 0.1875rem;
  min-width: 0;
}

.psb__sprint-phase {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--psb-accent);
}

.psb__title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
  letter-spacing: -0.01em;
  line-height: 1.28;
}

.psb__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.875rem;
  min-width: 0;
  margin-top: 0.1875rem;
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

.psb__range,
.psb__count,
.psb__hours {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.psb__head-end {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  align-self: start;
  gap: 0.75rem;
  margin-top: 0.125rem;
}

.psb__progress-value {
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  color: var(--psb-accent);
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.psb__rail {
  position: absolute;
  z-index: 1;
  right: 0;
  bottom: 0;
  left: 0;
  height: 3px;
  background: color-mix(in srgb, var(--psb-accent) 16%, var(--color-border));
  overflow: hidden;
}

.psb__rail-fill {
  display: block;
  height: 100%;
  background: var(--psb-accent);
}

.psb__add {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.25rem;
  padding: 0.375rem 0.75rem;
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

.psb__body {
  flex-shrink: 0;
}

.psb__table-wrap {
  overflow-x: auto;
}

.psb__table {
  width: 100%;
  min-width: 72rem;
  border-collapse: collapse;
  table-layout: fixed;
}

.psb__table thead th {
  padding: 0.5rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  text-align: left;
  white-space: nowrap;
  box-shadow: inset -1px 0 0 var(--color-border), 0 1px 0 var(--color-border);
}

.psb__table thead th:last-child {
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__th--name {
  width: 32%;
}

.psb__th--person {
  width: 7rem;
  text-align: center;
}

.psb__th--status {
  width: 11rem;
}

.psb__th--time {
  width: 13rem;
}

.psb__th--action {
  width: 3.25rem;
  text-align: center;
}

.psb__group-row td {
  padding: 0.5rem 0.75rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--color-surface-muted);
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__row {
  cursor: pointer;
}

.psb__row:hover td {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}

.psb__row--child td {
  background: color-mix(in srgb, var(--color-surface-muted) 55%, var(--color-surface));
}

.psb__row td {
  padding: 0.5625rem 0.75rem;
  font-size: 0.8125rem;
  color: var(--color-text);
  vertical-align: middle;
  box-shadow: inset -1px 0 0 var(--color-border), 0 1px 0 var(--color-border);
}

.psb__row td:last-child {
  box-shadow: 0 1px 0 var(--color-border);
}

.psb__td--name {
  overflow: hidden;
}

.psb__task {
  display: flex;
  align-items: flex-start;
  gap: 0.25rem;
  min-width: 0;
}

.psb__tree,
.psb__tree-spacer {
  flex-shrink: 0;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.2rem;
}

.psb__tree {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.psb__tree-icon {
  transition: transform 0.15s ease;
}

.psb__tree-icon--open {
  transform: rotate(90deg);
}

.psb__task-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  min-width: 0;
}

.psb__code {
  color: var(--color-text-muted);
  font-family: var(--font-family-mono, ui-monospace, monospace);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.psb__link {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  line-height: 1.35;
  text-align: left;
  text-decoration: none;
  white-space: normal;
  overflow-wrap: anywhere;
  cursor: pointer;
}

.psb__link:hover {
  color: var(--color-primary);
}

.psb__child-n {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

button.psb__child-n {
  padding: 0;
  border: 0;
  background: transparent;
  font-family: var(--font-family-base);
  font-weight: 600;
  cursor: pointer;
}

button.psb__child-n:hover {
  color: var(--color-primary);
}

.psb__muted {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.psb__td--person {
  width: 7rem;
  text-align: center;
  overflow: hidden;
}

.psb__td--status {
  width: 11rem;
  overflow: hidden;
}

.psb__td--time {
  min-width: 10rem;
}

.psb__time {
  display: flex;
  flex-direction: column;
  gap: 0.3125rem;
  min-width: 8.5rem;
}

.psb__time-label {
  font-variant-numeric: tabular-nums;
  font-size: 0.8125rem;
  white-space: nowrap;
}

.psb__time-label--overdue {
  color: var(--color-danger);
  font-weight: 600;
}

.psb__overdue {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 0.0625rem 0.375rem;
  border-radius: var(--radius-sm);
  background: var(--color-danger-tint-bg, var(--color-primary-surface));
  color: var(--color-danger);
  font-size: 0.6875rem;
  font-weight: 700;
}

.psb__track {
  display: block;
  width: 100%;
  height: 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-border);
  overflow: hidden;
}

.psb__track-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.psb__track-fill--overdue {
  background: var(--color-danger);
}

.psb__td--hours {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.psb__hours-stack {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  color: var(--color-text);
  font-size: 0.75rem;
  line-height: 1.35;
}

.psb__status-select {
  width: 100%;
  max-width: 100%;
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

.psb__td--action {
  text-align: center;
}

.psb__more {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.psb__more:hover,
.psb__more--open {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.psb__menu {
  position: fixed;
  z-index: 1200;
  width: 12.5rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.psb__menu-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
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

.psb__menu-item:hover {
  background: var(--color-surface-muted);
}

@media (max-width: 768px) {
  .psb {
    padding: 0.5rem;
  }
  .psb__head {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .psb__head-end {
    grid-column: 2;
    justify-content: flex-start;
  }
}

@media (max-width: 480px) {
  .psb__head {
    grid-template-columns: minmax(0, 1fr);
  }
  .psb__toggle,
  .psb__head-end {
    grid-column: 1;
  }
}
</style>
