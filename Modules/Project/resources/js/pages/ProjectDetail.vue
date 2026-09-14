<script setup>
//
// Trang Chi tiết dự án — TRANG RIÊNG (route /manager/project/:id), thay cho
// panel trượt "Chi tiết dự án" trước đây trong ProjectList.vue. Bố cục:
// PageHeader (Theo dõi / Thành viên / Thêm testcase; dấu + khi tab Testcase)
// + chrome 1 hàng (nav tab | thao tác theo ngữ cảnh tab) + 2 cột nội dung.
// Tab đồng bộ ?tab= trên URL.
//
// Tab: Chi tiết · Công việc · Thảo luận · Báo cáo (sheet nhân sự × việc,
// cùng kiểu Đánh giá) · Đính kèm · Testcase · Phản hồi.
//
// Thẻ thống kê (Tổng công việc/Quá hạn/Đang thực hiện/Hoàn thành/Chờ thực
// hiện) và "Danh mục công việc" (progress ring) tính từ cây task
// (type=category là "danh mục", type=task là việc thật) — không thêm bảng
// hay cột backend mới. Tab Chi tiết không còn khối Tài chính (chưa có model).
//
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import ProjectDiscussionTab from '../components/ProjectDiscussionTab.vue';
import ProjectDocumentsTab from '../components/ProjectDocumentsTab.vue';
import ProjectTasksTab from '../components/ProjectTasksTab.vue';
import ProjectTestcaseTab from '../components/ProjectTestcaseTab.vue';
import ProjectFeedbackTab from '../components/ProjectFeedbackTab.vue';
import ProjectReportTab from '../components/ProjectReportTab.vue';
import { showClientToast } from '@/lib/clientToast';
import { computeExpectedProgress } from '@/lib/progress';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectQuickActionModals from '../components/ProjectQuickActionModals.vue';

// core: true = tab cốt lõi, luôn hiện. core: false = tab tuỳ chọn, bật/tắt
// riêng theo từng dự án qua project.disabled_tabs (menu "Thao tác" → "Cấu
// hình tab hiển thị", xem ProjectService::OPTIONAL_TABS).
const TABS = [
  { key: 'general', label: 'Chi tiết', icon: 'layers', core: true },
  { key: 'tasks', label: 'Công việc', icon: 'clipboardCheck', core: true },
  { key: 'discussion', label: 'Thảo luận', icon: 'messageCircle', core: false },
  { key: 'report', label: 'Báo cáo', icon: 'fileText', core: false },
  { key: 'attachments', label: 'Đính kèm', icon: 'paperclip', core: false },
  { key: 'test_case', label: 'Testcase', icon: 'listChecks', core: false },
  { key: 'feedback', label: 'Phản hồi', icon: 'star', core: false },
];

const TASK_ADD_VARIANTS = [
  { key: 'normal', label: 'Thêm công việc thường', icon: 'plus' },
  { key: 'bulk', label: 'Thêm nhiều công việc thường', icon: 'layoutList' },
  { key: 'by_category', label: 'Thêm công việc theo danh mục', icon: 'listChecks' },
  { key: 'by_phase', label: 'Thêm công việc theo phase', icon: 'flag' },
];

const RULE_DEFS = [
  {
    key: 'hide_child_tasks_from_followers',
    title: 'Không cho phép người theo dõi xem được các công việc con',
  },
  {
    key: 'hide_cross_tasks_from_assignees',
    title: 'Không cho phép người thực hiện công việc xem chéo các công việc khác',
  },
  {
    key: 'shift_task_dates_with_project',
    title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo',
  },
  {
    key: 'constrain_task_dates_to_project',
    title: 'Thời gian dự kiến thực hiện công việc phải nằm trong khoảng thời gian của dự án',
  },
];

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loading = ref(false);
const notFound = ref(false);
const project = ref(null);
const taskTree = ref([]);
const tasksLoading = ref(false);
const followBusy = ref(false);
const avatarUploading = ref(false);
const avatarInput = ref(null);
const actionDialog = reactive({ kind: null, extra: {} });
const taskMenuOpen = ref(false);
const moreMenuOpen = ref(false);
const exportMenuOpen = ref(false);
const taskMenuRoot = ref(null);
const moreMenuRoot = ref(null);
const exportMenuRoot = ref(null);
const tabsRoot = ref(null);
const documentsTab = ref(null);
const testcaseTab = ref(null);
const feedbackTab = ref(null);
const reportTab = ref(null);
const taskMenuPlacement = reactive({ up: false, right: false });
const moreMenuPlacement = reactive({ up: false, right: true });
const exportMenuPlacement = reactive({ up: false, right: true });

function normalizeTabKey(raw) {
  const key = String(raw || 'general');
  return TABS.some((tab) => tab.key === key) ? key : 'general';
}

const activeTab = ref(normalizeTabKey(route.query.tab));

const options = ref({ type: [], status: [], importance: [], progress_method: [], scope_type: [] });

const projectId = computed(() => Number(route.params.id || 0));
const canEdit = computed(() => Boolean(project.value?.can_edit));
const canManageMembers = computed(() => Boolean(project.value?.can_manage_members));
const canCreateTask = computed(() => auth.can('task.create'));
const canDuplicate = computed(() => auth.can('project.create'));
const canViewReports = computed(
  () => auth.can('report.manage_department') || auth.can('report.view_assigned'),
);

const showTaskActions = computed(
  () => canCreateTask.value && (activeTab.value === 'general' || activeTab.value === 'tasks'),
);
const showDocActions = computed(() => activeTab.value === 'attachments' && canEdit.value);
const showFeedbackAction = computed(() => activeTab.value === 'feedback');
const showReportAction = computed(() => activeTab.value === 'report');
const showContextActions = computed(
  () => showTaskActions.value || showDocActions.value || showFeedbackAction.value || showReportAction.value,
);
const showMoreMenu = computed(() => canEdit.value || canDuplicate.value);

const headerPrimaryAction = computed(() => {
  if (activeTab.value !== 'test_case' || !project.value) return null;
  const items = [];
  if (canEdit.value) {
    items.push({
      key: 'testcase',
      label: 'Thêm testcase',
      icon: 'listChecks',
      description: 'Tạo testcase kiểm thử cho dự án này',
      onSelect: () => testcaseTab.value?.openCreate(),
    });
  }
  if (canManageMembers.value) {
    items.push({
      key: 'members',
      label: 'Thêm thành viên',
      icon: 'userPlus',
      description: 'Thêm người thực hiện hoặc theo dõi dự án',
      onSelect: () => openAction('members'),
    });
  }
  if (canCreateTask.value) {
    items.push({
      key: 'task',
      label: 'Thêm công việc',
      icon: 'clipboardCheck',
      description: 'Tạo công việc mới trong dự án',
      onSelect: () => openAction('task', { variant: 'normal' }),
    });
  }
  if (!items.length) return null;
  return { label: 'Thêm testcase hoặc công việc', icon: 'plus', items };
});

async function loadOptions() {
  try {
    const { data } = await window.axios.get('/api/project/options');
    options.value = {
      type: data.type ?? [],
      status: data.status ?? [],
      importance: data.importance ?? [],
      progress_method: data.progress_method ?? [],
      scope_type: data.scope_type ?? [],
    };
  } catch {
    // Không chặn trang nếu danh mục lỗi — chỉ ảnh hưởng nhãn hiển thị.
  }
}

async function loadProject() {
  if (!projectId.value) return;
  loading.value = true;
  notFound.value = false;
  try {
    const { data } = await window.axios.get(`/api/project/${projectId.value}`);
    project.value = data.project || null;
  } catch (error) {
    project.value = null;
    notFound.value = error?.response?.status === 404;
    if (!notFound.value) {
      showClientToast('error', error?.response?.data?.message || 'Không tải được chi tiết dự án.');
    }
  } finally {
    loading.value = false;
  }
}

async function loadTasks() {
  if (!projectId.value) return;
  tasksLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/project/${projectId.value}/tasks`);
    taskTree.value = data.tasks || [];
  } catch {
    taskTree.value = [];
  } finally {
    tasksLoading.value = false;
  }
}

onMounted(() => {
  loadOptions();
  loadProject();
  loadTasks();
  document.addEventListener('keydown', handleKeydown);
  document.addEventListener('pointerdown', onDocumentPointerDown, true);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown);
  document.removeEventListener('pointerdown', onDocumentPointerDown, true);
});

function handleKeydown(event) {
  if (event.key === 'Escape') {
    if (taskMenuOpen.value || moreMenuOpen.value || exportMenuOpen.value) {
      taskMenuOpen.value = false;
      moreMenuOpen.value = false;
      exportMenuOpen.value = false;
    }
  }
}

function onDocumentPointerDown(event) {
  if (taskMenuOpen.value && taskMenuRoot.value && !taskMenuRoot.value.contains(event.target)) {
    taskMenuOpen.value = false;
  }
  if (moreMenuOpen.value && moreMenuRoot.value && !moreMenuRoot.value.contains(event.target)) {
    moreMenuOpen.value = false;
  }
  if (exportMenuOpen.value && exportMenuRoot.value && !exportMenuRoot.value.contains(event.target)) {
    exportMenuOpen.value = false;
  }
}

function openAction(kind, extra = {}) {
  taskMenuOpen.value = false;
  moreMenuOpen.value = false;
  exportMenuOpen.value = false;
  actionDialog.kind = kind;
  actionDialog.extra = extra;
}

function closeActionDialog() {
  actionDialog.kind = null;
  actionDialog.extra = {};
}

async function onActionUpdated(updated) {
  if (updated?.id) project.value = updated;
  closeActionDialog();
  // Thời gian / thành viên có thể ảnh hưởng badge tab và cây việc.
  await loadTasks();
}

async function onTasksChanged() {
  closeActionDialog();
  await loadTasks();
  // Làm mới project để badge/thống kê phụ thuộc payload (nếu có) đồng bộ.
  await loadProject();
}

async function onProjectDuplicated(created) {
  closeActionDialog();
  if (created?.id) {
    showClientToast('success', `Đã nhân bản thành "${created.name}".`);
    router.push({ name: 'manager.project.detail', params: { id: created.id } });
  }
}

function placeMenu(rootEl, placement, preferRight = false) {
  placement.up = false;
  placement.right = preferRight;
  if (!rootEl) return;
  nextTick(() => {
    const menu = rootEl.querySelector('.pd__menu');
    if (!menu) return;
    const rect = rootEl.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    placement.up = spaceBelow < menuRect.height + 8 && spaceAbove > spaceBelow;
    const wouldOverflowRight = rect.left + menuRect.width > window.innerWidth - 8;
    const wouldOverflowLeft = rect.right - menuRect.width < 8;
    if (preferRight) {
      placement.right = !wouldOverflowLeft || wouldOverflowRight;
    } else {
      placement.right = wouldOverflowRight && !wouldOverflowLeft;
    }
  });
}

function toggleTaskMenu() {
  moreMenuOpen.value = false;
  taskMenuOpen.value = !taskMenuOpen.value;
  if (taskMenuOpen.value) placeMenu(taskMenuRoot.value, taskMenuPlacement, false);
}

function toggleMoreMenu() {
  taskMenuOpen.value = false;
  exportMenuOpen.value = false;
  moreMenuOpen.value = !moreMenuOpen.value;
  if (moreMenuOpen.value) placeMenu(moreMenuRoot.value, moreMenuPlacement, true);
}

function toggleExportMenu() {
  taskMenuOpen.value = false;
  moreMenuOpen.value = false;
  exportMenuOpen.value = !exportMenuOpen.value;
  if (exportMenuOpen.value) placeMenu(exportMenuRoot.value, exportMenuPlacement, true);
}

async function runExport(kind) {
  exportMenuOpen.value = false;
  if (!reportTab.value) return;
  if (kind === 'csv') reportTab.value.exportCsv();
  else if (kind === 'xlsx') await reportTab.value.exportXlsx();
  else if (kind === 'pdf') await reportTab.value.exportPdf();
}

function goBack() {
  router.push({ name: 'manager.project.index' });
}

function goEdit() {
  if (!project.value) return;
  router.push({ name: 'manager.project.edit', params: { id: project.value.id } });
}

async function deleteProject() {
  if (!project.value) return;
  if (!window.confirm(`Xoá dự án "${project.value.name}"? Thao tác này không thể hoàn tác.`)) return;
  try {
    await window.axios.delete(`/api/project/${project.value.id}`);
    showClientToast('success', 'Đã xoá dự án.');
    router.push({ name: 'manager.project.index' });
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không xoá được dự án.');
  }
}

async function toggleFollow() {
  if (!project.value || followBusy.value) return;
  followBusy.value = true;
  try {
    if (project.value.is_following) {
      const { data } = await window.axios.delete(`/api/project/${project.value.id}/follow`);
      project.value.is_following = data.is_following;
      showClientToast('success', 'Đã bỏ theo dõi dự án.');
    } else {
      const { data } = await window.axios.post(`/api/project/${project.value.id}/follow`);
      project.value.is_following = data.is_following;
      showClientToast('success', 'Đã theo dõi dự án.');
    }
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không cập nhật được theo dõi dự án.');
  } finally {
    followBusy.value = false;
  }
}

// ---------- Ảnh đại diện / thư viện / tệp đính kèm (y hệt panel cũ) ----------

function triggerAvatarInput() {
  avatarInput.value?.click();
}

async function onAvatarChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !project.value) return;

  avatarUploading.value = true;
  const fd = new FormData();
  fd.append('avatar', file);
  try {
    const { data } = await window.axios.post(`/api/project/${project.value.id}/avatar`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    project.value = data.project;
    showClientToast('success', 'Đã cập nhật ảnh đại diện.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải lên được ảnh đại diện.');
  } finally {
    avatarUploading.value = false;
  }
}

async function onDocumentsChanged() {
  if (!project.value?.id) return;
  try {
    const { data } = await window.axios.get(`/api/project/${project.value.id}`);
    if (data.project) project.value.attachments = data.project.attachments || [];
  } catch {
    // Badge tab Đính kèm sẽ cập nhật lần tải trang sau.
  }
}

// ---------- Nhãn hiển thị (từ danh mục /api/project/options) ----------

function typeLabel(value) {
  return options.value.type.find((o) => o.value === value)?.label || value || '—';
}

function statusLabel(value) {
  return options.value.status.find((o) => o.value === value)?.label || value || '—';
}

function importanceLabel(value) {
  return options.value.importance.find((o) => o.value === value)?.label || value || '—';
}

function progressMethodLabel(value) {
  return options.value.progress_method.find((o) => o.value === value)?.label || value || '—';
}

function statusTone(value) {
  if (value === 'completed') return 'success';
  if (value === 'cancelled') return 'umber';
  if (value === 'on_hold') return 'gold';
  if (value === 'in_progress') return 'primary';
  if (value === 'planning') return 'tertiary';
  return 'neutral';
}

function statusChipTone(value) {
  if (value === 'in_progress') return 'info';
  return statusTone(value);
}

function importanceTone(value) {
  if (value === 'strategic' || value === 'critical') return 'danger';
  if (value === 'high_priority' || value === 'high') return 'gold';
  if (value === 'important' || value === 'medium') return 'tertiary';
  if (value === 'assist') return 'info';
  return 'neutral';
}

function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('vi-VN');
}

function formatRange(start, end, days) {
  const startLabel = formatDate(start);
  const endLabel = formatDate(end);
  if (startLabel === '—' && endLabel === '—') return '—';
  const dayPart = days ? ` (${days} ngày)` : '';
  return `${startLabel} - ${endLabel}${dayPart}`;
}

function taskSpanDays(task) {
  if (!task?.start_date || !task?.end_date) return 0;
  const start = new Date(task.start_date).getTime();
  const end = new Date(task.end_date).getTime();
  if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) return 0;
  return Math.round((end - start) / 86400000) + 1;
}

// Phòng ban thực hiện đầy đủ — gộp executing_department + department trong
// scopes kiểu "Phòng Ban", khử trùng theo id (giống ProjectList.vue).
function executingDepartments(proj) {
  const list = [];
  const seen = new Set();
  const add = (dept) => {
    if (!dept || seen.has(dept.id)) return;
    seen.add(dept.id);
    list.push(dept);
  };
  for (const dept of proj.executing_departments || []) add(dept);
  add(proj.executing_department);
  for (const scope of proj.scopes || []) {
    if (scope.scope_type === 'department') add(scope.department);
  }
  return list;
}

// ---------- Cây công việc → thẻ thống kê + danh mục công việc ----------

// Duyệt phẳng toàn bộ cây (kể cả node con) — dùng cho thẻ thống kê & tab
// Công việc (chỉ đếm node type=task, "category"/"phase" là nhóm, không phải
// việc thật — khớp TASK_TYPE_LABELS trong constants/task.js).
function flattenTasks(nodes) {
  const out = [];
  const walk = (list) => {
    for (const node of list || []) {
      out.push(node);
      if (node.children?.length) walk(node.children);
    }
  };
  walk(nodes);
  return out;
}

const flatTasks = computed(() => flattenTasks(taskTree.value));
const realTasks = computed(() => flatTasks.value.filter((t) => t.type === 'task'));
const commentsCount = ref(0);
const testCaseCount = ref(0);
const feedbackCount = ref(0);

// Tab tuỳ chọn bị tắt riêng cho dự án này (menu "Thao tác" → "Cấu hình tab
// hiển thị") — Chi tiết/Công việc (core) luôn hiện, không phụ thuộc cờ này.
const visibleTabs = computed(() => {
  const disabled = new Set(project.value?.disabled_tabs || []);
  return TABS.filter((tab) => tab.core || !disabled.has(tab.key));
});

function tabBadge(key) {
  let count = null;
  if (key === 'tasks') count = realTasks.value.length;
  else if (key === 'discussion') count = commentsCount.value;
  else if (key === 'attachments') count = (project.value?.attachments || []).length;
  else if (key === 'test_case') count = testCaseCount.value;
  else if (key === 'feedback') count = feedbackCount.value;
  return count > 0 ? count : null;
}

const taskStats = computed(() => {
  const list = realTasks.value;
  return {
    total: list.length,
    overdue: list.filter((t) => t.is_overdue).length,
    in_progress: list.filter((t) => t.status === 'in_progress').length,
    completed: list.filter((t) => t.status === 'completed').length,
    not_started: list.filter((t) => t.status === 'not_started').length,
  };
});

const STAT_CARDS = [
  { key: 'all', tone: 'gold', icon: 'clipboardCheck', label: 'Tổng công việc', stat: 'total' },
  { key: 'overdue', tone: 'danger', icon: 'clock', label: 'Quá hạn', stat: 'overdue' },
  { key: 'in_progress', tone: 'info', icon: 'flag', label: 'Đang thực hiện', stat: 'in_progress' },
  { key: 'completed', tone: 'success', icon: 'check', label: 'Hoàn thành', stat: 'completed' },
  { key: 'not_started', tone: 'warning', icon: 'listChecks', label: 'Chờ thực hiện', stat: 'not_started' },
];

const taskStatFilter = ref('all');

function setActiveTab(key, { syncRoute = true } = {}) {
  const allowed = visibleTabs.value.some((tab) => tab.key === key) ? key : 'general';
  activeTab.value = allowed;
  taskMenuOpen.value = false;
  moreMenuOpen.value = false;
  if (!syncRoute) return;
  const current = String(route.query.tab || '');
  const next = allowed === 'general' ? '' : allowed;
  if (current === next) return;
  const query = { ...route.query };
  if (next) query.tab = next;
  else delete query.tab;
  router.replace({ query });
}

function onTabClick(key) {
  if (key === 'tasks') taskStatFilter.value = 'all';
  setActiveTab(key);
}

function openTaskStats(filter) {
  taskStatFilter.value = filter;
  setActiveTab('tasks');
}

function onTabListKeydown(event) {
  const keys = visibleTabs.value.map((tab) => tab.key);
  const idx = keys.indexOf(activeTab.value);
  if (idx < 0) return;
  let nextIdx = idx;
  if (event.key === 'ArrowRight') nextIdx = (idx + 1) % keys.length;
  else if (event.key === 'ArrowLeft') nextIdx = (idx - 1 + keys.length) % keys.length;
  else if (event.key === 'Home') nextIdx = 0;
  else if (event.key === 'End') nextIdx = keys.length - 1;
  else return;
  event.preventDefault();
  const next = keys[nextIdx];
  if (next === 'tasks') taskStatFilter.value = 'all';
  setActiveTab(next);
  nextTick(() => {
    tabsRoot.value?.querySelector(`[data-tab="${next}"]`)?.focus();
  });
}

function goReports() {
  router.push({ name: 'manager.reports.list' });
}

watch(
  () => route.query.tab,
  (tab) => {
    const key = normalizeTabKey(tab);
    if (key !== activeTab.value) setActiveTab(key, { syncRoute: false });
  },
);

watch(visibleTabs, (tabs) => {
  if (!tabs.some((tab) => tab.key === activeTab.value)) {
    setActiveTab('general');
  }
});

const activeStatCard = computed(() => STAT_CARDS.find((card) => card.key === taskStatFilter.value) || STAT_CARDS[0]);

// "Danh mục công việc" ở sidebar — mỗi node type=category ở cấp gốc là 1
// danh mục, tiến độ = bình quân % các việc thật (type=task) bên trong.
const taskCategories = computed(() => (taskTree.value || [])
  .filter((node) => node.type === 'category')
  .map((cat) => {
    const inner = flattenTasks(cat.children).filter((t) => t.type === 'task');
    const percent = inner.length
      ? Math.round(inner.reduce((sum, t) => sum + (t.progress_percent ?? 0), 0) / inner.length)
      : 0;
    return { id: cat.id, title: cat.title, count: inner.length, percent };
  }));

function ringStyle(percent) {
  const clamped = Math.max(0, Math.min(100, percent));
  return {
    background: `conic-gradient(var(--color-secondary) ${clamped * 3.6}deg, var(--color-surface-muted) 0deg)`,
  };
}

const plannedTimeLabel = computed(() => {
  if (!project.value) return '—';
  return formatRange(project.value.start_date, project.value.end_date, project.value.duration_days);
});

const actualTimeLabel = computed(() => {
  if (!project.value) return '—';
  return formatRange(
    project.value.actual_start_date,
    project.value.actual_end_date,
    project.value.actual_duration_days,
  );
});

const projectProgress = computed(() => {
  if (project.value?.progress_percent != null) return Number(project.value.progress_percent);
  const list = realTasks.value;
  if (!list.length) return 0;
  const pct = (task) => Number(task.progress_percent) || 0;
  const method = project.value?.progress_method;
  if (method === 'duration_weighted') {
    let weighted = 0;
    let total = 0;
    for (const task of list) {
      const days = taskSpanDays(task);
      weighted += pct(task) * days;
      total += days;
    }
    if (total > 0) return Math.round(weighted / total);
  }
  if (method === 'task_weighted') {
    let weighted = 0;
    let total = 0;
    for (const task of list) {
      const weight = Number(task.weight) || 0;
      weighted += pct(task) * weight;
      total += weight;
    }
    if (total > 0) return Math.round(weighted / total);
  }
  return Math.round(list.reduce((sum, task) => sum + pct(task), 0) / list.length);
});

const permissionLines = computed(() => RULE_DEFS.map((rule) => ({
  key: rule.key,
  title: rule.title,
  on: Boolean(project.value?.[rule.key]),
})));

function personSecondary(person) {
  if (!person) return '—';
  const parts = [person.email, person.department?.name].filter(Boolean);
  return parts.join(' · ') || '—';
}

function performerStat(person) {
  if (!person?.task_count) return 'Chưa có việc';
  const parts = [`${person.task_count} việc`];
  if (person.completed) parts.push(`${person.completed} xong`);
  return parts.join(' · ');
}

// Thành viên dự án + người đang được giao việc thật — khử trùng theo id,
// kèm số việc / hoàn thành / quá hạn lấy từ cây task đã tải.
const performers = computed(() => {
  const map = new Map();
  const add = (person) => {
    if (!person?.id || map.has(person.id)) return;
    map.set(person.id, {
      ...person,
      task_count: 0,
      completed: 0,
      overdue: 0,
    });
  };
  for (const member of project.value?.members || []) add(member);
  for (const task of realTasks.value) {
    if (task.assignee) add(task.assignee);
  }
  for (const task of realTasks.value) {
    const id = task.assignee?.id;
    if (!id || !map.has(id)) continue;
    const row = map.get(id);
    row.task_count += 1;
    if (task.status === 'completed') row.completed += 1;
    if (task.is_overdue) row.overdue += 1;
  }
  return [...map.values()].sort((a, b) => {
    if (b.task_count !== a.task_count) return b.task_count - a.task_count;
    return String(a.name || '').localeCompare(String(b.name || ''), 'vi');
  });
});
</script>

<template>
  <section class="pd" :class="{ 'pd--fill': project && (activeTab === 'tasks' || activeTab === 'report') }">
    <PageHeader
      :title="project?.name || 'Chi tiết dự án'"
      icon="layers"
      :primary-action="headerPrimaryAction"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Dự án', to: { name: 'manager.project.index' } },
        { label: project?.name || 'Chi tiết' },
      ]"
    >
      <template #actions>
        <template v-if="project">
          <button
            type="button"
            class="pd__header-btn"
            :class="{ 'pd__header-btn--on': project.is_following }"
            :disabled="followBusy"
            :aria-pressed="project.is_following ? 'true' : 'false'"
            @click="toggleFollow"
          >
            <AppIcon name="bell" :size="16" :stroke-width="1.75" />
            {{ project.is_following ? 'Đang theo dõi' : 'Theo dõi' }}
          </button>
        </template>
        <button type="button" class="pd__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Quay lại
        </button>
      </template>
    </PageHeader>

    <p v-if="loading" class="pd__empty">Đang tải chi tiết dự án…</p>
    <p v-else-if="notFound" class="pd__empty">Không tìm thấy dự án.</p>

    <div v-else-if="project" class="pd__page">
      <div class="pd__chrome">
        <div
          ref="tabsRoot"
          class="pd__nav hide-scrollbar"
          role="tablist"
          aria-label="Nhóm thông tin dự án"
          @keydown="onTabListKeydown"
        >
          <button
            v-for="tab in visibleTabs"
            :key="tab.key"
            type="button"
            role="tab"
            class="pd__tab"
            :class="{ 'pd__tab--active': activeTab === tab.key }"
            :data-tab="tab.key"
            :aria-selected="activeTab === tab.key"
            :tabindex="activeTab === tab.key ? 0 : -1"
            aria-controls="pd-tab-panel"
            @click="onTabClick(tab.key)"
          >
            <span class="pd__tab-icon" aria-hidden="true">
              <AppIcon :name="tab.icon" :size="14" :stroke-width="1.75" />
            </span>
            {{ tab.label }}
            <span v-if="tabBadge(tab.key) != null" class="pd__tab-count">{{ tabBadge(tab.key) }}</span>
          </button>
        </div>

        <div v-if="showContextActions || showMoreMenu" class="pd__toolbar" role="toolbar" aria-label="Thao tác dự án">
          <div class="pd__actions">
            <div v-if="showTaskActions" class="pd__action-group" aria-label="Công việc">
              <button type="button" class="pd__action" @click="openAction('category')">
                <span class="pd__action-icon"><AppIcon name="listChecks" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Danh mục</span>
              </button>
              <div ref="taskMenuRoot" class="pd__action-wrap">
                <button
                  type="button"
                  class="pd__action pd__action--primary"
                  :class="{ 'pd__action--open': taskMenuOpen }"
                  :aria-expanded="taskMenuOpen ? 'true' : 'false'"
                  aria-haspopup="menu"
                  @click="toggleTaskMenu"
                >
                  <span class="pd__action-icon"><AppIcon name="plus" :size="15" :stroke-width="1.75" /></span>
                  <span class="pd__action-label">Thêm việc</span>
                  <AppIcon name="chevronDown" :size="12" />
                </button>
                <div
                  v-if="taskMenuOpen"
                  class="pd__menu"
                  :class="{ 'pd__menu--up': taskMenuPlacement.up, 'pd__menu--right': taskMenuPlacement.right }"
                  role="menu"
                  aria-label="Thêm công việc"
                >
                  <button
                    v-for="item in TASK_ADD_VARIANTS"
                    :key="item.key"
                    type="button"
                    class="pd__menu-item"
                    role="menuitem"
                    @click="openAction('task', { variant: item.key })"
                  >
                    <AppIcon :name="item.icon" :size="14" :stroke-width="1.75" />
                    {{ item.label }}
                  </button>
                </div>
              </div>
              <button type="button" class="pd__action" @click="openAction('phase')">
                <span class="pd__action-icon"><AppIcon name="flag" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Thêm phase</span>
              </button>
            </div>

            <div v-else-if="showDocActions" class="pd__action-group" aria-label="Tài liệu">
              <button type="button" class="pd__action" @click="documentsTab?.openCreateFolder()">
                <span class="pd__action-icon"><AppIcon name="folderPlus" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Tạo thư mục</span>
              </button>
              <button type="button" class="pd__action" @click="documentsTab?.openAddLink()">
                <span class="pd__action-icon"><AppIcon name="link" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Thêm link</span>
              </button>
              <button
                type="button"
                class="pd__action pd__action--primary"
                :disabled="documentsTab?.uploading"
                @click="documentsTab?.triggerUpload()"
              >
                <span class="pd__action-icon"><AppIcon name="fileUp" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">{{ documentsTab?.uploading ? 'Đang tải…' : 'Tải tệp' }}</span>
              </button>
            </div>

            <div v-else-if="showFeedbackAction" class="pd__action-group" aria-label="Phản hồi">
              <button type="button" class="pd__action pd__action--primary" @click="feedbackTab?.focusComposer()">
                <span class="pd__action-icon"><AppIcon name="star" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Viết phản hồi</span>
              </button>
            </div>

            <div v-else-if="showReportAction" class="pd__action-group" aria-label="Báo cáo">
              <button
                v-if="canViewReports"
                type="button"
                class="pd__action"
                @click="goReports"
              >
                <span class="pd__action-icon"><AppIcon name="fileText" :size="15" :stroke-width="1.75" /></span>
                <span class="pd__action-label">Báo cáo phòng ban</span>
              </button>
              <div ref="exportMenuRoot" class="pd__action-wrap">
                <button
                  type="button"
                  class="pd__action pd__action--primary"
                  :class="{ 'pd__action--open': exportMenuOpen }"
                  :disabled="!reportTab?.hasData || reportTab?.xlsxBusy || reportTab?.pdfBusy"
                  aria-haspopup="menu"
                  :aria-expanded="exportMenuOpen ? 'true' : 'false'"
                  @click="toggleExportMenu"
                >
                  <span class="pd__action-icon"><AppIcon name="fileDown" :size="15" :stroke-width="1.75" /></span>
                  <span class="pd__action-label">
                    {{ reportTab?.xlsxBusy || reportTab?.pdfBusy ? 'Đang xuất…' : 'Xuất báo cáo' }}
                  </span>
                  <AppIcon name="chevronDown" :size="14" :stroke-width="2" />
                </button>
                <div
                  v-if="exportMenuOpen"
                  class="pd__menu pd__menu--export"
                  :class="{ 'pd__menu--up': exportMenuPlacement.up, 'pd__menu--right': exportMenuPlacement.right }"
                  role="menu"
                  aria-label="Định dạng xuất báo cáo"
                >
                  <button type="button" class="pd__menu-item" role="menuitem" @click="runExport('csv')">
                    <AppIcon name="fileText" :size="14" :stroke-width="1.75" />
                    <span class="pd__menu-item-copy">
                      <span class="pd__menu-item-title">CSV</span>
                      <span class="pd__menu-item-sub">Mở bằng Excel, Google Sheets</span>
                    </span>
                  </button>
                  <button type="button" class="pd__menu-item" role="menuitem" @click="runExport('xlsx')">
                    <AppIcon name="fileSpreadsheet" :size="14" :stroke-width="1.75" />
                    <span class="pd__menu-item-copy">
                      <span class="pd__menu-item-title">Excel (.xlsx)</span>
                      <span class="pd__menu-item-sub">Có định dạng cột, nhóm tiêu đề</span>
                    </span>
                  </button>
                  <button type="button" class="pd__menu-item" role="menuitem" @click="runExport('pdf')">
                    <AppIcon name="fileDown" :size="14" :stroke-width="1.75" />
                    <span class="pd__menu-item-copy">
                      <span class="pd__menu-item-title">PDF</span>
                      <span class="pd__menu-item-sub">Bảng in sẵn, chia trang tự động</span>
                    </span>
                  </button>
                </div>
              </div>
            </div>

            <span v-if="showContextActions && showMoreMenu" class="pd__action-split" aria-hidden="true" />

            <div v-if="showMoreMenu" ref="moreMenuRoot" class="pd__action-wrap">
              <button
                type="button"
                class="pd__action pd__action--icon"
                :class="{ 'pd__action--open': moreMenuOpen }"
                aria-label="Thêm thao tác"
                :aria-expanded="moreMenuOpen ? 'true' : 'false'"
                aria-haspopup="menu"
                @click="toggleMoreMenu"
              >
                <span class="pd__action-icon"><AppIcon name="moreVertical" :size="15" :stroke-width="1.75" /></span>
              </button>
              <div
                v-if="moreMenuOpen"
                class="pd__menu"
                :class="{ 'pd__menu--up': moreMenuPlacement.up, 'pd__menu--right': moreMenuPlacement.right }"
                role="menu"
                aria-label="Thao tác dự án"
              >
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="openAction('dates', { focus: 'planned' })"
                >
                  <AppIcon name="calendar" :size="14" :stroke-width="1.75" />
                  Cập nhật thời gian
                </button>
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="openAction('description')"
                >
                  <AppIcon name="fileText" :size="14" :stroke-width="1.75" />
                  Cập nhật mô tả
                </button>
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="openAction('baseline')"
                >
                  <AppIcon name="flag" :size="14" :stroke-width="1.75" />
                  Chốt baseline
                </button>
                <span v-if="canEdit" class="pd__menu-sep" />
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="openAction('tabs_config')"
                >
                  <AppIcon name="sliders" :size="14" :stroke-width="1.75" />
                  Cấu hình tab hiển thị
                </button>
                <button
                  v-if="canDuplicate"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="openAction('duplicate')"
                >
                  <AppIcon name="copy" :size="14" :stroke-width="1.75" />
                  Nhân bản dự án
                </button>
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item"
                  role="menuitem"
                  @click="goEdit(); moreMenuOpen = false"
                >
                  <AppIcon name="pencil" :size="14" :stroke-width="1.75" />
                  Sửa
                </button>
                <span v-if="canEdit" class="pd__menu-sep" />
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__menu-item pd__menu-item--danger"
                  role="menuitem"
                  @click="deleteProject(); moreMenuOpen = false"
                >
                  <AppIcon name="trash" :size="14" :stroke-width="1.75" />
                  Xoá
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="activeTab === 'general'"
        class="pd__stats"
      >
        <button
          v-for="card in STAT_CARDS"
          :key="card.key"
          type="button"
          class="pd__stat"
          :class="`pd__stat--${card.tone}`"
          @click="openTaskStats(card.key)"
        >
          <span class="pd__stat-icon">
            <AppIcon :name="card.icon" :size="18" :stroke-width="1.75" />
          </span>
          <span class="pd__stat-copy">
            <span class="pd__stat-value">{{ taskStats[card.stat] }}</span>
            <span class="pd__stat-label">{{ card.label }}</span>
          </span>
        </button>
      </div>

      <div
        class="pd__layout"
        id="pd-tab-panel"
        role="tabpanel"
        :class="{
          'pd__layout--docs': activeTab === 'attachments' || activeTab === 'discussion' || activeTab === 'tasks' || activeTab === 'test_case' || activeTab === 'feedback' || activeTab === 'report',
          'pd__layout--general': activeTab === 'general',
        }"
      >
        <div class="pd__col">
          <!-- Tab: Chi tiết — cột trái: thông tin + quyền + phạm vi -->
          <template v-if="activeTab === 'general'">
            <section class="pd__card">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--primary"><AppIcon name="layers" :size="14" /></span>
                <h3 class="pd__section-title">Thông tin chung</h3>
                <button
                  v-if="canEdit"
                  type="button"
                  class="pd__btn pd__btn--ghost"
                  :disabled="avatarUploading"
                  @click="triggerAvatarInput"
                >
                  {{ avatarUploading ? 'Đang tải…' : 'Đổi ảnh đại diện' }}
                </button>
                <input ref="avatarInput" type="file" accept="image/*" class="pd__hidden-input" @change="onAvatarChange" />
              </header>

              <dl class="pd__kv">
                <div class="pd__kv-row">
                  <dt class="pd__label">Mã dự án</dt>
                  <dd class="pd__value">{{ project.code || '—' }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Tên dự án</dt>
                  <dd class="pd__value pd__value--stack">
                    <span class="pd__name">
                      <span v-if="project.avatar_url" class="pd__name-avatar" @click="canEdit && triggerAvatarInput()">
                        <img :src="project.avatar_url" alt="" />
                      </span>
                      {{ project.name || '—' }}
                    </span>
                    <span v-if="(project.labels || []).length" class="pd__chip-list">
                      <span
                        v-for="label in project.labels"
                        :key="label.id"
                        class="pd__label-chip"
                        :class="`pd__label-chip--${label.color}`"
                      >
                        {{ label.name }}
                      </span>
                    </span>
                  </dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Trạng thái</dt>
                  <dd class="pd__value">
                    <span class="pd__chip" :class="`pd__chip--${statusChipTone(project.status)}`">
                      {{ statusLabel(project.status) }}
                    </span>
                  </dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Tiến độ</dt>
                  <dd class="pd__value pd__value--progress">
                    <div class="pd__progress">
                      <DualProgressBar
                        :actual="projectProgress"
                        :expected="computeExpectedProgress(project.start_date, project.end_date)"
                        size="md"
                      />
                    </div>
                  </dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Thời gian dự kiến</dt>
                  <dd class="pd__value">{{ plannedTimeLabel }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Thời gian thực tế</dt>
                  <dd class="pd__value">{{ actualTimeLabel }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Loại dự án</dt>
                  <dd class="pd__value">{{ typeLabel(project.type) }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Mức độ quan trọng</dt>
                  <dd class="pd__value">
                    <span class="pd__chip" :class="`pd__chip--${importanceTone(project.importance)}`">
                      {{ importanceLabel(project.importance) }}
                    </span>
                  </dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Phòng ban sở hữu</dt>
                  <dd class="pd__value">{{ project.owner_department?.name || '—' }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Phòng ban thực hiện</dt>
                  <dd class="pd__value">
                    {{ executingDepartments(project).length
                      ? executingDepartments(project).map((d) => d.name).join(', ')
                      : '—' }}
                  </dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Phòng ban phụ trách</dt>
                  <dd class="pd__value">{{ project.lead_department?.name || project.lead?.department?.name || '—' }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Cách tính tiến độ</dt>
                  <dd class="pd__value">{{ progressMethodLabel(project.progress_method) }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Điểm đánh giá</dt>
                  <dd class="pd__value">{{ project.evaluation_score != null ? project.evaluation_score : '—' }}</dd>
                </div>
                <div class="pd__kv-row">
                  <dt class="pd__label">Người tạo</dt>
                  <dd class="pd__value">
                    <span v-if="project.creator" class="pd__user-chip">
                      <UserAvatarTip :user="project.creator" label="Người tạo" />
                      {{ project.creator.name }}
                    </span>
                    <template v-else>—</template>
                  </dd>
                </div>
                <div class="pd__kv-row pd__kv-row--span">
                  <dt class="pd__label">Mô tả</dt>
                  <dd class="pd__value">{{ project.description || '—' }}</dd>
                </div>
              </dl>
            </section>

            <section class="pd__card">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--secondary"><AppIcon name="shield" :size="14" /></span>
                <h3 class="pd__section-title">Quyền dự án</h3>
              </header>
              <ul class="pd__rules">
                <li
                  v-for="line in permissionLines"
                  :key="line.key"
                  :class="{ 'pd__rules-item--off': !line.on }"
                >
                  <AppIcon
                    :name="line.on ? 'check' : 'minus'"
                    :size="14"
                    class="pd__rule-icon"
                    :class="{ 'pd__rule-icon--off': !line.on }"
                  />
                  <span>{{ line.title }}</span>
                </li>
              </ul>
            </section>

            <section v-if="(project.scopes || []).length" class="pd__card">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--info"><AppIcon name="globe" :size="14" /></span>
                <h3 class="pd__section-title">Phạm vi triển khai</h3>
              </header>
              <div class="pd__rows">
                <div v-for="scope in project.scopes" :key="scope.id" class="pd__row">
                  <span class="pd__row-label">
                    {{ scope.scope_type === 'department' ? scope.department?.name || 'Phòng ban' : (options.scope_type.find((o) => o.value === scope.scope_type)?.label || scope.scope_type) }}
                  </span>
                  <span class="pd__row-value">{{ scope.weight_percent }}%</span>
                </div>
              </div>
            </section>
          </template>

          <!-- Tab: Công việc (danh sách / cha / Kanban / Gantt) -->
          <template v-else-if="activeTab === 'tasks'">
            <section class="pd__card pd__card--gantt">
              <ProjectTasksTab
                :tree="taskTree"
                :loading="tasksLoading"
                :project="project"
                :filter="taskStatFilter"
                :filter-label="activeStatCard.label"
                :can-edit="canCreateTask"
              />
            </section>
          </template>

          <!-- Tab: Thảo luận -->
          <template v-else-if="activeTab === 'discussion'">
            <ProjectDiscussionTab
              :project="project"
              :can-create="true"
              @count-changed="commentsCount = $event"
            />
          </template>

          <!-- Tab: Báo cáo — sheet nhân sự × việc, kín khung như Đánh giá -->
          <template v-else-if="activeTab === 'report'">
            <section class="pd__card pd__card--gantt">
              <ProjectReportTab
                ref="reportTab"
                :project="project"
                :tree="taskTree"
                :loading="tasksLoading"
              />
            </section>
          </template>

          <!-- Tab: Đính kèm -->
          <template v-else-if="activeTab === 'attachments'">
            <ProjectDocumentsTab ref="documentsTab" :project="project" :can-edit="canEdit" @changed="onDocumentsChanged" />
          </template>

          <!-- Tab: Testcase -->
          <template v-else-if="activeTab === 'test_case'">
            <ProjectTestcaseTab ref="testcaseTab" :project="project" :can-manage="canEdit" @count-changed="testCaseCount = $event" />
          </template>

          <!-- Tab: Phản hồi -->
          <template v-else-if="activeTab === 'feedback'">
            <ProjectFeedbackTab ref="feedbackTab" :project="project" @count-changed="feedbackCount = $event" />
          </template>

        </div>

        <div
          v-if="activeTab !== 'attachments' && activeTab !== 'discussion' && activeTab !== 'tasks' && activeTab !== 'test_case' && activeTab !== 'feedback' && activeTab !== 'report'"
          class="pd__col pd__col--side"
        >
          <template v-if="activeTab === 'general'">
            <section class="pd__card pd__card--lead">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--primary"><AppIcon name="user" :size="14" /></span>
                <h3 class="pd__section-title">Người quản trị</h3>
              </header>
              <article v-if="project.lead" class="pd__person">
                <UserAvatarTip :user="project.lead" label="Người quản trị" />
                <div class="pd__person-copy">
                  <span class="pd__person-name">{{ project.lead.name }}</span>
                  <span class="pd__person-meta">{{ personSecondary(project.lead) }}</span>
                </div>
              </article>
              <p v-else class="pd__empty-inline">Chưa chỉ định người quản trị.</p>
            </section>

            <section class="pd__card">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--info"><AppIcon name="users" :size="14" /></span>
                <h3 class="pd__section-title">Người thực hiện</h3>
                <span class="pd__section-count">{{ performers.length }}</span>
                <button
                  v-if="canManageMembers"
                  type="button"
                  class="pd__btn pd__btn--ghost"
                  @click="openAction('members')"
                >
                  Cập nhật
                </button>
              </header>
              <div v-if="performers.length" class="pd__person-list hide-scrollbar">
                <article v-for="person in performers" :key="person.id" class="pd__person">
                  <UserAvatarTip :user="person" label="Người thực hiện" />
                  <div class="pd__person-copy">
                    <span class="pd__person-name">{{ person.name }}</span>
                    <span class="pd__person-meta">{{ personSecondary(person) }}</span>
                    <span class="pd__person-stat">
                      {{ performerStat(person) }}
                      <span v-if="person.overdue" class="pd__person-stat--overdue"> · {{ person.overdue }} quá hạn</span>
                    </span>
                  </div>
                </article>
              </div>
              <p v-else class="pd__empty-inline">Chưa có người thực hiện.</p>
            </section>

            <section class="pd__card">
              <header class="pd__section-head">
                <span class="pd__section-icon pd__section-icon--secondary"><AppIcon name="eye" :size="14" /></span>
                <h3 class="pd__section-title">Người theo dõi</h3>
                <span class="pd__section-count">{{ (project.followers || []).length }}</span>
              </header>
              <div v-if="(project.followers || []).length" class="pd__person-list hide-scrollbar">
                <article v-for="person in project.followers" :key="person.id" class="pd__person">
                  <UserAvatarTip :user="person" label="Người theo dõi" />
                  <div class="pd__person-copy">
                    <span class="pd__person-name">{{ person.name }}</span>
                    <span class="pd__person-meta">{{ personSecondary(person) }}</span>
                  </div>
                </article>
              </div>
              <p v-else class="pd__empty-inline">Chưa có người theo dõi.</p>
            </section>
          </template>

          <section v-if="taskCategories.length" class="pd__card">
            <header class="pd__section-head">
              <span class="pd__section-icon pd__section-icon--secondary"><AppIcon name="pieChart" :size="14" /></span>
              <h3 class="pd__section-title">Danh mục công việc</h3>
            </header>
            <div class="pd__category-list">
              <div v-for="cat in taskCategories" :key="cat.id" class="pd__category-row">
                <span class="pd__ring" :style="ringStyle(cat.percent)">
                  <span class="pd__ring-inner">{{ cat.percent }}%</span>
                </span>
                <span class="pd__category-copy">
                  <span class="pd__category-title">{{ cat.title }}</span>
                  <span class="pd__category-meta">{{ cat.count }} công việc</span>
                </span>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>

    <ProjectQuickActionModals
      :kind="actionDialog.kind"
      :project="project"
      :extra="actionDialog.extra"
      @close="closeActionDialog"
      @updated="onActionUpdated"
      @duplicated="onProjectDuplicated"
      @tasks-changed="onTasksChanged"
    />
  </section>
</template>

<style scoped>
.pd {
  position: relative;
  min-height: 100%;
  padding: 0 var(--space-5) var(--space-4);
  background: linear-gradient(180deg, var(--color-tertiary-50) 0%, var(--color-surface-muted) 22rem);
}

.pd--fill {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.pd__header-btn {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.pd__header-btn:hover {
  background: var(--color-surface-muted);
}

.pd__header-btn:disabled {
  opacity: 0.6;
  pointer-events: none;
}

.pd__header-btn--on {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.pd__empty {
  padding: var(--space-6) 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  text-align: center;
}

.pd__empty-inline {
  margin: 0;
  padding: var(--space-3) 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.pd__page {
  position: relative;
  display: grid;
  gap: var(--space-4);
}

.pd--fill .pd__page {
  flex: 1;
  min-height: 0;
  grid-template-rows: auto minmax(0, 1fr);
}

.pd__chrome {
  display: flex;
  flex-direction: row;
  align-items: stretch;
  width: auto;
  margin: var(--space-3) calc(var(--space-5) * -1) 0;
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.pd__nav {
  display: flex;
  flex: 1;
  align-items: stretch;
  gap: 0;
  min-width: 0;
  padding: 0 var(--space-5);
  overflow-x: auto;
}

.pd__tab {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
  padding: 0.7rem 0.875rem;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.pd__tab:hover {
  color: var(--color-text);
  background: transparent;
}

.pd__tab:focus-visible {
  outline: 2px solid var(--color-secondary);
  outline-offset: -2px;
}

.pd__tab--active {
  color: var(--color-secondary-700);
  background: transparent;
  box-shadow: none;
}

.pd__tab--active::after {
  content: '';
  position: absolute;
  left: 0.75rem;
  right: 0.75rem;
  bottom: 0;
  height: 2px;
  background: var(--color-secondary);
}

.pd__tab-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 0.875rem;
  height: 0.875rem;
  color: inherit;
  opacity: 0.75;
}

.pd__tab--active .pd__tab-icon {
  opacity: 1;
}

.pd__tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.125rem;
  height: 1.125rem;
  padding: 0 0.3125rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.pd__tab--active .pd__tab-count {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.pd__toolbar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: nowrap;
  gap: var(--space-3);
  min-height: 0;
  padding: 0 var(--space-5) 0 var(--space-2);
  /* KHÔNG overflow ở toolbar — .pd__menu định vị absolute; overflow-x: auto
     kéo theo overflow-y và cắt dropdown. */
  background: transparent;
}

.pd__actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.375rem;
  margin-left: auto;
}

.pd__action-group {
  display: flex;
  flex-shrink: 0;
  flex-wrap: nowrap;
  align-items: center;
  gap: 0.125rem;
}

.pd__action-split {
  flex-shrink: 0;
  width: 1px;
  align-self: stretch;
  margin: 0.25rem 0.125rem;
  background: var(--color-border);
}

.pd__action-wrap {
  position: relative;
  flex-shrink: 0;
}

.pd__action {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  gap: 0.3125rem;
  height: 2rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  white-space: nowrap;
  cursor: pointer;
}

.pd__action--icon {
  width: 2rem;
  padding: 0;
}

.pd__menu {
  position: absolute;
  top: calc(100% + 0.25rem);
  left: 0;
  z-index: 40;
  min-width: 15rem;
  padding: 0.375rem 0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.pd__menu--right {
  left: auto;
  right: 0;
}

.pd__menu--up {
  top: auto;
  bottom: calc(100% + 0.25rem);
}

.pd__menu--export {
  min-width: 17.5rem;
}

.pd__menu-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.875rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.pd__menu-item:hover {
  background: var(--color-surface-muted);
}

.pd__menu-item--danger {
  color: var(--color-danger-tint-fg);
}

.pd__menu-item--danger:hover {
  background: var(--color-danger-tint-bg);
}

.pd__menu-item-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.pd__menu-item-title {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.pd__menu-item-sub {
  color: var(--color-text-muted);
  font-size: 0.71875rem;
  font-weight: 400;
  font-style: italic;
}

.pd__menu-sep {
  display: block;
  height: 1px;
  margin: 0.375rem 0;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.pd__action:hover:not(:disabled) {
  background: var(--color-surface);
  color: var(--color-text);
}

.pd__action--open,
.pd__action--open:hover:not(:disabled) {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.pd__action--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.pd__action--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
  color: var(--color-on-primary);
}

.pd__action--primary.pd__action--open,
.pd__action--primary.pd__action--open:hover:not(:disabled) {
  background: var(--color-primary-hover);
  color: var(--color-on-primary);
}

.pd__action:disabled {
  opacity: 0.45;
  cursor: default;
}

.pd__action--danger {
  color: var(--color-danger-tint-fg);
  background: transparent;
}

.pd__action--danger:hover:not(:disabled) {
  color: var(--color-danger-tint-fg);
  background: var(--color-danger-tint-bg);
}

.pd__action-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 0.9375rem;
  height: 0.9375rem;
}

.pd__action-label {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.pd__stats {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--space-3);
}

.pd__stat {
  --stat-color: var(--color-text);
  --stat-fill: var(--color-surface-muted);
  --stat-on: var(--color-on-primary);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  min-width: 0;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: inherit;
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.pd__stat::after {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--stat-color);
  clip-path: circle(0% at 1.75rem 1.75rem);
  transition: clip-path 0.45s cubic-bezier(0.22, 1, 0.36, 1);
  pointer-events: none;
}

.pd__stat:hover,
.pd__stat:focus-visible,
.pd__stat--active {
  box-shadow: var(--shadow-md);
}

.pd__stat:hover::after,
.pd__stat:focus-visible::after,
.pd__stat--active::after {
  clip-path: circle(140% at 1.75rem 1.75rem);
}

.pd__stat:focus-visible {
  outline: none;
  box-shadow: var(--shadow-md), 0 0 0 2px var(--color-surface), 0 0 0 4px var(--stat-color);
}

.pd__stat--gold {
  --stat-color: var(--color-gold-600);
  --stat-fill: var(--color-gold-surface);
  --stat-on: var(--color-on-gold);
}

.pd__stat--danger {
  --stat-color: var(--color-danger);
  --stat-fill: var(--color-danger-tint-bg);
  --stat-on: var(--color-on-primary);
}

.pd__stat--info {
  --stat-color: var(--color-info);
  --stat-fill: var(--color-info-tint-bg);
  --stat-on: var(--color-on-primary);
}

.pd__stat--success {
  --stat-color: var(--color-success);
  --stat-fill: var(--color-success-tint-bg);
  --stat-on: var(--color-on-primary);
}

.pd__stat--warning {
  --stat-color: var(--color-warning);
  --stat-fill: var(--color-warning-tint-bg);
  --stat-on: var(--color-on-primary);
}

.pd__stat-icon,
.pd__stat-copy {
  position: relative;
  z-index: 1;
}

.pd__stat-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--stat-fill);
  color: var(--stat-color);
  transition: background 0.35s ease, color 0.35s ease;
}

.pd__stat:hover .pd__stat-icon,
.pd__stat:focus-visible .pd__stat-icon,
.pd__stat--active .pd__stat-icon {
  background: color-mix(in srgb, var(--stat-on) 22%, transparent);
  color: var(--stat-on);
}

.pd__stat-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.pd__stat-value {
  color: var(--stat-color);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.15;
  transition: color 0.35s ease;
}

.pd__stat-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.3;
  transition: color 0.35s ease;
}

.pd__stat:hover .pd__stat-value,
.pd__stat:focus-visible .pd__stat-value,
.pd__stat--active .pd__stat-value,
.pd__stat:hover .pd__stat-label,
.pd__stat:focus-visible .pd__stat-label,
.pd__stat--active .pd__stat-label {
  color: var(--stat-on);
}

@media (prefers-reduced-motion: reduce) {
  .pd__stat::after,
  .pd__stat-icon,
  .pd__stat-value,
  .pd__stat-label {
    transition: none;
  }
}

.pd__layout {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(18rem, 0.9fr);
  align-items: start;
  gap: var(--space-4);
}

.pd__layout--docs {
  grid-template-columns: minmax(0, 1fr);
}

.pd--fill .pd__layout {
  min-height: 0;
  align-items: stretch;
}

.pd--fill .pd__col {
  height: 100%;
  min-height: 0;
  grid-template-rows: minmax(0, 1fr);
}

.pd__layout--general {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.pd__col {
  display: grid;
  gap: var(--space-4);
  min-width: 0;
}

.pd__col--side {
  order: 2;
}

.pd__card {
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.pd__card--gantt {
  padding: 0;
  overflow: hidden;
}

.pd--fill .pd__card--gantt {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.pd__card--lead {
  position: relative;
  padding-left: calc(var(--space-2) + 3px + var(--space-5));
}

.pd__card--lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.pd__section-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0 0 var(--space-4);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.pd__section-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.pd__section-icon--primary { background: var(--color-primary-surface); color: var(--color-primary); }
.pd__section-icon--secondary { background: var(--color-secondary-surface); color: var(--color-secondary-700); }
.pd__section-icon--info { background: var(--color-info-tint-bg); color: var(--color-info-tint-fg); }
.pd__section-icon--gold { background: var(--color-gold-surface); color: var(--color-gold-600); }

.pd__section-title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.pd__section-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  padding: 0 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.pd__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 1.875rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.pd__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.pd__btn--ghost:hover {
  background: var(--color-surface);
}

.pd__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.pd__kv-cell--rules .pd__label,
.pd__kv-row--span .pd__label,
.pd__kv-cell--rules + .pd__kv-cell .pd__label {
  align-items: flex-start;
  padding-top: 0.75rem;
}

.pd__kv-cell--rules + .pd__kv-cell .pd__value {
  align-items: flex-start;
}

.pd__hidden-input {
  display: none;
}

.pd__kv {
  display: grid;
  margin: 0;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.pd__kv-row,
.pd__kv-cell {
  display: grid;
  grid-template-columns: 10.5rem minmax(0, 1fr);
  align-items: stretch;
  min-width: 0;
}

.pd__kv--cols .pd__kv-row--span,
.pd__kv--cols .pd__kv-cell {
  grid-template-columns: 10.5rem minmax(0, 1fr);
}

.pd__kv-row {
  box-shadow: 0 1px 0 var(--color-border);
}

.pd__kv-row:last-child {
  box-shadow: none;
}

.pd__kv--cols .pd__kv-row:not(.pd__kv-row--span) {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.pd__kv-cell + .pd__kv-cell {
  box-shadow: -1px 0 0 var(--color-border);
}

.pd__label {
  display: flex;
  align-items: center;
  padding: 0.625rem 0.75rem;
  background: var(--color-surface-muted);
  box-shadow: 1px 0 0 var(--color-border);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
}

.pd__label::after {
  content: ':';
}

.pd__value {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.5rem;
  min-width: 0;
  margin: 0;
  padding: 0.5rem 0.75rem;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.pd__value--stack {
  flex-direction: column;
  align-items: flex-start;
}

.pd__value--progress {
  align-items: stretch;
}

.pd__name {
  display: inline-flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.pd__name-avatar {
  display: inline-flex;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  cursor: pointer;
}

.pd__name-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pd__person-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  max-height: 22rem;
  overflow-y: auto;
}

.pd__person {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  min-width: 0;
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
}

.pd__person-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.pd__person-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.pd__person-meta {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.pd__person-stat {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
}

.pd__person-stat--overdue {
  color: var(--color-danger-tint-fg);
}

.pd__people {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.pd__user-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  width: fit-content;
  max-width: 100%;
  padding: 0.125rem 0.625rem 0.125rem 0.125rem;
  border-radius: var(--radius-full);
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
  font-size: 0.8125rem;
  font-weight: 600;
}

.pd__more-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.75rem;
  height: 1.5rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.pd__more-chip:hover {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.pd__chip {
  display: inline-flex;
  width: fit-content;
  align-items: center;
  padding: 0.1875rem 0.625rem;
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  font-weight: 600;
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.pd__chip--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.pd__chip--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.pd__chip--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.pd__chip--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.pd__chip--umber {
  background: var(--color-umber-surface);
  color: var(--color-umber);
}

.pd__chip--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.pd__chip--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.pd__chip--neutral {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.pd__progress {
  width: 100%;
  min-width: 0;
}

.pd__progress :deep(.dual-progress) {
  display: flex;
  width: 100%;
}

.pd__progress :deep(.dual-progress__track) {
  flex: 1;
  min-width: 0;
  max-width: none;
}

.pd__rules {
  display: grid;
  gap: 0.375rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pd__rules li {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.pd__rule-icon {
  flex-shrink: 0;
  margin-top: 0.15rem;
  color: var(--color-success);
}

.pd__rule-icon--off {
  color: var(--color-text-muted);
}

.pd__rules-item--off {
  color: var(--color-text-muted);
}

.pd__rows {
  display: flex;
  flex-direction: column;
}

.pd__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.pd__row:last-child {
  box-shadow: none;
}

.pd__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.pd__row-label::after {
  content: ':';
}

.pd__row-value {
  color: var(--color-text);
  text-align: right;
  overflow-wrap: anywhere;
}

.pd__chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.pd__label-chip {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 0.125rem 0.5rem;
  border-radius: 0;
  background: var(--chip-bg, var(--color-surface-muted));
  color: var(--chip-fg, var(--color-text));
  font-size: 0.75rem;
  font-weight: 600;
}

.pd__label-chip--primary { --chip-bg: var(--color-primary-50); --chip-fg: var(--color-primary-900); }
.pd__label-chip--secondary { --chip-bg: var(--color-secondary-50); --chip-fg: var(--color-secondary-800); }
.pd__label-chip--tertiary { --chip-bg: var(--color-tertiary-50); --chip-fg: var(--color-tertiary-800); }
.pd__label-chip--gold { --chip-bg: var(--color-gold-50); --chip-fg: var(--color-gold-800); }
.pd__label-chip--success { --chip-bg: var(--color-success-tint-bg); --chip-fg: var(--color-success-tint-fg); }
.pd__label-chip--info { --chip-bg: var(--color-info-tint-bg); --chip-fg: var(--color-info-tint-fg); }
.pd__label-chip--warning { --chip-bg: var(--color-warning-tint-bg); --chip-fg: var(--color-warning-tint-fg); }
.pd__label-chip--danger { --chip-bg: var(--color-danger-tint-bg); --chip-fg: var(--color-danger-tint-fg); }
.pd__label-chip--violet { --chip-bg: #f3e8ff; --chip-fg: #5b21b6; }

.pd__icon-btn {
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

.pd__icon-btn:hover {
  background: var(--color-surface-muted);
}

.pd__category-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.pd__category-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0.5rem 0.625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
}

.pd__ring {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
}

.pd__ring-inner {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.125rem;
  height: 2.125rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.6875rem;
  font-weight: 700;
}

.pd__category-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.pd__category-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.pd__category-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.pd__dialog {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: color-mix(in srgb, black 45%, transparent);
}

.pd__dialog-panel {
  width: 100%;
  max-width: 28rem;
  max-height: 80vh;
  overflow-y: auto;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.pd__dialog-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.pd__dialog-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
}

@media (max-width: 1280px) {
  .pd__stats {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }

  .pd__layout {
    grid-template-columns: 1fr;
  }

  .pd__col--side {
    order: -1;
  }

  .pd__layout--general .pd__col--side {
    order: 2;
  }
}

@media (max-width: 768px) {
  .pd {
    padding: 0 var(--space-3) var(--space-3);
  }

  .pd__chrome {
    margin-left: calc(var(--space-3) * -1);
    margin-right: calc(var(--space-3) * -1);
  }

  .pd__nav {
    padding-left: var(--space-3);
    padding-right: var(--space-3);
  }

  .pd__toolbar {
    padding-left: var(--space-2);
    padding-right: var(--space-3);
  }

  .pd__stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .pd__kv--cols .pd__kv-row:not(.pd__kv-row--span) {
    grid-template-columns: 1fr;
  }

  .pd__kv-cell + .pd__kv-cell {
    box-shadow: inset 0 1px 0 var(--color-border);
  }
}

@media (max-width: 480px) {
  .pd__stats {
    grid-template-columns: 1fr;
  }

  .pd__row {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.25rem;
  }

  .pd__row-value {
    text-align: left;
  }
}
</style>
