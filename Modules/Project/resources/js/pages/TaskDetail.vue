<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import GuideHelp from '@/components/GuideHelp.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { SCORE_FACTOR_GUIDES } from '@/constants/scoreFactorGuides.js';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import CommentList from '../components/CommentList.vue';
import ProjectMemberPicker from '../components/ProjectMemberPicker.vue';
import ProjectUserPicker from '../components/ProjectUserPicker.vue';
import TaskEvalFactors from '../components/TaskEvalFactors.vue';
import TaskRowContextMenu from '../components/TaskRowContextMenu.vue';
import TaskQuickActionModals from '../components/TaskQuickActionModals.vue';
import {
  TASK_DELEGATION_STATUS_LABELS,
  TASK_PRIORITY_LABELS,
  TASK_PRIORITY_TONES,
  TASK_PROGRESS_TYPE_LABELS,
  TASK_SCORE_RESULT_SUGGESTIONS,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  TASK_STATUSES,
  TASK_TYPE_LABELS,
} from '../constants/task.js';

const REPORT_COMPLETE_ACTION_LABELS = {
  none: 'Không tự chuyển trạng thái',
  completed: 'Chuyển sang Hoàn thành',
  under_review: 'Chuyển sang Đang đánh giá',
};
const INTERACTION_POLICY_LABELS = {
  allow: 'Cho phép',
  deny: 'Không cho phép',
  inherit: 'Biến động theo cài đặt',
};
const REPORT_REQUIREMENT_LABELS = {
  none: 'Không yêu cầu',
  on_report: 'Yêu cầu khi báo cáo',
  on_completion: 'Yêu cầu khi báo cáo hoàn thành',
};
const RULE_DEFS = [
  { key: 'hide_cross_tasks_from_assignees', label: 'Ẩn việc chéo' },
  { key: 'hide_from_parent_assignees', label: 'Ẩn với người làm việc cha' },
  { key: 'hide_from_parent_followers', label: 'Ẩn với người theo dõi việc cha' },
  { key: 'hide_child_tasks_from_followers', label: 'Ẩn việc con' },
  { key: 'allow_child_people_view_parent', label: 'Xem việc cha' },
];
const PERMISSION_RULE_VALUES = {
  hide_cross_tasks_from_assignees: {
    on: 'Người thực hiện không xem được việc chéo cùng việc cha',
    off: 'Người thực hiện được xem việc chéo',
  },
  hide_from_parent_assignees: {
    on: 'Người làm việc cha không xem được việc này',
    off: 'Người làm việc cha được xem việc này',
  },
  hide_from_parent_followers: {
    on: 'Người theo dõi việc cha không xem được việc này',
    off: 'Người theo dõi việc cha được xem việc này',
  },
  hide_child_tasks_from_followers: {
    on: 'Người theo dõi không xem được việc con',
    off: 'Người theo dõi được xem việc con',
  },
  allow_child_people_view_parent: {
    on: 'Người ở việc con được xem việc cha',
    off: 'Người ở việc con không xem việc cha',
  },
};

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loading = ref(false);
const notFound = ref(false);
const task = ref(null);
const attachments = ref([]);
const worklogs = ref([]);
const users = ref([]);
const importanceOptions = ref([]);
const lockDifficulty = ref(false);
const scoreKitMode = ref('');
const progressLevels = ref([]);

const deleting = ref(false);
const confirmingDelete = ref(false);
const attachmentUploading = ref(false);
const attachmentInput = ref(null);
const selectedAttachmentIds = ref([]);
const confirmingDeleteAttachments = ref(false);
const deletingAttachments = ref(false);
const fileMenuOpen = ref(false);
const fileMenuRoot = ref(null);
const fileMenuPos = reactive({ x: 0, y: 0 });
const fileMenuAttachment = ref(null);
const replaceInput = ref(null);
const replaceTargetId = ref(null);
const replacingAttachment = ref(false);
const renaming = ref(false);
const renameTarget = ref(null);
const savingRename = ref(false);
const renameForm = reactive({ file_name: '' });
const renameInput = ref(null);
const worklogFormOpen = ref(false);
const worklogSaving = ref(false);
const confirmingDeleteWorklog = ref(null);
const statusSaving = ref(false);
const reportCompleteSaving = ref(false);
const statusMenuOpen = ref(false);
const statusMenuRoot = ref(null);
const statusMenuEl = ref(null);
const statusMenuPos = reactive({ top: 0, left: 0 });
const peopleOpen = ref(false);
const peopleSaving = ref(false);
const worklogSection = ref(null);
const scoreFormOpen = ref(false);
const scoreSaving = ref(false);
// Thang chất lượng của khung chấm điểm "Cách 2 — Hiệu suất việc" (phòng ban
// của assignee) — nạp khi mở form chấm điểm, để ô "Kết quả đánh giá" cho
// CHỌN đúng theo thang thay vì gõ text tự do, khớp với cách
// EvaluationScoreComputeService::qualityFactor() tính điểm hiệu suất.
// Rỗng (phòng ban chưa cấu hình / đang dùng "Cách 1") → fallback ô text tự do.
const scoreQualityLevels = ref([]);
const scoreQualityLevelsLoading = ref(false);
const infoTab = ref('general');
const infoTabMeta = computed(() => (
  infoTab.value === 'general'
    ? { title: 'Thông tin chung', icon: 'clipboardCheck' }
    : { title: 'Tiến độ và quyền', icon: 'sliders' }
));

const worklogForm = reactive({ work_date: '', hours: '', note: '' });
const scoreForm = reactive({ rating_score: '', rating_result: '', is_passed: null, rating_desc: '' });
const peopleForm = reactive({
  assignee_id: '',
  manager_id: '',
  watcher_ids: [],
  collaborator_ids: [],
});
const taskId = computed(() => Number(route.params.id || 0));
const canEdit = computed(() => auth.can('task.create'));
const commentsCount = ref(0);
const canApprove = computed(() => auth.can('task.approve'));
const isAssignee = computed(() => Boolean(auth.user?.id) && task.value?.assignee_id === auth.user?.id);
const canReportComplete = computed(
  () => isAssignee.value && task.value && !['completed', 'cancelled'].includes(task.value.status),
);
const statusChoices = computed(() => TASK_STATUSES.filter((item) => item.value));
const hasScore = computed(() => Boolean(task.value?.task_score));
const worklogTotalHours = computed(() => {
  const total = worklogs.value.reduce((sum, log) => sum + Number(log.hours || 0), 0);
  return Number.isInteger(total) ? total : Math.round(total * 100) / 100;
});
const evalProgressPercent = computed(() => {
  if (task.value?.progress_percent == null) return null;
  const n = Number(task.value.progress_percent);
  if (Number.isNaN(n)) return null;
  return Math.max(0, Math.min(100, Math.round(n)));
});
const evalStars = computed(() => {
  const raw = task.value?.task_score?.rating_score;
  if (raw == null || raw === '') return 0;
  const n = Number(raw);
  if (Number.isNaN(n) || n <= 0) return 0;
  if (n <= 5) return Math.max(0, Math.min(5, Math.round(n)));
  return Math.max(0, Math.min(5, Math.round(n / 20)));
});
const evalStarCaption = computed(() => {
  const captions = { 1: 'Kém', 2: 'Yếu', 3: 'Trung bình', 4: 'Tốt', 5: 'Xuất sắc' };
  return captions[evalStars.value] || '';
});
const permissionLines = computed(() => {
  if (!task.value) return [];
  const reportAction = task.value.report_complete_action
    || (task.value.auto_complete_on_report ? 'completed' : 'none');
  const interaction = task.value.completed_interaction_policy || 'inherit';
  const descReq = task.value.report_description_requirement || 'none';
  const fileReq = task.value.report_attachment_requirement || 'none';

  return [
    ...RULE_DEFS.map((rule) => {
      const on = Boolean(task.value[rule.key]);
      return {
        key: rule.key,
        label: rule.label,
        value: PERMISSION_RULE_VALUES[rule.key][on ? 'on' : 'off'],
        on,
      };
    }),
    {
      key: 'constrain_child_dates',
      label: 'Khóa thời gian việc con',
      value: task.value.constrain_child_dates
        ? 'Công việc con không được vượt khoảng thời gian này'
        : 'Không khóa',
      on: Boolean(task.value.constrain_child_dates),
    },
    {
      key: 'report_complete_action',
      label: 'Sau khi báo cáo hoàn thành',
      value: REPORT_COMPLETE_ACTION_LABELS[reportAction] || REPORT_COMPLETE_ACTION_LABELS.none,
      on: reportAction === 'completed' || reportAction === 'under_review',
    },
    {
      key: 'completed_interaction_policy',
      label: 'Thảo luận và file sau hoàn thành',
      value: INTERACTION_POLICY_LABELS[interaction] || INTERACTION_POLICY_LABELS.inherit,
      on: interaction !== 'inherit',
    },
    {
      key: 'report_description_requirement',
      label: 'Mô tả khi báo cáo',
      value: REPORT_REQUIREMENT_LABELS[descReq] || REPORT_REQUIREMENT_LABELS.none,
      on: descReq !== 'none',
    },
    {
      key: 'report_attachment_requirement',
      label: 'File khi báo cáo',
      value: REPORT_REQUIREMENT_LABELS[fileReq] || REPORT_REQUIREMENT_LABELS.none,
      on: fileReq !== 'none',
    },
  ];
});
const plannedTimeLabel = computed(() => {
  if (!task.value) return '--';
  return formatDateRange(
    task.value.start_date,
    task.value.start_time,
    task.value.end_date,
    task.value.due_time,
  );
});
const actualTimeLabel = computed(() => {
  if (!task.value) return '--';
  if (task.value.actual_start_date && task.value.actual_end_date) {
    return `${formatDate(task.value.actual_start_date)} - ${formatDate(task.value.actual_end_date)}`;
  }
  return formatDate(task.value.actual_start_date || task.value.actual_end_date);
});
const hasDelegation = computed(() => Boolean(task.value?.delegation_status));
const peopleStory = computed(() => {
  const nameOf = (id) => users.value.find((user) => String(user.id) === String(id))?.name || '';
  const assignee = nameOf(peopleForm.assignee_id) || 'Chưa chọn người thực hiện';
  const manager = nameOf(peopleForm.manager_id) || 'chưa chọn người giao việc';
  return `${assignee} làm việc, ${manager} giao.`;
});
const selectedAttachments = computed(() =>
  attachments.value.filter((item) => selectedAttachmentIds.value.includes(item.id)),
);
const allAttachmentsSelected = computed(
  () => attachments.value.length > 0 && selectedAttachmentIds.value.length === attachments.value.length,
);

function formatDate(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleDateString('vi-VN');
}

function formatClock(value) {
  if (!value) return '';
  return String(value).slice(0, 5);
}

function formatDateWithTime(date, time) {
  const day = formatDate(date);
  const clock = formatClock(time);
  if (day === '--') return clock || '--';
  return clock ? `${day} ${clock}` : day;
}

function formatDateRange(start, startTime, end, endTime) {
  const left = formatDateWithTime(start, startTime);
  const right = formatDateWithTime(end, endTime);
  if (left === '--' && right === '--') return '--';
  return `${left} - ${right}`;
}

function formatHours(value) {
  if (value == null || value === '') return '--';
  const n = Number(value);
  if (Number.isNaN(n)) return '--';
  return Number.isInteger(n) ? `${n} giờ` : `${n} giờ`;
}

function formatWeight(value) {
  if (value == null || value === '') return '--';
  const n = Number(value);
  if (Number.isNaN(n)) return '--';
  return `${n}%`;
}

function formatVariance(value) {
  if (value == null || value === '') return '--';
  const n = Number(value);
  if (Number.isNaN(n)) return '--';
  if (n === 0) return 'Đúng hạn';
  return n > 0 ? `Trễ ${n} ngày` : `Sớm ${Math.abs(n)} ngày`;
}

function passedLabel(value) {
  if (value === true) return 'Đạt';
  if (value === false) return 'Không đạt';
  return '--';
}

function delegationLabel(value) {
  return TASK_DELEGATION_STATUS_LABELS[value] || value || '--';
}

function formatDateTime(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleString('vi-VN');
}

function formatScoreDateTime(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(date.getHours())}:${pad(date.getMinutes())} ${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

function ratingResultTone(text) {
  const value = String(text || '').toLowerCase();
  if (/không|kém|yếu|fail|chưa/.test(value)) return 'danger';
  if (/xuất sắc|tốt|đạt|hoàn thành/.test(value)) return 'success';
  return 'gold';
}

function formatFileSize(bytes) {
  if (!bytes) return '';
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(0)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
}

function fileExt(name) {
  const match = String(name || '').match(/\.([a-z0-9]+)$/i);
  return match ? match[1].toUpperCase() : 'FILE';
}

function fileExtTone(name) {
  const ext = fileExt(name).toLowerCase();
  if (['zip', 'rar', '7z'].includes(ext)) return 'gold';
  if (['pdf'].includes(ext)) return 'danger';
  if (['doc', 'docx'].includes(ext)) return 'info';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'success';
  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'tertiary';
  if (['dwg', 'dxf'].includes(ext)) return 'primary';
  return 'neutral';
}

function statusLabel(value) {
  return TASK_STATUS_LABELS[value] || value || '--';
}

function priorityLabel(value) {
  if (!value) return '--';
  const fromCriterion = importanceOptions.value.find((opt) => opt.value === value);
  return fromCriterion?.label || TASK_PRIORITY_LABELS[value] || value;
}

function typeLabel(value) {
  return TASK_TYPE_LABELS[value] || value || '--';
}

function progressTypeLabel(value) {
  return TASK_PROGRESS_TYPE_LABELS[value] || value || '--';
}

function priorityTone(value) {
  return TASK_PRIORITY_TONES[value] || 'neutral';
}

function applyTask(next) {
  task.value = next;
}

async function loadOptions() {
  try {
    const [usersRes, optionsRes] = await Promise.all([
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/tasks/options'),
    ]);
    users.value = usersRes.data.users ?? [];
    importanceOptions.value = optionsRes.data.importance ?? [];
    lockDifficulty.value = Boolean(optionsRes.data.lock_difficulty);
    scoreKitMode.value = optionsRes.data.mode || '';
    progressLevels.value = optionsRes.data.progress_levels ?? [];
  } catch {
    users.value = [];
  }
}

async function loadTask() {
  if (!taskId.value) return;
  loading.value = true;
  notFound.value = false;
  infoTab.value = 'general';
  try {
    const [{ data: detail }, { data: files }, { data: logs }] = await Promise.all([
      window.axios.get(`/api/project/tasks/${taskId.value}`),
      window.axios.get(`/api/project/tasks/${taskId.value}/attachments`),
      window.axios.get(`/api/project/tasks/${taskId.value}/worklogs`),
    ]);
    applyTask(detail.task || null);
    attachments.value = files.attachments || [];
    selectedAttachmentIds.value = [];
    worklogs.value = logs.worklogs || [];
  } catch (error) {
    task.value = null;
    notFound.value = error?.response?.status === 404;
    showClientToast('error', error?.response?.data?.message || 'Không tải được chi tiết công việc.');
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push({ name: 'manager.project.tasks' });
}

function goEdit() {
  if (!task.value) return;
  router.push({ name: 'manager.project.tasks.edit', params: { id: task.value.id } });
}

const FALLBACK_DIFFICULTY = [
  { value: 'support', label: 'Phụ trợ' },
  { value: 'assist', label: 'Hỗ trợ' },
  { value: 'important', label: 'Quan trọng' },
  { value: 'high_priority', label: 'Ưu tiên cao' },
  { value: 'strategic', label: 'Chiến lược / Sống còn' },
];
const childDifficultySaving = ref({});
const difficultyChoices = computed(() => (
  importanceOptions.value.length ? importanceOptions.value : FALLBACK_DIFFICULTY
));

function relatedTitle(item) {
  if (!item) return '--';
  return item.code ? `${item.code} - ${item.title}` : (item.title || '--');
}

function childDifficultyLocked(child) {
  return lockDifficulty.value
    && Boolean(child.assignee_id || child.assignee?.id)
    && Boolean(child.priority);
}

function childDifficultyChoices(child) {
  const rows = difficultyChoices.value.map((opt) => ({ value: opt.value, label: opt.label }));
  if (child.priority && !rows.some((opt) => opt.value === child.priority)) {
    rows.unshift({
      value: child.priority,
      label: child.priority_label || priorityLabel(child.priority) || child.priority,
    });
  }
  return rows;
}

function goCreateChild() {
  if (!task.value) return;
  const query = { parent_id: String(task.value.id) };
  if (task.value.project_id) query.project_id = String(task.value.project_id);
  router.push({ name: 'manager.project.tasks.create', query });
}

const childCtxMenu = reactive({ open: false, x: 0, y: 0, task: null });
const childActionDialog = reactive({ kind: null, task: null, extra: {} });

function openChildContextMenu(event, child) {
  if (childActionDialog.kind) return;
  childCtxMenu.open = true;
  childCtxMenu.x = event.clientX;
  childCtxMenu.y = event.clientY;
  childCtxMenu.task = child;
}

function closeChildContextMenu() {
  childCtxMenu.open = false;
}

function onChildContextAction({ type, task: child, status, variant }) {
  closeChildContextMenu();
  if (!child) return;
  if (type === 'status') {
    if (child.status !== status) {
      changeChildStatus(child, status);
    }
    return;
  }
  if (type === 'details') {
    if (variant === 'edit') {
      router.push({ name: 'manager.project.tasks.edit', params: { id: child.id } });
      return;
    }
    if (variant === 'blank') {
      window.open(router.resolve({ name: 'manager.project.tasks.detail', params: { id: child.id } }).href, '_blank');
      return;
    }
    router.push({ name: 'manager.project.tasks.detail', params: { id: child.id } });
    return;
  }
  childActionDialog.kind = type;
  childActionDialog.task = child;
  childActionDialog.extra = { variant };
}

function closeChildActionDialog() {
  childActionDialog.kind = null;
  childActionDialog.task = null;
  childActionDialog.extra = {};
}

function applyChildUpdate(updated) {
  if (!updated?.id || !task.value) return;
  applyTask({
    ...task.value,
    children: (task.value.children || []).map((item) => (
      item.id === updated.id ? { ...item, ...updated } : item
    )),
  });
  if (childCtxMenu.task?.id === updated.id) childCtxMenu.task = { ...childCtxMenu.task, ...updated };
  if (childActionDialog.task?.id === updated.id) childActionDialog.task = { ...childActionDialog.task, ...updated };
}

async function changeChildStatus(child, status) {
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${child.id}`, { status });
    applyChildUpdate(data.task ?? { id: child.id, status });
    showClientToast('success', `Đã chuyển sang ${statusLabel(status)}.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái.');
  }
}

function onChildTaskDuplicated() {
  closeChildActionDialog();
  loadTask();
}

function onChildTaskDeleted() {
  closeChildActionDialog();
  loadTask();
}

async function changeChildDifficulty(child, value) {
  if (!task.value || !canEdit.value || childDifficultySaving.value[child.id]) return;
  if (!value || value === child.priority) return;
  childDifficultySaving.value = { ...childDifficultySaving.value, [child.id]: true };
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${child.id}`, { priority: value });
    const nextPriority = data.task?.priority ?? value;
    const nextLabel = data.task?.priority_label || priorityLabel(nextPriority);
    applyTask({
      ...task.value,
      children: (task.value.children || []).map((item) => (
        item.id === child.id
          ? { ...item, priority: nextPriority, priority_label: nextLabel }
          : item
      )),
    });
    showClientToast('success', `Đã gán độ khó ${nextLabel} cho công việc con.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không gán được độ khó cho công việc con.');
  } finally {
    const next = { ...childDifficultySaving.value };
    delete next[child.id];
    childDifficultySaving.value = next;
  }
}

async function changeStatus(nextStatus) {
  if (!task.value || !canEdit.value || statusSaving.value) return;
  statusMenuOpen.value = false;
  if (task.value.status === nextStatus) return;
  statusSaving.value = true;
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${task.value.id}`, { status: nextStatus });
    applyTask(data.task);
    showClientToast('success', `Đã chuyển sang ${statusLabel(nextStatus)}.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái.');
  } finally {
    statusSaving.value = false;
  }
}

function positionStatusMenu() {
  const trigger = statusMenuRoot.value?.querySelector('button') || statusMenuRoot.value;
  const menu = statusMenuEl.value;
  if (!trigger || !menu) return;
  const rect = trigger.getBoundingClientRect();
  const pad = 8;
  const width = menu.offsetWidth;
  const height = menu.offsetHeight;
  let left = rect.right - width;
  let top = rect.bottom + 6;
  if (top + height > window.innerHeight - pad) {
    top = Math.max(pad, rect.top - height - 6);
  }
  left = Math.min(Math.max(pad, left), window.innerWidth - width - pad);
  statusMenuPos.top = top;
  statusMenuPos.left = left;
}

async function toggleStatusMenu() {
  if (!canEdit.value) return;
  if (statusMenuOpen.value) {
    statusMenuOpen.value = false;
    return;
  }
  const trigger = statusMenuRoot.value?.querySelector('button') || statusMenuRoot.value;
  if (trigger) {
    const rect = trigger.getBoundingClientRect();
    statusMenuPos.top = rect.bottom + 6;
    statusMenuPos.left = Math.max(8, rect.right - 224);
  }
  statusMenuOpen.value = true;
  await nextTick();
  positionStatusMenu();
}

function focusWorklog() {
  worklogSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeWorklogForm() {
  if (!worklogSaving.value) worklogFormOpen.value = false;
}

function openPeopleModal() {
  if (!task.value) return;
  statusMenuOpen.value = false;
  peopleForm.assignee_id = task.value.assignee_id || '';
  peopleForm.manager_id = task.value.manager_id || '';
  peopleForm.watcher_ids = (task.value.watchers || []).map((person) => person.id);
  peopleForm.collaborator_ids = (task.value.collaborators || []).map((person) => person.id);
  peopleOpen.value = true;
}

function closePeopleModal() {
  if (!peopleSaving.value) peopleOpen.value = false;
}

async function savePeople() {
  if (!task.value) return;
  peopleSaving.value = true;
  try {
    const { data } = await window.axios.put(`/api/project/tasks/${task.value.id}`, {
      assignee_id: peopleForm.assignee_id || null,
      manager_id: peopleForm.manager_id || null,
      watcher_ids: peopleForm.watcher_ids,
      collaborator_ids: peopleForm.collaborator_ids,
    });
    applyTask(data.task);
    peopleOpen.value = false;
    showClientToast('success', 'Đã cập nhật người tham gia.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được người tham gia.');
  } finally {
    peopleSaving.value = false;
  }
}

async function confirmDelete() {
  if (!task.value) return;
  deleting.value = true;
  try {
    await window.axios.delete(`/api/project/tasks/${task.value.id}`);
    showClientToast('success', 'Đã xoá công việc.');
    goBack();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được công việc — có thể còn công việc con.');
  } finally {
    deleting.value = false;
    confirmingDelete.value = false;
  }
}

function triggerAttachmentInput() {
  attachmentInput.value?.click();
}

function isAttachmentSelected(id) {
  return selectedAttachmentIds.value.includes(id);
}

function toggleAttachment(id) {
  if (selectedAttachmentIds.value.includes(id)) {
    selectedAttachmentIds.value = selectedAttachmentIds.value.filter((item) => item !== id);
    return;
  }
  selectedAttachmentIds.value = [...selectedAttachmentIds.value, id];
}

function toggleAllAttachments() {
  selectedAttachmentIds.value = allAttachmentsSelected.value ? [] : attachments.value.map((item) => item.id);
}

function downloadAttachment(att) {
  if (!att?.file_url) return;
  const link = document.createElement('a');
  link.href = att.file_url;
  link.download = att.file_name || 'file';
  link.rel = 'noopener';
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function downloadSelectedAttachments() {
  const items = selectedAttachments.value.filter((item) => item.file_url);
  if (!items.length) return;
  items.forEach((att, index) => {
    window.setTimeout(() => downloadAttachment(att), index * 250);
  });
}

function closeFileMenu() {
  fileMenuOpen.value = false;
  fileMenuAttachment.value = null;
}

function detachFileMenuListeners() {
  document.removeEventListener('pointerdown', onFileMenuPointerDown, true);
  document.removeEventListener('keydown', onFileMenuKeydown);
}

function onFileMenuPointerDown(event) {
  if (!fileMenuOpen.value) return;
  if (fileMenuRoot.value?.contains(event.target)) return;
  closeFileMenu();
}

function onFileMenuKeydown(event) {
  if (event.key === 'Escape' && fileMenuOpen.value) {
    event.preventDefault();
    closeFileMenu();
  }
}

async function openFileMenu(event, att) {
  event.preventDefault();
  event.stopPropagation();
  statusMenuOpen.value = false;
  fileMenuAttachment.value = att;
  fileMenuOpen.value = true;
  fileMenuPos.x = event.clientX;
  fileMenuPos.y = event.clientY;
  await nextTick();
  const el = fileMenuRoot.value;
  const w = el?.offsetWidth || 220;
  const h = el?.offsetHeight || 220;
  const pad = 8;
  fileMenuPos.x = Math.min(Math.max(pad, event.clientX), Math.max(pad, window.innerWidth - w - pad));
  fileMenuPos.y = Math.min(Math.max(pad, event.clientY), Math.max(pad, window.innerHeight - h - pad));
  el?.focus?.();
}

function viewAttachment(att) {
  if (!att?.file_url) return;
  window.open(att.file_url, '_blank', 'noopener');
}

function triggerReplaceInput() {
  const att = fileMenuAttachment.value;
  if (!att) return;
  replaceTargetId.value = att.id;
  closeFileMenu();
  window.setTimeout(() => replaceInput.value?.click(), 0);
}

async function onReplaceChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  const current = attachments.value.find((item) => item.id === replaceTargetId.value);
  replaceTargetId.value = null;
  if (!file || !current) return;
  replacingAttachment.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await window.axios.post(`/api/project/tasks/attachments/${current.id}/replace`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    attachments.value = attachments.value.map((item) => (item.id === current.id ? data.attachment : item));
    showClientToast('success', 'Đã cập nhật tệp đính kèm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được tệp đính kèm.');
  } finally {
    replacingAttachment.value = false;
  }
}

function startRenameAttachment(att) {
  if (!att) return;
  closeFileMenu();
  renameTarget.value = att;
  renameForm.file_name = att.file_name || '';
  renaming.value = true;
}

function startDeleteAttachment(att) {
  if (!att) return;
  closeFileMenu();
  selectedAttachmentIds.value = [att.id];
  confirmingDeleteAttachments.value = true;
}

function openRenameDialog() {
  startRenameAttachment(fileMenuAttachment.value);
}

function cancelRename() {
  if (savingRename.value) return;
  renaming.value = false;
  renameTarget.value = null;
}

async function saveRename() {
  const att = renameTarget.value;
  const name = renameForm.file_name.trim();
  if (!att || !name) return;
  savingRename.value = true;
  try {
    const { data } = await window.axios.put(`/api/project/tasks/attachments/${att.id}`, { file_name: name });
    attachments.value = attachments.value.map((item) => (item.id === att.id ? data.attachment : item));
    renaming.value = false;
    renameTarget.value = null;
    showClientToast('success', 'Đã đổi tên tệp.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được tên tệp.');
  } finally {
    savingRename.value = false;
  }
}

function downloadFromMenu() {
  const att = fileMenuAttachment.value;
  closeFileMenu();
  if (att) downloadAttachment(att);
}

function viewFromMenu() {
  const att = fileMenuAttachment.value;
  closeFileMenu();
  if (att) viewAttachment(att);
}

function deleteFromMenu() {
  startDeleteAttachment(fileMenuAttachment.value);
}

async function confirmDeleteAttachments() {
  const ids = [...selectedAttachmentIds.value];
  if (!ids.length) return;
  deletingAttachments.value = true;
  try {
    await Promise.all(ids.map((id) => window.axios.delete(`/api/project/tasks/attachments/${id}`)));
    attachments.value = attachments.value.filter((item) => !ids.includes(item.id));
    selectedAttachmentIds.value = [];
    showClientToast('success', ids.length === 1 ? 'Đã xoá tệp đính kèm.' : `Đã xoá ${ids.length} tệp đính kèm.`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được một số tệp đính kèm.');
  } finally {
    deletingAttachments.value = false;
    confirmingDeleteAttachments.value = false;
  }
}

async function onAttachmentChange(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !task.value) return;
  attachmentUploading.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await window.axios.post(`/api/project/tasks/${task.value.id}/attachments`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    attachments.value = [data.attachment, ...attachments.value];
    showClientToast('success', 'Đã tải lên tệp đính kèm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải lên được tệp đính kèm.');
  } finally {
    attachmentUploading.value = false;
  }
}

function openWorklogForm() {
  statusMenuOpen.value = false;
  worklogForm.work_date = new Date().toISOString().slice(0, 10);
  worklogForm.hours = '';
  worklogForm.note = '';
  worklogFormOpen.value = true;
}

async function saveWorklog() {
  if (!task.value) return;
  worklogSaving.value = true;
  try {
    const payload = {
      work_date: worklogForm.work_date,
      hours: Number(worklogForm.hours),
      note: worklogForm.note || null,
    };
    const { data } = await window.axios.post(`/api/project/tasks/${task.value.id}/worklogs`, payload);
    worklogs.value = [data.worklog, ...worklogs.value];
    task.value = { ...task.value, worklog_hours: Number(task.value.worklog_hours || 0) + payload.hours };
    worklogFormOpen.value = false;
    showClientToast('success', 'Đã thêm giờ làm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không thêm được giờ làm.');
  } finally {
    worklogSaving.value = false;
  }
}

async function reportComplete() {
  if (!task.value || !canReportComplete.value) return;
  reportCompleteSaving.value = true;
  try {
    const { data } = await window.axios.post(`/api/project/tasks/${task.value.id}/report-complete`);
    task.value = { ...task.value, ...data.task };
    showClientToast('success', 'Đã báo cáo hoàn thành công việc.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không báo cáo được hoàn thành.');
  } finally {
    reportCompleteSaving.value = false;
  }
}

function openScoreForm() {
  if (!task.value) return;
  const score = task.value.task_score;
  scoreForm.rating_score = score?.rating_score ?? '';
  scoreForm.rating_result = score?.rating_result ?? '';
  scoreForm.is_passed = typeof score?.is_passed === 'boolean' ? score.is_passed : null;
  scoreForm.rating_desc = score?.rating_desc ?? '';
  scoreFormOpen.value = true;
  loadScoreQualityLevels();
}

async function loadScoreQualityLevels() {
  scoreQualityLevels.value = [];
  const departmentId = task.value?.assignee_department_id;
  if (!departmentId) return;
  scoreQualityLevelsLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/score-kit/quality-levels', {
      params: { department_id: departmentId },
    });
    scoreQualityLevels.value = Array.isArray(data?.quality_levels) ? data.quality_levels : [];
  } catch {
    // Không lấy được thang chấm điểm (chưa cấu hình, lỗi mạng…) → fallback ô
    // text tự do như trước, không chặn luồng chấm điểm.
    scoreQualityLevels.value = [];
  } finally {
    scoreQualityLevelsLoading.value = false;
  }
}

function closeScoreForm() {
  if (!scoreSaving.value) scoreFormOpen.value = false;
}

async function saveScore() {
  if (!task.value) return;
  scoreSaving.value = true;
  try {
    const payload = {
      rating_score: scoreForm.rating_score === '' ? null : Number(scoreForm.rating_score),
      rating_result: scoreForm.rating_result || null,
      is_passed: scoreForm.is_passed,
      rating_desc: scoreForm.rating_desc || null,
    };
    const { data } = await window.axios.put(`/api/project/tasks/${task.value.id}/score`, payload);
    task.value = {
      ...task.value,
      task_score: data.task_score,
      failed_review_count: payload.is_passed === false
        ? Number(task.value.failed_review_count || 0) + 1
        : task.value.failed_review_count,
    };
    scoreFormOpen.value = false;
    showClientToast('success', 'Đã lưu đánh giá.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được đánh giá.');
  } finally {
    scoreSaving.value = false;
  }
}

function canEditWorklog(log) {
  return log.user_id === auth.user?.id || auth.can('task.approve');
}

async function confirmDeleteWorklog() {
  const log = confirmingDeleteWorklog.value;
  if (!log) return;
  try {
    await window.axios.delete(`/api/project/tasks/worklogs/${log.id}`);
    worklogs.value = worklogs.value.filter((item) => item.id !== log.id);
    if (task.value) {
      task.value = { ...task.value, worklog_hours: Math.max(0, Number(task.value.worklog_hours || 0) - Number(log.hours)) };
    }
    showClientToast('success', 'Đã xoá nhật ký giờ làm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được nhật ký giờ làm.');
  } finally {
    confirmingDeleteWorklog.value = null;
  }
}

function handleDocumentClick(event) {
  if (!statusMenuOpen.value) return;
  const inTrigger = statusMenuRoot.value?.contains(event.target);
  const inMenu = statusMenuEl.value?.contains(event.target);
  if (!inTrigger && !inMenu) statusMenuOpen.value = false;
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') {
    statusMenuOpen.value = false;
    if (renaming.value && !savingRename.value) cancelRename();
    if (worklogFormOpen.value) closeWorklogForm();
    if (scoreFormOpen.value) closeScoreForm();
  }
}

watch(fileMenuOpen, (open) => {
  detachFileMenuListeners();
  if (open) {
    document.addEventListener('pointerdown', onFileMenuPointerDown, true);
    document.addEventListener('keydown', onFileMenuKeydown);
  }
});

watch(renaming, async (open) => {
  if (!open) return;
  await nextTick();
  renameInput.value?.focus();
  renameInput.value?.select();
});

watch(statusMenuOpen, (open) => {
  if (open) {
    window.addEventListener('resize', positionStatusMenu);
    window.addEventListener('scroll', positionStatusMenu, true);
    return;
  }
  window.removeEventListener('resize', positionStatusMenu);
  window.removeEventListener('scroll', positionStatusMenu, true);
});

watch(() => route.params.id, loadTask);
onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  loadOptions();
  loadTask();
});
onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
  window.removeEventListener('resize', positionStatusMenu);
  window.removeEventListener('scroll', positionStatusMenu, true);
  detachFileMenuListeners();
});
</script>

<template>
  <section class="task-detail">
    <svg class="task-detail__wm-defs" aria-hidden="true" focusable="false">
      <filter id="task-detail-page-tint" color-interpolation-filters="sRGB">
        <feColorMatrix type="matrix" values="0 0 0 0 0.090  0 0 0 0 0.314  0 0 0 0 0.710  0 0 0 20 0" />
      </filter>
    </svg>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="task-detail__page-watermark"
      aria-hidden="true"
      :style="{ filter: 'url(#task-detail-page-tint)' }"
    />
    <PageHeader
      :title="task?.title || 'Chi tiết công việc'"
      icon="layoutList"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Tất cả công việc', to: { name: 'manager.project.tasks' } },
        { label: task?.title || 'Chi tiết' },
      ]"
    >
      <template #title>
        {{ task?.title || 'Chi tiết công việc' }}
        <GuideHelp :guide="SCORE_FACTOR_GUIDES.taskList" />
      </template>
      <template #actions>
        <button type="button" class="task-detail__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Quay lại
        </button>
      </template>
    </PageHeader>

    <p v-if="loading" class="task-detail__empty">Đang tải chi tiết công việc…</p>
    <p v-else-if="notFound" class="task-detail__empty">Không tìm thấy công việc.</p>

    <div v-else-if="task" class="task-detail__page">
      <div class="task-detail__toolbar">
        <div class="task-detail__tabs hide-scrollbar" role="tablist" aria-label="Nhóm thông tin công việc">
          <button
            type="button"
            role="tab"
            class="task-detail__tab"
            :class="{ 'task-detail__tab--active': infoTab === 'general' }"
            :aria-selected="infoTab === 'general'"
            aria-controls="task-detail-info-panel"
            @click="infoTab = 'general'"
          >
            Thông tin chung
          </button>
          <button
            type="button"
            role="tab"
            class="task-detail__tab"
            :class="{ 'task-detail__tab--active': infoTab === 'more' }"
            :aria-selected="infoTab === 'more'"
            aria-controls="task-detail-info-panel"
            @click="infoTab = 'more'"
          >
            Tiến độ và quyền
          </button>
        </div>
        <div class="task-detail__actions">
          <button
            v-if="canReportComplete"
            type="button"
            class="task-detail__action task-detail__action--success"
            :disabled="reportCompleteSaving"
            aria-label="Báo cáo hoàn thành công việc"
            @click="reportComplete"
          >
            <span class="task-detail__action-icon">
              <AppIcon name="check" :size="15" :stroke-width="1.75" />
            </span>
            <span class="task-detail__action-label">{{ reportCompleteSaving ? 'Đang gửi…' : 'Báo cáo hoàn thành' }}</span>
          </button>
          <div ref="statusMenuRoot" class="task-detail__action-wrap">
            <button
              type="button"
              class="task-detail__action"
              :class="{ 'task-detail__action--open': statusMenuOpen }"
              :disabled="!canEdit || statusSaving"
              :aria-expanded="statusMenuOpen"
              aria-haspopup="menu"
              aria-controls="task-detail-status-menu"
              aria-label="Cập nhật trạng thái"
              @click="toggleStatusMenu"
            >
              <span class="task-detail__action-icon">
                <AppIcon name="pauseCircle" :size="15" :stroke-width="1.75" />
              </span>
              <span class="task-detail__action-label">{{ statusSaving ? 'Đang đổi…' : 'Trạng thái' }}</span>
            </button>
          </div>
          <button
            type="button"
            class="task-detail__action"
            aria-label="Thêm giờ làm"
            @click="canEdit ? openWorklogForm() : focusWorklog()"
          >
            <span class="task-detail__action-icon">
              <AppIcon name="clock" :size="15" :stroke-width="1.75" />
            </span>
            <span class="task-detail__action-label">Thêm giờ làm</span>
          </button>
          <button
            type="button"
            class="task-detail__action"
            :disabled="!canEdit"
            aria-label="Thêm người thực hiện"
            @click="openPeopleModal"
          >
            <span class="task-detail__action-icon">
              <AppIcon name="userPlus" :size="15" :stroke-width="1.75" />
            </span>
            <span class="task-detail__action-label">Thực hiện</span>
          </button>
          <template v-if="canEdit">
            <button type="button" class="task-detail__action" aria-label="Chỉnh sửa công việc" @click="goEdit">
              <span class="task-detail__action-icon">
                <AppIcon name="pencil" :size="15" :stroke-width="1.75" />
              </span>
              <span class="task-detail__action-label">Chỉnh sửa</span>
            </button>
            <button type="button" class="task-detail__action task-detail__action--danger" aria-label="Xoá công việc" @click="confirmingDelete = true">
              <span class="task-detail__action-icon">
                <AppIcon name="trash" :size="15" :stroke-width="1.75" />
              </span>
              <span class="task-detail__action-label">Xoá</span>
            </button>
          </template>
        </div>
      </div>

      <div class="task-detail__summary">
        <span class="task-detail__chip" :class="`task-detail__chip--${TASK_STATUS_TONES[task.status] || 'tertiary'}`">
          {{ statusLabel(task.status) }}
        </span>
        <span class="task-detail__summary-item">
          <AppIcon name="flag" :size="14" :class="`task-detail__flag--${priorityTone(task.priority)}`" />
          {{ priorityLabel(task.priority) }}
        </span>
        <span v-if="evalProgressPercent != null" class="task-detail__summary-item task-detail__summary-item--progress">
          <span class="task-detail__summary-track" aria-hidden="true">
            <span class="task-detail__summary-bar" :style="{ width: `${evalProgressPercent}%` }" />
          </span>
          {{ evalProgressPercent }}%
        </span>
        <span class="task-detail__summary-item">
          <AppIcon name="calendar" :size="14" />
          {{ plannedTimeLabel }}
        </span>
        <span v-if="task.assignee" class="task-detail__summary-item">
          <UserAvatarTip :user="task.assignee" label="Người thực hiện" />
          {{ task.assignee.name }}
        </span>
        <span v-if="task.parent" class="task-detail__summary-item">
          <AppIcon name="gitBranch" :size="14" />
          Việc cha: {{ task.parent.title }}
        </span>
        <span v-if="task.children?.length" class="task-detail__summary-item">
          {{ task.children.length }} công việc con
        </span>
      </div>

      <TaskEvalFactors
        :task="task"
        :difficulty-label="priorityLabel(task.priority)"
        :difficulty-locked="lockDifficulty && Boolean(task.assignee_id)"
        :progress-levels="progressLevels"
        :can-approve="canApprove"
        :can-edit="canEdit"
        :weighted="scoreKitMode === 'weighted_task'"
        @evaluate="openScoreForm"
        @edit-difficulty="goEdit"
      />

      <div class="task-detail__layout">
        <div class="task-detail__col">
      <section class="task-detail__card task-detail__card--lead">
        <header class="task-detail__section-head">
          <span class="task-detail__section-icon task-detail__section-icon--primary" aria-hidden="true">
            <AppIcon :name="infoTabMeta.icon" :size="14" :stroke-width="1.75" />
          </span>
          <h3 class="task-detail__section-title">{{ infoTabMeta.title }}</h3>
        </header>
        <dl id="task-detail-info-panel" class="task-detail__kv task-detail__kv--cols" role="tabpanel">
          <template v-if="infoTab === 'general'">
            <div class="task-detail__kv-row task-detail__kv-row--span">
              <dt class="task-detail__label">Tên công việc</dt>
              <dd class="task-detail__value">{{ task.title || '--' }}</dd>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Mã công việc</dt>
                <dd class="task-detail__value">{{ task.code || '--' }}</dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Loại công việc</dt>
                <dd class="task-detail__value">{{ typeLabel(task.type) }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Dự án</dt>
                <dd class="task-detail__value">
                  <router-link
                    v-if="task.project"
                    class="task-detail__link"
                    :to="{ name: 'manager.project.edit', params: { id: task.project.id } }"
                  >
                    {{ task.project.code ? `${task.project.code} - ${task.project.name}` : task.project.name }}
                  </router-link>
                  <template v-else>Công việc thường xuyên</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Trạng thái</dt>
                <dd class="task-detail__value">
                  <span class="task-detail__chip" :class="`task-detail__chip--${TASK_STATUS_TONES[task.status] || 'tertiary'}`">
                    {{ statusLabel(task.status) }}
                  </span>
                </dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Thời gian dự kiến</dt>
                <dd class="task-detail__value">{{ plannedTimeLabel }}</dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Thời gian thực tế</dt>
                <dd class="task-detail__value">{{ actualTimeLabel }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Người giao việc</dt>
                <dd class="task-detail__value">
                  <span v-if="task.manager" class="task-detail__user-chip">
                    <UserAvatarTip :user="task.manager" label="Người giao việc" />
                    {{ task.manager.name }}
                  </span>
                  <template v-else>--</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Người thực hiện</dt>
                <dd class="task-detail__value">
                  <span v-if="task.assignee" class="task-detail__user-chip">
                    <UserAvatarTip :user="task.assignee" label="Người thực hiện" />
                    {{ task.assignee.name }}
                  </span>
                  <template v-else>--</template>
                </dd>
              </div>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--span">
              <dt class="task-detail__label">Người theo dõi</dt>
              <dd class="task-detail__value">
                <span v-if="task.watchers?.length" class="task-detail__people">
                  <span v-for="person in task.watchers" :key="`w-${person.id}`" class="task-detail__user-chip">
                    <UserAvatarTip :user="person" label="Người theo dõi" />
                    {{ person.name }}
                  </span>
                </span>
                <template v-else>--</template>
              </dd>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--span">
              <dt class="task-detail__label">Người phối hợp</dt>
              <dd class="task-detail__value">
                <span v-if="task.collaborators?.length" class="task-detail__people">
                  <span v-for="person in task.collaborators" :key="`c-${person.id}`" class="task-detail__user-chip">
                    <UserAvatarTip :user="person" label="Người phối hợp" />
                    {{ person.name }}
                  </span>
                </span>
                <template v-else>--</template>
              </dd>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Phòng ban</dt>
                <dd class="task-detail__value">{{ task.department?.name || '--' }}</dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Độ khó</dt>
                <dd class="task-detail__value">
                  <span class="task-detail__priority">
                    <AppIcon name="flag" :size="14" :class="`task-detail__flag--${priorityTone(task.priority)}`" />
                    {{ priorityLabel(task.priority) }}
                  </span>
                </dd>
              </div>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--span">
              <dt class="task-detail__label">Mô tả</dt>
              <dd class="task-detail__value">{{ task.description || '--' }}</dd>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--stack">
              <dt class="task-detail__label">Công việc cha</dt>
              <dd class="task-detail__value">
                <article v-if="task.parent" class="task-detail__related-card">
                  <router-link
                    class="task-detail__related-title"
                    :to="{ name: 'manager.project.tasks.detail', params: { id: task.parent.id } }"
                  >
                    {{ relatedTitle(task.parent) }}
                  </router-link>
                  <span class="task-detail__related-meta">
                    <span
                      class="task-detail__chip"
                      :class="`task-detail__chip--${TASK_STATUS_TONES[task.parent.status] || 'tertiary'}`"
                    >
                      {{ statusLabel(task.parent.status) }}
                    </span>
                    <span class="task-detail__priority">
                      <AppIcon name="flag" :size="14" :class="`task-detail__flag--${priorityTone(task.parent.priority)}`" />
                      {{ task.parent.priority_label || priorityLabel(task.parent.priority) }}
                    </span>
                    <span v-if="task.parent.assignee" class="task-detail__child-stat">{{ task.parent.assignee.name }}</span>
                  </span>
                </article>
                <p v-else class="task-detail__related-empty">Không có — đây là việc gốc.</p>
              </dd>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--stack">
              <dt class="task-detail__label">Công việc con</dt>
              <dd class="task-detail__value">
                <div class="task-detail__related">
                  <div v-if="canEdit" class="task-detail__related-toolbar">
                    <button type="button" class="task-detail__related-add" @click="goCreateChild">
                      <AppIcon name="plus" :size="14" :stroke-width="1.75" />
                      Thêm công việc con
                    </button>
                  </div>
                  <div v-if="task.children?.length" class="task-detail__child-table" role="table">
                    <div class="task-detail__child-head" role="row">
                      <span role="columnheader">Tên công việc</span>
                      <span role="columnheader">Trạng thái</span>
                      <span role="columnheader">Độ khó</span>
                      <span role="columnheader">Người làm</span>
                      <span role="columnheader">Tiến độ</span>
                    </div>
                    <div
                      v-for="child in task.children"
                      :key="child.id"
                      class="task-detail__child-row"
                      role="row"
                      @contextmenu.prevent.stop="openChildContextMenu($event, child)"
                    >
                      <router-link
                        class="task-detail__link task-detail__child-title"
                        role="cell"
                        :to="{ name: 'manager.project.tasks.detail', params: { id: child.id } }"
                      >
                        <span v-if="child.code" class="task-detail__child-code">{{ child.code }}</span>
                        {{ child.title }}
                      </router-link>
                      <span class="task-detail__child-cell" role="cell">
                        <span
                          class="task-detail__chip"
                          :class="`task-detail__chip--${TASK_STATUS_TONES[child.status] || 'tertiary'}`"
                        >
                          {{ statusLabel(child.status) }}
                        </span>
                      </span>
                      <span class="task-detail__child-cell" role="cell">
                        <select
                          v-if="canEdit && !childDifficultyLocked(child)"
                          class="task-detail__child-select"
                          :value="child.priority || ''"
                          :disabled="Boolean(childDifficultySaving[child.id])"
                          :aria-label="`Độ khó của ${child.title}`"
                          @change="changeChildDifficulty(child, $event.target.value)"
                        >
                          <option value="">Chưa chọn</option>
                          <option
                            v-for="opt in childDifficultyChoices(child)"
                            :key="opt.value"
                            :value="opt.value"
                          >
                            {{ opt.label }}
                          </option>
                        </select>
                        <span v-else class="task-detail__priority">
                          <AppIcon name="flag" :size="14" :class="`task-detail__flag--${priorityTone(child.priority)}`" />
                          {{ child.priority_label || priorityLabel(child.priority) || 'Chưa chọn' }}
                        </span>
                      </span>
                      <span class="task-detail__child-cell task-detail__child-stat" role="cell">
                        {{ child.assignee?.name || '--' }}
                      </span>
                      <span class="task-detail__child-cell task-detail__child-stat" role="cell">
                        {{ child.progress_percent != null ? `${child.progress_percent}%` : '--' }}
                      </span>
                    </div>
                  </div>
                  <p v-else class="task-detail__related-empty">Chưa có công việc con.</p>
                </div>
              </dd>
            </div>
          </template>
          <template v-else>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Cách tính tiến độ công việc</dt>
                <dd class="task-detail__value">{{ progressTypeLabel(task.progress_type) }}</dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Tiến độ</dt>
                <dd class="task-detail__value">
                  <div v-if="evalProgressPercent != null" class="task-detail__eval-progress">
                    <div class="task-detail__eval-track">
                      <div class="task-detail__eval-bar" :style="{ width: `${evalProgressPercent}%` }" />
                      <span class="task-detail__eval-knob" :style="{ left: `${evalProgressPercent}%` }">
                        {{ evalProgressPercent }}
                      </span>
                    </div>
                    <span class="task-detail__eval-clock" aria-hidden="true">
                      <AppIcon name="clock" :size="14" :stroke-width="1.75" />
                    </span>
                  </div>
                  <p v-if="task.progress_type === 'quantity'" class="task-detail__progress-qty">
                    {{ task.progress_number }} / {{ task.progress_total }} {{ task.unit }}
                  </p>
                  <template v-if="evalProgressPercent == null">--</template>
                </dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Số giờ dự kiến</dt>
                <dd class="task-detail__value">{{ formatHours(task.estimated_hours) }}</dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Tỷ trọng</dt>
                <dd class="task-detail__value">{{ formatWeight(task.weight) }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Người đã nhận</dt>
                <dd class="task-detail__value">
                  <span v-if="task.accepted_by_user" class="task-detail__user-chip">
                    <UserAvatarTip :user="task.accepted_by_user" label="Người đã nhận" />
                    {{ task.accepted_by_user.name }}
                  </span>
                  <template v-else>--</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Thời điểm nhận</dt>
                <dd class="task-detail__value">{{ formatDateTime(task.accepted_at) }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Quá hạn</dt>
                <dd class="task-detail__value">
                  <span v-if="task.is_overdue" class="task-detail__chip task-detail__chip--danger">Quá hạn</span>
                  <template v-else>Không</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Chênh lệch ngày</dt>
                <dd class="task-detail__value">{{ formatVariance(task.variance_days) }}</dd>
              </div>
            </div>
            <template v-if="hasDelegation">
              <div class="task-detail__kv-row">
                <div class="task-detail__kv-cell">
                  <dt class="task-detail__label">Chuyển giao</dt>
                  <dd class="task-detail__value">{{ delegationLabel(task.delegation_status) }}</dd>
                </div>
                <div class="task-detail__kv-cell">
                  <dt class="task-detail__label">Người tiếp nhận</dt>
                  <dd class="task-detail__value">
                    <span v-if="task.delegated_to_employee" class="task-detail__user-chip">
                      <UserAvatarTip :user="task.delegated_to_employee" label="Người tiếp nhận" />
                      {{ task.delegated_to_employee.name }}
                    </span>
                    <template v-else>--</template>
                  </dd>
                </div>
              </div>
              <div class="task-detail__kv-row">
                <div class="task-detail__kv-cell">
                  <dt class="task-detail__label">Phòng gốc</dt>
                  <dd class="task-detail__value">{{ task.origin_department?.name || '--' }}</dd>
                </div>
                <div class="task-detail__kv-cell">
                  <dt class="task-detail__label">Phòng tiếp nhận</dt>
                  <dd class="task-detail__value">{{ task.delegated_to_department?.name || '--' }}</dd>
                </div>
              </div>
            </template>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Người tạo</dt>
                <dd class="task-detail__value">
                  <span v-if="task.creator" class="task-detail__user-chip">
                    <UserAvatarTip :user="task.creator" label="Người tạo" />
                    {{ task.creator.name }}
                  </span>
                  <template v-else>--</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Ngày tạo</dt>
                <dd class="task-detail__value">{{ formatDateTime(task.created_at) }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row">
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Người cập nhật</dt>
                <dd class="task-detail__value">
                  <span v-if="task.updater" class="task-detail__user-chip">
                    <UserAvatarTip :user="task.updater" label="Người cập nhật" />
                    {{ task.updater.name }}
                  </span>
                  <template v-else>--</template>
                </dd>
              </div>
              <div class="task-detail__kv-cell">
                <dt class="task-detail__label">Ngày cập nhật</dt>
                <dd class="task-detail__value">{{ formatDateTime(task.updated_at) }}</dd>
              </div>
            </div>
            <div class="task-detail__kv-row task-detail__kv-row--span">
              <dt class="task-detail__label">Quyền công việc</dt>
              <dd class="task-detail__value">
                <ul class="task-detail__rules">
                  <li
                    v-for="line in permissionLines"
                    :key="line.key"
                    :class="{ 'task-detail__rules-item--off': !line.on }"
                  >
                    <AppIcon
                      :name="line.on ? 'check' : 'minus'"
                      :size="14"
                      class="task-detail__rule-icon"
                      :class="{ 'task-detail__rule-icon--off': !line.on }"
                    />
                    <span>
                      <span class="task-detail__rule-name">{{ line.label }}</span>
                      {{ line.value }}
                    </span>
                  </li>
                </ul>
              </dd>
            </div>
          </template>
        </dl>
      </section>

      <section ref="worklogSection" class="task-detail__card task-detail__card--worklog">
        <svg class="task-detail__wm-defs" aria-hidden="true" focusable="false">
          <filter id="task-detail-worklog-tint" color-interpolation-filters="sRGB">
            <feColorMatrix type="matrix" values="0 0 0 0 0  0 0 0 0 0.565  0 0 0 0 0.510  0 0 0 20 0" />
          </filter>
        </svg>
        <img
          src="/images/background/background-logo.png"
          alt=""
          class="task-detail__worklog-watermark"
          aria-hidden="true"
          :style="{ filter: 'url(#task-detail-worklog-tint)' }"
        />
        <div class="task-detail__worklog-inner">
          <header class="task-detail__section-head task-detail__section-head--worklog">
            <div class="task-detail__worklog-heading">
              <span class="task-detail__worklog-icon" aria-hidden="true">
                <AppIcon name="clock" :size="14" :stroke-width="1.75" />
              </span>
              <div>
                <h3 class="task-detail__section-title">Nhật ký giờ làm</h3>
                <p v-if="worklogs.length" class="task-detail__worklog-total">
                  Tổng {{ worklogTotalHours }} giờ đã ghi nhận
                </p>
              </div>
            </div>
          </header>

          <ul v-if="worklogs.length" class="task-detail__worklog-list">
            <li v-for="log in worklogs" :key="log.id" class="task-detail__worklog-row">
              <span class="task-detail__worklog-hours">{{ log.hours }}<small>giờ</small></span>
              <span class="task-detail__worklog-body">
                <span class="task-detail__worklog-meta">
                  <span class="task-detail__person">
                    <UserAvatarTip v-if="log.user" :user="log.user" label="Người ghi giờ" />
                    {{ log.user?.name || '--' }}
                  </span>
                  <span class="task-detail__worklog-date">
                    <AppIcon name="calendar" :size="12" />
                    {{ formatDate(log.work_date) }}
                  </span>
                </span>
                <span class="task-detail__worklog-note">{{ log.note || 'Không có ghi chú' }}</span>
              </span>
              <button
                v-if="canEditWorklog(log)"
                type="button"
                class="task-detail__icon-btn"
                aria-label="Xoá nhật ký giờ làm"
                @click="confirmingDeleteWorklog = log"
              >
                <AppIcon name="trash" :size="14" />
              </button>
            </li>
          </ul>
          <p v-else class="task-detail__empty">Chưa có nhật ký giờ làm.</p>
        </div>
      </section>

          <div class="task-detail__pair">
          <section class="task-detail__card">
            <header class="task-detail__section-head">
              <span class="task-detail__section-icon task-detail__section-icon--tertiary" aria-hidden="true">
                <AppIcon name="paperclip" :size="14" :stroke-width="1.75" />
              </span>
              <h3 class="task-detail__section-title">Đính kèm</h3>
              <span class="task-detail__section-count">{{ attachments.length }}</span>
              <button
                v-if="canEdit"
                type="button"
                class="task-detail__btn task-detail__btn--ghost"
                :disabled="attachmentUploading"
                @click="triggerAttachmentInput"
              >
                {{ attachmentUploading ? 'Đang tải…' : 'Tải file' }}
              </button>
            </header>
            <input ref="attachmentInput" type="file" class="task-detail__hidden" @change="onAttachmentChange" />
            <input
              ref="replaceInput"
              type="file"
              class="task-detail__hidden"
              :disabled="replacingAttachment"
              @change="onReplaceChange"
            />
            <div
              v-if="attachments.length"
              class="task-detail__files"
              :class="{ 'task-detail__files--picking': selectedAttachmentIds.length }"
            >
              <div class="task-detail__file-head">
                <label class="task-detail__file-check">
                  <input
                    type="checkbox"
                    :checked="allAttachmentsSelected"
                    :indeterminate="selectedAttachmentIds.length > 0 && !allAttachmentsSelected"
                    aria-label="Chọn tất cả tệp"
                    @change="toggleAllAttachments"
                  />
                </label>
                <span>Tệp tin</span>
                <div v-if="selectedAttachmentIds.length" class="task-detail__file-bulk">
                  <button type="button" class="task-detail__bulk-btn" @click="downloadSelectedAttachments">
                    <AppIcon name="download" :size="14" :stroke-width="1.75" />
                    Tải xuống
                  </button>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="task-detail__bulk-btn task-detail__bulk-btn--danger"
                    @click="confirmingDeleteAttachments = true"
                  >
                    <AppIcon name="trash" :size="14" :stroke-width="1.75" />
                    Xóa
                  </button>
                </div>
              </div>
              <article
                v-for="att in attachments"
                :key="att.id"
                class="task-detail__file"
                :class="{ 'task-detail__file--checked': isAttachmentSelected(att.id) }"
                @click="toggleAttachment(att.id)"
                @contextmenu="openFileMenu($event, att)"
              >
                <label class="task-detail__file-check" @click.stop>
                  <input
                    type="checkbox"
                    :checked="isAttachmentSelected(att.id)"
                    :aria-label="`Chọn ${att.file_name}`"
                    @change="toggleAttachment(att.id)"
                  />
                </label>
                <a :href="att.file_url" class="task-detail__file-main" target="_blank" rel="noopener" @click.stop>
                  <span class="task-detail__file-thumb" :class="`task-detail__file-thumb--${fileExtTone(att.file_name)}`">
                    {{ fileExt(att.file_name) }}
                  </span>
                  <span class="task-detail__file-copy">
                    <span class="task-detail__file-name">{{ att.file_name }}</span>
                    <span class="task-detail__file-sub">{{ task.title }}</span>
                    <span class="task-detail__file-meta">
                      <template v-if="formatFileSize(att.file_size)">{{ formatFileSize(att.file_size) }} · </template>{{ formatDateTime(att.created_at) }}
                    </span>
                  </span>
                </a>
                <UserAvatarTip v-if="att.uploader" :user="att.uploader" label="Người tạo" />
              </article>
            </div>
            <p v-else class="task-detail__empty">Chưa có tệp đính kèm.</p>
          </section>

          <section class="task-detail__card">
            <header class="task-detail__section-head">
              <span class="task-detail__section-icon task-detail__section-icon--gold" aria-hidden="true">
                <AppIcon name="starFilled" :size="14" :stroke-width="1.75" />
              </span>
              <h3 class="task-detail__section-title">Kết quả đánh giá</h3>
              <GuideHelp :guide="SCORE_FACTOR_GUIDES.quality" tone="gold" />
              <button
                v-if="canApprove"
                type="button"
                class="task-detail__btn task-detail__btn--ghost"
                @click="openScoreForm"
              >
                Chấm điểm
              </button>
            </header>
            <dl v-if="hasScore || task.failed_review_count > 0" class="task-detail__kv">
              <div v-if="task.failed_review_count > 0" class="task-detail__kv-row task-detail__kv-row--stack">
                <dt class="task-detail__label">Số lần đánh giá không hoàn thành công việc</dt>
                <dd class="task-detail__value">{{ task.failed_review_count }}</dd>
              </div>
              <template v-if="hasScore">
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Người đánh giá</dt>
                  <dd class="task-detail__value">
                    <span v-if="task.task_score.scorer" class="task-detail__user-chip">
                      <UserAvatarTip :user="task.task_score.scorer" label="Người đánh giá" />
                      {{ task.task_score.scorer.name }}
                    </span>
                    <template v-else>--</template>
                  </dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Thời gian đánh giá</dt>
                  <dd class="task-detail__value">{{ formatScoreDateTime(task.task_score.scored_at) }}</dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Điểm số</dt>
                  <dd class="task-detail__value">{{ task.task_score.rating_score ?? '--' }}</dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Đạt / Không đạt</dt>
                  <dd class="task-detail__value">
                    <span
                      v-if="task.task_score.is_passed === true || task.task_score.is_passed === false"
                      class="task-detail__chip"
                      :class="task.task_score.is_passed ? 'task-detail__chip--success' : 'task-detail__chip--danger'"
                    >
                      {{ passedLabel(task.task_score.is_passed) }}
                    </span>
                    <template v-else>--</template>
                  </dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Trạng thái đánh giá</dt>
                  <dd class="task-detail__value">
                    <span
                      v-if="task.task_score.rating_result"
                      class="task-detail__chip"
                      :class="`task-detail__chip--${ratingResultTone(task.task_score.rating_result)}`"
                    >
                      {{ task.task_score.rating_result }}
                    </span>
                    <template v-else>--</template>
                  </dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Tiến độ</dt>
                  <dd class="task-detail__value">
                    <div v-if="evalProgressPercent != null" class="task-detail__eval-progress">
                      <div class="task-detail__eval-track">
                        <div class="task-detail__eval-bar" :style="{ width: `${evalProgressPercent}%` }" />
                        <span class="task-detail__eval-knob" :style="{ left: `${evalProgressPercent}%` }">
                          {{ evalProgressPercent }}
                        </span>
                      </div>
                      <span class="task-detail__eval-clock" aria-hidden="true">
                        <AppIcon name="clock" :size="14" :stroke-width="1.75" />
                      </span>
                    </div>
                    <template v-else>--</template>
                  </dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Kết quả đánh giá</dt>
                  <dd class="task-detail__value">
                    <div class="task-detail__rating">
                      <span
                        v-for="n in 5"
                        :key="n"
                        class="task-detail__star"
                        :class="{ 'task-detail__star--on': n <= evalStars }"
                      >
                        <AppIcon :name="n <= evalStars ? 'starFilled' : 'star'" :size="16" :stroke-width="1.75" />
                      </span>
                      <span v-if="evalStarCaption" class="task-detail__rating-caption">{{ evalStarCaption }}</span>
                    </div>
                  </dd>
                </div>
                <div class="task-detail__kv-row">
                  <dt class="task-detail__label">Ý kiến đánh giá</dt>
                  <dd class="task-detail__value">{{ task.task_score.rating_desc || '--' }}</dd>
                </div>
              </template>
            </dl>
            <p v-else class="task-detail__empty">Không tìm thấy kết quả nào</p>
          </section>
          </div>
        </div>
        <div class="task-detail__col task-detail__col--discuss">
          <section class="task-detail__card task-detail__card--discuss">
            <header class="task-detail__section-head">
              <span class="task-detail__section-icon task-detail__section-icon--tertiary" aria-hidden="true">
                <AppIcon name="messageCircle" :size="14" :stroke-width="1.75" />
              </span>
              <h3 class="task-detail__section-title">Thảo luận</h3>
              <span class="task-detail__section-count">{{ commentsCount }}</span>
            </header>
            <CommentList
              endpoint-base="/api/project/tasks"
              :commentable-id="task.id"
              :can-create="canEdit"
              layout="sidebar"
              @count-changed="commentsCount = $event"
            />
          </section>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="worklogFormOpen" class="task-detail__dialog" role="presentation" @mousedown.self="closeWorklogForm">
        <div
          class="task-detail__dialog-panel task-detail__dialog-panel--compact"
          role="dialog"
          aria-modal="true"
          aria-labelledby="task-detail-worklog-title"
        >
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon task-detail__dialog-icon--secondary" aria-hidden="true">
              <AppIcon name="clock" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-worklog-title" class="task-detail__dialog-title">Thêm giờ làm</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="worklogSaving" @click="closeWorklogForm">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <form class="task-detail__dialog-body" @submit.prevent="saveWorklog">
            <div class="task-detail__worklog-form">
              <label class="task-detail__field">
                <span>Ngày làm</span>
                <input v-model="worklogForm.work_date" type="date" required />
              </label>
              <label class="task-detail__field">
                <span>Số giờ</span>
                <input v-model="worklogForm.hours" type="number" min="0.25" max="24" step="0.25" required placeholder="Ví dụ: 2" />
              </label>
              <label class="task-detail__field task-detail__field--wide">
                <span>Ghi chú</span>
                <input v-model="worklogForm.note" type="text" placeholder="Đã làm gì trong khoảng thời gian này" />
              </label>
            </div>
          </form>
          <div class="task-detail__dialog-actions">
            <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="worklogSaving" @click="closeWorklogForm">Huỷ</button>
            <button type="button" class="task-detail__btn" :disabled="worklogSaving" @click="saveWorklog">
              {{ worklogSaving ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="scoreFormOpen" class="task-detail__dialog" role="presentation" @mousedown.self="closeScoreForm">
        <div
          class="task-detail__dialog-panel task-detail__dialog-panel--compact"
          role="dialog"
          aria-modal="true"
          aria-labelledby="task-detail-score-title"
        >
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon task-detail__dialog-icon--gold" aria-hidden="true">
              <AppIcon name="starFilled" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-score-title" class="task-detail__dialog-title">Chấm điểm công việc</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="scoreSaving" @click="closeScoreForm">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <form class="task-detail__dialog-body" @submit.prevent="saveScore">
            <div class="task-detail__worklog-form">
              <label class="task-detail__field">
                <span>Kết quả</span>
                <span class="task-detail__choice-list" role="radiogroup" aria-label="Kết quả đánh giá">
                  <button
                    type="button"
                    class="task-detail__choice task-detail__choice--success"
                    :class="{ 'task-detail__choice--on': scoreForm.is_passed === true }"
                    role="radio"
                    :aria-checked="scoreForm.is_passed === true ? 'true' : 'false'"
                    @click="scoreForm.is_passed = true"
                  >
                    <span class="task-detail__choice-dot" aria-hidden="true" />
                    Đạt
                  </button>
                  <button
                    type="button"
                    class="task-detail__choice task-detail__choice--danger"
                    :class="{ 'task-detail__choice--on': scoreForm.is_passed === false }"
                    role="radio"
                    :aria-checked="scoreForm.is_passed === false ? 'true' : 'false'"
                    @click="scoreForm.is_passed = false"
                  >
                    <span class="task-detail__choice-dot" aria-hidden="true" />
                    Không đạt
                  </button>
                </span>
              </label>
              <label class="task-detail__field">
                <span>Điểm số</span>
                <input v-model="scoreForm.rating_score" type="number" min="0" step="0.1" placeholder="Ví dụ: 8.5" />
              </label>
              <label class="task-detail__field task-detail__field--wide">
                <span>Kết quả đánh giá <GuideHelp :guide="SCORE_FACTOR_GUIDES.quality" tone="gold" /></span>
                <select v-if="scoreQualityLevels.length" v-model="scoreForm.rating_result">
                  <option value="">Chưa chọn mức</option>
                  <option v-for="level in scoreQualityLevels" :key="level.code || level.label" :value="level.label || level.code">
                    {{ level.label || level.code }}
                  </option>
                  <!-- Giá trị đã chấm trước đây (text tự do, chưa khớp thang hiện
                       tại) — vẫn hiện để không mất/che dữ liệu cũ khi mở lại form. -->
                  <option v-if="scoreForm.rating_result && !scoreQualityLevels.some((l) => (l.label || l.code) === scoreForm.rating_result)" :value="scoreForm.rating_result">
                    {{ scoreForm.rating_result }} (giá trị cũ)
                  </option>
                </select>
                <template v-else>
                  <input v-model="scoreForm.rating_result" type="text" list="task-score-result-suggestions" maxlength="100" placeholder="Ví dụ: Đạt, Xuất sắc" />
                  <datalist id="task-score-result-suggestions">
                    <option v-for="item in TASK_SCORE_RESULT_SUGGESTIONS" :key="item" :value="item" />
                  </datalist>
                </template>
              </label>
              <label class="task-detail__field task-detail__field--wide">
                <span>Ý kiến đánh giá</span>
                <textarea v-model="scoreForm.rating_desc" rows="3" maxlength="5000" placeholder="Nhận xét ngắn về kết quả thực hiện…" />
              </label>
            </div>
          </form>
          <div class="task-detail__dialog-actions">
            <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="scoreSaving" @click="closeScoreForm">Huỷ</button>
            <button type="button" class="task-detail__btn" :disabled="scoreSaving" @click="saveScore">
              {{ scoreSaving ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="peopleOpen" class="task-detail__dialog" role="presentation" @mousedown.self="closePeopleModal">
        <div
          class="task-detail__dialog-panel task-detail__dialog-panel--people"
          role="dialog"
          aria-modal="true"
          aria-labelledby="task-detail-people-title"
        >
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon" aria-hidden="true">
              <AppIcon name="userPlus" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-people-title" class="task-detail__dialog-title">Người thực hiện</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="peopleSaving" @click="closePeopleModal">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <div class="task-detail__dialog-body hide-scrollbar">
            <p class="task-detail__people-story">{{ peopleStory }}</p>
            <div class="task-detail__people-grid">
              <article class="task-detail__people-card">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="userPlus" :size="16" :stroke-width="1.75" />
                  </span>
                  <h3>Người thực hiện</h3>
                </div>
                <ProjectUserPicker
                  v-model="peopleForm.assignee_id"
                  :users="users"
                  :disabled="peopleSaving"
                  search-label="Tìm người thực hiện"
                  placeholder="Tìm tên, email hoặc phòng ban"
                  remove-aria-label="Bỏ người thực hiện"
                />
              </article>
              <article class="task-detail__people-card task-detail__people-card--gold">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="user" :size="16" :stroke-width="1.75" />
                  </span>
                  <h3>Người giao việc</h3>
                </div>
                <ProjectUserPicker
                  v-model="peopleForm.manager_id"
                  :users="users"
                  :disabled="peopleSaving"
                  search-label="Tìm người giao việc"
                  placeholder="Tìm tên, email hoặc phòng ban"
                  remove-aria-label="Bỏ người giao việc"
                />
              </article>
              <article class="task-detail__people-card task-detail__people-card--tertiary">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="eye" :size="16" :stroke-width="1.75" />
                  </span>
                  <h3>Người theo dõi</h3>
                  <span class="task-detail__people-count">{{ peopleForm.watcher_ids.length }}</span>
                </div>
                <ProjectMemberPicker
                  v-model="peopleForm.watcher_ids"
                  :users="users"
                  :disabled="peopleSaving"
                  search-label="Tìm người theo dõi"
                  placeholder="Tìm người theo dõi"
                  remove-aria-label="Bỏ người theo dõi"
                  tone="tertiary"
                />
              </article>
              <article class="task-detail__people-card task-detail__people-card--gold">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="users" :size="16" :stroke-width="1.75" />
                  </span>
                  <h3>Người phối hợp</h3>
                  <span class="task-detail__people-count">{{ peopleForm.collaborator_ids.length }}</span>
                </div>
                <ProjectMemberPicker
                  v-model="peopleForm.collaborator_ids"
                  :users="users"
                  :disabled="peopleSaving"
                  search-label="Tìm người phối hợp"
                  placeholder="Tìm người phối hợp"
                  remove-aria-label="Bỏ người phối hợp"
                  tone="gold"
                />
              </article>
            </div>
          </div>
          <div class="task-detail__dialog-actions">
            <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="peopleSaving" @click="closePeopleModal">Huỷ</button>
            <button type="button" class="task-detail__btn" :disabled="peopleSaving" @click="savePeople">
              {{ peopleSaving ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="statusMenuOpen && canEdit"
        id="task-detail-status-menu"
        ref="statusMenuEl"
        role="menu"
        class="task-detail__menu task-detail__menu--status"
        :style="{ top: `${statusMenuPos.top}px`, left: `${statusMenuPos.left}px` }"
        @mousedown.stop
      >
        <button
          v-for="item in statusChoices"
          :key="item.value"
          type="button"
          role="menuitem"
          class="task-detail__menu-item"
          :class="{ 'task-detail__menu-item--current': item.value === task.status }"
          @click="changeStatus(item.value)"
        >
          <span class="task-detail__status-dot" :class="`task-detail__status-dot--${TASK_STATUS_TONES[item.value]}`" />
          <span>{{ item.label }}</span>
          <AppIcon v-if="item.value === task.status" name="check" :size="14" :stroke-width="2" />
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="fileMenuOpen && fileMenuAttachment"
        ref="fileMenuRoot"
        class="task-detail__menu task-detail__menu--file"
        role="menu"
        tabindex="-1"
        :style="{ left: `${fileMenuPos.x}px`, top: `${fileMenuPos.y}px` }"
        @contextmenu.prevent
        @mousedown.stop
      >
        <button type="button" role="menuitem" class="task-detail__menu-item" @click="downloadFromMenu">
          <AppIcon name="download" :size="15" :stroke-width="1.75" />
          Tải xuống
        </button>
        <button type="button" role="menuitem" class="task-detail__menu-item" @click="viewFromMenu">
          <AppIcon name="eye" :size="15" :stroke-width="1.75" />
          Xem
        </button>
        <button
          v-if="canEdit"
          type="button"
          role="menuitem"
          class="task-detail__menu-item"
          :disabled="replacingAttachment"
          @click="triggerReplaceInput"
        >
          <AppIcon name="fileUp" :size="15" :stroke-width="1.75" />
          Cập nhật lại
        </button>
        <button v-if="canEdit" type="button" role="menuitem" class="task-detail__menu-item" @click="openRenameDialog">
          <AppIcon name="pencil" :size="15" :stroke-width="1.75" />
          Đổi tên
        </button>
        <button
          v-if="canEdit"
          type="button"
          role="menuitem"
          class="task-detail__menu-item task-detail__menu-item--danger"
          @click="deleteFromMenu"
        >
          <AppIcon name="trash" :size="15" :stroke-width="1.75" />
          Xóa
        </button>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="renaming" class="task-detail__dialog" role="presentation" @mousedown.self="cancelRename">
        <div
          class="task-detail__dialog-panel task-detail__dialog-panel--compact"
          role="dialog"
          aria-modal="true"
          aria-labelledby="task-detail-rename-title"
        >
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon" aria-hidden="true">
              <AppIcon name="pencil" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-rename-title" class="task-detail__dialog-title">Đổi tên tệp</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="savingRename" @click="cancelRename">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <form class="task-detail__dialog-body" @submit.prevent="saveRename">
            <label class="task-detail__field task-detail__field--wide">
              <span>Tên tệp</span>
              <input ref="renameInput" v-model="renameForm.file_name" type="text" required maxlength="255" />
            </label>
          </form>
          <div class="task-detail__dialog-actions">
            <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="savingRename" @click="cancelRename">Huỷ</button>
            <button type="button" class="task-detail__btn" :disabled="savingRename || !renameForm.file_name.trim()" @click="saveRename">
              {{ savingRename ? 'Đang lưu…' : 'Lưu' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="confirmingDelete"
      title="Xoá công việc"
      :description="`Bạn có chắc muốn xoá công việc “${task?.title || ''}”? Thao tác này không thể hoàn tác.`"
      confirm-label="Xoá"
      :loading="deleting"
      danger
      @confirm="confirmDelete"
      @update:open="confirmingDelete = $event"
    />
    <ConfirmDialog
      :open="confirmingDeleteAttachments"
      title="Xoá tệp đính kèm"
      :description="selectedAttachmentIds.length === 1 ? 'Bạn có chắc muốn xoá tệp đã chọn? Thao tác này không thể hoàn tác.' : `Bạn có chắc muốn xoá ${selectedAttachmentIds.length} tệp đã chọn? Thao tác này không thể hoàn tác.`"
      confirm-label="Xóa"
      :loading="deletingAttachments"
      danger
      @confirm="confirmDeleteAttachments"
      @update:open="confirmingDeleteAttachments = $event"
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
    <TaskRowContextMenu
      :open="childCtxMenu.open"
      :x="childCtxMenu.x"
      :y="childCtxMenu.y"
      :task="childCtxMenu.task"
      :can-edit="canEdit"
      :can-approve="canApprove"
      :can-duplicate="canEdit"
      @close="closeChildContextMenu"
      @action="onChildContextAction"
    />
    <TaskQuickActionModals
      :kind="childActionDialog.kind"
      :task="childActionDialog.task"
      :extra="childActionDialog.extra"
      @close="closeChildActionDialog"
      @updated="applyChildUpdate"
      @duplicated="onChildTaskDuplicated"
      @deleted="onChildTaskDeleted"
    />
  </section>
</template>

<style scoped>
.task-detail {
  position: relative;
  isolation: isolate;
  min-height: 100%;
  padding: 0 var(--space-5) var(--space-4);
  background: linear-gradient(180deg, var(--color-tertiary-50) 0%, var(--color-surface-muted) 22rem);
}

.task-detail__page-watermark {
  position: absolute;
  top: 0;
  right: 1.5rem;
  z-index: 0;
  width: 22rem;
  height: auto;
  max-width: 32vw;
  pointer-events: none;
  opacity: 0.05;
}

.task-detail__header-btn {
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

.task-detail__header-btn:hover {
  background: var(--color-surface-muted);
}

.task-detail__page {
  position: relative;
  z-index: 1;
  display: grid;
  gap: var(--space-4);
}

.task-detail__layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 20rem;
  align-items: start;
  gap: var(--space-4);
}

.task-detail__col {
  display: grid;
  gap: var(--space-4);
  min-width: 0;
}

.task-detail__col--discuss {
  position: sticky;
  top: var(--space-4);
  z-index: 1;
  width: 20rem;
}

.task-detail__pair {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--space-4);
  min-width: 0;
}

.task-detail__pair > .task-detail__card {
  min-width: 0;
}

.task-detail__toolbar {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  flex-wrap: nowrap;
  gap: var(--space-4);
  min-height: 2.75rem;
  margin: var(--space-3) calc(var(--space-5) * -1) 0;
  padding: 0 var(--space-5);
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__tabs {
  display: flex;
  flex: 1;
  align-items: stretch;
  gap: 0;
  min-width: 0;
  overflow-x: auto;
}

.task-detail__tab {
  position: relative;
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  padding: 0.7rem 0.875rem;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.task-detail__tab:hover {
  color: var(--color-text);
}

.task-detail__tab--active {
  color: var(--color-secondary-700);
}

.task-detail__tab--active::after {
  content: '';
  position: absolute;
  left: 0.75rem;
  right: 0.75rem;
  bottom: 0;
  height: 2px;
  background: var(--color-secondary);
}

.task-detail__actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.125rem;
}

.task-detail__action-wrap {
  position: relative;
  flex-shrink: 0;
}

.task-detail__action {
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

.task-detail__action:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.task-detail__action--open,
.task-detail__action--open:hover:not(:disabled) {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.task-detail__action:disabled {
  opacity: 0.45;
  cursor: default;
}

.task-detail__action-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 0.9375rem;
  height: 0.9375rem;
}

.task-detail__action-label {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.task-detail__action--danger {
  color: var(--color-danger-tint-fg);
}

.task-detail__action--danger:hover:not(:disabled) {
  color: var(--color-danger-tint-fg);
  background: var(--color-danger-tint-bg);
}

.task-detail__action--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.task-detail__action--success:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-success) 18%, var(--color-success-tint-bg));
}

.task-detail__summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-4);
  min-width: 0;
  padding: 0.75rem var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__summary-item {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
}

.task-detail__summary-item--progress {
  flex: 1 1 8rem;
  max-width: 14rem;
  font-weight: 700;
  color: var(--color-text);
}

.task-detail__summary-track {
  display: block;
  flex: 1;
  min-width: 4rem;
  height: 0.375rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.task-detail__summary-bar {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-secondary);
}

.task-detail__status-dot,
.task-detail__status-dot--tertiary {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: currentColor;
}

.task-detail__status-dot--primary { background: var(--color-primary); }
.task-detail__status-dot--gold { background: var(--color-gold); }
.task-detail__status-dot--success { background: var(--color-success); }
.task-detail__status-dot--umber { background: var(--color-umber); }

.task-detail__menu {
  position: absolute;
  top: calc(100% + 0.375rem);
  right: 0;
  z-index: 40;
  min-width: 14rem;
  padding: 0.375rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.task-detail__menu--status,
.task-detail__menu--file {
  position: fixed;
  top: auto;
  right: auto;
  z-index: 1400;
}

.task-detail__menu--file {
  min-width: 13.5rem;
}

.task-detail__menu-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  min-height: 2.25rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.task-detail__menu-item:hover,
.task-detail__menu-item--current {
  background: var(--color-surface-muted);
}

.task-detail__menu-item:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.task-detail__menu-item--danger {
  color: var(--color-danger-tint-fg);
}

.task-detail__card {
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__card--lead {
  position: relative;
  padding-left: calc(var(--space-2) + 3px + var(--space-5));
}

.task-detail__card--lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.task-detail__card--discuss {
  display: flex;
  flex-direction: column;
  max-height: min(32rem, calc(100vh - 8rem));
  overflow: hidden;
}

.task-detail__card--discuss .task-detail__section-head {
  flex-shrink: 0;
}

.task-detail__section-icon {
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

.task-detail__section-icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-detail__section-icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-600);
}

.task-detail__section-icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.task-detail__section-icon--umber {
  background: var(--color-umber-surface);
  color: var(--color-umber-600);
}

.task-detail__section-count {
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

.task-detail__card--worklog {
  position: relative;
  padding: 0;
  overflow: hidden;
}

.task-detail__wm-defs {
  position: absolute;
  width: 0;
  height: 0;
  overflow: hidden;
}

.task-detail__worklog-watermark {
  position: absolute;
  top: -10%;
  right: -6%;
  z-index: 0;
  width: 12rem;
  height: auto;
  max-width: 36%;
  pointer-events: none;
  opacity: 0.04;
  transform: rotate(-4deg);
}

.task-detail__worklog-inner {
  position: relative;
  z-index: 1;
  padding: var(--space-4);
}

.task-detail__section-head--worklog {
  align-items: flex-start;
  padding-bottom: var(--space-3);
  box-shadow: none;
}

.task-detail__worklog-heading {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.task-detail__worklog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-secondary-surface);
  color: var(--color-secondary-600);
}

.task-detail__worklog-total {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__worklog-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--space-3);
}

.task-detail__choice-list {
  display: flex;
  gap: var(--space-2);
}

.task-detail__choice {
  display: inline-flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  height: 2.25rem;
  padding: 0 var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-detail__choice-dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: currentColor;
  opacity: 0.4;
}

.task-detail__choice--on.task-detail__choice--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.task-detail__choice--on.task-detail__choice--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-detail__choice--on .task-detail__choice-dot {
  opacity: 1;
}

.task-detail__worklog-list {
  display: grid;
  gap: 0.625rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-detail__worklog-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-3);
  padding: 0.75rem var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-detail__worklog-hours {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-md);
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.1;
}

.task-detail__worklog-hours small {
  color: var(--color-secondary-600);
  font-size: 0.625rem;
  font-weight: 600;
}

.task-detail__worklog-body {
  display: grid;
  gap: 0.25rem;
  min-width: 0;
}

.task-detail__worklog-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem var(--space-3);
}

.task-detail__worklog-date {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__worklog-note {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-detail__section-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0 0 var(--space-4);
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__section-title {
  flex: 1;
  min-width: 0;
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.task-detail__kv {
  display: grid;
  margin: 0;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-detail__kv-row,
.task-detail__kv-cell {
  display: grid;
  grid-template-columns: 10.5rem minmax(0, 1fr);
  align-items: stretch;
  min-width: 0;
}

.task-detail__kv--cols .task-detail__kv-row--span,
.task-detail__kv--cols .task-detail__kv-cell {
  grid-template-columns: 12rem minmax(0, 1fr);
}

.task-detail__kv-row {
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__kv-row:last-child {
  box-shadow: none;
}

.task-detail__kv-row--stack {
  grid-template-columns: 1fr;
}

.task-detail__kv--cols .task-detail__kv-row:not(.task-detail__kv-row--span):not(.task-detail__kv-row--stack) {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
}

.task-detail__kv-cell + .task-detail__kv-cell {
  box-shadow: -1px 0 0 var(--color-border);
}

.task-detail__kv-row--stack .task-detail__label {
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__label {
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

.task-detail__label::after {
  content: ':';
}

.task-detail__value {
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

.task-detail__value:has(.task-detail__eval-progress),
.task-detail__value:has(.task-detail__rules),
.task-detail__value:has(.task-detail__related),
.task-detail__value:has(.task-detail__related-card),
.task-detail__value:has(.task-detail__related-empty) {
  flex-direction: column;
  align-items: stretch;
}

.task-detail__kv .task-detail__eval-progress,
.task-detail__kv .task-detail__rules {
  width: 100%;
}

.task-detail__kv .task-detail__eval-track {
  margin: 0.375rem 0.625rem 0.375rem 0;
}

.task-detail__link {
  color: var(--color-tertiary-600);
  text-decoration: none;
}

.task-detail__link:hover {
  text-decoration: underline;
}

.task-detail__chip {
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

.task-detail__chip--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-detail__chip--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.task-detail__chip--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.task-detail__chip--umber {
  background: var(--color-umber-surface);
  color: var(--color-umber);
}

.task-detail__chip--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.task-detail__related {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  width: 100%;
}

.task-detail__related-toolbar {
  display: flex;
  justify-content: flex-end;
}

.task-detail__related-add {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.task-detail__related-add:hover,
.task-detail__related-add:focus-visible {
  text-decoration: underline;
  outline: none;
}

.task-detail__related-card {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1rem;
  width: 100%;
  padding: 0.75rem 0.875rem;
  padding-left: calc(var(--space-2) + 3px + 0.875rem);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__related-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-tertiary);
}

.task-detail__related-title {
  min-width: 0;
  flex: 1 1 12rem;
  color: var(--color-tertiary-600);
  font-weight: 650;
  text-decoration: none;
}

.task-detail__related-title:hover {
  text-decoration: underline;
}

.task-detail__related-meta {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
}

.task-detail__related-empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-style: italic;
}

.task-detail__child-table {
  display: flex;
  flex-direction: column;
  width: 100%;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-detail__child-head,
.task-detail__child-row {
  display: grid;
  grid-template-columns: minmax(10rem, 2fr) 8.5rem minmax(9rem, 1fr) minmax(7rem, 1fr) 4.5rem;
  align-items: center;
  gap: 0.5rem 0.75rem;
  min-width: 0;
  padding: 0.625rem 0.75rem;
}

.task-detail__child-head {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 650;
}

.task-detail__child-row {
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__child-row:last-child {
  box-shadow: none;
}

.task-detail__child-title {
  min-width: 0;
}

.task-detail__child-code {
  margin-right: 0.375rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__child-cell {
  display: flex;
  min-width: 0;
  align-items: center;
}

.task-detail__child-select {
  width: 100%;
  max-width: 12rem;
  min-width: 0;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
}

.task-detail__child-select:disabled {
  opacity: 0.7;
}

.task-detail__child-stat {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
}

.task-detail__chip--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-detail__user-chip {
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

.task-detail__eval-progress {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}

.task-detail__eval-track {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 0.5rem;
  margin: 0.5rem 0.75rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
}

.task-detail__eval-bar {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-gold-400), var(--color-gold-600));
}

.task-detail__eval-knob {
  position: absolute;
  top: 50%;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.375rem;
  height: 1.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-gold-700);
  font-size: 0.625rem;
  font-weight: 700;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  transform: translate(-50%, -50%);
}

.task-detail__eval-clock {
  display: inline-flex;
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.task-detail__rating {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.125rem 0.5rem;
}

.task-detail__star {
  color: var(--color-gold-200);
}

.task-detail__star--on {
  color: var(--color-gold);
}

.task-detail__rating-caption {
  color: var(--color-gold-700);
  font-size: 0.8125rem;
  font-weight: 600;
}

.task-detail__priority {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.task-detail__flag--info { color: var(--color-info); }
.task-detail__flag--gold { color: var(--color-gold); }
.task-detail__flag--danger { color: var(--color-danger); }
.task-detail__flag--tertiary { color: var(--color-tertiary); }
.task-detail__flag--neutral { color: var(--color-text-muted); }

.task-detail__rules {
  display: grid;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-detail__rules li {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.task-detail__rule-icon {
  flex-shrink: 0;
  margin-top: 0.15rem;
  color: var(--color-success);
}

.task-detail__rule-icon--off {
  color: var(--color-text-muted);
}

.task-detail__rules-item--off {
  color: var(--color-text-muted);
}

.task-detail__rule-name {
  display: block;
  color: var(--color-text);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-detail__person,
.task-detail__people {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
}

.task-detail__progress {
  display: grid;
  gap: 0.5rem;
}

.task-detail__progress-qty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__progress-meta {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__list {
  display: grid;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-detail__list li {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) auto minmax(0, 1fr) auto;
  gap: 0.5rem;
  align-items: center;
  padding: 0.625rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__files {
  display: grid;
  margin: 0 calc(var(--space-5) * -1);
  padding: 0;
}

.task-detail__file-bulk {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.125rem;
  margin-left: auto;
}

.task-detail__bulk-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-detail__bulk-btn:hover {
  background: var(--color-surface-muted);
}

.task-detail__bulk-btn--danger {
  color: var(--color-danger-tint-fg);
}

.task-detail__file-head {
  display: grid;
  grid-template-columns: 1.25rem minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.5rem;
  padding: 0.25rem var(--space-5);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__file-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
}

.task-detail__files:hover .task-detail__file-check,
.task-detail__files--picking .task-detail__file-check,
.task-detail__file:focus-within .task-detail__file-check {
  opacity: 1;
  pointer-events: auto;
}

.task-detail__file-check input {
  width: 0.875rem;
  height: 0.875rem;
  margin: 0;
  accent-color: var(--color-tertiary);
  cursor: pointer;
}

.task-detail__file {
  display: grid;
  grid-template-columns: 1.25rem minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-2);
  padding: 0.75rem var(--space-5);
  box-shadow: 0 1px 0 var(--color-border);
  cursor: pointer;
  user-select: none;
}

.task-detail__file--checked {
  background: color-mix(in srgb, var(--color-tertiary) 8%, transparent);
}

.task-detail__file:last-child {
  box-shadow: none;
}

.task-detail__file-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.task-detail__file-thumb {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.task-detail__file-thumb--primary { color: var(--color-primary); background: var(--color-primary-surface); }
.task-detail__file-thumb--gold { color: var(--color-gold-600); background: var(--color-gold-surface); }
.task-detail__file-thumb--danger { color: var(--color-danger); background: var(--color-danger-tint-bg); }
.task-detail__file-thumb--info { color: var(--color-info); background: var(--color-surface-muted); }
.task-detail__file-thumb--success { color: var(--color-success); background: var(--color-success-tint-bg); }
.task-detail__file-thumb--tertiary { color: var(--color-tertiary); background: var(--color-tertiary-surface); }

.task-detail__file-copy {
  display: grid;
  gap: 0.125rem;
  min-width: 0;
}

.task-detail__file-name {
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-detail__file-sub {
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-detail__file-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.task-detail__field {
  display: grid;
  gap: 0.375rem;
  min-width: 0;
}

.task-detail__field--wide {
  grid-column: 1 / -1;
}

.task-detail__field span {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.task-detail__field input,
.task-detail__field select,
.task-detail__field textarea {
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

.task-detail__field input::placeholder,
.task-detail__field textarea::placeholder {
  color: var(--color-text-muted);
}

.task-detail__field input:focus,
.task-detail__field textarea:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.task-detail__field input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.task-detail__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.task-detail__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-secondary);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-detail__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-detail__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-detail__btn--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-detail__icon-btn {
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

.task-detail__icon-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.task-detail__hidden {
  display: none;
}

.task-detail__meta {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__dialog {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.task-detail__dialog-panel {
  display: flex;
  flex-direction: column;
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.task-detail__dialog-panel--compact {
  width: min(28rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: min(24rem, calc(100vh - 2.5rem));
}

.task-detail__dialog-panel--people {
  width: min(40rem, calc(100vw - 2.5rem));
  height: auto;
  max-height: calc(100vh - 2.5rem);
}

.task-detail__dialog-panel--people .task-detail__dialog-head,
.task-detail__dialog-panel--people .task-detail__dialog-actions {
  padding: var(--space-3) var(--space-4);
}

.task-detail__dialog-panel--people .task-detail__dialog-body {
  padding: var(--space-3) var(--space-4);
}

.task-detail__dialog-panel--people .task-detail__dialog-title {
  font-size: 1.0625rem;
}

.task-detail__dialog-head,
.task-detail__dialog-actions {
  flex-shrink: 0;
}

.task-detail__dialog-head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__dialog-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary-600);
}

.task-detail__dialog-icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-600);
}

.task-detail__dialog-icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.task-detail__dialog-head-copy {
  flex: 1;
  min-width: 0;
}

.task-detail__dialog-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
}

.task-detail__dialog-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-4);
}

.task-detail__dialog-actions {
  padding: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.task-detail__people-story {
  margin: 0 0 var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-gold-surface);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.45;
}

.task-detail__people-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.task-detail__people-card {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);

  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-width: 0;
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__people-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--step-color);
}

.task-detail__people-card--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.task-detail__people-card--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.task-detail__people-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.task-detail__people-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--step-surface);
  color: var(--step-color);
}

.task-detail__people-head h3 {
  margin: 0;
  color: var(--step-color);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.task-detail__people-count {
  margin-left: auto;
  min-width: 1.25rem;
  padding: 0.0625rem 0.375rem;
  border-radius: var(--radius-full);
  background: var(--step-surface);
  color: var(--step-color);
  font-size: 0.6875rem;
  font-weight: 700;
  text-align: center;
}

.task-detail__field textarea {
  resize: vertical;
}

@media (max-width: 1280px) {
  .task-detail__pair {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 1100px) {
  .task-detail__layout,
  .task-detail__pair,
  .task-detail__people-grid {
    grid-template-columns: 1fr;
  }

  .task-detail__col--discuss {
    position: static;
    width: auto;
  }

  .task-detail__card--discuss {
    max-height: none;
    overflow: visible;
  }

  .task-detail__card--discuss :deep(.comments--sidebar),
  .task-detail__card--discuss :deep(.comments__list--scroll) {
    flex: none;
    max-height: none;
    overflow: visible;
  }

  .task-detail__field--span2 {
    grid-column: auto;
  }

  .task-detail__toolbar {
    flex-direction: column;
  }

  .task-detail__actions {
    justify-content: flex-end;
  }
}

@media (max-width: 720px) {
  .task-detail__kv--cols .task-detail__kv-row:not(.task-detail__kv-row--span) {
    grid-template-columns: 1fr;
  }

  .task-detail__child-head {
    display: none;
  }

  .task-detail__child-head,
  .task-detail__child-row {
    grid-template-columns: 1fr;
    gap: 0.35rem;
  }

  .task-detail__kv-cell + .task-detail__kv-cell {
    box-shadow: inset 0 1px 0 var(--color-border);
  }
}

@media (max-width: 480px) {
  .task-detail__worklog-row {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .task-detail__worklog-row .task-detail__icon-btn {
    grid-column: 2;
    justify-self: end;
  }
}
</style>
