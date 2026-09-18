<script setup>
//
// Chế độ xem "Kế hoạch" trên tab Công việc — timeline dạng thanh ngang
// giống Gantt nhưng mỗi hàng là 1 Phase hoặc 1 Sprint (không phải từng
// công việc lẻ). Mở rộng 1 hàng Phase để thấy Sprint con, mở rộng tiếp 1
// hàng Sprint để thấy công việc bên trong. Tái dùng engine vẽ timeline từ
// constants/gantt.js (buildTimeline/dateToX/barLayout).
//
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { showClientToast } from '@/lib/clientToast';
import {
  GANTT_DEFAULT_DAY_WIDTH,
  GANTT_PAD_AFTER,
  GANTT_PAD_BEFORE,
  GANTT_ROW_HEIGHT,
  GANTT_ZOOM_STEPS,
  barLayout,
  buildTimeline,
  dateToX,
  formatViDate,
  startOfToday,
} from '../constants/gantt.js';
import { sprintStatusLabel, sprintStatusTone } from '../constants/sprint.js';
import { parseYmd } from '../constants/task.js';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
});

const router = useRouter();
const leftWidth = 260;
const dayWidth = ref(GANTT_DEFAULT_DAY_WIDTH);
const bodyWrap = ref(null);
const sprintsByPhase = ref({});
const sprintsLoading = ref(new Set());
const expandedPhases = ref(new Set());
const expandedSprints = ref(new Set());

useDragScroll(bodyWrap, { axis: 'x' });

const phases = computed(() => (props.tree || []).filter((node) => node.type === 'phase'));

async function loadSprintsForPhase(phaseId) {
  if (sprintsByPhase.value[phaseId] || sprintsLoading.value.has(phaseId)) return;
  sprintsLoading.value.add(phaseId);
  try {
    const { data } = await window.axios.get(`/api/project/${props.project.id}/sprints`, {
      params: { phase_id: phaseId },
    });
    sprintsByPhase.value = { ...sprintsByPhase.value, [phaseId]: data.sprints ?? [] };
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được sprint của giai đoạn này.');
  } finally {
    sprintsLoading.value.delete(phaseId);
  }
}

function togglePhase(phase) {
  const next = new Set(expandedPhases.value);
  if (next.has(phase.id)) {
    next.delete(phase.id);
  } else {
    next.add(phase.id);
    loadSprintsForPhase(phase.id);
  }
  expandedPhases.value = next;
}

function toggleSprint(sprint) {
  const next = new Set(expandedSprints.value);
  if (next.has(sprint.id)) next.delete(sprint.id);
  else next.add(sprint.id);
  expandedSprints.value = next;
}

function tasksOfSprint(sprintId) {
  const out = [];
  const walk = (nodes) => {
    for (const node of nodes || []) {
      if (node.type === 'task' && node.sprint_id === sprintId) out.push(node);
      walk(node.children);
    }
  };
  walk(props.tree);
  return out;
}

/** Hàng phẳng để vẽ timeline: phase → (sprint con nếu mở rộng) → (task nếu mở rộng). */
const rows = computed(() => {
  const out = [];
  for (const phase of phases.value) {
    out.push({ kind: 'phase', id: `phase-${phase.id}`, node: phase });
    if (expandedPhases.value.has(phase.id)) {
      const sprints = sprintsByPhase.value[phase.id] || [];
      for (const sprint of sprints) {
        out.push({ kind: 'sprint', id: `sprint-${sprint.id}`, node: sprint, phaseId: phase.id });
        if (expandedSprints.value.has(sprint.id)) {
          for (const task of tasksOfSprint(sprint.id)) {
            out.push({ kind: 'task', id: `task-${task.id}`, node: task, sprintId: sprint.id });
          }
        }
      }
    }
  }
  return out;
});

const dateBounds = computed(() => {
  const starts = [];
  const ends = [];
  const push = (value) => {
    const date = parseYmd(value);
    if (date) {
      starts.push(date);
      ends.push(date);
    }
  };
  push(props.project?.start_date);
  push(props.project?.end_date);
  for (const row of rows.value) {
    push(row.node.start_date);
    push(row.node.end_date);
  }
  const today = startOfToday();
  if (!starts.length) {
    const from = new Date(today.getFullYear(), today.getMonth(), 1);
    const to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    return { from, to };
  }
  return {
    from: new Date(Math.min(...starts.map((d) => d.getTime()))),
    to: new Date(Math.max(today.getTime(), ...ends.map((d) => d.getTime()))),
  };
});

const rangeStart = computed(() => {
  const from = dateBounds.value.from;
  return new Date(from.getFullYear(), from.getMonth(), from.getDate() - GANTT_PAD_BEFORE);
});
const rangeEnd = computed(() => {
  const to = dateBounds.value.to;
  return new Date(to.getFullYear(), to.getMonth(), to.getDate() + GANTT_PAD_AFTER);
});

const timeline = computed(() => buildTimeline(rangeStart.value, rangeEnd.value, 'day', dayWidth.value));
const today = computed(() => startOfToday());
const todayX = computed(() => dateToX(today.value, timeline.value.origin, timeline.value.pxPerDay));

function barFor(row) {
  const start = parseYmd(row.node.start_date);
  const end = parseYmd(row.node.end_date);
  if (!start || !end) return null;
  return barLayout({ start, end }, timeline.value.origin, timeline.value.pxPerDay);
}

function barTone(row) {
  if (row.kind === 'phase') return 'phase';
  if (row.kind === 'sprint') return 'sprint';
  return 'task';
}

function rangeLabel(node) {
  if (!node.start_date && !node.end_date) return 'Chưa có ngày';
  return `${formatViDate(node.start_date)} – ${formatViDate(node.end_date)}`;
}

function zoomBy(delta) {
  const idx = GANTT_ZOOM_STEPS.indexOf(dayWidth.value);
  const next = Math.max(0, Math.min(GANTT_ZOOM_STEPS.length - 1, (idx >= 0 ? idx : 2) + delta));
  dayWidth.value = GANTT_ZOOM_STEPS[next];
}

function openTask(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

onMounted(() => {
  // Mở sẵn giai đoạn đầu tiên để người dùng thấy ngay có sprint bên trong.
  if (phases.value[0]) togglePhase(phases.value[0]);
});

watch(
  () => props.tree,
  () => {
    expandedPhases.value = new Set();
    expandedSprints.value = new Set();
    sprintsByPhase.value = {};
  },
);
</script>

<template>
  <div class="pplan">
    <div class="pplan__toolbar">
      <span class="pplan__hint">Mỗi hàng là 1 giai đoạn hoặc 1 đợt làm việc — bấm mũi tên để xem chi tiết bên trong.</span>
      <div class="pplan__zoom">
        <button type="button" class="pplan__zoom-btn" aria-label="Thu nhỏ" @click="zoomBy(-1)">
          <AppIcon name="minus" :size="14" />
        </button>
        <button type="button" class="pplan__zoom-btn" aria-label="Phóng to" @click="zoomBy(1)">
          <AppIcon name="plus" :size="14" />
        </button>
      </div>
    </div>

    <p v-if="loading" class="pplan__empty">Đang tải…</p>
    <p v-else-if="!phases.length" class="pplan__empty">Dự án chưa có giai đoạn nào.</p>
    <div v-else ref="bodyWrap" class="pplan__body hide-scrollbar">
      <div class="pplan__grid" :style="{ width: `${leftWidth + timeline.totalWidth}px` }">
        <div class="pplan__left" :style="{ width: `${leftWidth}px` }">
          <div
            v-for="row in rows"
            :key="row.id"
            class="pplan__row-label"
            :class="`pplan__row-label--${row.kind}`"
            :style="{ height: `${GANTT_ROW_HEIGHT}px` }"
          >
            <button
              v-if="row.kind === 'phase'"
              type="button"
              class="pplan__toggle"
              :aria-label="expandedPhases.has(row.node.id) ? 'Thu gọn giai đoạn' : 'Mở rộng giai đoạn'"
              @click="togglePhase(row.node)"
            >
              <AppIcon :name="expandedPhases.has(row.node.id) ? 'chevronDown' : 'chevronRight'" :size="14" />
            </button>
            <button
              v-else-if="row.kind === 'sprint'"
              type="button"
              class="pplan__toggle"
              :aria-label="expandedSprints.has(row.node.id) ? 'Thu gọn sprint' : 'Mở rộng sprint'"
              @click="toggleSprint(row.node)"
            >
              <AppIcon :name="expandedSprints.has(row.node.id) ? 'chevronDown' : 'chevronRight'" :size="14" />
            </button>
            <span v-else class="pplan__toggle pplan__toggle--spacer" />
            <span
              class="pplan__row-title"
              :class="{ 'pplan__row-title--clickable': row.kind === 'task' }"
              @click="row.kind === 'task' && openTask(row.node)"
            >
              {{ row.node.title || row.node.name }}
            </span>
            <span v-if="row.kind === 'sprint'" class="pplan__row-status">
              <span class="pplan__dot" :class="`pplan__dot--${sprintStatusTone(row.node.status)}`" />
              {{ sprintStatusLabel(row.node.status) }}
            </span>
          </div>
        </div>

        <div class="pplan__timeline" :style="{ width: `${timeline.totalWidth}px` }">
          <div class="pplan__head">
            <div
              v-for="group in timeline.groups"
              :key="group.key"
              class="pplan__head-group"
              :style="{ left: `${group.left}px`, width: `${group.width}px` }"
            >
              {{ group.label }}
            </div>
          </div>
          <div class="pplan__rows" :style="{ height: `${rows.length * GANTT_ROW_HEIGHT}px` }">
            <div v-if="todayX >= 0" class="pplan__today" :style="{ left: `${todayX}px` }" />
            <div
              v-for="(row, index) in rows"
              :key="row.id"
              class="pplan__row"
              :style="{ top: `${index * GANTT_ROW_HEIGHT}px`, height: `${GANTT_ROW_HEIGHT}px` }"
            >
              <div
                v-if="barFor(row)"
                class="pplan__bar"
                :class="`pplan__bar--${barTone(row)}`"
                :style="{ left: `${barFor(row).left}px`, width: `${barFor(row).width}px` }"
              >
                <span class="pplan__bar-label">{{ rangeLabel(row.node) }}</span>
              </div>
              <span v-else class="pplan__bar-none">{{ rangeLabel(row.node) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pplan {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--color-surface);
}

.pplan__toolbar {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.5rem 0.875rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.pplan__hint {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.pplan__zoom {
  display: flex;
  gap: 0.25rem;
  flex-shrink: 0;
}

.pplan__zoom-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.pplan__empty {
  margin: 2rem auto;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.pplan__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.pplan__grid {
  position: relative;
  display: flex;
}

.pplan__left {
  flex-shrink: 0;
  position: sticky;
  left: 0;
  z-index: 2;
  background: var(--color-surface);
  box-shadow: 1px 0 0 var(--color-border);
}

.pplan__row-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0 0.625rem;
  box-shadow: 0 1px 0 var(--color-border);
  overflow: hidden;
}

.pplan__row-label--phase {
  font-weight: 700;
  color: var(--color-text);
}

.pplan__row-label--sprint {
  padding-left: 1.5rem;
  color: var(--color-text);
}

.pplan__row-label--task {
  padding-left: 2.75rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.pplan__toggle {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.pplan__toggle--spacer {
  cursor: default;
}

.pplan__row-title {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pplan__row-title--clickable {
  cursor: pointer;
}

.pplan__row-title--clickable:hover {
  text-decoration: underline;
}

.pplan__row-status {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.6875rem;
  color: var(--color-text-muted);
}

.pplan__dot {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}
.pplan__dot--primary { background: var(--color-primary); }
.pplan__dot--success { background: var(--color-success); }
.pplan__dot--umber { background: var(--color-umber); }
.pplan__dot--neutral { background: var(--color-text-muted); }

.pplan__timeline {
  position: relative;
  flex-shrink: 0;
}

.pplan__head {
  position: sticky;
  top: 0;
  z-index: 1;
  height: 1.75rem;
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.pplan__head-group {
  position: absolute;
  top: 0;
  height: 100%;
  display: flex;
  align-items: center;
  padding-left: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--color-text-muted);
  box-shadow: 1px 0 0 var(--color-border);
}

.pplan__rows {
  position: relative;
}

.pplan__row {
  position: absolute;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 0 var(--color-border);
}

.pplan__today {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: var(--color-danger);
  z-index: 1;
}

.pplan__bar {
  position: absolute;
  height: 1.5rem;
  border-radius: var(--radius-md, 0.5rem);
  display: flex;
  align-items: center;
  padding: 0 0.5rem;
  overflow: hidden;
}

.pplan__bar--phase { background: var(--color-gold); }
.pplan__bar--sprint { background: var(--color-info); }
.pplan__bar--task { background: var(--color-primary-300, var(--color-primary)); }

.pplan__bar-label {
  font-size: 0.6875rem;
  color: var(--color-surface);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pplan__bar-none {
  padding-left: 0.5rem;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

@media (max-width: 768px) {
  .pplan__row-label--task {
    padding-left: 2rem;
  }
}
</style>
