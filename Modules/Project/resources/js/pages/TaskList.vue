<script setup>
//
// "Tất cả công việc" — cùng chrome với ProjectList: tìm trên PageHeader,
// hàng tab (Danh sách / Kanban + lọc nhanh có đếm), bảng nhóm theo dự án,
// pill/progress, kéo cột, panel chi tiết 28rem. Tạo công việc mới vẫn
// không có ở trang này (menu chuột phải trong từng dự án).
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import TablePagesBar from '@/components/TablePagesBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { showClientToast } from '@/lib/clientToast';
import { useDragScroll } from '@/composables/useDragScroll';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import {
  COLUMN_STORAGE_KEY,
  COLUMN_WIDTH_KEY,
  COLLAPSED_GROUPS_KEY,
  FILTER_STORAGE_KEY,
  KANBAN_ASSIGNEES_KEY,
  KANBAN_GROUP_KEY,
  TASK_COLUMNS,
  TASK_FILTERS,
  TASK_PRIORITY_LABELS,
  TASK_PRIORITY_TONES,
  TASK_PROGRESS_TYPE_LABELS,
  TASK_SCORE_RESULT_SUGGESTIONS,
  TASK_STATUS_LABELS,
  TASK_STATUS_TAB_KEYS,
  TASK_STATUS_TONES,
  TASK_STATUSES,
  TASK_TABS,
  TASK_TYPE_LABELS,
  TASK_TYPE_TONES,
  VIEW_MODE_KEY,
  ZOOM_STORAGE_KEY,
  loadVisibility,
  saveVisibility,
} from '../constants/task.js';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const CELL_PAD_X = 32;
const COL_EXTRA = 24;
const ASSIGNEE_AVATAR_EXTRA = 42;
const STATUS_DOT_EXTRA = 28;
const MIN_COL_PX = 72;
const UNGROUPED_KEY = '__ungrouped__';
const KANBAN_PER_PAGE = 500;
const KANBAN_DRAG_THRESHOLD = 7;
const KANBAN_GROUP_MODES = ['status', 'assignees', 'project', 'priority', 'type'];
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

let measureCtx = null;
let wrapObserver = null;
let kanbanPointer = null;
let kanbanPendingX = 0;
let kanbanPendingY = 0;
let kanbanRaf = 0;
let kanbanScrollRaf = 0;
let kanbanJustMovedTimer = 0;

const tasks = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 });
const loading = ref(false);
const selected = ref(null);
const editing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);

const taskAttachments = ref([]);
const attachmentUploading = ref(false);
const attachmentInput = ref(null);

const taskWorklogs = ref([]);
const worklogFormOpen = ref(false);
const worklogSaving = ref(false);
const worklogForm = reactive({ work_date: '', hours: '', note: '' });
const confirmingDeleteWorklog = ref(null);

const scoreSaving = ref(false);
const scoreForm = reactive({ rating_score: '', rating_result: '', rating_desc: '' });

const query = ref('');
const projectId = ref('');
const assigneeId = ref('');
const managerId = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const isOverdueOnly = ref(false);
const progressTypeFilter = ref('');
const perPage = ref(20);
const sortBy = ref('');
const sortDir = ref('desc');

const selectedTaskIds = ref(new Set());
const bulkSaving = ref(false);
const bulkManagerId = ref('');
const bulkWeight = ref('');

// ---------- Xuất/Nhập Excel (PR8) — cùng khuôn ProjectList.vue ----------
const exporting = ref(false);
const importDialogOpen = ref(false);
const importStep = ref('select');
const importFile = ref(null);
const importPreview = ref(null);
const importSelectedRows = ref(new Set());
const importResult = ref(null);
const previewing = ref(false);
const confirming = ref(false);
const editingRowKey = ref(null);
const editingRowDraft = reactive({});
const resolvingRow = ref(false);

/** key frontend (cột Excel) — khớp TaskExcelExporter::COLUMNS; 'code'/'project_code' luôn xuất. */
const EXPORT_COLUMNS = [
  { key: 'code', label: 'Mã công việc', always: true },
  { key: 'project_code', label: 'Mã dự án', always: true },
  { key: 'title', label: 'Tên công việc' },
  { key: 'type_label', label: 'Loại' },
  { key: 'status_label', label: 'Trạng thái' },
  { key: 'priority_label', label: 'Mức độ ưu tiên' },
  { key: 'assignee_email', label: 'Người thực hiện (email)' },
  { key: 'manager_email', label: 'Người quản lý (email)' },
  { key: 'start_date', label: 'Ngày bắt đầu' },
  { key: 'end_date', label: 'Ngày kết thúc' },
  { key: 'progress_type_label', label: 'Cách tính tiến độ' },
  { key: 'progress_percent', label: 'Tiến độ (%)' },
  { key: 'progress_number', label: 'Khối lượng hoàn thành' },
  { key: 'progress_total', label: 'Khối lượng cần hoàn thành' },
  { key: 'unit', label: 'Đơn vị' },
  { key: 'estimated_hours', label: 'Thời gian dự kiến (giờ)' },
  { key: 'weight', label: 'Tỷ trọng (%)' },
  { key: 'description', label: 'Mô tả' },
];
const exportDialogOpen = ref(false);
const exportSelectedColumns = ref(new Set(EXPORT_COLUMNS.map((c) => c.key)));

const activeTab = ref(typeof route.query.tab === 'string' && route.query.tab ? route.query.tab : 'all');
const tabCounts = ref({});

const projects = ref([]);
const users = ref([]);

const visibleColumns = reactive(loadVisibility(COLUMN_STORAGE_KEY, TASK_COLUMNS));
const visibleFilters = reactive(loadVisibility(FILTER_STORAGE_KEY, TASK_FILTERS));
const columnWidths = reactive(loadColumnWidths());
const collapsedGroups = ref(new Set(loadCollapsedGroups()));

const tableWrap = ref(null);
const kanbanWrap = ref(null);
const resizing = ref(false);
const tableZoom = ref(loadZoom());

useDragScroll(tableWrap, { isBlocked: () => resizing.value, axis: 'x' });
useDragScroll(kanbanWrap, { axis: 'x', isBlocked: () => kanbanDrag.active });

const editForm = reactive({
  title: '',
  status: 'not_started',
  priority: '',
  start_date: '',
  start_time: '',
  end_date: '',
  due_time: '',
  actual_start_date: '',
  actual_end_date: '',
  assignee_id: '',
  progress_percent: '',
  description: '',
  parent_id: '',
  manager_id: '',
  estimated_hours: '',
  progress_type: 'percent',
  progress_number: '',
  progress_total: '',
  unit: '',
  weight: '',
});

/** Ước tính progress_percent phía client khi progress_type=quantity — chỉ
 *  hiển thị tham khảo trước khi lưu, server luôn là nguồn thật (present()). */
const editFormEstimatedPercent = computed(() => {
  const number = Number(editForm.progress_number);
  const total = Number(editForm.progress_total);
  if (!editForm.progress_number || !total || total <= 0) return null;
  return Math.round((number / total) * 100);
});

const viewMode = ref(loadViewMode());
const kanbanGroupBy = ref(loadKanbanGroup());
const kanbanAssigneeIds = ref(loadKanbanAssigneeIds());
const kanbanAssigneePickerOpen = ref(false);
const viewModeOpen = ref(false);
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

const shownColumns = computed(() => TASK_COLUMNS.filter((col) => col.always || visibleColumns[col.key]));
const colSpan = computed(() => Math.max(shownColumns.value.length, 1) + 1); // +1 cột checkbox chọn dòng (PR7)
const isKanban = computed(() => viewMode.value === 'kanban');
const canEdit = computed(() => auth.can('task.create'));
const canApprove = computed(() => auth.can('task.approve'));

const KANBAN_GROUP_LABELS = {
  status: 'Theo trạng thái',
  assignees: 'Theo người thực hiện',
  project: 'Theo dự án',
  priority: 'Theo mức độ ưu tiên',
  type: 'Theo loại',
};

const viewModeTriggerLabel = computed(() => {
  if (!isKanban.value) return 'Danh sách';
  return KANBAN_GROUP_LABELS[kanbanGroupBy.value] || 'Kanban';
});

const hasActiveFilters = computed(
  () =>
    Boolean(query.value.trim()) ||
    Boolean(projectId.value) ||
    Boolean(assigneeId.value) ||
    Boolean(managerId.value) ||
    Boolean(dateFrom.value) ||
    Boolean(dateTo.value) ||
    isOverdueOnly.value ||
    Boolean(progressTypeFilter.value) ||
    (activeTab.value && activeTab.value !== 'all'),
);

const hasVisibleFilterFields = computed(() => TASK_FILTERS.some((item) => visibleFilters[item.key]));

const tableWidthPx = computed(() => {
  const keys = shownColumns.value.map((col) => col.key);
  const sum = keys.reduce((total, key) => total + (Number(columnWidths[key]) || 0), 0);
  return sum > 0 ? `${sum}px` : '100%';
});

const groupedTasks = computed(() => {
  const groups = new Map();
  for (const task of tasks.value) {
    const key = task.project_id ? `project-${task.project_id}` : UNGROUPED_KEY;
    const label = task.project?.name || 'Chưa gắn dự án';
    if (!groups.has(key)) {
      groups.set(key, { key, label, tone: hashTone(key), tasks: [] });
    }
    groups.get(key).tasks.push(task);
  }
  const list = Array.from(groups.values()).sort((a, b) => a.label.localeCompare(b.label, 'vi'));
  for (const group of list) {
    group.tasks = buildTaskTree(group.tasks);
  }
  return list;
});

/**
 * Cây WBS thu gọn/mở rộng theo parent_id trong phạm vi 1 group (dự án) —
 * thứ tự cha-trước-con, kèm depth để thụt lề + hasChildren để hiện mũi tên.
 * Chỉ áp dụng List view (Kanban giữ nguyên phẳng theo status/assignee/...).
 */
function buildTaskTree(tasksInGroup) {
  const byParent = new Map();
  for (const task of tasksInGroup) {
    const key = task.parent_id ?? 0;
    if (!byParent.has(key)) byParent.set(key, []);
    byParent.get(key).push(task);
  }

  const result = [];
  function walk(parentKey, depth) {
    for (const task of byParent.get(parentKey) || []) {
      const hasChildren = byParent.has(task.id);
      result.push({ ...task, depth, hasChildren });
      if (hasChildren && !isTaskCollapsed(task.id)) walk(task.id, depth + 1);
    }
  }
  walk(0, 0);
  return result;
}

const allAssignableUsers = computed(() => {
  const map = new Map();
  for (const user of users.value) map.set(user.id, user);
  for (const task of tasks.value) {
    if (task.assignee) map.set(task.assignee.id, task.assignee);
  }
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name, 'vi'));
});

const kanbanSelectedUsers = computed(() =>
  kanbanAssigneeIds.value.map((id) => allAssignableUsers.value.find((u) => u.id === id)).filter(Boolean),
);

const kanbanStatusFill = computed(
  () => isKanban.value && kanbanGroupBy.value === 'status' && TASK_STATUS_TAB_KEYS.includes(activeTab.value),
);

const kanbanSpread = computed(
  () => isKanban.value && (kanbanGroupBy.value === 'priority' || kanbanGroupBy.value === 'type'),
);

const kanbanStatusColumns = computed(() => {
  const cols = TASK_STATUSES.filter((item) => item.value).map((item) => ({
    key: item.value,
    dropKey: item.value,
    label: item.label,
    tone: statusTone(item.value),
    tasks: tasks.value.filter((task) => task.status === item.value),
  }));
  if (!kanbanStatusFill.value) return cols;
  return cols.filter((col) => col.tasks.length > 0);
});

const kanbanAssigneeColumns = computed(() =>
  kanbanSelectedUsers.value.map((user, index) => ({
    key: `user-${user.id}`,
    label: user.name,
    tone: THEME_TONES[index % THEME_TONES.length],
    user,
    tasks: tasks.value.filter((task) => task.assignee_id === user.id),
  })),
);

const kanbanProjectColumns = computed(() => {
  const map = new Map();
  for (const task of tasks.value) {
    const id = task.project_id || 0;
    const label = task.project?.name || 'Chưa gắn dự án';
    if (!map.has(id)) {
      map.set(id, { key: `project-${id}`, label, tone: hashTone(`project-${id}`), tasks: [] });
    }
    map.get(id).tasks.push(task);
  }
  return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label, 'vi'));
});

const kanbanPriorityColumns = computed(() =>
  [
    { value: 'urgent', label: 'Khẩn cấp', tone: 'danger' },
    { value: 'high', label: 'Cao', tone: 'gold' },
    { value: 'medium', label: 'Trung bình', tone: 'info' },
    { value: 'low', label: 'Thấp', tone: 'neutral' },
    { value: '', label: 'Chưa đặt', tone: 'tertiary' },
  ].map((item) => ({
    key: `priority-${item.value || 'none'}`,
    dropKey: item.value || '__none__',
    label: item.label,
    tone: item.tone,
    tasks: tasks.value.filter((task) => (task.priority || '') === item.value),
  })),
);

const kanbanTypeColumns = computed(() =>
  Object.entries(TASK_TYPE_LABELS).map(([value, label]) => ({
    key: `type-${value}`,
    dropKey: value,
    label,
    tone: TASK_TYPE_TONES[value] || 'neutral',
    tasks: tasks.value.filter((task) => task.type === value),
  })),
);

const kanbanColumns = computed(() => {
  if (kanbanGroupBy.value === 'assignees') return kanbanAssigneeColumns.value;
  if (kanbanGroupBy.value === 'project') return kanbanProjectColumns.value;
  if (kanbanGroupBy.value === 'priority') return kanbanPriorityColumns.value;
  if (kanbanGroupBy.value === 'type') return kanbanTypeColumns.value;
  return kanbanStatusColumns.value;
});

const isKanbanDragGroup = computed(
  () => kanbanGroupBy.value === 'status' || kanbanGroupBy.value === 'priority' || kanbanGroupBy.value === 'type',
);

const kanbanCardsMovable = computed(() => {
  if (!isKanbanDragGroup.value || !canEdit.value) return false;
  if (kanbanStatusFill.value && kanbanStatusColumns.value.length < 2) return false;
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

function loadViewMode() {
  try {
    const raw = localStorage.getItem(VIEW_MODE_KEY);
    if (raw === 'list' || raw === 'kanban') return raw;
  } catch {
    // Bỏ qua.
  }
  return 'list';
}

function loadKanbanGroup() {
  try {
    const raw = localStorage.getItem(KANBAN_GROUP_KEY);
    if (KANBAN_GROUP_MODES.includes(raw)) return raw;
  } catch {
    // Bỏ qua.
  }
  return 'status';
}

function loadKanbanAssigneeIds() {
  try {
    const raw = localStorage.getItem(KANBAN_ASSIGNEES_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function loadCollapsedGroups() {
  try {
    const raw = localStorage.getItem(COLLAPSED_GROUPS_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveCollapsedGroups() {
  try {
    localStorage.setItem(COLLAPSED_GROUPS_KEY, JSON.stringify(Array.from(collapsedGroups.value)));
  } catch {
    // Bỏ qua.
  }
}

function loadZoom() {
  try {
    const raw = Number(localStorage.getItem(ZOOM_STORAGE_KEY));
    if (raw === 0.9 || raw === 1 || raw === 1.15) return raw;
  } catch {
    // Bỏ qua.
  }
  return 1;
}

function loadColumnWidths() {
  try {
    const raw = localStorage.getItem(COLUMN_WIDTH_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
  } catch {
    // Bỏ qua nếu trình duyệt chặn localStorage.
  }
  return {};
}

function currentFilterParams() {
  return {
    q: query.value.trim() || undefined,
    project_id: projectId.value || undefined,
    assignee_id: assigneeId.value || undefined,
    manager_id: managerId.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    is_overdue: isOverdueOnly.value ? 1 : undefined,
    progress_type: progressTypeFilter.value || undefined,
    tab: activeTab.value && activeTab.value !== 'all' ? activeTab.value : undefined,
    sort_by: sortBy.value || undefined,
    sort_dir: sortBy.value ? sortDir.value : undefined,
  };
}

async function loadOptions() {
  try {
    const { data } = await window.axios.get('/api/project', { params: { per_page: 200 } });
    projects.value = (data.projects ?? []).map((p) => ({ id: p.id, name: p.name, code: p.code }));
  } catch {
    // Không có quyền xem danh sách dự án — dropdown "Dự án" chỉ rỗng.
  }

  try {
    const { data } = await window.axios.get('/api/project/assignable-users');
    users.value = data.users ?? [];
  } catch {
    // Bỏ qua — dropdown "Người thực hiện" chỉ rỗng.
  }
}

async function loadTasks(page = 1) {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/project/tasks', {
      params: { ...currentFilterParams(), page, per_page: isKanban.value ? KANBAN_PER_PAGE : perPage.value },
    });
    tasks.value = data.tasks ?? [];
    meta.value = data.meta ?? { current_page: 1, last_page: 1, total: 0, from: 0, to: 0, per_page: 20 };
    tabCounts.value = data.tab_counts ?? {};
    clearSelection();

    if (selected.value) {
      const fresh = tasks.value.find((t) => t.id === selected.value.id);
      selected.value = fresh || null;
      if (!fresh) editing.value = false;
    }
    nextTick(fitColumnsToContent);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách công việc.');
  } finally {
    loading.value = false;
  }
}

function goPage(page) {
  if (page < 1 || page > meta.value.last_page || page === meta.value.current_page) return;
  loadTasks(page);
}

function tabCount(key) {
  return tabCounts.value[key] ?? 0;
}

function selectTab(key) {
  if (activeTab.value === key) return;
  activeTab.value = key;
  router.replace({ query: { ...route.query, tab: key === 'all' ? undefined : key } });
  loadTasks(1);
}

function setViewMode(mode) {
  viewModeOpen.value = false;
  if (viewMode.value === mode) return;
  viewMode.value = mode;
  try {
    localStorage.setItem(VIEW_MODE_KEY, mode);
  } catch {
    // Bỏ qua.
  }
  loadTasks(1);
}

function toggleViewModeMenu() {
  viewModeOpen.value = !viewModeOpen.value;
}

function closeViewModeMenu() {
  viewModeOpen.value = false;
}

function chooseKanbanGroup(mode) {
  const switching = viewMode.value !== 'kanban';
  if (kanbanGroupBy.value !== mode) {
    kanbanGroupBy.value = mode;
    try {
      localStorage.setItem(KANBAN_GROUP_KEY, mode);
    } catch {
      // Bỏ qua.
    }
  }
  if (switching) {
    viewMode.value = 'kanban';
    try {
      localStorage.setItem(VIEW_MODE_KEY, 'kanban');
    } catch {
      // Bỏ qua.
    }
    loadTasks(1);
  }
  closeViewModeMenu();
}

function saveKanbanAssigneeIds() {
  try {
    localStorage.setItem(KANBAN_ASSIGNEES_KEY, JSON.stringify(kanbanAssigneeIds.value));
  } catch {
    // Bỏ qua.
  }
}

function toggleKanbanAssignee(userId) {
  const next = kanbanAssigneeIds.value.includes(userId)
    ? kanbanAssigneeIds.value.filter((id) => id !== userId)
    : [...kanbanAssigneeIds.value, userId];
  kanbanAssigneeIds.value = next;
  saveKanbanAssigneeIds();
}

function removeKanbanAssignee(userId) {
  kanbanAssigneeIds.value = kanbanAssigneeIds.value.filter((id) => id !== userId);
  saveKanbanAssigneeIds();
}

function toggleKanbanAssigneePicker() {
  kanbanAssigneePickerOpen.value = !kanbanAssigneePickerOpen.value;
}

function closeKanbanAssigneePicker() {
  kanbanAssigneePickerOpen.value = false;
}

function clearFilters() {
  query.value = '';
  projectId.value = '';
  assigneeId.value = '';
  managerId.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  isOverdueOnly.value = false;
  progressTypeFilter.value = '';
  if (activeTab.value !== 'all') {
    selectTab('all');
    return;
  }
  loadTasks(1);
}

function inspect(task) {
  if (selected.value?.id === task.id) return;
  selected.value = task;
  editing.value = false;
  loadAttachments(task.id);
  loadWorklogs(task.id);
  worklogFormOpen.value = false;
  hydrateScoreForm(task);
}

function hydrateScoreForm(task) {
  scoreForm.rating_score = task.task_score?.rating_score ?? '';
  scoreForm.rating_result = task.task_score?.rating_result || '';
  scoreForm.rating_desc = task.task_score?.rating_desc || '';
}

function closePanel() {
  selected.value = null;
  editing.value = false;
  taskAttachments.value = [];
  taskWorklogs.value = [];
  worklogFormOpen.value = false;
}

async function loadAttachments(taskId) {
  taskAttachments.value = [];
  try {
    const { data } = await window.axios.get(`/api/project/tasks/${taskId}/attachments`);
    taskAttachments.value = data.attachments ?? [];
  } catch {
    // Bỏ qua — panel vẫn hiển thị, chỉ danh sách đính kèm rỗng.
  }
}

function triggerAttachmentInput() {
  attachmentInput.value?.click();
}

async function onAttachmentChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !selected.value) return;

  attachmentUploading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await window.axios.post(`/api/project/tasks/${selected.value.id}/attachments`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    taskAttachments.value = [data.attachment, ...taskAttachments.value];
    bumpAttachmentsCount(1);
    showClientToast('success', 'Đã tải lên tệp đính kèm.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải lên được tệp đính kèm.');
  } finally {
    attachmentUploading.value = false;
  }
}

async function removeAttachment(attachment) {
  try {
    await window.axios.delete(`/api/project/tasks/attachments/${attachment.id}`);
    taskAttachments.value = taskAttachments.value.filter((a) => a.id !== attachment.id);
    bumpAttachmentsCount(-1);
    showClientToast('success', 'Đã xoá tệp đính kèm.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được tệp đính kèm.');
  }
}

/** Cập nhật attachments_count tại chỗ trên selected + dòng trong bảng list —
 *  tránh gọi lại toàn bộ danh sách chỉ để phản ánh 1 thay đổi nhỏ (CLAUDE.md §14). */
function bumpAttachmentsCount(delta) {
  if (!selected.value) return;
  const nextCount = Math.max(0, (selected.value.attachments_count || 0) + delta);
  selected.value = { ...selected.value, attachments_count: nextCount };
  const index = tasks.value.findIndex((t) => t.id === selected.value.id);
  if (index !== -1) tasks.value[index] = { ...tasks.value[index], attachments_count: nextCount };
}

function formatFileSize(bytes) {
  if (!bytes) return '';
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(0)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
}

async function loadWorklogs(taskId) {
  taskWorklogs.value = [];
  try {
    const { data } = await window.axios.get(`/api/project/tasks/${taskId}/worklogs`);
    taskWorklogs.value = data.worklogs ?? [];
  } catch {
    // Bỏ qua — panel vẫn hiển thị, chỉ danh sách nhật ký giờ làm rỗng.
  }
}

function openWorklogForm() {
  worklogForm.work_date = new Date().toISOString().slice(0, 10);
  worklogForm.hours = '';
  worklogForm.note = '';
  worklogFormOpen.value = true;
}

function cancelWorklogForm() {
  worklogFormOpen.value = false;
}

async function saveWorklog() {
  if (!selected.value) return;
  worklogSaving.value = true;
  try {
    const payload = {
      work_date: worklogForm.work_date,
      hours: Number(worklogForm.hours),
      note: worklogForm.note || null,
    };
    const { data } = await window.axios.post(`/api/project/tasks/${selected.value.id}/worklogs`, payload);
    taskWorklogs.value = [data.worklog, ...taskWorklogs.value];
    bumpWorklogHours(payload.hours);
    worklogFormOpen.value = false;
    showClientToast('success', 'Đã thêm giờ làm.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không thêm được giờ làm.');
  } finally {
    worklogSaving.value = false;
  }
}

function canEditWorklog(log) {
  return log.user_id === auth.user?.id || auth.can('task.approve');
}

function askDeleteWorklog(log) {
  confirmingDeleteWorklog.value = log;
}

async function confirmDeleteWorklog() {
  const log = confirmingDeleteWorklog.value;
  if (!log) return;
  try {
    await window.axios.delete(`/api/project/tasks/worklogs/${log.id}`);
    taskWorklogs.value = taskWorklogs.value.filter((l) => l.id !== log.id);
    bumpWorklogHours(-Number(log.hours));
    showClientToast('success', 'Đã xoá nhật ký giờ làm.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được nhật ký giờ làm.');
  } finally {
    confirmingDeleteWorklog.value = null;
  }
}

/** Cộng/trừ worklog_hours tại chỗ trên selected + dòng trong bảng list —
 *  tránh gọi lại toàn bộ danh sách chỉ để phản ánh 1 thay đổi nhỏ (CLAUDE.md §14). */
function bumpWorklogHours(delta) {
  if (!selected.value) return;
  const nextHours = Math.max(0, Number(selected.value.worklog_hours || 0) + delta);
  selected.value = { ...selected.value, worklog_hours: nextHours };
  const index = tasks.value.findIndex((t) => t.id === selected.value.id);
  if (index !== -1) tasks.value[index] = { ...tasks.value[index], worklog_hours: nextHours };
}

async function saveScore() {
  if (!selected.value) return;
  scoreSaving.value = true;
  try {
    const payload = {
      rating_score: scoreForm.rating_score === '' ? null : Number(scoreForm.rating_score),
      rating_result: scoreForm.rating_result || null,
      rating_desc: scoreForm.rating_desc || null,
    };
    const { data } = await window.axios.put(`/api/project/tasks/${selected.value.id}/score`, payload);
    selected.value = { ...selected.value, task_score: data.task_score };
    const index = tasks.value.findIndex((t) => t.id === selected.value.id);
    if (index !== -1) tasks.value[index] = { ...tasks.value[index], task_score: data.task_score };
    showClientToast('success', 'Đã lưu đánh giá.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được đánh giá.');
  } finally {
    scoreSaving.value = false;
  }
}

function startEdit() {
  if (!selected.value) return;
  editForm.title = selected.value.title || '';
  editForm.status = selected.value.status || 'not_started';
  editForm.priority = selected.value.priority || '';
  editForm.start_date = selected.value.start_date || '';
  editForm.start_time = selected.value.start_time || '';
  editForm.end_date = selected.value.end_date || '';
  editForm.due_time = selected.value.due_time || '';
  editForm.actual_start_date = selected.value.actual_start_date || '';
  editForm.actual_end_date = selected.value.actual_end_date || '';
  editForm.assignee_id = selected.value.assignee_id || '';
  editForm.progress_percent = selected.value.progress_percent ?? '';
  editForm.description = selected.value.description || '';
  editForm.parent_id = selected.value.parent_id || '';
  editForm.manager_id = selected.value.manager_id || '';
  editForm.estimated_hours = selected.value.estimated_hours ?? '';
  editForm.progress_type = selected.value.progress_type || 'percent';
  editForm.progress_number = selected.value.progress_number ?? '';
  editForm.progress_total = selected.value.progress_total ?? '';
  editForm.unit = selected.value.unit || '';
  editForm.weight = selected.value.weight ?? '';
  editing.value = true;
}

function cancelEdit() {
  editing.value = false;
}

function applyTaskUpdate(updated) {
  const index = tasks.value.findIndex((t) => t.id === updated.id);
  if (index !== -1) tasks.value[index] = updated;
  if (selected.value?.id === updated.id) selected.value = updated;
}

async function saveEdit() {
  if (!selected.value) return;
  saving.value = true;
  try {
    const isQuantity = editForm.progress_type === 'quantity';
    const payload = {
      title: editForm.title.trim(),
      status: editForm.status,
      priority: editForm.priority || null,
      start_date: editForm.start_date || null,
      start_time: editForm.start_time || null,
      end_date: editForm.end_date || null,
      due_time: editForm.due_time || null,
      actual_start_date: editForm.actual_start_date || null,
      actual_end_date: editForm.actual_end_date || null,
      assignee_id: editForm.assignee_id || null,
      description: editForm.description || null,
      parent_id: editForm.parent_id || null,
      manager_id: editForm.manager_id || null,
      estimated_hours: editForm.estimated_hours === '' ? null : Number(editForm.estimated_hours),
      progress_type: editForm.progress_type,
      weight: editForm.weight === '' ? null : Number(editForm.weight),
      // progress_percent chỉ gửi khi progress_type=percent (server prohibited
      // khi quantity — hệ thống tự tính); progress_number/total/unit chỉ gửi
      // khi quantity — đúng ràng buộc required_if/prohibited_if của Request.
      ...(isQuantity
        ? {
            progress_number: editForm.progress_number === '' ? null : Number(editForm.progress_number),
            progress_total: editForm.progress_total === '' ? null : Number(editForm.progress_total),
            unit: editForm.unit || null,
          }
        : {
            progress_percent: editForm.progress_percent === '' ? null : Number(editForm.progress_percent),
          }),
    };
    const { data } = await window.axios.put(`/api/project/tasks/${selected.value.id}`, payload);
    applyTaskUpdate(data.task);
    editing.value = false;
    showClientToast('success', 'Đã cập nhật công việc.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không cập nhật được công việc.');
  } finally {
    saving.value = false;
  }
}

function askDelete() {
  confirmingDelete.value = true;
}

async function confirmDelete() {
  if (!selected.value) return;
  deleting.value = true;
  try {
    await window.axios.delete(`/api/project/tasks/${selected.value.id}`);
    tasks.value = tasks.value.filter((t) => t.id !== selected.value.id);
    meta.value.total = Math.max(0, meta.value.total - 1);
    showClientToast('success', 'Đã xoá công việc.');
    closePanel();
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được công việc — có thể còn công việc con.');
  } finally {
    deleting.value = false;
    confirmingDelete.value = false;
  }
}

function statusLabel(value) {
  return TASK_STATUS_LABELS[value] || value || '—';
}

function statusTone(value) {
  return TASK_STATUS_TONES[value] || 'neutral';
}

function typeLabel(value) {
  return TASK_TYPE_LABELS[value] || value || '—';
}

function typeTone(value) {
  return TASK_TYPE_TONES[value] || 'neutral';
}

function priorityLabel(value) {
  if (!value) return '—';
  return TASK_PRIORITY_LABELS[value] || value;
}

function priorityTone(value) {
  return TASK_PRIORITY_TONES[value] || 'neutral';
}

function progressTone(percent) {
  if (percent == null) return 'neutral';
  if (percent >= 80) return 'success';
  if (percent >= 50) return 'tertiary';
  if (percent >= 25) return 'gold';
  return 'warning';
}

function hashTone(key) {
  if (!key || key === UNGROUPED_KEY) return 'neutral';
  let hash = 0;
  const text = String(key);
  for (let i = 0; i < text.length; i += 1) {
    hash = (hash * 31 + text.charCodeAt(i)) | 0;
  }
  return THEME_TONES[Math.abs(hash) % THEME_TONES.length];
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('vi-VN');
}

function dateRangeLabel(task) {
  if (!task.start_date && !task.end_date) return '';
  return `${formatDate(task.start_date)} – ${formatDate(task.end_date)}`;
}

function formatDateTime(value) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('vi-VN');
}

/** Nhóm A — variance_days: dương = trễ, âm = sớm, null = chưa có đủ mốc. */
function formatVarianceDays(value) {
  if (value == null) return '—';
  if (value > 0) return `Trễ ${value} ngày`;
  if (value < 0) return `Sớm ${Math.abs(value)} ngày`;
  return 'Đúng hạn';
}

/** Khớp TaskRepository::SORTABLE_COLUMNS (PR7) — chỉ giá trị đơn giản
 *  (ngày/số), không sort theo quan hệ (manager/parent). */
const SORTABLE_COLUMN_KEYS = ['end_date', 'progress_percent', 'weight', 'estimated_hours', 'worklog_hours', 'created_at'];

function isSortableColumn(key) {
  return SORTABLE_COLUMN_KEYS.includes(key);
}

/** 3 trạng thái: tăng → giảm → bỏ sort (về mặc định created_at desc). */
function toggleSort(key) {
  if (sortBy.value !== key) {
    sortBy.value = key;
    sortDir.value = 'asc';
    return;
  }
  if (sortDir.value === 'asc') {
    sortDir.value = 'desc';
    return;
  }
  sortBy.value = '';
  sortDir.value = 'desc';
}

function isTaskSelected(taskId) {
  return selectedTaskIds.value.has(taskId);
}

function toggleTaskSelected(taskId) {
  const next = new Set(selectedTaskIds.value);
  if (next.has(taskId)) next.delete(taskId);
  else next.add(taskId);
  selectedTaskIds.value = next;
}

function toggleSelectAll() {
  if (selectedTaskIds.value.size === tasks.value.length && tasks.value.length > 0) {
    selectedTaskIds.value = new Set();
    return;
  }
  selectedTaskIds.value = new Set(tasks.value.map((t) => t.id));
}

function clearSelection() {
  selectedTaskIds.value = new Set();
  bulkManagerId.value = '';
  bulkWeight.value = '';
}

async function applyBulkUpdate() {
  const payload = { task_ids: Array.from(selectedTaskIds.value) };
  if (bulkManagerId.value !== '') payload.manager_id = bulkManagerId.value;
  if (bulkWeight.value !== '') payload.weight = Number(bulkWeight.value);

  if (!payload.manager_id && payload.weight === undefined) {
    showClientToast('warning', 'Chọn ít nhất một trường để cập nhật hàng loạt.');
    return;
  }

  bulkSaving.value = true;
  try {
    const { data } = await window.axios.patch('/api/project/tasks/bulk', payload);
    const updatedById = new Map((data.tasks || []).map((t) => [t.id, t]));
    tasks.value = tasks.value.map((t) => updatedById.get(t.id) || t);
    showClientToast('success', `Đã cập nhật ${updatedById.size} công việc.`);
    clearSelection();
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không cập nhật hàng loạt được.');
  } finally {
    bulkSaving.value = false;
  }
}

// ---------- Xuất/Nhập Excel (PR8) — cùng khuôn ProjectList.vue::downloadFile() ----------
async function downloadFile(url, params, busyRef, fallbackFilename, successMsg, failMsg) {
  busyRef.value = true;
  try {
    const response = await window.axios.get(url, {
      params,
      paramsSerializer: { indexes: null },
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || failMsg);
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || fallbackFilename);

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', successMsg);
  } catch (error) {
    showClientToast('error', error?.message || failMsg);
  } finally {
    busyRef.value = false;
  }
}

async function exportExcel(columnKeys = null) {
  await downloadFile(
    '/api/project/tasks/export',
    { ...currentFilterParams(), ...(columnKeys ? { columns: columnKeys } : {}) },
    exporting,
    'Cong_viec.xlsx',
    'Đã tải file Excel.',
    'Không xuất được file Excel.',
  );
}

function openExportDialog() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.map((c) => c.key));
  exportDialogOpen.value = true;
}

function closeExportDialog() {
  if (exporting.value) return;
  exportDialogOpen.value = false;
}

function toggleExportColumn(key) {
  const col = EXPORT_COLUMNS.find((c) => c.key === key);
  if (col?.always) return;
  const next = new Set(exportSelectedColumns.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  exportSelectedColumns.value = next;
}

function selectAllExportColumns() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.map((c) => c.key));
}

function deselectAllExportColumns() {
  exportSelectedColumns.value = new Set(EXPORT_COLUMNS.filter((c) => c.always).map((c) => c.key));
}

async function submitExportDialog() {
  await exportExcel(Array.from(exportSelectedColumns.value));
  if (!exporting.value) exportDialogOpen.value = false;
}

function openImportDialog() {
  importFile.value = null;
  importPreview.value = null;
  importResult.value = null;
  importSelectedRows.value = new Set();
  importStep.value = 'select';
  editingRowKey.value = null;
  importDialogOpen.value = true;
}

function closeImportDialog() {
  if (previewing.value || confirming.value) return;
  importDialogOpen.value = false;
}

function onImportFileChange(event) {
  importFile.value = event.target.files?.[0] ?? null;
}

function backToSelect() {
  importStep.value = 'select';
  importPreview.value = null;
  editingRowKey.value = null;
}

function toggleImportRowSelected(rowNum) {
  const next = new Set(importSelectedRows.value);
  if (next.has(rowNum)) next.delete(rowNum);
  else next.add(rowNum);
  importSelectedRows.value = next;
}

async function runImportPreview() {
  if (!importFile.value) {
    showClientToast('error', 'Vui lòng chọn file Excel cần nhập.');
    return;
  }
  previewing.value = true;
  try {
    const formData = new FormData();
    formData.append('file', importFile.value);
    const { data } = await window.axios.post('/api/project/tasks/import/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    importPreview.value = data;
    importSelectedRows.value = new Set((data.rows ?? []).filter((r) => r.status === 'valid').map((r) => r.row));
    importStep.value = 'preview';
  } catch (err) {
    if (err?.response?.status === 422) {
      showClientToast('error', err.response.data?.message || 'File không hợp lệ.');
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không đọc được file Excel.');
    }
  } finally {
    previewing.value = false;
  }
}

function importRowDraftFromData(row) {
  const d = row.data ?? {};
  return {
    code: row.code || '',
    project_code: d.project_code || '',
    title: d.title || '',
    type_input: typeLabel(d.type) === '—' ? '' : typeLabel(d.type),
    status_input: statusLabel(d.status) === '—' ? '' : statusLabel(d.status),
    priority_input: d.priority ? priorityLabel(d.priority) : '',
    assignee_input: d.assignee_name ? d.assignee_name : '',
    manager_input: d.manager_name ? d.manager_name : '',
    start_input: d.start_date ? formatDate(d.start_date) : '',
    end_input: d.end_date ? formatDate(d.end_date) : '',
    progress_type_input: TASK_PROGRESS_TYPE_LABELS[d.progress_type] || '',
    progress_percent_input: d.progress_percent != null ? String(d.progress_percent) : '',
    progress_number_input: d.progress_number != null ? String(d.progress_number) : '',
    progress_total_input: d.progress_total != null ? String(d.progress_total) : '',
    unit: d.unit || '',
    estimated_hours_input: d.estimated_hours != null ? String(d.estimated_hours) : '',
    weight_input: d.weight != null ? String(d.weight) : '',
    description: d.description || '',
  };
}

function startEditImportRow(row) {
  editingRowKey.value = row.row;
  Object.assign(editingRowDraft, importRowDraftFromData(row));
}

function cancelEditImportRow() {
  editingRowKey.value = null;
}

async function saveEditImportRow(row) {
  resolvingRow.value = true;
  try {
    const { data } = await window.axios.post('/api/project/tasks/import/resolve-row', { ...editingRowDraft });
    const rows = importPreview.value?.rows ?? [];
    const idx = rows.findIndex((r) => r.row === row.row);
    if (idx !== -1) {
      rows[idx] = { ...data, row: row.row };
      if (data.status === 'valid') {
        importSelectedRows.value = new Set([...importSelectedRows.value, row.row]);
      } else {
        const next = new Set(importSelectedRows.value);
        next.delete(row.row);
        importSelectedRows.value = next;
      }
    }
    editingRowKey.value = null;
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không sửa được dòng này.');
  } finally {
    resolvingRow.value = false;
  }
}

async function confirmImportRows() {
  const rows = (importPreview.value?.rows ?? [])
    .filter((r) => r.status === 'valid' && importSelectedRows.value.has(r.row))
    .map((r) => ({ ...r.data, action: r.action, task_id: r.task_id, provided_fields: r.provided_fields, row: r.row }));
  if (rows.length === 0) {
    showClientToast('error', 'Chưa chọn dòng hợp lệ nào để nhập.');
    return;
  }
  confirming.value = true;
  try {
    const { data } = await window.axios.post('/api/project/tasks/import/confirm', { rows });
    importResult.value = data;
    importStep.value = 'result';
    if (data.created?.length || data.updated?.length) {
      await loadTasks(1);
    }
    const createdCount = data.created?.length ?? 0;
    const updatedCount = data.updated?.length ?? 0;
    const errorCount = data.errors?.length ?? 0;
    if ((createdCount || updatedCount) && !errorCount) {
      showClientToast('success', `Đã tạo ${createdCount} công việc, cập nhật ${updatedCount} công việc.`);
    } else if (createdCount || updatedCount) {
      showClientToast('warning', `Đã tạo ${createdCount} công việc, cập nhật ${updatedCount} công việc, còn ${errorCount} dòng lỗi.`);
    } else {
      showClientToast('error', 'Không nhập được công việc nào, xem chi tiết lỗi bên dưới.');
    }
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không nhập được công việc.');
  } finally {
    confirming.value = false;
  }
}

const taskDataExportOptions = computed(() => {
  const options = [
    {
      key: 'excel',
      label: 'Xuất Excel',
      description: 'Chọn cột và xuất theo bộ lọc hiện tại.',
      onSelect: openExportDialog,
    },
  ];
  if (canEdit.value) {
    options.push({
      key: 'import',
      label: 'Nhập Excel',
      description: 'Tải file lên, xem trước rồi mới xác nhận nhập',
      separatorBefore: true,
      onSelect: openImportDialog,
    });
  }
  return options;
});

const taskDataExportBusyKey = computed(() => {
  if (exporting.value) return 'excel';
  if (previewing.value || confirming.value) return 'import';
  return undefined;
});

function cellText(task, key) {
  if (key === 'code') return task.code || '—';
  if (key === 'title') return task.title || '—';
  if (key === 'project') return task.project?.name || '—';
  if (key === 'start_date' || key === 'end_date' || key === 'actual_start_date' || key === 'actual_end_date') {
    return formatDate(task[key]);
  }
  if (key === 'progress_percent') return task.progress_percent == null ? '—' : `${task.progress_percent}%`;
  if (key === 'type') return typeLabel(task.type);
  if (key === 'priority') return priorityLabel(task.priority);
  return '—';
}

function isGroupCollapsed(key) {
  return collapsedGroups.value.has(key);
}

/** Cây WBS — tái dùng nguyên collapsedGroups (Set + localStorage) với key
 *  dạng `task-{id}` để không đụng key `project-{id}` của group ngoài. */
function isTaskCollapsed(taskId) {
  return collapsedGroups.value.has(`task-${taskId}`);
}

function toggleTaskCollapse(taskId) {
  toggleGroup(`task-${taskId}`);
}

function toggleGroup(key) {
  const next = new Set(collapsedGroups.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedGroups.value = next;
  saveCollapsedGroups();
}

function colWidthStyle(key) {
  const width = columnWidths[key];
  return width ? `${width}px` : undefined;
}

function measureText(text, font) {
  if (!measureCtx) {
    measureCtx = document.createElement('canvas').getContext('2d');
  }
  measureCtx.font = font;
  return measureCtx.measureText(String(text ?? '')).width;
}

function fontOf(el, fallback) {
  if (!el) return fallback;
  const style = getComputedStyle(el);
  return `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
}

function readTableFonts() {
  const table = tableWrap.value?.querySelector('.task-page__table');
  return {
    header: fontOf(table?.querySelector('thead th'), '600 12px "Be Vietnam Pro", sans-serif'),
    cell: fontOf(table?.querySelector('tbody td'), '400 14px "Be Vietnam Pro", sans-serif'),
    title: fontOf(table?.querySelector('.task-page__name-title'), '400 14px "Be Vietnam Pro", sans-serif'),
  };
}

function columnContentWidth(key, fonts) {
  const label = TASK_COLUMNS.find((col) => col.key === key)?.label ?? '';
  const valueFont = key === 'title' ? fonts.title : fonts.cell;
  let maxW = measureText(label, fonts.header);
  for (const task of tasks.value) {
    if (key === 'assignee') {
      maxW = Math.max(maxW, measureText(task.assignee?.name || '—', fonts.cell));
    } else if (key === 'status') {
      maxW = Math.max(maxW, measureText(statusLabel(task.status), fonts.cell));
    } else {
      maxW = Math.max(maxW, measureText(cellText(task, key), valueFont));
    }
  }
  let extra = 0;
  if (key === 'assignee') extra = ASSIGNEE_AVATAR_EXTRA;
  if (key === 'status' || key === 'priority') extra = STATUS_DOT_EXTRA;
  if (key === 'progress_percent') extra = 64;
  return Math.max(MIN_COL_PX, Math.ceil(maxW + CELL_PAD_X + COL_EXTRA + extra));
}

function distributeExtraWidth(widths, keys, available) {
  const sum = keys.reduce((total, key) => total + widths[key], 0);
  if (sum <= 0 || available <= sum) return widths;

  const extra = available - sum;
  const next = { ...widths };
  let used = 0;
  keys.forEach((key, index) => {
    if (index === keys.length - 1) {
      next[key] = available - used;
      return;
    }
    next[key] = widths[key] + Math.floor((widths[key] / sum) * extra);
    used += next[key];
  });
  return next;
}

function fitColumnsToContent() {
  const wrap = tableWrap.value;
  const keys = shownColumns.value.map((col) => col.key);
  if (!wrap || keys.length === 0 || resizing.value || isKanban.value) return;

  const fonts = readTableFonts();
  const measured = {};
  for (const key of keys) {
    measured[key] = columnContentWidth(key, fonts);
  }

  const next = distributeExtraWidth(measured, keys, wrap.clientWidth);
  for (const key of keys) {
    columnWidths[key] = next[key];
  }
}

function startResize(event, key) {
  const keys = shownColumns.value.map((col) => col.key);
  const index = keys.indexOf(key);
  if (index < 0) return;

  const neighbor = keys[index + 1] ?? keys[index - 1];
  if (!neighbor || neighbor === key) return;

  const towardNext = keys.indexOf(neighbor) > index;
  const startX = event.clientX;
  const startA = Number(columnWidths[key]) || MIN_COL_PX;
  const startB = Number(columnWidths[neighbor]) || MIN_COL_PX;
  const pair = startA + startB;

  resizing.value = true;

  function onMove(moveEvent) {
    const delta = (moveEvent.clientX - startX) * (towardNext ? 1 : -1);
    let nextA = Math.round(startA + delta);
    nextA = Math.min(Math.max(nextA, MIN_COL_PX), pair - MIN_COL_PX);
    columnWidths[key] = nextA;
    columnWidths[neighbor] = pair - nextA;
  }

  function onUp() {
    resizing.value = false;
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }

  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function onColumnToggle(key, checked) {
  if (!checked) {
    const remaining = TASK_COLUMNS.filter((col) => !col.always && visibleColumns[col.key] && col.key !== key).length;
    if (remaining < 1) {
      showClientToast('warning', 'Cần giữ ít nhất một cột trên bảng.');
      return;
    }
  }
  visibleColumns[key] = checked;
}

function onFilterToggle(key, checked) {
  visibleFilters[key] = checked;
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
  document.body.classList.remove('task-kanban-dragging');
  resetKanbanDragFields();
}

function readKanbanDropKey(clientX, clientY) {
  const el = document.elementFromPoint(clientX, clientY);
  const col = el?.closest?.('.task-kanban__col');
  return col?.dataset?.dropKey || null;
}

function autoScrollKanban(clientX, clientY) {
  const wrap = kanbanWrap.value;
  if (!wrap) return;
  const rect = wrap.getBoundingClientRect();
  const edge = 56;
  if (clientX < rect.left + edge) wrap.scrollLeft -= 18;
  else if (clientX > rect.right - edge) wrap.scrollLeft += 18;

  const body = document.elementFromPoint(clientX, clientY)?.closest?.('.task-kanban__col-body');
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
  document.body.style.cursor = 'grabbing';
  document.body.classList.add('task-kanban-dragging');
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
    if (pointer?.task) inspect(pointer.task);
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

  const slot = kanbanWrap.value?.querySelector('.task-kanban__placeholder');
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

async function patchTask(taskId, payload, successMessage) {
  const task = tasks.value.find((item) => item.id === taskId);
  if (!task) return;

  const previous = { ...task };
  Object.assign(task, payload);
  markKanbanJustMoved(taskId);
  const busy = new Set(kanbanStatusUpdating.value);
  busy.add(taskId);
  kanbanStatusUpdating.value = busy;

  try {
    const { data } = await window.axios.put(`/api/project/tasks/${taskId}`, payload);
    applyTaskUpdate(data.task);
    showClientToast('success', successMessage);
  } catch (error) {
    Object.assign(task, previous);
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

function handleDocumentKeydown(event) {
  if (event.key !== 'Escape') return;
  if (confirmingDelete.value) {
    confirmingDelete.value = false;
    return;
  }
  if (kanbanDrag.active) {
    clearKanbanDrag();
    return;
  }
  if (editing.value) {
    cancelEdit();
    return;
  }
  if (viewModeOpen.value) {
    closeViewModeMenu();
    return;
  }
  if (kanbanAssigneePickerOpen.value) {
    closeKanbanAssigneePicker();
    return;
  }
  if (selected.value) closePanel();
}

function handleDocumentClickForPickers(event) {
  if (kanbanAssigneePickerOpen.value) {
    const el = document.getElementById('task-kanban-assignee-root');
    if (el && !el.contains(event.target)) closeKanbanAssigneePicker();
  }
  if (viewModeOpen.value) {
    const el = document.getElementById('task-view-mode-root');
    if (el && !el.contains(event.target)) closeViewModeMenu();
  }
}

watch(visibleColumns, (value) => saveVisibility(COLUMN_STORAGE_KEY, value), { deep: true });
watch(visibleFilters, (value) => saveVisibility(FILTER_STORAGE_KEY, value), { deep: true });
watch(columnWidths, (value) => saveVisibility(COLUMN_WIDTH_KEY, value), { deep: true });
watch(tableZoom, (value) => {
  try {
    localStorage.setItem(ZOOM_STORAGE_KEY, String(value));
  } catch {
    // Bỏ qua.
  }
  nextTick(fitColumnsToContent);
});
watch(selected, () => nextTick(fitColumnsToContent));
watch(shownColumns, () => nextTick(fitColumnsToContent));
watch(perPage, () => loadTasks(1));
watch(
  [projectId, assigneeId, managerId, dateFrom, dateTo, isOverdueOnly, progressTypeFilter, sortBy, sortDir],
  () => loadTasks(1),
);

onMounted(() => {
  document.addEventListener('keydown', handleDocumentKeydown);
  document.addEventListener('mousedown', handleDocumentClickForPickers);
  loadOptions();
  loadTasks(1);
  nextTick(() => {
    fitColumnsToContent();
    if (tableWrap.value) {
      let lastWrapWidth = tableWrap.value.clientWidth;
      wrapObserver = new ResizeObserver((entries) => {
        const width = Math.round(entries[0]?.contentRect?.width || 0);
        if (!width || width === lastWrapWidth || resizing.value) return;
        lastWrapWidth = width;
        fitColumnsToContent();
      });
      wrapObserver.observe(tableWrap.value);
    }
  });
  document.fonts?.ready?.then(() => nextTick(fitColumnsToContent));
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleDocumentKeydown);
  document.removeEventListener('mousedown', handleDocumentClickForPickers);
  wrapObserver?.disconnect();
  clearKanbanDrag();
  window.clearTimeout(kanbanJustMovedTimer);
});
</script>

<template>
  <section class="task-page">
    <PageHeader
      title="Tất cả công việc"
      icon="layoutList"
      description="Quản lý danh sách công việc của tổ chức."
      export-label="Dữ liệu"
      :export-options="taskDataExportOptions"
      :export-busy-key="taskDataExportBusyKey"
    >
      <template #actions>
        <div class="task-page__header-search">
          <AppIcon name="search" :size="15" />
          <input
            v-model="query"
            type="search"
            placeholder="Tìm theo tên công việc…"
            @keydown.enter="loadTasks(1)"
          />
        </div>
        <button type="button" class="task-page__header-btn" :disabled="loading" @click="loadTasks(meta.current_page)">
          <AppIcon name="refresh" :size="16" :class="{ 'task-page__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="task-page__body">
      <div class="task-page__main">
        <div class="task-tabs-row">
          <div id="task-view-mode-root" class="task-view-mode">
            <button
              type="button"
              class="task-view-mode__trigger"
              aria-haspopup="menu"
              :aria-expanded="viewModeOpen"
              aria-label="Chế độ xem"
              @click.stop="toggleViewModeMenu"
            >
              <AppIcon :name="isKanban ? 'layoutGrid' : 'layoutList'" :size="15" />
              <span>{{ viewModeTriggerLabel }}</span>
              <AppIcon name="chevronDown" :size="14" />
            </button>
            <div v-if="viewModeOpen" class="task-view-mode__menu" role="menu" @click.stop>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': !isKanban }"
                role="menuitem"
                @click="setViewMode('list')"
              >
                <AppIcon name="layoutList" :size="15" />
                <span>Danh sách</span>
                <AppIcon v-if="!isKanban" name="check" :size="14" />
              </button>
              <p class="task-view-mode__group">Kanban</p>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === 'status' }"
                role="menuitem"
                @click="chooseKanbanGroup('status')"
              >
                <AppIcon name="layoutGrid" :size="15" />
                <span>Theo trạng thái</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'status'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === 'assignees' }"
                role="menuitem"
                @click="chooseKanbanGroup('assignees')"
              >
                <AppIcon name="users" :size="15" />
                <span>Theo người thực hiện</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'assignees'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === 'project' }"
                role="menuitem"
                @click="chooseKanbanGroup('project')"
              >
                <AppIcon name="layers" :size="15" />
                <span>Theo dự án</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'project'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === 'priority' }"
                role="menuitem"
                @click="chooseKanbanGroup('priority')"
              >
                <AppIcon name="bookmark" :size="15" />
                <span>Theo mức độ ưu tiên</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'priority'" name="check" :size="14" />
              </button>
              <button
                type="button"
                class="task-view-mode__item"
                :class="{ 'task-view-mode__item--on': isKanban && kanbanGroupBy === 'type' }"
                role="menuitem"
                @click="chooseKanbanGroup('type')"
              >
                <AppIcon name="gitBranch" :size="15" />
                <span>Theo loại</span>
                <AppIcon v-if="isKanban && kanbanGroupBy === 'type'" name="check" :size="14" />
              </button>
            </div>
          </div>

          <nav class="task-tabs hide-scrollbar" aria-label="Lọc nhanh công việc">
            <button
              v-for="tab in TASK_TABS"
              :key="tab.key"
              type="button"
              class="task-tabs__item"
              :class="[`task-tabs__item--${tab.tone}`, { 'task-tabs__item--active': activeTab === tab.key }]"
              @click="selectTab(tab.key)"
            >
              <span class="task-tabs__label">{{ tab.label }}</span>
              <span class="task-tabs__count">{{ tabCount(tab.key) }}</span>
            </button>
          </nav>
        </div>

        <div v-if="isKanban && kanbanGroupBy === 'assignees'" class="task-view-bar">
          <div id="task-kanban-assignee-root" class="task-kanban-members">
            <button
              type="button"
              class="task-kanban-members__trigger"
              :aria-expanded="kanbanAssigneePickerOpen"
              @click.stop="toggleKanbanAssigneePicker"
            >
              <AppIcon name="userPlus" :size="15" />
              Chọn người thực hiện
              <span v-if="kanbanSelectedUsers.length" class="task-kanban-members__count">{{ kanbanSelectedUsers.length }}</span>
            </button>

            <div v-if="kanbanAssigneePickerOpen" class="task-kanban-members__dropdown hide-scrollbar" @click.stop>
              <label v-for="user in allAssignableUsers" :key="user.id" class="task-kanban-members__option">
                <input
                  type="checkbox"
                  :checked="kanbanAssigneeIds.includes(user.id)"
                  @change="toggleKanbanAssignee(user.id)"
                />
                <span>{{ user.name }}</span>
              </label>
              <p v-if="!allAssignableUsers.length" class="task-kanban-members__empty">Chưa có người thực hiện nào.</p>
            </div>

            <span v-if="kanbanSelectedUsers.length" class="task-kanban-members__chips">
              <span v-for="user in kanbanSelectedUsers" :key="user.id" class="task-kanban-members__chip">
                {{ user.name }}
                <button type="button" aria-label="Bỏ chọn người này" @click="removeKanbanAssignee(user.id)">
                  <AppIcon name="close" :size="10" />
                </button>
              </span>
            </span>
          </div>
        </div>

        <div v-if="!isKanban && hasVisibleFilterFields" class="task-page__toolbar">
          <div class="task-page__filters">
            <div v-if="visibleFilters.project_id" class="task-page__field">
              <label class="task-page__label" for="task-project">Dự án</label>
              <select id="task-project" v-model="projectId" class="task-page__input">
                <option value="">Tất cả dự án</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div v-if="visibleFilters.assignee_id" class="task-page__field">
              <label class="task-page__label" for="task-assignee">Người thực hiện</label>
              <select id="task-assignee" v-model="assigneeId" class="task-page__input">
                <option value="">Tất cả người thực hiện</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div v-if="visibleFilters.manager_id" class="task-page__field">
              <label class="task-page__label" for="task-manager">Người quản lý</label>
              <select id="task-manager" v-model="managerId" class="task-page__input">
                <option value="">Tất cả người quản lý</option>
                <option v-for="u in allAssignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div v-if="visibleFilters.date_from" class="task-page__field">
              <label class="task-page__label" for="task-from">Từ ngày</label>
              <input id="task-from" v-model="dateFrom" type="date" class="task-page__input" />
            </div>
            <div v-if="visibleFilters.date_to" class="task-page__field">
              <label class="task-page__label" for="task-to">Đến ngày</label>
              <input id="task-to" v-model="dateTo" type="date" class="task-page__input" />
            </div>
            <div v-if="visibleFilters.progress_type" class="task-page__field">
              <label class="task-page__label" for="task-progress-type">Cách tính tiến độ</label>
              <select id="task-progress-type" v-model="progressTypeFilter" class="task-page__input">
                <option value="">Tất cả</option>
                <option v-for="(label, value) in TASK_PROGRESS_TYPE_LABELS" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>
            <label v-if="visibleFilters.is_overdue" class="task-page__check">
              <input type="checkbox" v-model="isOverdueOnly" />
              <span>Chỉ hiện việc quá hạn</span>
            </label>
          </div>
        </div>

        <TablePagesBar
          v-if="!isKanban"
          placement="top"
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          :zoom="tableZoom"
          show-search
          :show-clear-filters="hasActiveFilters"
          :filters-active="hasActiveFilters"
          @search="loadTasks(1)"
          @clear-filters="clearFilters"
          @update:page="goPage"
          @update:per-page="perPage = $event"
          @update:zoom="tableZoom = $event"
        >
          <template #filters>
            <label v-for="item in TASK_FILTERS" :key="item.key" class="task-page__check">
              <input
                type="checkbox"
                :checked="visibleFilters[item.key]"
                @change="onFilterToggle(item.key, $event.target.checked)"
              />
              <span>{{ item.label }}</span>
            </label>
          </template>
          <template #settings>
            <label v-for="col in TASK_COLUMNS" :key="col.key" class="task-page__check">
              <input
                type="checkbox"
                :checked="col.always || visibleColumns[col.key]"
                :disabled="col.always"
                @change="onColumnToggle(col.key, $event.target.checked)"
              />
              <span>{{ col.label }}</span>
            </label>
          </template>
        </TablePagesBar>

        <div v-if="!isKanban && selectedTaskIds.size > 0" class="task-page__bulk-bar">
          <span class="task-page__bulk-count">Đã chọn {{ selectedTaskIds.size }} công việc</span>
          <label class="task-page__field task-page__bulk-field">
            <span class="task-page__label">Gán người quản lý</span>
            <select v-model="bulkManagerId" class="task-page__input">
              <option value="">Không đổi</option>
              <option v-for="u in allAssignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>
          <label class="task-page__field task-page__bulk-field">
            <span class="task-page__label">Tỷ trọng (%)</span>
            <input v-model="bulkWeight" type="number" min="0" max="100" step="0.1" class="task-page__input" />
          </label>
          <button type="button" class="task-page__btn" :disabled="bulkSaving" @click="applyBulkUpdate">
            {{ bulkSaving ? 'Đang lưu…' : 'Áp dụng' }}
          </button>
          <button type="button" class="task-page__btn task-page__btn--ghost" @click="clearSelection">
            Bỏ chọn
          </button>
        </div>

        <div
          v-if="!isKanban"
          ref="tableWrap"
          class="task-page__table-wrap hide-scrollbar"
          :class="{ 'task-page__table-wrap--resizing': resizing }"
          :style="{ '--table-zoom': tableZoom }"
        >
          <table class="task-page__table" :style="{ width: tableWidthPx }">
            <colgroup>
              <col class="task-page__col-check" />
              <col v-for="col in shownColumns" :key="col.key" :style="{ width: colWidthStyle(col.key) }" />
            </colgroup>
            <thead>
              <tr>
                <th class="task-page__th-check">
                  <input
                    type="checkbox"
                    aria-label="Chọn tất cả công việc"
                    :checked="tasks.length > 0 && selectedTaskIds.size === tasks.length"
                    @change="toggleSelectAll"
                  />
                </th>
                <th v-for="col in shownColumns" :key="col.key">
                  <button
                    v-if="isSortableColumn(col.key)"
                    type="button"
                    class="task-page__th-sort"
                    @click="toggleSort(col.key)"
                  >
                    <span>{{ col.label }}</span>
                    <AppIcon
                      v-if="sortBy === col.key"
                      :name="sortDir === 'asc' ? 'chevronsUp' : 'chevronsDown'"
                      :size="12"
                    />
                  </button>
                  <span v-else>{{ col.label }}</span>
                  <button
                    type="button"
                    class="task-page__resize"
                    aria-label="Kéo để đổi độ rộng cột"
                    @click.stop
                    @mousedown.stop.prevent="startResize($event, col.key)"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="colSpan" class="task-page__empty">Đang tải…</td>
              </tr>
              <tr v-else-if="tasks.length === 0">
                <td :colspan="colSpan" class="task-page__empty">
                  {{ hasActiveFilters ? 'Không tìm thấy công việc khớp bộ lọc.' : 'Chưa có công việc nào.' }}
                </td>
              </tr>
              <template v-for="group in groupedTasks" v-else :key="group.key">
                <tr
                  class="task-page__group-row"
                  :class="`task-page__group-row--${group.tone}`"
                  @click="toggleGroup(group.key)"
                >
                  <td :colspan="colSpan">
                    <span class="task-page__group-toggle">
                      <span class="task-page__group-head">
                        <AppIcon
                          name="chevronRight"
                          :size="14"
                          class="task-page__group-chevron"
                          :class="{ 'task-page__group-chevron--open': !isGroupCollapsed(group.key) }"
                        />
                        <span class="task-page__group-label">{{ group.label }}</span>
                      </span>
                      <span class="task-page__group-count">{{ group.tasks.length }} công việc</span>
                    </span>
                  </td>
                </tr>
                <tr
                  v-for="task in group.tasks"
                  v-show="!isGroupCollapsed(group.key)"
                  :key="task.id"
                  class="task-page__data-row"
                  :class="[
                    `task-page__data-row--${group.tone}`,
                    { 'task-page__row--active': selected?.id === task.id },
                  ]"
                  @dblclick="inspect(task)"
                >
                  <td class="task-page__td-check" @click.stop>
                    <input
                      type="checkbox"
                      :aria-label="`Chọn công việc ${task.title}`"
                      :checked="isTaskSelected(task.id)"
                      @change="toggleTaskSelected(task.id)"
                    />
                  </td>
                  <td
                    v-for="col in shownColumns"
                    :key="col.key"
                    :class="{
                      'task-page__td--name': col.key === 'title',
                      'task-page__td--avatar': col.key === 'assignee' || col.key === 'creator',
                    }"
                  >
                    <span v-if="col.key === 'code'" class="task-page__pill task-page__pill--code">{{ task.code || '—' }}</span>
                    <span v-else-if="col.key === 'title'" class="task-page__name-cell">
                      <span
                        class="task-page__name-title-row"
                        :style="task.depth ? { paddingLeft: `${task.depth * 20}px` } : undefined"
                      >
                        <button
                          v-if="task.hasChildren"
                          type="button"
                          class="task-page__tree-toggle"
                          :aria-label="isTaskCollapsed(task.id) ? 'Mở rộng công việc con' : 'Thu gọn công việc con'"
                          @click.stop="toggleTaskCollapse(task.id)"
                        >
                          <AppIcon
                            name="chevronRight"
                            :size="12"
                            class="task-page__tree-chevron"
                            :class="{ 'task-page__tree-chevron--open': !isTaskCollapsed(task.id) }"
                          />
                        </button>
                        <span class="task-page__name-title">{{ task.title }}</span>
                      </span>
                    </span>
                    <span
                      v-else-if="col.key === 'project'"
                      class="task-page__pill"
                      :class="`task-page__pill--${hashTone(`project-${task.project_id}`)}`"
                    >
                      {{ task.project?.name || '—' }}
                    </span>
                    <span v-else-if="col.key === 'assignee'">
                      <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                      <span v-else>—</span>
                    </span>
                    <span v-else-if="col.key === 'status'" class="task-page__pill" :class="`task-page__pill--${statusTone(task.status)}`">
                      <span class="task-page__dot" :class="`task-page__dot--${statusTone(task.status)}`" />
                      {{ statusLabel(task.status) }}
                    </span>
                    <span v-else-if="col.key === 'priority'" class="task-page__pill" :class="`task-page__pill--${priorityTone(task.priority)}`">
                      <span class="task-page__dot" :class="`task-page__dot--${priorityTone(task.priority)}`" />
                      {{ priorityLabel(task.priority) }}
                    </span>
                    <span v-else-if="col.key === 'start_date'" class="task-page__pill task-page__pill--date">{{ formatDate(task.start_date) }}</span>
                    <span v-else-if="col.key === 'end_date'" class="task-page__pill task-page__pill--date">{{ formatDate(task.end_date) }}</span>
                    <span v-else-if="col.key === 'actual_start_date'" class="task-page__pill task-page__pill--date">{{ formatDate(task.actual_start_date) }}</span>
                    <span v-else-if="col.key === 'actual_end_date'" class="task-page__pill task-page__pill--date">{{ formatDate(task.actual_end_date) }}</span>
                    <span v-else-if="col.key === 'progress_percent'" class="task-page__progress-cell">
                      <template v-if="task.progress_percent != null">
                        <span class="task-page__mini-track">
                          <span
                            class="task-page__mini-fill"
                            :class="`task-page__mini-fill--${progressTone(task.progress_percent)}`"
                            :style="{ width: `${task.progress_percent}%` }"
                          />
                        </span>
                        <span class="task-page__pill" :class="`task-page__pill--${progressTone(task.progress_percent)}`">
                          {{ task.progress_percent }}%
                        </span>
                      </template>
                      <span v-else>—</span>
                    </span>
                    <span v-else-if="col.key === 'type'" class="task-page__pill" :class="`task-page__pill--${typeTone(task.type)}`">
                      {{ typeLabel(task.type) }}
                    </span>
                    <span v-else-if="col.key === 'creator'">
                      <UserAvatarTip v-if="task.creator" :user="task.creator" label="Người tạo" />
                      <span v-else>—</span>
                    </span>
                    <span v-else-if="col.key === 'created_at'" class="task-page__cell">{{ formatDateTime(task.created_at) }}</span>
                    <span v-else-if="col.key === 'updated_at'" class="task-page__cell">{{ formatDateTime(task.updated_at) }}</span>
                    <span v-else-if="col.key === 'parent'" class="task-page__cell">{{ task.parent?.title || '—' }}</span>
                    <span v-else-if="col.key === 'attachments_count'" class="task-page__cell">{{ task.attachments_count || 0 }}</span>
                    <span v-else-if="col.key === 'estimated_hours'" class="task-page__cell">{{ task.estimated_hours ?? '—' }}</span>
                    <span v-else-if="col.key === 'worklog_hours'" class="task-page__cell">{{ task.worklog_hours || 0 }}</span>
                    <span v-else-if="col.key === 'manager'">
                      <UserAvatarTip v-if="task.manager" :user="task.manager" label="Người quản lý" />
                      <span v-else>—</span>
                    </span>
                    <span v-else-if="col.key === 'accepted_by'" class="task-page__cell">{{ task.accepted_by_user?.name || '—' }}</span>
                    <span v-else-if="col.key === 'weight'" class="task-page__cell">{{ task.weight != null ? `${task.weight}%` : '—' }}</span>
                    <span v-else-if="col.key === 'is_overdue'" class="task-page__pill" :class="`task-page__pill--${task.is_overdue ? 'danger' : 'success'}`">
                      <span class="task-page__dot" :class="`task-page__dot--${task.is_overdue ? 'danger' : 'success'}`" />
                      {{ task.is_overdue ? 'Quá hạn' : 'Đúng hạn' }}
                    </span>
                    <span v-else-if="col.key === 'variance_days'" class="task-page__cell">{{ formatVarianceDays(task.variance_days) }}</span>
                    <span v-else class="task-page__cell">{{ cellText(task, col.key) }}</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <TablePagesBar
          v-if="!isKanban"
          placement="bottom"
          paging-only
          :from="meta.from || 0"
          :to="meta.to || 0"
          :total="meta.total || 0"
          :page="meta.current_page || 1"
          :last-page="meta.last_page || 1"
          :per-page="perPage"
          @update:page="goPage"
          @update:per-page="perPage = $event"
        />

        <div
          v-if="isKanban"
          ref="kanbanWrap"
          class="task-kanban hide-scrollbar"
          :class="{
            'task-kanban--dragging': kanbanDrag.active,
            'task-kanban--fill': kanbanStatusFill,
            'task-kanban--spread': kanbanSpread,
          }"
        >
          <p v-if="kanbanGroupBy === 'assignees' && !kanbanSelectedUsers.length" class="task-kanban__hint">
            Chọn ít nhất một người thực hiện ở trên để xem theo cột.
          </p>
          <p v-else-if="kanbanStatusFill && !kanbanStatusColumns.length" class="task-kanban__hint">
            Không có công việc nào.
          </p>
          <p v-else-if="!kanbanColumns.length" class="task-kanban__hint">
            Không có công việc nào.
          </p>
          <div
            v-for="col in kanbanColumns"
            :key="col.key"
            class="task-kanban__col"
            :class="[
              `task-kanban__col--${col.tone || 'primary'}`,
              { 'task-kanban__col--drop': isKanbanDragGroup && kanbanDrag.active && kanbanDrag.overKey === col.dropKey },
            ]"
            :data-drop-key="isKanbanDragGroup ? col.dropKey : undefined"
          >
            <header class="task-kanban__col-head">
              <div class="task-kanban__col-head-main">
                <UserAvatarTip v-if="col.user" :user="col.user" label="Người thực hiện" />
                <span class="task-kanban__col-title">{{ col.label }}</span>
              </div>
              <span class="task-kanban__col-count">{{ col.tasks.length }}</span>
            </header>

            <div class="task-kanban__col-body hide-scrollbar">
              <div
                v-if="kanbanDropSlot(col)"
                class="task-kanban__placeholder"
                :style="{ height: `${kanbanDrag.height}px` }"
              />

              <article
                v-for="task in col.tasks"
                :key="task.id"
                class="task-kanban__card"
                :class="{
                  'task-kanban__card--movable': kanbanCardsMovable,
                  'task-kanban__card--slot': kanbanDrag.active && kanbanDrag.taskId === task.id,
                  'task-kanban__card--enter': kanbanJustMovedId === task.id,
                }"
                data-no-drag-scroll
                @pointerdown="onKanbanCardPointerDown($event, task)"
                @click="!isKanbanDragGroup && inspect(task)"
              >
                <span v-if="task.is_overdue" class="task-kanban__overdue-dot" aria-hidden="true" />
                <header class="task-kanban__card-head">
                  <span v-if="task.code" class="task-kanban__card-code">{{ task.code }}</span>
                  <span class="task-kanban__card-type">{{ task.project?.name || typeLabel(task.type) }}</span>
                  <span v-if="task.weight != null" class="task-kanban__card-weight">{{ task.weight }}%</span>
                </header>
                <h3 class="task-kanban__card-title">{{ task.title }}</h3>
                <div v-if="task.priority && task.priority !== 'low'" class="task-kanban__card-labels">
                  <span class="task-kanban__importance" :class="`task-kanban__importance--${priorityTone(task.priority)}`">
                    {{ priorityLabel(task.priority) }}
                  </span>
                </div>
                <p v-if="task.description" class="task-kanban__card-desc">{{ task.description }}</p>
                <dl v-if="task.assignee?.name || task.manager?.name || dateRangeLabel(task)" class="task-kanban__card-facts">
                  <div v-if="task.assignee?.name" class="task-kanban__card-fact">
                    <dt>Người thực hiện</dt>
                    <dd>{{ task.assignee.name }}</dd>
                  </div>
                  <div v-if="task.manager?.name" class="task-kanban__card-fact">
                    <dt>Người quản lý</dt>
                    <dd>{{ task.manager.name }}</dd>
                  </div>
                  <div v-if="dateRangeLabel(task)" class="task-kanban__card-fact">
                    <dt>Thời hạn</dt>
                    <dd>{{ dateRangeLabel(task) }}</dd>
                  </div>
                  <div v-if="task.worklog_hours" class="task-kanban__card-fact">
                    <dt>Thời gian thực hiện</dt>
                    <dd>{{ task.worklog_hours }} giờ</dd>
                  </div>
                </dl>
                <div v-if="task.progress_percent != null" class="task-kanban__card-progress">
                  <div class="task-kanban__card-progress-head">
                    <span class="task-kanban__card-progress-label">Tiến độ</span>
                    <span class="task-kanban__card-progress-value">{{ task.progress_percent }}%</span>
                  </div>
                  <span class="task-page__mini-track">
                    <span
                      class="task-page__mini-fill"
                      :class="`task-page__mini-fill--${progressTone(task.progress_percent)}`"
                      :style="{ width: `${task.progress_percent}%` }"
                    />
                  </span>
                </div>
                <footer class="task-kanban__card-foot">
                  <span class="task-kanban__card-avatars">
                    <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                  </span>
                  <span class="task-kanban__card-meta">
                    <span class="task-kanban__card-stat">{{ statusLabel(task.status) }}</span>
                  </span>
                </footer>
                <span v-if="kanbanCardsMovable" class="task-kanban__card-grip" aria-hidden="true">
                  <AppIcon name="gripVertical" :size="14" />
                </span>
                <div v-if="kanbanStatusUpdating.has(task.id)" class="task-kanban__card-busy">
                  <AppIcon name="refresh" :size="14" class="task-page__spin" />
                </div>
              </article>

              <p v-if="!col.tasks.length && !kanbanDropSlot(col)" class="task-kanban__col-empty">Không có công việc nào.</p>
            </div>
          </div>
        </div>
      </div>

      <aside v-if="selected" class="task-page__side" aria-label="Chi tiết công việc">
        <div class="task-page__side-head">
          <h2 class="task-page__side-title">Chi tiết công việc</h2>
          <div class="task-page__side-actions">
            <button
              v-if="!editing && canEdit"
              type="button"
              class="task-page__icon-btn"
              aria-label="Sửa công việc"
              @click="startEdit"
            >
              <AppIcon name="pencil" :size="16" />
            </button>
            <button
              v-if="!editing && canEdit"
              type="button"
              class="task-page__icon-btn"
              aria-label="Xoá công việc"
              @click="askDelete"
            >
              <AppIcon name="trash" :size="16" />
            </button>
            <button type="button" class="task-page__icon-btn" aria-label="Đóng" @click="closePanel">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
        </div>

        <div class="task-page__side-lead" :class="`task-page__side-lead--${statusTone(selected.status)}`">
          <span class="task-page__dot task-page__dot--lg" :class="`task-page__dot--${statusTone(selected.status)}`" />
          <div>
            <span class="task-page__side-lead-project">{{ selected.project?.name || '—' }}</span>
            <p class="task-page__side-lead-desc">{{ selected.title }}</p>
          </div>
        </div>

        <form v-if="editing" class="task-page__form" @submit.prevent="saveEdit">
          <label class="task-page__field">
            <span class="task-page__label">Tên công việc</span>
            <input v-model="editForm.title" type="text" class="task-page__input" required maxlength="255" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Trạng thái</span>
            <select v-model="editForm.status" class="task-page__input">
              <option v-for="item in TASK_STATUSES.filter((s) => s.value)" :key="item.value" :value="item.value">
                {{ item.label }}
              </option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Người thực hiện</span>
            <select v-model="editForm.assignee_id" class="task-page__input">
              <option value="">Chưa gán</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Mức độ ưu tiên</span>
            <select v-model="editForm.priority" class="task-page__input">
              <option value="">Chưa đặt</option>
              <option v-for="(label, value) in TASK_PRIORITY_LABELS" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Cách tính tiến độ</span>
            <select v-model="editForm.progress_type" class="task-page__input">
              <option v-for="(label, value) in TASK_PROGRESS_TYPE_LABELS" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Tiến độ (%)</span>
            <input
              v-model="editForm.progress_percent"
              type="number"
              min="0"
              max="100"
              class="task-page__input"
              :disabled="editForm.progress_type === 'quantity'"
            />
          </label>
          <template v-if="editForm.progress_type === 'quantity'">
            <label class="task-page__field">
              <span class="task-page__label">Khối lượng đã hoàn thành</span>
              <input v-model="editForm.progress_number" type="number" min="0" step="0.01" class="task-page__input" />
            </label>
            <label class="task-page__field">
              <span class="task-page__label">Khối lượng cần hoàn thành</span>
              <input v-model="editForm.progress_total" type="number" min="0.01" step="0.01" class="task-page__input" />
            </label>
            <label class="task-page__field">
              <span class="task-page__label">Đơn vị</span>
              <input v-model="editForm.unit" type="text" maxlength="50" class="task-page__input" />
            </label>
            <p v-if="editFormEstimatedPercent != null" class="task-page__hint">
              Ước tính: {{ editFormEstimatedPercent }}% (hệ thống tự tính khi lưu)
            </p>
          </template>
          <label class="task-page__field">
            <span class="task-page__label">Ngày bắt đầu</span>
            <input v-model="editForm.start_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Giờ bắt đầu</span>
            <input v-model="editForm.start_time" type="time" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Ngày kết thúc</span>
            <input v-model="editForm.end_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Giờ hạn</span>
            <input v-model="editForm.due_time" type="time" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Bắt đầu thực tế</span>
            <input v-model="editForm.actual_start_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Kết thúc thực tế</span>
            <input v-model="editForm.actual_end_date" type="date" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Người quản lý</span>
            <select v-model="editForm.manager_id" class="task-page__input">
              <option value="">Chưa gán</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Thời gian dự kiến (giờ)</span>
            <input v-model="editForm.estimated_hours" type="number" min="0" step="0.5" class="task-page__input" />
          </label>
          <label class="task-page__field">
            <span class="task-page__label">Tỷ trọng (%)</span>
            <input v-model="editForm.weight" type="number" min="0" max="100" step="0.1" class="task-page__input" />
          </label>
          <label class="task-page__field task-page__field--full">
            <span class="task-page__label">Mô tả</span>
            <textarea v-model="editForm.description" class="task-page__input task-page__textarea" rows="3" />
          </label>
          <div class="task-page__form-actions">
            <button type="button" class="task-page__btn task-page__btn--ghost" :disabled="saving" @click="cancelEdit">
              Huỷ
            </button>
            <button type="submit" class="task-page__btn" :disabled="saving">
              {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}
            </button>
          </div>
        </form>

        <div v-else class="task-page__rows">
          <div class="task-page__row">
            <span class="task-page__row-label">Dự án</span>
            <span class="task-page__row-value">{{ selected.project?.name || '—' }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Mã công việc</span>
            <span class="task-page__row-value">{{ selected.code || selected.id }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Loại</span>
            <span class="task-page__row-value">{{ typeLabel(selected.type) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Trạng thái</span>
            <span class="task-page__row-value task-page__row-value--status">
              <span class="task-page__dot" :class="`task-page__dot--${statusTone(selected.status)}`" />
              {{ statusLabel(selected.status) }}
            </span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Mức độ ưu tiên</span>
            <span class="task-page__row-value">{{ priorityLabel(selected.priority) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Người thực hiện</span>
            <span class="task-page__row-value task-page__row-person">
              <UserAvatarTip :user="selected.assignee" label="Người thực hiện" />
              <span>{{ selected.assignee?.name || 'Chưa gán' }}</span>
            </span>
          </div>
          <div v-if="selected.progress_percent != null" class="task-page__row task-page__row--progress">
            <span class="task-page__row-label">Tiến độ</span>
            <span class="task-page__progress">
              <span class="task-page__progress-track">
                <span class="task-page__progress-fill" :style="{ width: `${selected.progress_percent}%` }" />
              </span>
              <span class="task-page__row-value">{{ selected.progress_percent }}%</span>
            </span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Ngày bắt đầu</span>
            <span class="task-page__row-value">{{ formatDate(selected.start_date) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Ngày kết thúc</span>
            <span class="task-page__row-value">{{ formatDate(selected.end_date) }}</span>
          </div>
          <div v-if="selected.actual_start_date" class="task-page__row">
            <span class="task-page__row-label">Bắt đầu thực tế</span>
            <span class="task-page__row-value">{{ formatDate(selected.actual_start_date) }}</span>
          </div>
          <div v-if="selected.actual_end_date" class="task-page__row">
            <span class="task-page__row-label">Kết thúc thực tế</span>
            <span class="task-page__row-value">{{ formatDate(selected.actual_end_date) }}</span>
          </div>
          <div v-if="selected.description" class="task-page__row">
            <span class="task-page__row-label">Mô tả</span>
            <span class="task-page__row-value">{{ selected.description }}</span>
          </div>

          <div v-if="selected.parent" class="task-page__row">
            <span class="task-page__row-label">Công việc cha</span>
            <span class="task-page__row-value">{{ selected.parent.code ? `${selected.parent.code} — ` : '' }}{{ selected.parent.title }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Tình trạng hạn</span>
            <span class="task-page__row-value task-page__row-value--status">
              <span class="task-page__dot" :class="`task-page__dot--${selected.is_overdue ? 'danger' : 'success'}`" />
              {{ selected.is_overdue ? 'Quá hạn' : 'Đúng hạn' }}
            </span>
          </div>
          <div v-if="selected.variance_days != null" class="task-page__row">
            <span class="task-page__row-label">Chênh lệch</span>
            <span class="task-page__row-value">{{ formatVarianceDays(selected.variance_days) }}</span>
          </div>
          <div v-if="selected.estimated_hours != null" class="task-page__row">
            <span class="task-page__row-label">Thời gian dự kiến</span>
            <span class="task-page__row-value">{{ selected.estimated_hours }} giờ</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Thời gian thực hiện</span>
            <span class="task-page__row-value">{{ selected.worklog_hours || 0 }} giờ</span>
          </div>
          <div v-if="selected.manager" class="task-page__row">
            <span class="task-page__row-label">Người quản lý</span>
            <span class="task-page__row-value task-page__row-person">
              <UserAvatarTip :user="selected.manager" label="Người quản lý" />
              <span>{{ selected.manager.name }}</span>
            </span>
          </div>
          <div v-if="selected.accepted_by_user" class="task-page__row">
            <span class="task-page__row-label">Người đã nhận</span>
            <span class="task-page__row-value">{{ selected.accepted_by_user.name }} — {{ formatDateTime(selected.accepted_at) }}</span>
          </div>
          <div v-if="selected.progress_type === 'quantity'" class="task-page__row">
            <span class="task-page__row-label">Khối lượng</span>
            <span class="task-page__row-value">{{ selected.progress_number }} / {{ selected.progress_total }} {{ selected.unit }}</span>
          </div>
          <div v-if="selected.weight != null" class="task-page__row">
            <span class="task-page__row-label">Tỷ trọng</span>
            <span class="task-page__row-value">{{ selected.weight }}%</span>
          </div>
          <div v-if="selected.task_score" class="task-page__row">
            <span class="task-page__row-label">Điểm đánh giá</span>
            <span class="task-page__row-value">{{ selected.task_score.rating_score ?? '—' }}</span>
          </div>
          <div v-if="selected.task_score?.rating_result" class="task-page__row">
            <span class="task-page__row-label">Kết quả đánh giá</span>
            <span class="task-page__row-value">{{ selected.task_score.rating_result }}</span>
          </div>
          <div v-if="selected.task_score?.rating_desc" class="task-page__row">
            <span class="task-page__row-label">Ý kiến đánh giá</span>
            <span class="task-page__row-value">{{ selected.task_score.rating_desc }}</span>
          </div>

          <div class="task-page__row">
            <span class="task-page__row-label">Người tạo</span>
            <span class="task-page__row-value">{{ selected.creator?.name || '—' }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Ngày tạo</span>
            <span class="task-page__row-value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div class="task-page__row">
            <span class="task-page__row-label">Cập nhật lần cuối</span>
            <span class="task-page__row-value">{{ formatDateTime(selected.updated_at) }}</span>
          </div>
        </div>

        <section v-if="!editing" class="task-page__subsection">
          <h3 class="task-page__subsection-title">Tệp đính kèm</h3>
          <div v-if="taskAttachments.length" class="task-page__attachment-list">
            <div v-for="att in taskAttachments" :key="att.id" class="task-page__attachment">
              <AppIcon name="fileText" :size="16" />
              <a :href="att.file_url" target="_blank" rel="noopener" class="task-page__attachment-name">
                {{ att.file_name }}
              </a>
              <span v-if="att.file_size" class="task-page__attachment-size">{{ formatFileSize(att.file_size) }}</span>
              <button
                v-if="canEdit"
                type="button"
                class="task-page__icon-btn"
                aria-label="Xoá tệp đính kèm"
                @click="removeAttachment(att)"
              >
                <AppIcon name="trash" :size="14" />
              </button>
            </div>
          </div>
          <button
            v-if="canEdit"
            type="button"
            class="task-page__btn task-page__btn--ghost"
            :disabled="attachmentUploading"
            @click="triggerAttachmentInput"
          >
            {{ attachmentUploading ? 'Đang tải lên…' : 'Tải file lên' }}
          </button>
          <input ref="attachmentInput" type="file" class="task-page__hidden-input" @change="onAttachmentChange" />
        </section>

        <section v-if="!editing" class="task-page__subsection">
          <h3 class="task-page__subsection-title">Nhật ký giờ làm</h3>
          <ul v-if="taskWorklogs.length" class="task-page__worklog-list">
            <li v-for="log in taskWorklogs" :key="log.id" class="task-page__worklog-item">
              <span class="task-page__worklog-main">
                <span class="task-page__worklog-user">{{ log.user?.name || '—' }}</span>
                <span class="task-page__worklog-date">{{ formatDate(log.work_date) }}</span>
                <span class="task-page__worklog-hours">{{ log.hours }} giờ</span>
              </span>
              <span v-if="log.note" class="task-page__worklog-note">{{ log.note }}</span>
              <button
                v-if="canEditWorklog(log)"
                type="button"
                class="task-page__icon-btn"
                aria-label="Xoá nhật ký giờ làm"
                @click="askDeleteWorklog(log)"
              >
                <AppIcon name="trash" :size="14" />
              </button>
            </li>
          </ul>

          <form v-if="worklogFormOpen" class="task-page__form task-page__form--compact" @submit.prevent="saveWorklog">
            <label class="task-page__field">
              <span class="task-page__label">Ngày làm</span>
              <input v-model="worklogForm.work_date" type="date" class="task-page__input" required />
            </label>
            <label class="task-page__field">
              <span class="task-page__label">Số giờ</span>
              <input v-model="worklogForm.hours" type="number" min="0.25" max="24" step="0.25" class="task-page__input" required />
            </label>
            <label class="task-page__field task-page__field--full">
              <span class="task-page__label">Ghi chú</span>
              <textarea v-model="worklogForm.note" class="task-page__input task-page__textarea" rows="2" />
            </label>
            <div class="task-page__form-actions">
              <button type="button" class="task-page__btn task-page__btn--ghost" :disabled="worklogSaving" @click="cancelWorklogForm">
                Huỷ
              </button>
              <button type="submit" class="task-page__btn" :disabled="worklogSaving">
                {{ worklogSaving ? 'Đang lưu…' : 'Lưu' }}
              </button>
            </div>
          </form>
          <button
            v-else-if="canEdit"
            type="button"
            class="task-page__btn task-page__btn--ghost"
            @click="openWorklogForm"
          >
            + Thêm giờ làm
          </button>
        </section>

        <section v-if="!editing && canApprove" class="task-page__subsection">
          <h3 class="task-page__subsection-title">Đánh giá công việc</h3>
          <form class="task-page__form" @submit.prevent="saveScore">
            <label class="task-page__field">
              <span class="task-page__label">Điểm số</span>
              <input v-model="scoreForm.rating_score" type="number" min="0" step="0.1" class="task-page__input" />
            </label>
            <label class="task-page__field">
              <span class="task-page__label">Kết quả</span>
              <input
                v-model="scoreForm.rating_result"
                type="text"
                maxlength="100"
                list="task-score-suggestions"
                class="task-page__input"
              />
              <datalist id="task-score-suggestions">
                <option v-for="s in TASK_SCORE_RESULT_SUGGESTIONS" :key="s" :value="s" />
              </datalist>
            </label>
            <label class="task-page__field task-page__field--full">
              <span class="task-page__label">Ý kiến đánh giá</span>
              <textarea v-model="scoreForm.rating_desc" class="task-page__input task-page__textarea" rows="3" />
            </label>
            <div class="task-page__form-actions">
              <button type="submit" class="task-page__btn" :disabled="scoreSaving">
                {{ scoreSaving ? 'Đang lưu…' : 'Lưu đánh giá' }}
              </button>
            </div>
          </form>
        </section>
      </aside>
    </div>

    <ConfirmDialog
      :open="confirmingDelete"
      title="Xoá công việc"
      :description="`Bạn có chắc muốn xoá công việc “${selected?.title || ''}”? Thao tác này không thể hoàn tác.`"
      confirm-label="Xoá"
      :loading="deleting"
      danger
      @confirm="confirmDelete"
      @update:open="confirmingDelete = $event"
    />

    <ConfirmDialog
      :open="Boolean(confirmingDeleteWorklog)"
      title="Xoá nhật ký giờ làm"
      description="Bạn có chắc muốn xoá dòng nhật ký giờ làm này? Thao tác này không thể hoàn tác."
      confirm-label="Xoá"
      danger
      @confirm="confirmDeleteWorklog"
      @update:open="confirmingDeleteWorklog = $event ? confirmingDeleteWorklog : null"
    />

    <Teleport to="body">
      <Transition name="task-dialog-fade">
        <div
          v-if="exportDialogOpen"
          class="task-page__dialog"
          role="presentation"
          @mousedown.self="closeExportDialog"
        >
          <div
            class="task-page__dialog-panel task-page__dialog-panel--import"
            role="dialog"
            aria-modal="true"
            aria-labelledby="task-export-title"
          >
            <div class="task-page__dialog-head">
              <span class="task-page__dialog-icon" aria-hidden="true">
                <AppIcon name="fileDown" :size="22" :stroke-width="1.75" />
              </span>
              <div class="task-page__dialog-head-copy">
                <h2 id="task-export-title" class="task-page__dialog-title">Xuất công việc ra Excel</h2>
              </div>
              <button
                type="button"
                class="task-page__dialog-close"
                aria-label="Đóng"
                :disabled="exporting"
                @click="closeExportDialog"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="task-page__dialog-body">
              <p class="task-page__import-hint">
                Xuất theo đúng bộ lọc đang xem trên trang. Chọn cột cần xuất — Mã công việc và Mã dự án luôn được xuất.
              </p>
              <div class="task-page__export-toolbar">
                <button type="button" class="task-page__import-template" @click="selectAllExportColumns">Chọn tất cả</button>
                <button type="button" class="task-page__import-template" @click="deselectAllExportColumns">Bỏ chọn tất cả</button>
              </div>
              <div class="task-page__export-grid">
                <label
                  v-for="col in EXPORT_COLUMNS"
                  :key="col.key"
                  class="task-page__export-col"
                  :class="{ 'task-page__export-col--disabled': col.always }"
                >
                  <input
                    type="checkbox"
                    :checked="exportSelectedColumns.has(col.key)"
                    :disabled="col.always"
                    @change="toggleExportColumn(col.key)"
                  />
                  <span>{{ col.label }}</span>
                </label>
              </div>
            </div>
            <div class="task-page__dialog-actions">
              <button type="button" class="task-page__dialog-btn task-page__dialog-btn--ghost" :disabled="exporting" @click="closeExportDialog">
                Đóng
              </button>
              <button
                type="button"
                class="task-page__dialog-btn task-page__dialog-btn--primary"
                :disabled="exporting || exportSelectedColumns.size === 0"
                @click="submitExportDialog"
              >
                {{ exporting ? 'Đang xuất…' : `Xuất Excel (${exportSelectedColumns.size} cột)` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="task-dialog-fade">
        <div
          v-if="importDialogOpen"
          class="task-page__dialog"
          role="presentation"
          @mousedown.self="closeImportDialog"
        >
          <div
            class="task-page__dialog-panel"
            :class="importStep === 'preview' ? 'task-page__dialog-panel--preview' : 'task-page__dialog-panel--import'"
            role="dialog"
            aria-modal="true"
            aria-labelledby="task-import-title"
          >
            <div class="task-page__dialog-head">
              <span class="task-page__dialog-icon" aria-hidden="true">
                <AppIcon name="fileUp" :size="22" :stroke-width="1.75" />
              </span>
              <div class="task-page__dialog-head-copy">
                <h2 id="task-import-title" class="task-page__dialog-title">Nhập công việc từ Excel</h2>
              </div>
              <button
                type="button"
                class="task-page__dialog-close"
                aria-label="Đóng"
                :disabled="previewing || confirming"
                @click="closeImportDialog"
              >
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <template v-if="importStep === 'select'">
              <div class="task-page__dialog-body">
                <p class="task-page__import-hint">
                  Chọn file Excel (.xlsx) đúng theo cấu trúc cột đã xuất. Để trống Mã công việc để tạo công việc mới (khi đó Mã dự án và Tên công việc là bắt buộc). Điền đúng Mã công việc đã có để cập nhật công việc đó — ô nào để trống khi cập nhật sẽ giữ nguyên giá trị cũ, không bị xoá. Người thực hiện / người quản lý nhập bằng email hoặc tên.
                  <button type="button" class="task-page__import-template" :disabled="exporting" @click="exportExcel()">
                    Tải file mẫu (xuất danh sách hiện tại, đủ cột)
                  </button>
                </p>
                <div class="task-page__dialog-field">
                  <label class="task-page__dialog-label" for="task-import-file">
                    File Excel
                    <span class="task-page__dialog-req" aria-hidden="true">*</span>
                  </label>
                  <input
                    id="task-import-file"
                    type="file"
                    accept=".xlsx"
                    class="task-page__import-file"
                    :disabled="previewing"
                    @change="onImportFileChange"
                  />
                </div>
              </div>
              <div class="task-page__dialog-actions">
                <button type="button" class="task-page__dialog-btn task-page__dialog-btn--ghost" :disabled="previewing" @click="closeImportDialog">
                  Đóng
                </button>
                <button
                  type="button"
                  class="task-page__dialog-btn task-page__dialog-btn--primary"
                  :disabled="previewing || !importFile"
                  @click="runImportPreview"
                >
                  {{ previewing ? 'Đang đọc file…' : 'Xem trước' }}
                </button>
              </div>
            </template>

            <template v-else-if="importStep === 'preview'">
              <div class="task-page__dialog-body task-page__dialog-body--preview">
                <div class="task-page__import-summary">
                  <span class="task-page__import-dot task-page__import-dot--ok" />
                  {{ importSelectedRows.size }}/{{ importPreview?.rows?.length ?? 0 }}
                  dòng hợp lệ được chọn để nhập. Nhấn vào dòng lỗi để sửa trực tiếp.
                </div>
                <div class="task-page__preview-table-wrap hide-scrollbar">
                  <table class="task-page__preview-table">
                    <thead>
                      <tr>
                        <th class="task-page__preview-th task-page__preview-th--check"></th>
                        <th class="task-page__preview-th">Dòng</th>
                        <th class="task-page__preview-th">Hành động</th>
                        <th class="task-page__preview-th">Mã công việc</th>
                        <th class="task-page__preview-th">Mã dự án</th>
                        <th class="task-page__preview-th">Tên công việc</th>
                        <th class="task-page__preview-th">Trạng thái</th>
                        <th class="task-page__preview-th">Ưu tiên</th>
                        <th class="task-page__preview-th">Người thực hiện</th>
                        <th class="task-page__preview-th">Người quản lý</th>
                        <th class="task-page__preview-th">Ngày bắt đầu</th>
                        <th class="task-page__preview-th">Ngày kết thúc</th>
                        <th class="task-page__preview-th">Tiến độ</th>
                        <th class="task-page__preview-th">Mô tả</th>
                        <th class="task-page__preview-th">Trạng thái dòng</th>
                        <th class="task-page__preview-th">Ghi chú</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-for="row in importPreview?.rows ?? []" :key="row.row">
                        <tr
                          v-if="editingRowKey !== row.row"
                          class="task-page__preview-row"
                          :class="{ 'task-page__preview-row--invalid': row.status !== 'valid' }"
                          @dblclick="startEditImportRow(row)"
                        >
                          <td class="task-page__preview-td task-page__preview-td--check">
                            <input
                              v-if="row.status === 'valid'"
                              type="checkbox"
                              :checked="importSelectedRows.has(row.row)"
                              @change="toggleImportRowSelected(row.row)"
                            />
                          </td>
                          <td class="task-page__preview-td">{{ row.row }}</td>
                          <td class="task-page__preview-td">
                            {{ row.action === 'update' ? `Cập nhật công việc ${row.code || ''}` : 'Tạo mới' }}
                          </td>
                          <td class="task-page__preview-td">{{ row.code || '—' }}</td>
                          <td class="task-page__preview-td">{{ row.data?.project_code || '—' }}</td>
                          <td class="task-page__preview-td">{{ row.data?.title || '—' }}</td>
                          <td class="task-page__preview-td">{{ statusLabel(row.data?.status) }}</td>
                          <td class="task-page__preview-td">{{ priorityLabel(row.data?.priority) }}</td>
                          <td class="task-page__preview-td">{{ row.data?.assignee_name || '—' }}</td>
                          <td class="task-page__preview-td">{{ row.data?.manager_name || '—' }}</td>
                          <td class="task-page__preview-td">{{ formatDate(row.data?.start_date) }}</td>
                          <td class="task-page__preview-td">{{ formatDate(row.data?.end_date) }}</td>
                          <td class="task-page__preview-td">{{ row.data?.progress_percent != null ? `${row.data.progress_percent}%` : '—' }}</td>
                          <td class="task-page__preview-td task-page__preview-td--desc">{{ row.data?.description || '—' }}</td>
                          <td class="task-page__preview-td">
                            <span class="task-page__import-summary">
                              <span
                                class="task-page__import-dot"
                                :class="row.status === 'valid' ? 'task-page__import-dot--ok' : 'task-page__import-dot--error'"
                              />
                              {{ row.status === 'valid' ? 'Hợp lệ' : 'Không hợp lệ' }}
                            </span>
                          </td>
                          <td class="task-page__preview-td task-page__preview-td--issues">
                            <p v-for="(issue, idx) in row.issues" :key="idx" class="task-page__preview-issue">
                              {{ issue.message }}
                            </p>
                            <button
                              v-if="row.status !== 'valid'"
                              type="button"
                              class="task-page__import-template"
                              @click="startEditImportRow(row)"
                            >
                              Sửa dòng này
                            </button>
                          </td>
                        </tr>
                        <tr v-else class="task-page__preview-row task-page__preview-row--editing">
                          <td class="task-page__preview-td" colspan="16">
                            <div class="task-page__edit-grid">
                              <label class="task-page__edit-field">
                                <span>Mã công việc</span>
                                <input v-model="editingRowDraft.code" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Mã dự án</span>
                                <input v-model="editingRowDraft.project_code" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Tên công việc</span>
                                <input v-model="editingRowDraft.title" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Trạng thái</span>
                                <input v-model="editingRowDraft.status_input" type="text" list="task-import-status-options" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Mức độ ưu tiên</span>
                                <input v-model="editingRowDraft.priority_input" type="text" list="task-import-priority-options" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Người thực hiện (email)</span>
                                <input v-model="editingRowDraft.assignee_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Người quản lý (email)</span>
                                <input v-model="editingRowDraft.manager_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Ngày bắt đầu</span>
                                <input v-model="editingRowDraft.start_input" type="text" placeholder="dd/mm/yyyy" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Ngày kết thúc</span>
                                <input v-model="editingRowDraft.end_input" type="text" placeholder="dd/mm/yyyy" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Cách tính tiến độ</span>
                                <input v-model="editingRowDraft.progress_type_input" type="text" list="task-import-progress-type-options" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Tiến độ (%)</span>
                                <input v-model="editingRowDraft.progress_percent_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Khối lượng hoàn thành</span>
                                <input v-model="editingRowDraft.progress_number_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Khối lượng cần hoàn thành</span>
                                <input v-model="editingRowDraft.progress_total_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Đơn vị</span>
                                <input v-model="editingRowDraft.unit" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Thời gian dự kiến (giờ)</span>
                                <input v-model="editingRowDraft.estimated_hours_input" type="text" />
                              </label>
                              <label class="task-page__edit-field">
                                <span>Tỷ trọng (%)</span>
                                <input v-model="editingRowDraft.weight_input" type="text" />
                              </label>
                              <label class="task-page__edit-field task-page__edit-field--full">
                                <span>Mô tả</span>
                                <input v-model="editingRowDraft.description" type="text" />
                              </label>
                            </div>
                            <div class="task-page__edit-actions">
                              <button type="button" class="task-page__dialog-btn task-page__dialog-btn--ghost" :disabled="resolvingRow" @click="cancelEditImportRow">
                                Huỷ
                              </button>
                              <button
                                type="button"
                                class="task-page__dialog-btn task-page__dialog-btn--primary"
                                :disabled="resolvingRow"
                                @click="saveEditImportRow(row)"
                              >
                                {{ resolvingRow ? 'Đang kiểm tra…' : 'Lưu và kiểm tra lại' }}
                              </button>
                            </div>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                  <datalist id="task-import-status-options">
                    <option v-for="(label, value) in TASK_STATUS_LABELS" :key="value" :value="label" />
                  </datalist>
                  <datalist id="task-import-priority-options">
                    <option v-for="(label, value) in TASK_PRIORITY_LABELS" :key="value" :value="label" />
                  </datalist>
                  <datalist id="task-import-progress-type-options">
                    <option v-for="(label, value) in TASK_PROGRESS_TYPE_LABELS" :key="value" :value="label" />
                  </datalist>
                </div>
              </div>
              <div class="task-page__dialog-actions">
                <button type="button" class="task-page__dialog-btn task-page__dialog-btn--ghost" :disabled="confirming" @click="backToSelect">
                  Quay lại
                </button>
                <button
                  type="button"
                  class="task-page__dialog-btn task-page__dialog-btn--primary"
                  :disabled="confirming || importSelectedRows.size === 0"
                  @click="confirmImportRows"
                >
                  {{ confirming ? 'Đang nhập…' : `Xác nhận nhập (${importSelectedRows.size})` }}
                </button>
              </div>
            </template>

            <template v-else>
              <div class="task-page__dialog-body">
                <div v-if="importResult" class="task-page__import-result">
                  <div class="task-page__import-summary">
                    <span class="task-page__import-dot task-page__import-dot--ok" />
                    Đã tạo {{ importResult.created.length }} công việc, cập nhật {{ importResult.updated?.length ?? 0 }} công việc
                    <template v-if="importResult.errors.length">
                      , còn {{ importResult.errors.length }} dòng lỗi
                    </template>.
                  </div>
                  <ul v-if="importResult.errors.length" class="task-page__import-errors">
                    <li v-for="err in importResult.errors" :key="err.row" class="task-page__import-error">
                      <span class="task-page__import-dot task-page__import-dot--error" />
                      Dòng {{ err.row }}: {{ err.message }}
                    </li>
                  </ul>
                </div>
              </div>
              <div class="task-page__dialog-actions">
                <button type="button" class="task-page__dialog-btn task-page__dialog-btn--primary" @click="closeImportDialog">
                  Đóng
                </button>
              </div>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="kanbanDrag.active && kanbanDrag.task"
        class="task-kanban__ghost"
        :class="{
          'task-kanban__ghost--live': !kanbanDrag.settling,
          'task-kanban__ghost--settle': kanbanDrag.settling,
          [`task-kanban__ghost--${kanbanGhostTone}`]: true,
        }"
        :style="kanbanGhostStyle"
      >
        <header class="task-kanban__card-head">
          <span v-if="kanbanDrag.task.code" class="task-kanban__card-code">{{ kanbanDrag.task.code }}</span>
          <span class="task-kanban__card-type">{{ kanbanDrag.task.project?.name || typeLabel(kanbanDrag.task.type) }}</span>
        </header>
        <h3 class="task-kanban__card-title">{{ kanbanDrag.task.title }}</h3>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.task-page {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 0 var(--space-5) var(--space-3);
  overflow: hidden;
}

.task-page__header-btn {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.task-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.task-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-page__header-search {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  height: 2rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-page__header-search input {
  width: 15rem;
  max-width: 40vw;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.task-page__header-search input:focus {
  outline: none;
}

.task-page__spin {
  animation: task-spin 0.8s linear infinite;
}

@keyframes task-spin {
  to {
    transform: rotate(360deg);
  }
}

.task-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.task-page__main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.task-tabs-row {
  position: relative;
  z-index: 12;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
  min-width: 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-view-mode {
  position: relative;
  flex-shrink: 0;
  display: flex;
  align-items: stretch;
}

.task-view-mode__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5625rem 0.75rem 0.5rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 1px 0 0 var(--color-border);
}

.task-view-mode__trigger:hover,
.task-view-mode__trigger[aria-expanded='true'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.task-view-mode__menu {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 17.5rem;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.task-view-mode__group {
  margin: 0.25rem 0 0.125rem;
  padding: 0.25rem 0.625rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.task-view-mode__item {
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
  font-size: 0.8125rem;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.task-view-mode__item span {
  flex: 1;
}

.task-view-mode__item:hover,
.task-view-mode__item--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-tabs {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: stretch;
  gap: 0.125rem;
  overflow-x: auto;
}

.task-tabs__item {
  --tab-accent: var(--color-primary);
  --tab-bg: var(--color-primary-surface);
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5625rem 0.75rem 0.5rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.task-tabs__item:hover,
.task-tabs__item--active {
  color: var(--tab-accent);
  background: var(--tab-bg);
}

.task-tabs__item--active {
  box-shadow: 0 2px 0 var(--tab-accent);
}

.task-tabs__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--tab-bg);
  color: var(--tab-accent);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.task-tabs__item--active .task-tabs__count {
  background: var(--tab-accent);
  color: #fff;
}

.task-tabs__item--primary {
  --tab-accent: var(--color-primary);
  --tab-bg: var(--color-primary-surface);
}
.task-tabs__item--success {
  --tab-accent: var(--color-success);
  --tab-bg: var(--color-success-tint-bg);
}
.task-tabs__item--info {
  --tab-accent: var(--color-info);
  --tab-bg: var(--color-info-tint-bg);
}
.task-tabs__item--warning {
  --tab-accent: var(--color-warning);
  --tab-bg: var(--color-warning-tint-bg);
}
.task-tabs__item--gold {
  --tab-accent: var(--color-gold-600);
  --tab-bg: var(--color-gold-surface);
}
.task-tabs__item--tertiary {
  --tab-accent: var(--color-tertiary);
  --tab-bg: var(--color-tertiary-surface);
}
.task-tabs__item--umber {
  --tab-accent: var(--color-umber);
  --tab-bg: var(--color-umber-surface);
}

.task-view-bar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0;
}

.task-kanban-members {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.task-kanban-members__trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-kanban-members__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.125rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.6875rem;
  font-weight: 700;
}

.task-kanban-members__dropdown {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.375rem);
  left: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 15rem;
  max-height: 16rem;
  overflow-y: auto;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.task-kanban-members__option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.task-kanban-members__option:hover {
  background: var(--color-primary-surface);
}

.task-kanban-members__empty {
  margin: 0;
  padding: 0.375rem 0.5rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.task-kanban-members__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.task-kanban-members__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  padding: 0.25rem 0.375rem 0.25rem 0.625rem;
  border: 1px solid var(--color-primary);
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-kanban-members__chip button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1rem;
  height: 1rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.task-page__toolbar {
  position: relative;
  z-index: 6;
  flex-shrink: 0;
  margin: var(--space-3) 0 0;
}

.task-page__bulk-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-3);
  flex-shrink: 0;
  margin: var(--space-2) 0 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 20%, transparent);
}

.task-page__bulk-count {
  flex-shrink: 0;
  align-self: center;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.task-page__bulk-field {
  width: auto;
  min-width: 10rem;
}

.task-page__col-check {
  width: 2.25rem;
}

.task-page__th-check,
.task-page__td-check {
  width: 2.25rem;
  text-align: center;
}

.task-page__filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-3);
  width: 100%;
}

.task-page__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  width: 100%;
}

.task-page__field--full {
  grid-column: 1 / -1;
}

.task-page__hint {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-page__hidden-input {
  display: none;
}

.task-page__subsection {
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.task-page__subsection-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.task-page__attachment-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin-bottom: var(--space-2);
}

.task-page__attachment {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.task-page__attachment-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
}

.task-page__attachment-size {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-page__worklog-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin: 0 0 var(--space-2);
  padding: 0;
  list-style: none;
}

.task-page__worklog-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.task-page__worklog-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  min-width: 0;
}

.task-page__worklog-user {
  color: var(--color-text);
  font-weight: 600;
}

.task-page__worklog-date,
.task-page__worklog-hours {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-page__worklog-note {
  flex-basis: 100%;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  overflow-wrap: anywhere;
}

.task-page__form--compact {
  margin-bottom: var(--space-2);
}

.task-page__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-page__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.task-page__textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.task-page__check {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.task-page__table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.task-page__table-wrap--resizing {
  cursor: col-resize;
  user-select: none;
}

.task-page__table {
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: calc(0.875rem * var(--table-zoom, 1));
}

.task-page__table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  overflow: hidden;
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: calc(0.75rem * var(--table-zoom, 1));
  letter-spacing: 0.02em;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-page__th-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  letter-spacing: inherit;
  cursor: pointer;
}

.task-page__resize {
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

.task-page__resize::after {
  content: '';
  position: absolute;
  top: 25%;
  right: 2px;
  width: 2px;
  height: 50%;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.task-page__resize:hover::after {
  background: var(--color-primary);
}

.task-page__table tbody td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  vertical-align: middle;
  white-space: nowrap;
  overflow: hidden;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.task-page__td--name {
  overflow: visible;
  white-space: normal;
  vertical-align: top;
}

.task-page__td--avatar {
  overflow: visible;
}

.task-page__table tbody tr {
  cursor: pointer;
}

.task-page__table tbody tr.task-page__data-row:hover td {
  filter: brightness(0.97);
}

.task-page__row--active td {
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface)) !important;
}

.task-page__name-cell {
  display: flex;
  flex-direction: column;
  gap: 0.3125rem;
  white-space: normal;
}

.task-page__name-title {
  display: block;
  font-weight: 400;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.task-page__name-title-row {
  display: flex;
  align-items: flex-start;
  gap: 0.25rem;
}

.task-page__tree-toggle {
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

.task-page__tree-chevron {
  transition: transform 0.15s ease;
}

.task-page__tree-chevron--open {
  transform: rotate(90deg);
}

.task-page__pill {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  max-width: 100%;
  padding: 0.1875rem 0.5625rem;
  border-radius: 0;
  background: var(--pill-bg, var(--color-surface-muted));
  color: var(--pill-fg, var(--color-text));
  font-size: calc(0.75rem * var(--table-zoom, 1));
  font-weight: 600;
  line-height: 1.3;
}

.task-page__pill--primary {
  --pill-bg: var(--color-primary-50);
  --pill-fg: var(--color-primary-900);
}
.task-page__pill--secondary {
  --pill-bg: var(--color-secondary-50);
  --pill-fg: var(--color-secondary-800);
}
.task-page__pill--tertiary {
  --pill-bg: var(--color-tertiary-50);
  --pill-fg: var(--color-tertiary-800);
}
.task-page__pill--gold {
  --pill-bg: var(--color-gold-50);
  --pill-fg: var(--color-gold-800);
}
.task-page__pill--success {
  --pill-bg: var(--color-success-tint-bg);
  --pill-fg: var(--color-success-tint-fg);
}
.task-page__pill--info {
  --pill-bg: var(--color-info-tint-bg);
  --pill-fg: var(--color-info-tint-fg);
}
.task-page__pill--warning {
  --pill-bg: var(--color-warning-tint-bg);
  --pill-fg: var(--color-warning-tint-fg);
}
.task-page__pill--danger {
  --pill-bg: var(--color-danger-tint-bg);
  --pill-fg: var(--color-danger-tint-fg);
}
.task-page__pill--violet {
  --pill-bg: color-mix(in srgb, var(--color-tertiary-surface) 65%, var(--color-primary-surface));
  --pill-fg: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
}
.task-page__pill--teal {
  --pill-bg: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-tertiary-surface));
  --pill-fg: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary-800));
}
.task-page__pill--rose {
  --pill-bg: color-mix(in srgb, var(--color-primary-surface) 62%, var(--color-gold-surface));
  --pill-fg: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold-800));
}
.task-page__pill--umber {
  --pill-bg: var(--color-umber-surface);
  --pill-fg: var(--color-umber-700);
}
.task-page__pill--neutral,
.task-page__pill--code,
.task-page__pill--date {
  --pill-bg: var(--color-surface-muted);
  --pill-fg: var(--color-text);
}

.task-page__progress-cell {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.task-page__mini-track {
  display: block;
  width: 3.25rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.task-page__mini-fill {
  display: block;
  height: 100%;
  border-radius: var(--radius-full);
  background: var(--color-tertiary);
}

.task-page__mini-fill--success {
  background: var(--color-success);
}
.task-page__mini-fill--tertiary {
  background: var(--color-tertiary);
}
.task-page__mini-fill--gold {
  background: var(--color-gold);
}
.task-page__mini-fill--warning {
  background: var(--color-warning);
}
.task-page__mini-fill--neutral {
  background: var(--color-text-muted);
}

.task-page__group-row {
  cursor: pointer;
}

.task-page__group-row td {
  position: relative;
  width: 100%;
  overflow: visible;
  padding: var(--space-2) var(--space-4) var(--space-2) calc(var(--space-4) + 3px + var(--space-2)) !important;
  background: var(--color-surface);
  color: var(--group-fg, var(--color-text));
  box-shadow: inset 0 -2px 0 var(--group-accent, var(--color-border)) !important;
}

.task-page__group-row td::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--group-accent, var(--color-border));
}

.task-page__group-row--primary {
  --group-fg: var(--color-primary);
  --group-accent: var(--color-primary);
}
.task-page__group-row--secondary {
  --group-fg: var(--color-secondary);
  --group-accent: var(--color-secondary);
}
.task-page__group-row--tertiary {
  --group-fg: var(--color-tertiary);
  --group-accent: var(--color-tertiary);
}
.task-page__group-row--gold {
  --group-fg: var(--color-gold);
  --group-accent: var(--color-gold);
}
.task-page__group-row--info {
  --group-fg: var(--color-info);
  --group-accent: var(--color-info);
}
.task-page__group-row--warning {
  --group-fg: var(--color-warning);
  --group-accent: var(--color-warning);
}
.task-page__group-row--success {
  --group-fg: var(--color-success);
  --group-accent: var(--color-success);
}
.task-page__group-row--violet {
  --group-fg: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --group-accent: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
}
.task-page__group-row--teal {
  --group-fg: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --group-accent: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
}
.task-page__group-row--rose {
  --group-fg: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --group-accent: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
}
.task-page__group-row--umber {
  --group-fg: var(--color-umber);
  --group-accent: var(--color-umber);
}
.task-page__group-row--neutral {
  --group-fg: var(--color-text);
  --group-accent: var(--color-text-muted);
}

.task-page__group-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  min-width: 0;
}

.task-page__group-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
}

.task-page__group-chevron {
  flex-shrink: 0;
  color: var(--group-accent, var(--color-text-muted));
  transition: transform 0.15s ease;
}

.task-page__group-chevron--open {
  transform: rotate(90deg);
}

.task-page__group-label {
  min-width: 0;
  overflow: hidden;
  color: inherit;
  font-size: calc(0.8125rem * var(--table-zoom, 1));
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-page__group-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  margin-left: auto;
  height: calc(1.125rem * var(--table-zoom, 1));
  padding: 0 0.4375rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--group-accent, var(--color-text-muted)) 14%, var(--color-surface));
  color: var(--group-accent, var(--color-text-muted));
  font-size: calc(0.6875rem * var(--table-zoom, 1));
  font-weight: 700;
  white-space: nowrap;
}

.task-page__data-row--primary td {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}
.task-page__data-row--secondary td {
  background: color-mix(in srgb, var(--color-secondary) 4%, var(--color-surface));
}
.task-page__data-row--tertiary td {
  background: color-mix(in srgb, var(--color-tertiary) 4%, var(--color-surface));
}
.task-page__data-row--gold td {
  background: color-mix(in srgb, var(--color-gold) 5%, var(--color-surface));
}
.task-page__data-row--info td {
  background: color-mix(in srgb, var(--color-info) 4%, var(--color-surface));
}
.task-page__data-row--warning td {
  background: color-mix(in srgb, var(--color-warning) 5%, var(--color-surface));
}
.task-page__data-row--success td {
  background: color-mix(in srgb, var(--color-success) 4%, var(--color-surface));
}
.task-page__data-row--violet td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary)) 4%, var(--color-surface));
}
.task-page__data-row--teal td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary)) 4%, var(--color-surface));
}
.task-page__data-row--rose td {
  background: color-mix(in srgb, color-mix(in srgb, var(--color-primary) 55%, var(--color-gold)) 4%, var(--color-surface));
}
.task-page__data-row--umber td {
  background: color-mix(in srgb, var(--color-umber) 5%, var(--color-surface));
}

.task-page__dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.task-page__dot--lg {
  margin-top: 0.375rem;
  width: 0.625rem;
  height: 0.625rem;
}

.task-page__dot--primary {
  background: var(--color-primary);
}
.task-page__dot--success {
  background: var(--color-success);
}
.task-page__dot--info {
  background: var(--color-info);
}
.task-page__dot--gold,
.task-page__dot--warning {
  background: var(--color-gold);
}
.task-page__dot--danger {
  background: var(--color-danger);
}
.task-page__dot--tertiary {
  background: var(--color-tertiary);
}
.task-page__dot--umber {
  background: var(--color-umber);
}

.task-page__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  white-space: normal;
}

.task-page__side {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.task-page__side-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.task-page__side-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.task-page__side-actions {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.task-page__icon-btn {
  display: inline-flex;
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

.task-page__icon-btn:hover {
  background: var(--color-surface);
}

.task-page__side-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: var(--space-3) 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-page__side-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-text-muted);
}

.task-page__side-lead--primary::before,
.task-page__side-lead--info::before {
  background: var(--color-primary);
}
.task-page__side-lead--gold::before,
.task-page__side-lead--warning::before {
  background: var(--color-gold);
}
.task-page__side-lead--success::before {
  background: var(--color-success);
}
.task-page__side-lead--umber::before,
.task-page__side-lead--danger::before {
  background: var(--color-danger);
}
.task-page__side-lead--tertiary::before {
  background: var(--color-tertiary);
}

.task-page__side-lead-project {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-page__side-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-weight: 400;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.task-page__rows {
  display: flex;
  flex-direction: column;
}

.task-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.task-page__row:last-child {
  box-shadow: none;
}

.task-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.task-page__row-label::after {
  content: ':';
}

.task-page__row-value {
  color: var(--color-text);
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

.task-page__row-value--status {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.375rem;
}

.task-page__row-person {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.task-page__progress {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.task-page__progress-track {
  display: block;
  width: 5rem;
  height: 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  overflow: hidden;
}

.task-page__progress-fill {
  display: block;
  height: 100%;
  background: var(--color-primary);
}

.task-page__form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.task-page__form-actions {
  grid-column: 1 / -1;
  margin-top: var(--space-2);
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.task-page__btn {
  height: 2.375rem;
  padding: 0 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-page__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-kanban {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  padding: var(--space-3) 0 var(--space-2);
  overflow-x: auto;
  overflow-y: hidden;
}

.task-kanban--dragging {
  cursor: grabbing;
}

.task-kanban--fill {
  overflow-x: hidden;
  overflow-y: hidden;
}

.task-kanban--fill .task-kanban__col,
.task-kanban--spread .task-kanban__col {
  flex: 1 1 0;
  width: auto;
  max-width: none;
  min-width: 0;
}

.task-kanban--fill .task-kanban__col-body {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  align-content: start;
  align-items: stretch;
  overflow-x: hidden;
  overflow-y: auto;
}

.task-kanban--fill .task-kanban__card {
  max-width: 100%;
}

.task-kanban--spread {
  overflow-x: hidden;
}

.task-kanban__col {
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
  height: 100%;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--col-well);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 18%, var(--color-border));
}

.task-kanban__col--drop {
  background: color-mix(in srgb, var(--col-well) 82%, var(--color-surface));
  box-shadow:
    inset 0 0 0 2px var(--col-accent),
    0 12px 28px color-mix(in srgb, var(--col-accent) 18%, transparent);
  transform: translateY(-2px);
}

.task-kanban__col--primary {
  --col-accent: var(--color-primary);
  --col-head: var(--color-primary);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-primary-surface);
}
.task-kanban__col--secondary {
  --col-accent: var(--color-secondary);
  --col-head: var(--color-secondary);
  --col-on: var(--color-on-secondary);
  --col-well: var(--color-secondary-surface);
}
.task-kanban__col--tertiary {
  --col-accent: var(--color-tertiary);
  --col-head: var(--color-tertiary);
  --col-on: var(--color-on-tertiary);
  --col-well: var(--color-tertiary-surface);
}
.task-kanban__col--gold {
  --col-accent: var(--color-gold-600);
  --col-head: var(--color-gold-600);
  --col-on: var(--color-on-gold);
  --col-well: var(--color-gold-surface);
}
.task-kanban__col--success {
  --col-accent: var(--color-success);
  --col-head: var(--color-success);
  --col-on: #ffffff;
  --col-well: var(--color-success-tint-bg);
}
.task-kanban__col--danger {
  --col-accent: var(--color-danger);
  --col-head: var(--color-danger);
  --col-on: var(--color-on-primary);
  --col-well: var(--color-danger-tint-bg);
}
.task-kanban__col--warning {
  --col-accent: var(--color-warning);
  --col-head: color-mix(in srgb, var(--color-warning) 82%, var(--color-text));
  --col-on: #ffffff;
  --col-well: var(--color-warning-tint-bg);
}
.task-kanban__col--info {
  --col-accent: var(--color-info);
  --col-head: var(--color-info);
  --col-on: #ffffff;
  --col-well: var(--color-info-tint-bg);
}
.task-kanban__col--violet {
  --col-accent: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --col-head: color-mix(in srgb, var(--color-tertiary) 58%, var(--color-primary));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-tertiary-surface) 65%, var(--color-primary-surface));
}
.task-kanban__col--teal {
  --col-accent: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --col-head: color-mix(in srgb, var(--color-secondary) 62%, var(--color-tertiary));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-secondary-surface) 62%, var(--color-tertiary-surface));
}
.task-kanban__col--rose {
  --col-accent: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --col-head: color-mix(in srgb, var(--color-primary) 55%, var(--color-gold));
  --col-on: #ffffff;
  --col-well: color-mix(in srgb, var(--color-primary-surface) 62%, var(--color-gold-surface));
}
.task-kanban__col--umber {
  --col-accent: var(--color-umber);
  --col-head: var(--color-umber);
  --col-on: var(--color-on-umber);
  --col-well: var(--color-umber-surface);
}
.task-kanban__col--neutral {
  --col-accent: var(--color-text-muted);
  --col-head: var(--color-text-muted);
  --col-on: #ffffff;
  --col-well: var(--color-surface-muted);
}

.task-kanban__col-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.875rem var(--space-4);
  background: var(--col-head);
  color: var(--col-on);
}

.task-kanban__col-head-main {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.task-kanban__col-title {
  min-width: 0;
  overflow: hidden;
  color: var(--col-on);
  font-size: 0.875rem;
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-kanban__col-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.5rem;
  padding: 0 0.5rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--col-on) 18%, transparent);
  color: var(--col-on);
  font-size: 0.75rem;
}

.task-kanban__col-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: var(--space-3);
}

.task-kanban__col-empty,
.task-kanban__hint {
  margin: var(--space-3) auto;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.task-kanban__placeholder {
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--col-accent) 14%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 32%, transparent);
}

.task-kanban__card {
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

.task-kanban__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--col-accent);
}

.task-kanban__card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
}

.task-kanban__card--movable {
  touch-action: none;
  cursor: grab;
}

.task-kanban__card--slot {
  overflow: hidden;
  background: color-mix(in srgb, var(--col-accent) 12%, var(--color-surface-muted));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--col-accent) 26%, transparent);
}

.task-kanban__card--slot > *:not(.task-kanban__card-busy) {
  visibility: hidden;
}

.task-kanban__card--slot::before {
  opacity: 0;
}

.task-kanban__card-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
  padding-right: 1.125rem;
}

.task-kanban__card-code,
.task-kanban__card-type {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-kanban__card-code {
  flex-shrink: 0;
}

.task-kanban__card-type {
  flex: 1 1 auto;
  font-style: italic;
  text-align: right;
}

.task-kanban__card-weight {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-kanban__overdue-dot {
  position: absolute;
  top: var(--space-3);
  right: var(--space-3);
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
}

.task-kanban__card-title {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 400;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.task-kanban__card-labels {
  display: flex;
  flex-wrap: wrap;
  flex-shrink: 0;
  gap: 0.25rem;
  min-width: 0;
}

.task-kanban__importance {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
}

.task-kanban__importance--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}
.task-kanban__importance--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-700);
}
.task-kanban__importance--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.task-kanban__card-desc {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.45;
  overflow-wrap: anywhere;
}

.task-kanban__card-facts {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  margin: 0;
  min-width: 0;
}

.task-kanban__card-fact {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  min-width: 0;
  padding: 0.375rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-kanban__card-fact dt {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-kanban__card-fact dt::after {
  content: ':';
}

.task-kanban__card-fact dd {
  margin: 0;
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-kanban__card-progress {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  gap: 0.375rem;
  min-width: 0;
}

.task-kanban__card-progress-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  min-width: 0;
}

.task-kanban__card-progress-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-kanban__card-progress-label::after {
  content: ':';
}

.task-kanban__card-progress-value {
  flex-shrink: 0;
  color: var(--color-text);
  font-size: 0.75rem;
  font-style: italic;
}

.task-kanban__card-progress .task-page__mini-track {
  width: 100%;
}

.task-kanban__card-foot {
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

.task-kanban__card-avatars {
  display: flex;
  flex-shrink: 0;
  min-width: max-content;
  align-items: center;
  padding: 2px;
  overflow: visible;
}

.task-kanban__card-meta,
.task-kanban__card-stat {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
}

.task-kanban__card-grip {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  color: var(--color-text-muted);
  opacity: 0;
  pointer-events: none;
}

.task-kanban__card--movable:hover .task-kanban__card-grip {
  opacity: 0.5;
}

.task-kanban__card-busy {
  position: absolute;
  top: var(--space-2);
  right: var(--space-2);
  z-index: 1;
  display: inline-flex;
  color: var(--color-primary);
}

.task-kanban__ghost {
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

.task-kanban__ghost::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--col-accent, var(--color-primary));
}

.task-kanban__ghost--primary {
  --col-accent: var(--color-primary);
}
.task-kanban__ghost--gold {
  --col-accent: var(--color-gold-600);
}
.task-kanban__ghost--success {
  --col-accent: var(--color-success);
}
.task-kanban__ghost--danger {
  --col-accent: var(--color-danger);
}
.task-kanban__ghost--info {
  --col-accent: var(--color-info);
}
.task-kanban__ghost--tertiary {
  --col-accent: var(--color-tertiary);
}
.task-kanban__ghost--umber {
  --col-accent: var(--color-umber);
}

.task-kanban__ghost--live {
  box-shadow:
    var(--shadow-lg),
    0 18px 40px color-mix(in srgb, var(--col-accent, var(--color-primary)) 18%, transparent);
}

:global(body.task-kanban-dragging) {
  cursor: grabbing;
}

@media (max-width: 1279px) {
  .task-page__body {
    flex-direction: column;
  }

  .task-page__side {
    width: 100%;
    max-height: 42%;
  }

  .task-page__table-wrap {
    min-height: 16rem;
  }

  .task-page__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .task-page__form {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 768px) {
  .task-page {
    padding: 0 var(--space-4) var(--space-2);
  }

  .task-page__header-search input {
    width: 10rem;
  }
}

@media (max-width: 480px) {
  .task-page {
    padding: 0 var(--space-3) var(--space-2);
  }

  .task-page__header-search input {
    width: 8rem;
  }

  .task-kanban__col {
    width: 13.5rem;
  }

  .task-kanban--fill .task-kanban__col-body {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .task-page__spin {
    animation: none;
  }
}

/* ---------- Xuất/Nhập Excel (PR8) — cùng khuôn ProjectList.vue ---------- */
.task-page__dialog {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.task-page__dialog-panel {
  width: min(54rem, calc(100vw - 2.5rem));
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

.task-page__dialog-panel--import {
  width: min(40rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: calc(100vh - 2.5rem);
}

.task-page__dialog-panel--preview {
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
}

.task-page__dialog-head,
.task-page__dialog-actions {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  background: var(--color-surface);
}

.task-page__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  padding-bottom: var(--space-3);
}

.task-page__dialog-icon {
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

.task-page__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.task-page__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.task-page__dialog-close {
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

.task-page__dialog-close:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.task-page__dialog-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}

.task-page__dialog-body--preview {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  overflow: hidden;
}

.task-page__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.task-page__dialog-btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-page__dialog-btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.task-page__dialog-btn--primary:hover {
  background: var(--color-primary-hover);
}

.task-page__dialog-btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.task-page__dialog-btn--ghost:hover {
  background: var(--color-surface-muted);
}

.task-page__dialog-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.task-page__dialog-field {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  column-gap: 0.875rem;
  row-gap: 0.375rem;
  align-items: start;
  min-width: 0;
}

.task-page__dialog-label {
  padding-top: 0.65rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.3;
}

.task-page__dialog-req {
  color: var(--color-primary);
}

.task-page__import-hint {
  margin: 0 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
}

.task-page__import-template {
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

.task-page__import-template:disabled {
  opacity: 0.6;
  cursor: default;
}

.task-page__import-file {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.task-page__import-result {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-page__import-summary {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.task-page__import-errors {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin: 0;
  padding: 0;
  max-height: 12rem;
  overflow-y: auto;
  list-style: none;
}

.task-page__import-error {
  display: flex;
  align-items: flex-start;
  gap: 0.375rem;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.task-page__import-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  margin-top: 0.375rem;
  border-radius: var(--radius-full);
}

.task-page__import-dot--ok {
  background: var(--color-success);
}

.task-page__import-dot--error {
  background: var(--color-danger);
}

.task-page__preview-table-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-page__preview-table {
  width: 100%;
  min-width: 80rem;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.task-page__preview-th {
  position: sticky;
  top: 0;
  z-index: 1;
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-page__preview-th--check {
  width: 2rem;
}

.task-page__preview-td {
  padding: var(--space-2) var(--space-3);
  vertical-align: top;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-page__preview-td--check {
  text-align: center;
}

.task-page__preview-td--issues {
  min-width: 16rem;
}

.task-page__preview-row--invalid {
  opacity: 0.7;
}

.task-page__preview-issue {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.task-page__preview-issue + .task-page__preview-issue {
  margin-top: 0.125rem;
}

.task-page__preview-td--desc {
  min-width: 12rem;
  max-width: 18rem;
}

.task-page__preview-row:not(.task-page__preview-row--editing) {
  cursor: pointer;
}

.task-page__preview-row--editing {
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
}

.task-page__edit-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
  padding: var(--space-2) 0;
}

.task-page__edit-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-page__edit-field--full {
  grid-column: 1 / -1;
}

.task-page__edit-field input {
  padding: 0.375rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 400;
}

.task-page__edit-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding-top: var(--space-2);
}

.task-page__export-toolbar {
  display: flex;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}

.task-page__export-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
}

.task-page__export-col {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.task-page__export-col:hover {
  background: var(--color-surface-muted);
}

.task-page__export-col--disabled {
  color: var(--color-text-muted);
  cursor: default;
}

.task-page__export-col--disabled:hover {
  background: transparent;
}

.task-dialog-fade-enter-active,
.task-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.task-dialog-fade-enter-from,
.task-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .task-page__dialog-panel,
  .task-page__dialog-panel--import,
  .task-page__dialog-panel--preview {
    width: calc(100vw - 1.5rem);
    height: auto;
    max-height: calc(100vh - 1.5rem);
  }

  .task-page__edit-grid,
  .task-page__export-grid {
    grid-template-columns: 1fr;
  }
}
</style>
