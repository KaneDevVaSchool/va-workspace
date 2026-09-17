<script setup>
//
// Tab Công việc trên chi tiết dự án — chọn chế độ xem giống danh sách
// công việc: Tất cả / Việc cha / Kanban / Gantt. Dữ liệu lấy từ cây WBS
// đã tải sẵn, không gọi lại API list.
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { useDragScroll } from '@/composables/useDragScroll';
import { showClientToast } from '@/lib/clientToast';
import { computeExpectedProgress } from '@/lib/progress';
import { exportTaskListCsv, exportTaskListPdf, exportTaskListXlsx } from '../lib/taskListExport.js';
import {
  PROJECT_TASK_COL_KEY,
  PROJECT_TASK_COLUMNS,
  PROJECT_TASK_KANBAN_GROUP_KEY,
  PROJECT_TASK_VIEW_KEY,
  PROJECT_TASK_VIEWS,
  PROJECT_TASK_WIDTH_KEY,
  PROJECT_TASK_ZOOM_KEY,
  TASK_PRIORITY_LABELS,
  TASK_PRIORITY_TONES,
  TASK_STATUSES,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  TASK_TYPE_LABELS,
  TASK_TYPE_TONES,
  flattenAllProjectNodes,
  flattenProjectTasks,
  formatTaskDate,
  formatTaskDateTime,
  formatTaskVarianceDays,
  groupProjectTasksByPhase,
  loadVisibility,
  saveVisibility,
  taskCellText,
  taskPriorityLabel,
  taskPriorityTone,
  taskStatusLabel,
  taskStatusTone,
  taskTypeLabel,
  taskTypeTone,
} from '../constants/task.js';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectGanttTab from './ProjectGanttTab.vue';
import ProjectPlanTab from './ProjectPlanTab.vue';
import ProjectSprintBoard from './ProjectSprintBoard.vue';
import ProjectTaskViewModeMenu from './ProjectTaskViewModeMenu.vue';
import TaskRowContextMenu from './TaskRowContextMenu.vue';
import TaskQuickActionModals from './TaskQuickActionModals.vue';
import TaskTableRow from './TaskTableRow.vue';

const props = defineProps({
  tree: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  project: { type: Object, default: null },
  filter: { type: String, default: 'all' },
  filterLabel: { type: String, default: 'Tổng công việc' },
  canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['tasks-changed']);

const router = useRouter();
const auth = useAuthStore();
const canApprove = computed(() => auth.can('task.approve'));

const KANBAN_GROUPS = ['status', 'assignees', 'priority', 'type'];
const THEME_TONES = [
  'primary',
  'secondary',
  'tertiary',
  'gold',
  'umber',
  'success',
  'warning',
  'info',
  'violet',
  'teal',
  'rose',
];
const KANBAN_GROUP_LABELS = {
  status: 'Theo trạng thái',
  assignees: 'Theo người thực hiện',
  priority: 'Theo mức độ ưu tiên',
  type: 'Theo loại',
};
const MIN_COL_PX = 72;
const KANBAN_DRAG_THRESHOLD = 7;

const viewMode = ref(loadView());
const kanbanGroupBy = ref(loadKanbanGroup());
const viewModeOpen = ref(false);
const query = ref('');
const perPage = ref(20);
const page = ref(1);
const tableZoom = ref(loadZoom());
const collapsedIds = ref(new Set());
const collapsedPhaseKeys = ref(new Set());
const visibility = reactive(loadVisibility(PROJECT_TASK_COL_KEY, PROJECT_TASK_COLUMNS));
const columnWidths = reactive(loadWidths());

// ---------- Xuất dữ liệu (CSV / Excel / PDF) — theo đúng bộ lọc + cột chọn ----------
const exportMenuOpen = ref(false);
const exportDialogOpen = ref(false);
const exportKind = ref('xlsx');
const exportBusy = ref(false);
const exportSelectedColumns = ref(new Set(PROJECT_TASK_COLUMNS.filter((col) => col.defaultOn).map((col) => col.key)));

const tableWrap = ref(null);
const kanbanWrap = ref(null);
const resizing = ref(false);
const kanbanStatusUpdating = ref(new Set());
const kanbanJustMovedId = ref(null);
const kanbanDrag = reactive({
  active: false,
  settling: false,
  taskId: null,
  fromKey: null,
  overKey: null,
  task: null,
  width: 0,
  height: 0,
  x: 0,
  y: 0,
});
let kanbanPointer = null;
let kanbanPendingX = 0;
let kanbanPendingY = 0;
let kanbanRaf = 0;
let kanbanScrollRaf = 0;
let kanbanJustMovedTimer = 0;

const ctxMenu = reactive({ open: false, x: 0, y: 0, task: null });
const actionDialog = reactive({ kind: null, task: null, extra: {} });

useDragScroll(tableWrap, { isBlocked: () => resizing.value, axis: 'x' });
useDragScroll(kanbanWrap, { axis: 'x', isBlocked: () => kanbanDrag.active });

const isList = computed(() => viewMode.value === 'all' || viewMode.value === 'parents');
const isPhaseGroup = computed(() => viewMode.value === 'phase');
const isSprintBoard = computed(() => viewMode.value === 'sprint');
const isKanban = computed(() => viewMode.value === 'kanban');
const isGantt = computed(() => viewMode.value === 'gantt');
const isPlan = computed(() => viewMode.value === 'plan');
const isTableLike = computed(() => isList.value || isPhaseGroup.value);

const triggerMeta = computed(() => {
  if (isKanban.value) {
    return {
      label: KANBAN_GROUP_LABELS[kanbanGroupBy.value] || 'Kanban',
      icon: 'layoutGrid',
    };
  }
  const item = PROJECT_TASK_VIEWS.find((view) => view.key === viewMode.value);
  return { label: item?.label || 'Tất cả công việc', icon: item?.icon || 'layoutList' };
});

const shownColumns = computed(() =>
  PROJECT_TASK_COLUMNS.filter((col) => col.always || visibility[col.key]),
);
const colSpan = computed(() => Math.max(shownColumns.value.length, 1));

const sourceTasks = computed(() =>
  flattenProjectTasks(props.tree, {
    parentsOnly: viewMode.value === 'parents',
    filter: props.filter,
    query: query.value,
  }),
);

// Kanban "Theo loại" cần cả phase/category, không chỉ type=task như sourceTasks.
const kanbanSourceTasks = computed(() => {
  if (!isKanban.value || kanbanGroupBy.value !== 'type') return sourceTasks.value;
  return flattenAllProjectNodes(props.tree, { filter: props.filter, query: query.value });
});

const phaseGroups = computed(() => {
  if (!isPhaseGroup.value) return [];
  const groups = groupProjectTasksByPhase(props.tree, { filter: props.filter, query: query.value });
  // Ẩn hẳn công việc con của 1 việc cha đang thu gọn (giống cách viewMode
  // 'all' xử lý ở visibleTasks) thay vì chỉ v-show — tránh cuộn qua hàng
  // đã thu gọn.
  return groups.map((group) => {
    const ids = new Set(group.tasks.map((task) => task.id));
    const collapsedAncestor = new Set();
    const tasks = group.tasks.filter((task) => {
      const parentKey = ids.has(task.parent_id) ? task.parent_id : null;
      if (parentKey != null && collapsedAncestor.has(parentKey)) {
        collapsedAncestor.add(task.id);
        return false;
      }
      if (collapsedIds.value.has(task.id)) collapsedAncestor.add(task.id);
      return true;
    });
    return { ...group, tasks };
  });
});
const phaseGroupTotal = computed(() => phaseGroups.value.reduce((sum, group) => sum + group.tasks.length, 0));

const visibleTasks = computed(() => {
  if (viewMode.value !== 'all') return sourceTasks.value;
  const ids = new Set(sourceTasks.value.map((task) => task.id));
  const byParent = new Map();
  for (const task of sourceTasks.value) {
    const key = ids.has(task.parent_id) ? task.parent_id : 0;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key).push(task);
  }
  const out = [];
  const walk = (parentKey, depth) => {
    for (const task of byParent.get(parentKey) || []) {
      const kids = (byParent.get(task.id) || []).length > 0;
      out.push({ ...task, depth, hasChildren: kids });
      if (kids && !collapsedIds.value.has(task.id)) walk(task.id, depth + 1);
    }
  };
  walk(0, 0);
  return out;
});

const total = computed(() => visibleTasks.value.length);
const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value) || 1));
const pagedTasks = computed(() => {
  if (!isList.value) return visibleTasks.value;
  const start = (page.value - 1) * perPage.value;
  return visibleTasks.value.slice(start, start + perPage.value);
});
const from = computed(() => (total.value ? (page.value - 1) * perPage.value + 1 : 0));
const to = computed(() => Math.min(page.value * perPage.value, total.value));
const tableWidthPx = computed(() => {
  const sum = shownColumns.value.reduce((acc, col) => acc + (Number(columnWidths[col.key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const kanbanColumns = computed(() => {
  const tasks = kanbanSourceTasks.value;
  if (kanbanGroupBy.value === 'assignees') {
    const map = new Map();
    for (const task of tasks) {
      const id = task.assignee?.id || 0;
      const label = task.assignee?.name || 'Chưa giao';
      if (!map.has(id)) {
        map.set(id, {
          key: `user-${id}`,
          label,
          tone: id ? hashTone(`user-${id}`) : 'neutral',
          user: task.assignee || null,
          tasks: [],
        });
      }
      map.get(id).tasks.push(task);
    }
    return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label, 'vi'));
  }
  if (kanbanGroupBy.value === 'priority') {
    const order = ['strategic', 'high_priority', 'important', 'assist', 'support', 'urgent', 'high', 'medium', 'low', ''];
    const map = new Map();
    for (const task of tasks) {
      const value = task.priority || '';
      if (!map.has(value)) {
        map.set(value, {
          key: `priority-${value || 'none'}`,
          dropKey: value || '__none__',
          label: value ? (TASK_PRIORITY_LABELS[value] || value) : 'Chưa đặt',
          tone: TASK_PRIORITY_TONES[value] || 'tertiary',
          tasks: [],
        });
      }
      map.get(value).tasks.push(task);
    }
    return Array.from(map.values()).sort((a, b) => order.indexOf(a.key.replace('priority-', '').replace('none', '')) - order.indexOf(b.key.replace('priority-', '').replace('none', '')));
  }
  if (kanbanGroupBy.value === 'type') {
    return Object.entries(TASK_TYPE_LABELS).map(([value, label]) => ({
      key: `type-${value}`,
      dropKey: value,
      label,
      tone: TASK_TYPE_TONES[value] || 'neutral',
      tasks: tasks.filter((task) => task.type === value),
    }));
  }
  return TASK_STATUSES.filter((item) => item.value).map((item) => ({
    key: item.value,
    dropKey: item.value,
    label: item.label,
    tone: TASK_STATUS_TONES[item.value] || 'neutral',
    tasks: tasks.filter((task) => task.status === item.value),
  }));
});

const isKanbanDragGroup = computed(
  () => kanbanGroupBy.value === 'status' || kanbanGroupBy.value === 'priority' || kanbanGroupBy.value === 'type',
);

const kanbanCardsMovable = computed(() => {
  if (!isKanbanDragGroup.value || !props.canEdit) return false;
  if (kanbanGroupBy.value === 'status' && kanbanColumns.value.length < 2) return false;
  return true;
});

const kanbanGhostStyle = computed(() => {
  const rotate = kanbanDrag.settling ? 0 : 3.5;
  const scale = kanbanDrag.settling ? 1 : 1.04;
  return {
    width: `${kanbanDrag.width}px`,
    height: `${kanbanDrag.height}px`,
    transform: `translate3d(${Math.round(kanbanDrag.x)}px, ${Math.round(kanbanDrag.y)}px, 0) rotate(${rotate}deg) scale(${scale})`,
  };
});

const kanbanGhostTone = computed(() => {
  const col = kanbanColumns.value.find((item) => item.dropKey === kanbanDrag.fromKey);
  return col?.tone || 'primary';
});

watch([() => props.filter, viewMode, query, perPage], () => {
  page.value = 1;
});

watch(lastPage, (max) => {
  if (page.value > max) page.value = max;
});

function loadView() {
  try {
    const raw = localStorage.getItem(PROJECT_TASK_VIEW_KEY);
    if (PROJECT_TASK_VIEWS.some((item) => item.key === raw)) return raw;
  } catch {
    // Bỏ qua.
  }
  return 'all';
}

function loadKanbanGroup() {
  try {
    const raw = localStorage.getItem(PROJECT_TASK_KANBAN_GROUP_KEY);
    if (KANBAN_GROUPS.includes(raw)) return raw;
  } catch {
    // Bỏ qua.
  }
  return 'status';
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(PROJECT_TASK_ZOOM_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadWidths() {
  try {
    const parsed = JSON.parse(localStorage.getItem(PROJECT_TASK_WIDTH_KEY) || '{}');
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua.
  }
  return {};
}

function persistView(mode) {
  try {
    localStorage.setItem(PROJECT_TASK_VIEW_KEY, mode);
  } catch {
    // Bỏ qua.
  }
}

function setViewMode(mode) {
  viewModeOpen.value = false;
  if (viewMode.value === mode) return;
  viewMode.value = mode;
  persistView(mode);
}

function chooseKanban(group) {
  kanbanGroupBy.value = group;
  viewMode.value = 'kanban';
  viewModeOpen.value = false;
  persistView('kanban');
  try {
    localStorage.setItem(PROJECT_TASK_KANBAN_GROUP_KEY, group);
  } catch {
    // Bỏ qua.
  }
}

function toggleViewMenu() {
  viewModeOpen.value = !viewModeOpen.value;
}

function closeViewMenu() {
  viewModeOpen.value = false;
}

function onDocClick(event) {
  const root = document.getElementById('project-task-view-mode');
  if (viewModeOpen.value && root && !root.contains(event.target)) closeViewMenu();
  const exportRoot = document.getElementById('project-task-export-menu');
  if (exportMenuOpen.value && exportRoot && !exportRoot.contains(event.target)) exportMenuOpen.value = false;
}

function onKeydown(event) {
  if (event.key !== 'Escape') return;
  if (kanbanDrag.active) {
    clearKanbanDrag();
    return;
  }
  if (exportDialogOpen.value) {
    closeExportDialog();
    return;
  }
  exportMenuOpen.value = false;
  closeViewMenu();
}

function openTask(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.detail', params: { id: task.id } });
}

function openTaskEdit(task) {
  if (!task?.id) return;
  router.push({ name: 'manager.project.tasks.edit', params: { id: task.id } });
}

function openRowContextMenu(event, task) {
  if (actionDialog.kind) return;
  event.preventDefault();
  event.stopPropagation();
  ctxMenu.open = true;
  ctxMenu.x = event.clientX;
  ctxMenu.y = event.clientY;
  ctxMenu.task = task;
}

function closeRowContextMenu() {
  ctxMenu.open = false;
}

function onRowContextAction({ type, task, status, variant, focus }) {
  closeRowContextMenu();
  if (!task) return;
  if (type === 'status') {
    if (task.status !== status) {
      patchTask(task.id, { status }, `Đã chuyển sang ${statusLabel(status)}.`);
    }
    return;
  }
  if (type === 'details') {
    if (variant === 'edit') {
      openTaskEdit(task);
      return;
    }
    if (variant === 'blank') {
      window.open(router.resolve({ name: 'manager.project.tasks.detail', params: { id: task.id } }).href, '_blank');
      return;
    }
    openTask(task);
    return;
  }
  actionDialog.kind = type;
  actionDialog.task = task;
  actionDialog.extra = { variant, focus };
}

function closeActionDialog() {
  actionDialog.kind = null;
  actionDialog.task = null;
  actionDialog.extra = {};
}

function applyTaskUpdate(updated) {
  if (!updated?.id) return;
  const node = findTreeTask(props.tree, updated.id);
  applyPresentedTask(node, updated);
  if (ctxMenu.task?.id === updated.id) ctxMenu.task = { ...ctxMenu.task, ...updated };
  if (actionDialog.task?.id === updated.id) actionDialog.task = { ...actionDialog.task, ...updated };
}

function onTaskDuplicated() {
  closeActionDialog();
  emit('tasks-changed');
}

function onTaskDeleted() {
  closeActionDialog();
  emit('tasks-changed');
}

function toggleCollapse(id) {
  const next = new Set(collapsedIds.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  collapsedIds.value = next;
}

function toggleCollapsePhase(key) {
  const next = new Set(collapsedPhaseKeys.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedPhaseKeys.value = next;
}

const statusLabel = taskStatusLabel;
const statusTone = taskStatusTone;
const typeLabel = taskTypeLabel;
const typeTone = taskTypeTone;
const priorityLabel = taskPriorityLabel;
const priorityTone = taskPriorityTone;
function progressTone(percent) {
  if (percent == null) return 'neutral';
  if (percent >= 80) return 'success';
  if (percent >= 50) return 'tertiary';
  if (percent >= 25) return 'gold';
  return 'warning';
}
function hashTone(key) {
  let hash = 0;
  for (let i = 0; i < String(key).length; i += 1) hash = (hash * 31 + String(key).charCodeAt(i)) | 0;
  return THEME_TONES[Math.abs(hash) % THEME_TONES.length];
}
const formatDate = formatTaskDate;
const formatDateTime = formatTaskDateTime;
function dateRangeLabel(task) {
  if (!task.start_date && !task.end_date) return '';
  return `${formatDate(task.start_date)} – ${formatDate(task.end_date)}`;
}
const formatVarianceDays = formatTaskVarianceDays;
const cellText = taskCellText;
function colWidthStyle(key) {
  return columnWidths[key] ? `${columnWidths[key]}px` : undefined;
}
function toggleCol(key, on) {
  const col = PROJECT_TASK_COLUMNS.find((item) => item.key === key);
  if (!col || col.always) return;
  visibility[key] = on;
  saveVisibility(PROJECT_TASK_COL_KEY, { ...visibility });
}

function toggleExportMenu() {
  exportMenuOpen.value = !exportMenuOpen.value;
}

function openExportDialog(kind) {
  exportMenuOpen.value = false;
  exportKind.value = kind;
  exportSelectedColumns.value = new Set(PROJECT_TASK_COLUMNS.filter((col) => col.defaultOn).map((col) => col.key));
  exportDialogOpen.value = true;
}

function closeExportDialog() {
  if (exportBusy.value) return;
  exportDialogOpen.value = false;
}

function toggleExportColumn(key) {
  const col = PROJECT_TASK_COLUMNS.find((item) => item.key === key);
  if (col?.always) return;
  const next = new Set(exportSelectedColumns.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  exportSelectedColumns.value = next;
}

function selectAllExportColumns() {
  exportSelectedColumns.value = new Set(PROJECT_TASK_COLUMNS.map((col) => col.key));
}

function deselectAllExportColumns() {
  exportSelectedColumns.value = new Set(PROJECT_TASK_COLUMNS.filter((col) => col.always).map((col) => col.key));
}

function buildExportContext() {
  const columns = PROJECT_TASK_COLUMNS.filter((col) => col.always || exportSelectedColumns.value.has(col.key));
  const rows = visibleTasks.value.map((task) => columns.map((col) => cellText(task, col.key)));
  return { project: props.project, columns, rows };
}

async function submitExportDialog() {
  exportBusy.value = true;
  try {
    const ctx = buildExportContext();
    if (exportKind.value === 'csv') exportTaskListCsv(ctx);
    else if (exportKind.value === 'xlsx') await exportTaskListXlsx(ctx);
    else if (exportKind.value === 'pdf') await exportTaskListPdf(ctx);
    showClientToast('success', 'Đã xuất file.');
    exportDialogOpen.value = false;
  } catch (error) {
    showClientToast('error', error?.message || 'Không xuất được file.');
  } finally {
    exportBusy.value = false;
  }
}
function persistWidths() {
  try {
    localStorage.setItem(PROJECT_TASK_WIDTH_KEY, JSON.stringify({ ...columnWidths }));
  } catch {
    // Bỏ qua.
  }
}
function startResize(event, key) {
  const keys = shownColumns.value.map((col) => col.key);
  const index = keys.indexOf(key);
  const neighbor = keys[index + 1] ?? keys[index - 1];
  if (index < 0 || !neighbor || neighbor === key) return;
  const startX = event.clientX;
  const startA = Number(columnWidths[key]) || MIN_COL_PX;
  const startB = Number(columnWidths[neighbor]) || MIN_COL_PX;
  resizing.value = true;
  function onMove(moveEvent) {
    const delta = moveEvent.clientX - startX;
    const nextA = Math.max(MIN_COL_PX, startA + delta);
    const nextB = Math.max(MIN_COL_PX, startA + startB - nextA);
    columnWidths[key] = nextA;
    columnWidths[neighbor] = nextB;
  }
  function onUp() {
    resizing.value = false;
    persistWidths();
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

function kanbanDropKeyOf(task) {
  if (kanbanGroupBy.value === 'status') return task.status;
  if (kanbanGroupBy.value === 'type') return task.type;
  return task.priority || '__none__';
}

function kanbanDropSlot(col) {
  return (
    isKanbanDragGroup.value &&
    kanbanDrag.active &&
    col.dropKey &&
    kanbanDrag.overKey === col.dropKey &&
    kanbanDrag.fromKey !== col.dropKey
  );
}

function prefersReducedMotion() {
  return Boolean(window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches);
}

function waitMs(ms) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function stopKanbanPointerListeners() {
  window.removeEventListener('pointermove', onKanbanPointerMove);
  window.removeEventListener('pointerup', onKanbanPointerUp);
  window.removeEventListener('pointercancel', onKanbanPointerCancel);
}

function resetKanbanDragFields() {
  kanbanDrag.active = false;
  kanbanDrag.settling = false;
  kanbanDrag.taskId = null;
  kanbanDrag.fromKey = null;
  kanbanDrag.overKey = null;
  kanbanDrag.task = null;
  kanbanDrag.width = 0;
  kanbanDrag.height = 0;
  kanbanDrag.x = 0;
  kanbanDrag.y = 0;
}

function clearKanbanDrag() {
  if (kanbanRaf) {
    cancelAnimationFrame(kanbanRaf);
    kanbanRaf = 0;
  }
  if (kanbanScrollRaf) {
    cancelAnimationFrame(kanbanScrollRaf);
    kanbanScrollRaf = 0;
  }
  stopKanbanPointerListeners();
  kanbanPointer = null;
  document.body.style.userSelect = '';
  document.body.style.cursor = '';
  document.body.classList.remove('ptasks-kanban-dragging');
  resetKanbanDragFields();
}

function readKanbanDropKey(clientX, clientY) {
  const el = document.elementFromPoint(clientX, clientY);
  const col = el?.closest?.('.ptasks-kanban__col');
  return col?.dataset?.dropKey || null;
}

function autoScrollKanban(clientX, clientY) {
  const wrap = kanbanWrap.value;
  if (!wrap) return;
  const rect = wrap.getBoundingClientRect();
  const edge = 56;
  if (clientX < rect.left + edge) wrap.scrollLeft -= 18;
  else if (clientX > rect.right - edge) wrap.scrollLeft += 18;

  const body = document.elementFromPoint(clientX, clientY)?.closest?.('.ptasks-kanban__body');
  if (!body) return;
  const bodyRect = body.getBoundingClientRect();
  if (clientY < bodyRect.top + 44) body.scrollTop -= 14;
  else if (clientY > bodyRect.bottom - 44) body.scrollTop += 14;
}

function runKanbanAutoScroll() {
  kanbanScrollRaf = 0;
  if (!kanbanDrag.active || kanbanDrag.settling) return;
  autoScrollKanban(kanbanPendingX, kanbanPendingY);
  kanbanScrollRaf = requestAnimationFrame(runKanbanAutoScroll);
}

function flushKanbanGhost() {
  kanbanRaf = 0;
  if (!kanbanDrag.active || kanbanDrag.settling) return;
  kanbanDrag.x = kanbanPendingX - (kanbanPointer?.grabX || 0);
  kanbanDrag.y = kanbanPendingY - (kanbanPointer?.grabY || 0);
  const over = readKanbanDropKey(kanbanPendingX, kanbanPendingY);
  if (over) kanbanDrag.overKey = over;
}

function startKanbanDrag(event) {
  const pointer = kanbanPointer;
  if (!pointer) return;
  const rect = pointer.cardEl.getBoundingClientRect();
  pointer.grabX = event.clientX - rect.left;
  pointer.grabY = event.clientY - rect.top;
  kanbanDrag.active = true;
  kanbanDrag.settling = false;
  kanbanDrag.taskId = pointer.task.id;
  kanbanDrag.fromKey = kanbanDropKeyOf(pointer.task);
  kanbanDrag.overKey = kanbanDrag.fromKey;
  kanbanDrag.task = pointer.task;
  kanbanDrag.width = rect.width;
  kanbanDrag.height = rect.height;
  kanbanDrag.x = rect.left;
  kanbanDrag.y = rect.top;
  document.body.style.userSelect = 'none';
  document.body.classList.add('ptasks-kanban-dragging');
  event.preventDefault();
  if (!kanbanScrollRaf) kanbanScrollRaf = requestAnimationFrame(runKanbanAutoScroll);
}

function onKanbanCardPointerDown(event, task) {
  if (!isKanbanDragGroup.value) return;
  if (event.button !== 0) return;
  if (kanbanStatusUpdating.value.has(task.id)) return;
  if (event.target.closest('button, a, input, select, textarea, [role="menu"]')) return;
  if (kanbanDrag.active || kanbanDrag.settling) return;

  kanbanPointer = {
    task,
    cardEl: event.currentTarget,
    startX: event.clientX,
    startY: event.clientY,
    grabX: 0,
    grabY: 0,
  };
  kanbanPendingX = event.clientX;
  kanbanPendingY = event.clientY;
  window.addEventListener('pointermove', onKanbanPointerMove);
  window.addEventListener('pointerup', onKanbanPointerUp);
  window.addEventListener('pointercancel', onKanbanPointerCancel);
}

function onKanbanPointerMove(event) {
  if (!kanbanPointer) return;
  kanbanPendingX = event.clientX;
  kanbanPendingY = event.clientY;
  if (!kanbanDrag.active) {
    if (!kanbanCardsMovable.value) return;
    const dist = Math.hypot(event.clientX - kanbanPointer.startX, event.clientY - kanbanPointer.startY);
    if (dist < KANBAN_DRAG_THRESHOLD) return;
    startKanbanDrag(event);
  }
  if (!kanbanRaf) kanbanRaf = requestAnimationFrame(flushKanbanGhost);
}

async function settleKanbanGhost(rect) {
  if (!rect || prefersReducedMotion()) return;
  kanbanDrag.settling = true;
  kanbanDrag.x = rect.left;
  kanbanDrag.y = rect.top;
  await waitMs(220);
}

async function onKanbanPointerUp() {
  const pointer = kanbanPointer;
  const wasDragging = kanbanDrag.active;
  stopKanbanPointerListeners();

  if (!wasDragging) {
    kanbanPointer = null;
    if (pointer?.task) openTask(pointer.task);
    return;
  }

  const taskId = kanbanDrag.taskId;
  const fromKey = kanbanDrag.fromKey;
  const target = kanbanDrag.overKey;
  const sameColumn = !target || target === fromKey;

  if (sameColumn) {
    const origin = pointer?.cardEl?.getBoundingClientRect();
    await settleKanbanGhost(origin);
    if (!kanbanDrag.active) return;
    clearKanbanDrag();
    return;
  }

  const slot = kanbanWrap.value?.querySelector('.ptasks-kanban__placeholder');
  await settleKanbanGhost(slot?.getBoundingClientRect());
  if (!kanbanDrag.active) return;
  clearKanbanDrag();
  await commitKanbanDrop(taskId, target);
}

function onKanbanPointerCancel() {
  clearKanbanDrag();
}

function markKanbanJustMoved(taskId) {
  kanbanJustMovedId.value = taskId;
  window.clearTimeout(kanbanJustMovedTimer);
  kanbanJustMovedTimer = window.setTimeout(() => {
    if (kanbanJustMovedId.value === taskId) kanbanJustMovedId.value = null;
  }, 360);
}

function findTreeTask(nodes, id) {
  for (const node of nodes || []) {
    if (Number(node.id) === Number(id)) return node;
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

async function patchTask(taskId, payload, successMessage) {
  const node = findTreeTask(props.tree, taskId);
  if (!node) return;

  const previous = { ...node, children: node.children };
  Object.assign(node, payload);
  markKanbanJustMoved(taskId);
  const busy = new Set(kanbanStatusUpdating.value);
  busy.add(taskId);
  kanbanStatusUpdating.value = busy;

  try {
    const { data } = await window.axios.put(`/api/project/tasks/${taskId}`, payload);
    applyPresentedTask(node, data.task);
    showClientToast('success', successMessage);
  } catch (error) {
    applyPresentedTask(node, previous);
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được công việc.');
  } finally {
    const next = new Set(kanbanStatusUpdating.value);
    next.delete(taskId);
    kanbanStatusUpdating.value = next;
  }
}

async function commitKanbanDrop(taskId, targetKey) {
  if (kanbanGroupBy.value === 'status') {
    await patchTask(taskId, { status: targetKey }, `Đã chuyển sang ${statusLabel(targetKey)}.`);
    return;
  }
  if (kanbanGroupBy.value === 'priority') {
    const priority = targetKey === '__none__' ? null : targetKey;
    await patchTask(taskId, { priority }, `Đã đổi mức độ ưu tiên thành ${priorityLabel(priority)}.`);
    return;
  }
  if (kanbanGroupBy.value === 'type') {
    await patchTask(taskId, { type: targetKey }, `Đã chuyển sang ${typeLabel(targetKey)}.`);
  }
}

/** Gán độ rộng mặc định cho cột chưa có width — chạy lại mỗi khi danh sách
 *  cột hiển thị đổi (bật/tắt checkbox cột), không chỉ lúc mount. Thiếu bước
 *  này thì cột mới bật không có width trong <colgroup>, table-layout: fixed
 *  sẽ co giãn sai làm vỡ format khi bật nhiều cột. */
function ensureColumnWidths() {
  for (const col of shownColumns.value) {
    if (!columnWidths[col.key]) {
      columnWidths[col.key] = col.key === 'title' ? 280 : 140;
    }
  }
}

watch(shownColumns, () => nextTick(ensureColumnWidths));

onMounted(() => {
  document.addEventListener('mousedown', onDocClick);
  document.addEventListener('keydown', onKeydown);
  nextTick(ensureColumnWidths);
});

onBeforeUnmount(() => {
  clearKanbanDrag();
  window.clearTimeout(kanbanJustMovedTimer);
  document.removeEventListener('mousedown', onDocClick);
  document.removeEventListener('keydown', onKeydown);
});

watch(tableZoom, (value) => {
  try {
    localStorage.setItem(PROJECT_TASK_ZOOM_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
});
</script>

<template>
  <div class="ptasks" :class="{ 'ptasks--gantt': isGantt }">
    <div class="ptasks__modes">
      <ProjectTaskViewModeMenu
        :open="viewModeOpen"
        :mode="viewMode"
        :kanban-group-by="kanbanGroupBy"
        :trigger-label="triggerMeta.label"
        :trigger-icon="triggerMeta.icon"
        @toggle="toggleViewMenu"
        @select="setViewMode"
        @select-kanban="chooseKanban"
      />
      <h3 class="ptasks__filter">{{ filterLabel }}</h3>
      <label v-if="!isGantt && !isPlan" class="ptasks__search" :class="{ 'ptasks__search--with-export': isList }">
        <AppIcon name="search" :size="15" />
        <input v-model="query" type="search" placeholder="Tìm theo tên công việc…" />
      </label>
      <div v-if="isList" id="project-task-export-menu" class="ptasks__export">
        <button
          type="button"
          class="ptasks__export-trigger"
          aria-haspopup="menu"
          :aria-expanded="exportMenuOpen"
          @click.stop="toggleExportMenu"
        >
          <AppIcon name="fileDown" :size="15" />
          <span>Xuất dữ liệu</span>
          <AppIcon name="chevronDown" :size="14" />
        </button>
        <div v-if="exportMenuOpen" class="ptasks__export-menu" role="menu" @click.stop>
          <button type="button" class="ptasks__export-item" role="menuitem" @click="openExportDialog('csv')">
            <AppIcon name="fileText" :size="14" :stroke-width="1.75" />
            <span class="ptasks__export-item-copy">
              <span class="ptasks__export-item-title">CSV</span>
              <span class="ptasks__export-item-sub">Mở bằng Excel, Google Sheets</span>
            </span>
          </button>
          <button type="button" class="ptasks__export-item" role="menuitem" @click="openExportDialog('xlsx')">
            <AppIcon name="fileSpreadsheet" :size="14" :stroke-width="1.75" />
            <span class="ptasks__export-item-copy">
              <span class="ptasks__export-item-title">Excel (.xlsx)</span>
              <span class="ptasks__export-item-sub">Có định dạng cột, lọc nhanh</span>
            </span>
          </button>
          <button type="button" class="ptasks__export-item" role="menuitem" @click="openExportDialog('pdf')">
            <AppIcon name="fileDown" :size="14" :stroke-width="1.75" />
            <span class="ptasks__export-item-copy">
              <span class="ptasks__export-item-title">PDF</span>
              <span class="ptasks__export-item-sub">Bảng in sẵn, chia trang tự động</span>
            </span>
          </button>
        </div>
      </div>
    </div>

    <template v-if="isList">
      <TablePagesBar
        placement="top"
        :from="from"
        :to="to"
        :total="total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        :zoom="tableZoom"
        show-search
        :show-clear-filters="Boolean(query.trim()) || filter !== 'all'"
        @search="page = 1"
        @clear-filters="query = ''"
        @update:page="page = $event"
        @update:per-page="perPage = $event"
        @update:zoom="tableZoom = $event"
      >
        <template #settings>
          <label v-for="col in PROJECT_TASK_COLUMNS" :key="col.key" class="ptasks__check">
            <input
              type="checkbox"
              :checked="col.always || visibility[col.key]"
              :disabled="col.always"
              @change="toggleCol(col.key, $event.target.checked)"
            />
            <span>{{ col.label }}</span>
          </label>
        </template>
      </TablePagesBar>

      <div
        ref="tableWrap"
        class="ptasks__table-wrap hide-scrollbar"
        :class="{ 'ptasks__table-wrap--resizing': resizing }"
        :style="{ '--table-zoom': tableZoom }"
      >
        <table class="ptasks__table" :style="{ width: tableWidthPx }">
          <colgroup>
            <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
          </colgroup>
          <thead>
            <tr>
              <th v-for="col in shownColumns" :key="col.key" :class="{ 'ptasks__th--name': col.key === 'title' }">
                <span>{{ col.label }}</span>
                <button
                  type="button"
                  class="ptasks__resize"
                  aria-label="Kéo để đổi độ rộng cột"
                  @click.stop
                  @mousedown.stop.prevent="startResize($event, col.key)"
                />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="colSpan" class="ptasks__empty">Đang tải công việc…</td>
            </tr>
            <tr v-else-if="!pagedTasks.length">
              <td :colspan="colSpan" class="ptasks__empty">
                {{ query.trim() || filter !== 'all' ? 'Không có công việc phù hợp.' : 'Dự án chưa có công việc nào.' }}
              </td>
            </tr>
            <TaskTableRow
              v-for="task in pagedTasks"
              v-else
              :key="task.id"
              :task="task"
              :shown-columns="shownColumns"
              :collapsed-ids="collapsedIds"
              @open="openTask"
              @context-menu="openRowContextMenu"
              @toggle-collapse="toggleCollapse"
            />
          </tbody>
        </table>
      </div>

      <TablePagesBar
        placement="bottom"
        paging-only
        :from="from"
        :to="to"
        :total="total"
        :page="page"
        :last-page="lastPage"
        :per-page="perPage"
        @update:page="page = $event"
        @update:per-page="perPage = $event"
      />
    </template>

    <template v-else-if="isPhaseGroup">
      <TablePagesBar
        placement="top"
        :from="phaseGroupTotal ? 1 : 0"
        :to="phaseGroupTotal"
        :total="phaseGroupTotal"
        :page="1"
        :last-page="1"
        :per-page="phaseGroupTotal || 1"
        show-search
        :show-clear-filters="Boolean(query.trim()) || filter !== 'all'"
        @clear-filters="query = ''"
      >
        <template #settings>
          <label v-for="col in PROJECT_TASK_COLUMNS" :key="col.key" class="ptasks__check">
            <input
              type="checkbox"
              :checked="col.always || visibility[col.key]"
              :disabled="col.always"
              @change="toggleCol(col.key, $event.target.checked)"
            />
            <span>{{ col.label }}</span>
          </label>
        </template>
      </TablePagesBar>

      <div
        ref="tableWrap"
        class="ptasks__table-wrap hide-scrollbar"
        :class="{ 'ptasks__table-wrap--resizing': resizing }"
        :style="{ '--table-zoom': tableZoom }"
      >
        <table class="ptasks__table" :style="{ width: tableWidthPx }">
          <colgroup>
            <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
          </colgroup>
          <thead>
            <tr>
              <th v-for="col in shownColumns" :key="col.key" :class="{ 'ptasks__th--name': col.key === 'title' }">
                <span>{{ col.label }}</span>
                <button
                  type="button"
                  class="ptasks__resize"
                  aria-label="Kéo để đổi độ rộng cột"
                  @click.stop
                  @mousedown.stop.prevent="startResize($event, col.key)"
                />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="colSpan" class="ptasks__empty">Đang tải công việc…</td>
            </tr>
            <tr v-else-if="!phaseGroups.length">
              <td :colspan="colSpan" class="ptasks__empty">
                {{ query.trim() || filter !== 'all' ? 'Không có công việc phù hợp.' : 'Dự án chưa có công việc nào.' }}
              </td>
            </tr>
            <template v-for="group in phaseGroups" v-else :key="group.key">
              <tr class="ptasks__group-row" @click="toggleCollapsePhase(group.key)">
                <td :colspan="colSpan">
                  <span class="ptasks__group-toggle">
                    <span class="ptasks__group-head">
                      <AppIcon
                        name="chevronRight"
                        :size="14"
                        class="ptasks__group-chevron"
                        :class="{ 'ptasks__group-chevron--open': !collapsedPhaseKeys.has(group.key) }"
                      />
                      <span class="ptasks__group-label">{{ group.title }}</span>
                    </span>
                    <span class="ptasks__group-meta">
                      <span v-if="group.avgProgress != null" class="ptasks__group-progress">Tiến độ {{ group.avgProgress }}%</span>
                      <span class="ptasks__group-count">{{ group.tasks.length }} công việc</span>
                    </span>
                  </span>
                </td>
              </tr>
              <TaskTableRow
                v-for="task in group.tasks"
                v-show="!collapsedPhaseKeys.has(group.key)"
                :key="task.id"
                :task="task"
                :shown-columns="shownColumns"
                :collapsed-ids="collapsedIds"
                @open="openTask"
                @context-menu="openRowContextMenu"
                @toggle-collapse="toggleCollapse"
              />
            </template>
          </tbody>
        </table>
      </div>
    </template>

    <div
      v-else-if="isKanban"
      ref="kanbanWrap"
      class="ptasks-kanban hide-scrollbar"
      :class="{
        'ptasks-kanban--spread': kanbanGroupBy === 'priority' || kanbanGroupBy === 'type',
        'ptasks-kanban--dragging': kanbanDrag.active,
      }"
    >
      <p v-if="loading" class="ptasks__empty">Đang tải công việc…</p>
      <p v-else-if="!kanbanSourceTasks.length" class="ptasks__empty">
        {{ query.trim() || filter !== 'all' ? 'Không có công việc phù hợp.' : 'Dự án chưa có công việc nào.' }}
      </p>
      <div
        v-for="col in kanbanColumns"
        v-else
        :key="col.key"
        class="ptasks-kanban__col"
        :class="[
          `ptasks-kanban__col--${col.tone || 'primary'}`,
          { 'ptasks-kanban__col--drop': isKanbanDragGroup && kanbanDrag.active && kanbanDrag.overKey === col.dropKey },
        ]"
        :data-drop-key="isKanbanDragGroup ? col.dropKey : undefined"
      >
        <header class="ptasks-kanban__head">
          <div class="ptasks-kanban__head-main">
            <UserAvatarTip v-if="col.user" :user="col.user" label="Người thực hiện" />
            <span class="ptasks-kanban__title">{{ col.label }}</span>
          </div>
          <span class="ptasks-kanban__count">{{ col.tasks.length }}</span>
        </header>
        <div class="ptasks-kanban__body hide-scrollbar">
          <div
            v-if="kanbanDropSlot(col)"
            class="ptasks-kanban__placeholder"
            :style="{ height: `${kanbanDrag.height}px` }"
          />
          <article
            v-for="task in col.tasks"
            :key="task.id"
            class="ptasks-kanban__card"
            :class="{
              'ptasks-kanban__card--movable': kanbanCardsMovable,
              'ptasks-kanban__card--slot': kanbanDrag.active && kanbanDrag.taskId === task.id,
              'ptasks-kanban__card--enter': kanbanJustMovedId === task.id,
            }"
            data-no-drag-scroll
            @pointerdown="onKanbanCardPointerDown($event, task)"
            @click="!isKanbanDragGroup && openTask(task)"
            @contextmenu.stop="openRowContextMenu($event, task)"
          >
            <span v-if="task.is_overdue" class="ptasks-kanban__overdue" aria-hidden="true" />
            <header class="ptasks-kanban__card-head">
              <span v-if="task.code" class="ptasks-kanban__code">{{ task.code }}</span>
              <span class="ptasks-kanban__type">{{ typeLabel(task.type) }}</span>
              <span v-if="task.weight != null" class="ptasks-kanban__weight">{{ task.weight }}%</span>
            </header>
            <h3 class="ptasks-kanban__card-title">{{ task.title }}</h3>
            <div v-if="task.priority && task.priority !== 'low'" class="ptasks-kanban__labels">
              <span class="ptasks-kanban__prio" :class="`ptasks-kanban__prio--${priorityTone(task.priority)}`">
                {{ priorityLabel(task.priority) }}
              </span>
            </div>
            <dl v-if="task.assignee?.name || dateRangeLabel(task)" class="ptasks-kanban__facts">
              <div v-if="task.assignee?.name" class="ptasks-kanban__fact">
                <dt>Người thực hiện</dt>
                <dd>{{ task.assignee.name }}</dd>
              </div>
              <div v-if="dateRangeLabel(task)" class="ptasks-kanban__fact">
                <dt>Thời hạn</dt>
                <dd>{{ dateRangeLabel(task) }}</dd>
              </div>
            </dl>
            <div v-if="task.progress_percent != null" class="ptasks-kanban__progress">
              <div class="ptasks-kanban__progress-head">
                <span class="ptasks-kanban__progress-label">Tiến độ</span>
                <span>{{ task.progress_percent }}%</span>
              </div>
              <span class="ptasks__mini">
                <span
                  class="ptasks__mini-fill"
                  :class="`ptasks__mini-fill--${progressTone(task.progress_percent)}`"
                  :style="{ width: `${task.progress_percent}%` }"
                />
              </span>
            </div>
            <footer class="ptasks-kanban__foot">
              <span class="ptasks-kanban__avatars">
                <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
              </span>
              <span class="ptasks-kanban__meta">
                <span class="ptasks-kanban__stat">{{ statusLabel(task.status) }}</span>
              </span>
            </footer>
            <span v-if="kanbanCardsMovable" class="ptasks-kanban__grip" aria-hidden="true">
              <AppIcon name="gripVertical" :size="14" />
            </span>
            <div v-if="kanbanStatusUpdating.has(task.id)" class="ptasks-kanban__busy">
              <AppIcon name="refresh" :size="14" class="ptasks__spin" />
            </div>
          </article>
          <p v-if="!col.tasks.length && !kanbanDropSlot(col)" class="ptasks-kanban__empty">Không có công việc nào.</p>
        </div>
      </div>
    </div>

    <ProjectSprintBoard
      v-else-if="isSprintBoard"
      :tree="tree"
      :loading="loading"
      :project="project"
      :filter="filter"
      :filter-label="filterLabel"
      :query="query"
    />

    <ProjectPlanTab
      v-else-if="isPlan"
      :tree="tree"
      :loading="loading"
      :project="project"
    />

    <ProjectGanttTab
      v-else
      fill
      :tree="tree"
      :loading="loading"
      :project="project"
      :filter="filter"
      :filter-label="filterLabel"
    />

    <Teleport to="body">
      <div
        v-if="kanbanDrag.active && kanbanDrag.task"
        class="ptasks-kanban__ghost"
        :class="{
          'ptasks-kanban__ghost--live': !kanbanDrag.settling,
          'ptasks-kanban__ghost--settle': kanbanDrag.settling,
          [`ptasks-kanban__ghost--${kanbanGhostTone}`]: true,
        }"
        :style="kanbanGhostStyle"
      >
        <header class="ptasks-kanban__card-head">
          <span v-if="kanbanDrag.task.code" class="ptasks-kanban__code">{{ kanbanDrag.task.code }}</span>
          <span class="ptasks-kanban__type">{{ typeLabel(kanbanDrag.task.type) }}</span>
        </header>
        <h3 class="ptasks-kanban__card-title">{{ kanbanDrag.task.title }}</h3>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="exportDialogOpen"
        class="ptasks__dialog"
        role="presentation"
        @mousedown.self="closeExportDialog"
      >
        <div class="ptasks__dialog-panel" role="dialog" aria-modal="true" aria-labelledby="ptasks-export-title">
          <div class="ptasks__dialog-head">
            <span class="ptasks__dialog-icon" aria-hidden="true">
              <AppIcon name="fileDown" :size="22" :stroke-width="1.75" />
            </span>
            <div class="ptasks__dialog-head-copy">
              <h2 id="ptasks-export-title" class="ptasks__dialog-title">
                Xuất công việc ra {{ exportKind === 'csv' ? 'CSV' : exportKind === 'pdf' ? 'PDF' : 'Excel' }}
              </h2>
            </div>
            <button
              type="button"
              class="ptasks__dialog-close"
              aria-label="Đóng"
              :disabled="exportBusy"
              @click="closeExportDialog"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="ptasks__dialog-body">
            <p class="ptasks__dialog-hint">
              Xuất theo đúng bộ lọc và tìm kiếm đang xem trên tab. Chọn cột cần xuất — cột Tên công việc luôn được xuất.
            </p>
            <div class="ptasks__dialog-toolbar">
              <button type="button" class="ptasks__dialog-link" @click="selectAllExportColumns">Chọn tất cả</button>
              <button type="button" class="ptasks__dialog-link" @click="deselectAllExportColumns">Bỏ chọn tất cả</button>
            </div>
            <div class="ptasks__dialog-grid">
              <label
                v-for="col in PROJECT_TASK_COLUMNS"
                :key="col.key"
                class="ptasks__dialog-col"
                :class="{ 'ptasks__dialog-col--disabled': col.always }"
              >
                <input
                  type="checkbox"
                  :checked="col.always || exportSelectedColumns.has(col.key)"
                  :disabled="col.always"
                  @change="toggleExportColumn(col.key)"
                />
                <span>{{ col.label }}</span>
              </label>
            </div>
          </div>

          <div class="ptasks__dialog-actions">
            <button type="button" class="ptasks__dialog-btn ptasks__dialog-btn--ghost" :disabled="exportBusy" @click="closeExportDialog">
              Đóng
            </button>
            <button
              type="button"
              class="ptasks__dialog-btn ptasks__dialog-btn--primary"
              :disabled="exportBusy"
              @click="submitExportDialog"
            >
              {{ exportBusy ? 'Đang xuất…' : `Xuất (${visibleTasks.length} công việc)` }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <TaskRowContextMenu
      :open="ctxMenu.open"
      :x="ctxMenu.x"
      :y="ctxMenu.y"
      :task="ctxMenu.task"
      :can-edit="canEdit"
      :can-approve="canApprove"
      :can-duplicate="canEdit"
      @close="closeRowContextMenu"
      @action="onRowContextAction"
    />

    <TaskQuickActionModals
      :kind="actionDialog.kind"
      :task="actionDialog.task"
      :extra="actionDialog.extra"
      @close="closeActionDialog"
      @updated="applyTaskUpdate"
      @duplicated="onTaskDuplicated"
      @deleted="onTaskDeleted"
    />
  </div>
</template>

<style scoped>
.ptasks {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  height: 100%;
  overflow: hidden;
  background: var(--color-surface);
}

.ptasks--gantt {
  min-height: 0;
}

.ptasks__modes {
  position: relative;
  z-index: 12;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
  min-width: 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.ptasks__filter {
  margin: 0;
  display: flex;
  align-items: center;
  padding: 0 0.75rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  white-space: nowrap;
}

.ptasks__search {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.375rem 0.75rem 0.375rem auto;
  height: 1.875rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.ptasks__search--with-export {
  margin-right: 0.5rem;
}

.ptasks__search input {
  width: 14rem;
  max-width: 36vw;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.ptasks__search input:focus {
  outline: none;
}

.ptasks__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.ptasks__export {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  margin: 0.375rem 0.75rem 0.375rem 0;
}

.ptasks__export-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 1.875rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.ptasks__export-trigger:hover,
.ptasks__export-trigger[aria-expanded='true'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.ptasks__export-menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  right: 0;
  display: flex;
  flex-direction: column;
  min-width: 16.5rem;
  padding: 0.25rem;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.ptasks__export-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.ptasks__export-item:hover {
  background: var(--color-surface-muted);
}

.ptasks__export-item-copy {
  display: flex;
  flex-direction: column;
  gap: 0.0625rem;
}

.ptasks__export-item-title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.ptasks__export-item-sub {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.ptasks__dialog {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.ptasks__dialog-panel {
  width: min(40rem, calc(100vw - 2.5rem));
  height: auto;
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: 1rem 1.25rem 1rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.ptasks__dialog-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-3);
}

.ptasks__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.ptasks__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.ptasks__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.ptasks__dialog-close {
  display: inline-flex;
  flex-shrink: 0;
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

.ptasks__dialog-close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.ptasks__dialog-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}

.ptasks__dialog-hint {
  margin: 0 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.ptasks__dialog-toolbar {
  display: flex;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}

.ptasks__dialog-link {
  display: inline;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

.ptasks__dialog-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
  overflow-y: auto;
}

.ptasks__dialog-col {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.ptasks__dialog-col:hover {
  background: var(--color-surface-muted);
}

.ptasks__dialog-col--disabled {
  color: var(--color-text-muted);
  cursor: default;
}

.ptasks__dialog-col--disabled:hover {
  background: transparent;
}

.ptasks__dialog-actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.ptasks__dialog-btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.ptasks__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.ptasks__dialog-btn--primary:hover {
  background: var(--color-primary-hover);
}

.ptasks__dialog-btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.ptasks__dialog-btn--ghost:hover {
  background: var(--color-surface-muted);
}

.ptasks__dialog-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 768px) {
  .ptasks__dialog-panel {
    width: calc(100vw - 1.5rem);
    max-height: calc(100vh - 1.5rem);
  }

  .ptasks__dialog-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

.ptasks__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.ptasks__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.ptasks__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.ptasks__table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  overflow: hidden;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: calc(0.75rem * var(--table-zoom, 1));
  text-align: center;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.ptasks__table thead th > span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ptasks__th--name {
  text-align: left;
}

.ptasks__resize {
  position: absolute;
  top: 0;
  right: 0;
  z-index: 2;
  width: 0.5rem;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  cursor: col-resize;
}

.ptasks__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.ptasks__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.ptasks__td--name {
  overflow: visible;
  white-space: normal;
}

.ptasks__td--avatar {
  overflow: visible;
}

.ptasks__row {
  cursor: pointer;
}

.ptasks__row:hover td {
  filter: brightness(0.97);
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

.ptasks__empty {
  padding: var(--space-5);
  color: var(--color-text-muted);
  text-align: center;
  white-space: normal;
}

.ptasks__group-row {
  cursor: pointer;
}

.ptasks__group-row td {
  position: relative;
  width: 100%;
  overflow: visible;
  padding: var(--space-2) var(--space-4) var(--space-2) calc(var(--space-4) + 3px + var(--space-2)) !important;
  background: var(--color-surface-muted);
  color: var(--color-text);
  box-shadow: inset 0 -2px 0 var(--color-border) !important;
}

.ptasks__group-row td::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--color-gold);
}

.ptasks__group-row:hover td {
  background: color-mix(in srgb, var(--color-gold) 6%, var(--color-surface));
}

.ptasks__group-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  min-width: 0;
}

.ptasks__group-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.ptasks__group-chevron {
  flex-shrink: 0;
  color: var(--color-gold);
  transition: transform 0.15s ease;
}

.ptasks__group-chevron--open {
  transform: rotate(90deg);
}

.ptasks__group-label {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: calc(0.8125rem * var(--table-zoom, 1));
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ptasks__group-meta {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
}

.ptasks__group-progress,
.ptasks__group-count {
  color: var(--color-text-muted);
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 600;
  white-space: nowrap;
}

.ptasks__mini {
  display: block;
  width: 100%;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.ptasks__mini-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}
.ptasks__mini-fill--success { background: var(--color-success); }
.ptasks__mini-fill--tertiary { background: var(--color-tertiary); }
.ptasks__mini-fill--gold { background: var(--color-gold); }
.ptasks__mini-fill--warning { background: var(--color-warning); }
.ptasks__mini-fill--neutral { background: var(--color-text-muted); }

.ptasks-kanban {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  padding: var(--space-3);
  overflow-x: auto;
  overflow-y: hidden;
}

.ptasks-kanban--dragging {
  cursor: var(--cursor-grabbing);
}

.ptasks-kanban--spread {
  overflow-x: hidden;
}

.ptasks-kanban--spread .ptasks-kanban__col {
  flex: 1 1 0;
  width: auto;
}

.ptasks-kanban__col {
  --col-accent: var(--color-primary);
  --col-head: var(--color-primary);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-primary-surface);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  width: 22rem;
  min-width: 0;
  min-height: 0;
  height: auto;
  align-self: stretch;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--col-well);
}

.ptasks-kanban__col--drop {
  background: color-mix(in srgb, var(--col-well) 82%, var(--color-surface));
  box-shadow:
    inset 0 0 0 2px var(--col-accent),
    0 12px 28px color-mix(in srgb, var(--col-accent) 18%, transparent);
  transform: translateY(-2px);
}

.ptasks-kanban__col--primary { --col-accent: var(--color-primary); --col-head: var(--color-primary); --col-on: var(--color-on-primary); --col-well: var(--color-primary-surface); }
.ptasks-kanban__col--tertiary { --col-accent: var(--color-tertiary); --col-head: var(--color-tertiary); --col-on: var(--color-on-tertiary); --col-well: var(--color-tertiary-surface); }
.ptasks-kanban__col--gold { --col-accent: var(--color-gold-600); --col-head: var(--color-gold-600); --col-on: var(--color-on-gold); --col-well: var(--color-gold-surface); }
.ptasks-kanban__col--success { --col-accent: var(--color-success); --col-head: var(--color-success); --col-on: #fff; --col-well: var(--color-success-tint-bg); }
.ptasks-kanban__col--danger { --col-accent: var(--color-danger); --col-head: var(--color-danger); --col-on: #fff; --col-well: var(--color-danger-tint-bg); }
.ptasks-kanban__col--warning { --col-accent: var(--color-warning); --col-head: var(--color-warning); --col-on: #fff; --col-well: var(--color-warning-tint-bg); }
.ptasks-kanban__col--info { --col-accent: var(--color-info); --col-head: var(--color-info); --col-on: #fff; --col-well: var(--color-info-tint-bg); }
.ptasks-kanban__col--violet { --col-accent: var(--color-tertiary); --col-head: var(--color-tertiary); --col-on: #fff; --col-well: var(--color-tertiary-surface); }
.ptasks-kanban__col--umber { --col-accent: var(--color-umber); --col-head: var(--color-umber); --col-on: var(--color-on-umber); --col-well: var(--color-umber-surface); }
.ptasks-kanban__col--neutral { --col-accent: var(--color-text-muted); --col-head: var(--color-text-muted); --col-on: #fff; --col-well: var(--color-surface-muted); }
.ptasks-kanban__col--secondary { --col-accent: var(--color-secondary); --col-head: var(--color-secondary); --col-on: var(--color-on-secondary); --col-well: var(--color-secondary-surface); }
.ptasks-kanban__col--teal { --col-accent: var(--color-secondary); --col-head: var(--color-secondary); --col-on: #fff; --col-well: var(--color-secondary-surface); }
.ptasks-kanban__col--rose { --col-accent: var(--color-primary); --col-head: var(--color-primary); --col-on: #fff; --col-well: var(--color-primary-surface); }

.ptasks-kanban__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0.875rem var(--space-4);
  background: var(--col-head);
  color: var(--col-on);
}

.ptasks-kanban__head-main {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.5rem;
}

.ptasks-kanban__title {
  overflow: hidden;
  color: var(--col-on);
  font-size: 0.875rem;
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ptasks-kanban__count {
  display: inline-flex;
  min-width: 1.5rem;
  height: 1.5rem;
  align-items: center;
  justify-content: center;
  padding: 0 0.5rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--col-on) 18%, transparent);
  color: var(--col-on);
  font-size: 0.75rem;
}

.ptasks-kanban__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3);
}

.ptasks-kanban__placeholder {
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--col-accent) 14%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 32%, transparent);
}

.ptasks-kanban__card {
  position: relative;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  gap: 0.625rem;
  min-width: 0;
  min-height: 18rem;
  padding: var(--space-4);
  padding-bottom: var(--space-5);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  overflow: visible;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.ptasks-kanban__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--col-accent);
}

.ptasks-kanban__card:hover {
  box-shadow: var(--shadow-md);
}

.ptasks-kanban__card--movable {
  touch-action: none;
  cursor: var(--cursor-grab);
}

.ptasks-kanban__card--slot {
  overflow: hidden;
  background: color-mix(in srgb, var(--col-accent) 12%, var(--color-surface-muted));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 26%, transparent);
}

.ptasks-kanban__card--slot > *:not(.ptasks-kanban__busy) {
  visibility: hidden;
}

.ptasks-kanban__card--slot::before {
  opacity: 0;
}

.ptasks-kanban__card--enter {
  animation: ptasks-kanban-pop 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes ptasks-kanban-pop {
  from {
    transform: scale(0.96);
  }
  to {
    transform: scale(1);
  }
}

.ptasks-kanban__overdue {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
}

.ptasks-kanban__card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding-right: 1.125rem;
}

.ptasks-kanban__code,
.ptasks-kanban__type,
.ptasks-kanban__weight {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.ptasks-kanban__type {
  flex: 1;
  font-style: italic;
  text-align: right;
}

.ptasks-kanban__card-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 400;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.ptasks-kanban__labels {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.ptasks-kanban__prio {
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
}

.ptasks-kanban__prio--danger { background: var(--color-danger-tint-bg); color: var(--color-danger-tint-fg); }
.ptasks-kanban__prio--gold { background: var(--color-gold-surface); color: var(--color-gold-800); }
.ptasks-kanban__prio--info { background: var(--color-info-tint-bg); color: var(--color-info-tint-fg); }

.ptasks-kanban__facts {
  margin: 0;
  display: grid;
  gap: 0.25rem;
}

.ptasks-kanban__fact {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

.ptasks-kanban__fact dt {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.ptasks-kanban__fact dt::after {
  content: ':';
}

.ptasks-kanban__fact dd {
  margin: 0;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
  text-align: right;
}

.ptasks-kanban__progress {
  display: grid;
  gap: 0.375rem;
}

.ptasks-kanban__progress-head {
  display: flex;
  justify-content: space-between;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.ptasks-kanban__progress-label::after {
  content: ':';
}

.ptasks-kanban__foot {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  margin-top: auto;
  padding-top: 0.625rem;
  overflow: visible;
  box-shadow: 0 -1px 0 var(--color-border);
}

.ptasks-kanban__avatars {
  display: flex;
  flex-shrink: 0;
  min-width: max-content;
  align-items: center;
  padding: 2px;
  overflow: visible;
}

.ptasks-kanban__meta,
.ptasks-kanban__stat {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
}

.ptasks-kanban__grip {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  color: var(--color-text-muted);
  opacity: 0;
  pointer-events: none;
}

.ptasks-kanban__card--movable:hover .ptasks-kanban__grip {
  opacity: 0.5;
}

.ptasks-kanban__busy {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  z-index: 1;
  display: inline-flex;
  color: var(--color-primary);
}

.ptasks__spin {
  animation: ptasks-spin 0.8s linear infinite;
}

@keyframes ptasks-spin {
  to {
    transform: rotate(360deg);
  }
}

.ptasks-kanban__ghost {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 80;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  min-height: 18rem;
  padding: var(--space-4);
  padding-bottom: var(--space-5);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  pointer-events: none;
  transform-origin: top left;
}

.ptasks-kanban__ghost::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  background: var(--col-accent, var(--color-primary));
}

.ptasks-kanban__ghost--primary { --col-accent: var(--color-primary); }
.ptasks-kanban__ghost--gold { --col-accent: var(--color-gold-600); }
.ptasks-kanban__ghost--success { --col-accent: var(--color-success); }
.ptasks-kanban__ghost--danger { --col-accent: var(--color-danger); }
.ptasks-kanban__ghost--info { --col-accent: var(--color-info); }
.ptasks-kanban__ghost--tertiary { --col-accent: var(--color-tertiary); }
.ptasks-kanban__ghost--umber { --col-accent: var(--color-umber); }
.ptasks-kanban__ghost--secondary { --col-accent: var(--color-secondary); }
.ptasks-kanban__ghost--violet { --col-accent: var(--color-tertiary); }
.ptasks-kanban__ghost--warning { --col-accent: var(--color-warning); }
.ptasks-kanban__ghost--neutral { --col-accent: var(--color-text-muted); }
.ptasks-kanban__ghost--teal { --col-accent: var(--color-secondary); }
.ptasks-kanban__ghost--rose { --col-accent: var(--color-primary); }

.ptasks-kanban__ghost--live {
  box-shadow:
    var(--shadow-lg),
    0 18px 40px color-mix(in srgb, var(--col-accent, var(--color-primary)) 18%, transparent);
}

.ptasks-kanban__ghost--settle {
  opacity: 1;
  transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.22s ease;
}

:global(body.ptasks-kanban-dragging) {
  cursor: var(--cursor-grabbing);
}

@media (prefers-reduced-motion: reduce) {
  .ptasks__spin,
  .ptasks-kanban__card--enter {
    animation: none;
  }

  .ptasks-kanban__ghost--settle {
    transition: none;
  }
}

.ptasks-kanban__empty {
  margin: auto 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.ptasks :deep(.gantt) {
  flex: 1;
  min-height: 0;
  height: auto;
}
</style>
