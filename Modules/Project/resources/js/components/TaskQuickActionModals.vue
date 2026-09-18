<script setup>
//
// Modal thao tác nhanh từ menu chuột phải trên danh sách công việc.
// Form nằm trong viewport; 2–3 cột khi có nhiều field; field dài span full.
//
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import GuideHelp from '@/components/GuideHelp.vue';
import { SCORE_FACTOR_GUIDES } from '@/constants/scoreFactorGuides.js';
import { showClientToast } from '@/lib/clientToast';
import { TASK_SCORE_RESULT_SUGGESTIONS, TASK_STATUS_LABELS, TASK_STATUS_TONES } from '../constants/task.js';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';
import TaskParentPicker from './TaskParentPicker.vue';

const props = defineProps({
  kind: { type: String, default: null },
  task: { type: Object, default: null },
  extra: { type: Object, default: () => ({}) },
  users: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated', 'duplicated', 'deleted']);

const KIND_META = {
  members: { title: 'Thêm người thực hiện', icon: 'userPlus', tone: 'tertiary' },
  dates: { title: 'Cập nhật thời gian', icon: 'calendar', tone: 'info' },
  move: { title: 'Chuyển công việc', icon: 'move', tone: 'secondary' },
  progress: { title: 'Cập nhật tiến độ', icon: 'trendingUp', tone: 'secondary' },
  evaluate: { title: 'Đánh giá kết quả', icon: 'starFilled', tone: 'gold' },
  documents: { title: 'Thêm tài liệu', icon: 'fileUp', tone: 'info' },
  duplicate: { title: 'Nhân bản công việc', icon: 'copy', tone: 'success' },
  delete: { title: 'Xoá công việc', icon: 'trash', tone: 'danger' },
};

const saving = ref(false);
const startInput = ref(null);
const endInput = ref(null);
const actualStartInput = ref(null);
const actualEndInput = ref(null);
const plannedBlock = ref(null);
const actualBlock = ref(null);
const fileInput = ref(null);
const dropActive = ref(false);
const pendingFiles = ref([]);
const scoreQualityLevels = ref([]);

const peopleForm = reactive({
  assignee_id: '',
  manager_id: '',
  watcher_ids: [],
  collaborator_ids: [],
});
const datesForm = reactive({
  start_date: '',
  end_date: '',
  start_time: '',
  due_time: '',
  actual_start_date: '',
  actual_end_date: '',
});
const moveForm = reactive({ parent_id: '' });
const progressForm = reactive({ progress_percent: '', progress_number: '', progress_total: '', unit: '' });
const scoreForm = reactive({
  rating_score: '',
  rating_result: '',
  is_passed: null,
  rating_desc: '',
});

const isOpen = computed(() => Boolean(props.kind && props.task && props.kind !== 'duplicate' && props.kind !== 'delete'));
const dateFocus = computed(() => props.extra?.focus || 'planned');
const isActualFocus = computed(() => dateFocus.value === 'actual');
const dialogMeta = computed(() => KIND_META[props.kind] || { title: '', icon: 'layers', tone: 'primary' });
const panelClass = computed(() => {
  if (props.kind === 'evaluate' || props.kind === 'move' || props.kind === 'documents' || props.kind === 'progress') return 'task-qa__panel--md';
  if (props.kind === 'members') return 'task-qa__panel--xl';
  return 'task-qa__panel--lg';
});

const taskLabel = computed(() => {
  const t = props.task;
  if (!t) return '';
  return t.code ? `[${t.code}] ${t.title}` : t.title;
});

const statusTone = computed(() => TASK_STATUS_TONES[props.task?.status] || 'neutral');
const statusLabel = computed(() => TASK_STATUS_LABELS[props.task?.status] || props.task?.status || '—');

const bannerMeta = computed(() => {
  const t = props.task;
  if (!t) return [];
  const items = [];
  if (t.code) items.push({ key: 'code', label: 'Mã', value: t.code });
  items.push({ key: 'status', label: 'Trạng thái', value: statusLabel.value, status: true });
  if (t.project?.name) {
    items.push({ key: 'project', label: 'Dự án', value: t.project.name });
  } else {
    items.push({ key: 'project', label: 'Dự án', value: 'Công việc thường xuyên' });
  }
  return items;
});

const preferredDeptIds = computed(() => {
  const ids = [];
  const t = props.task;
  if (!t) return ids;
  if (t.department?.id) ids.push(t.department.id);
  if (t.assignee?.department_id) ids.push(t.assignee.department_id);
  if (t.project?.owner_department?.id) ids.push(t.project.owner_department.id);
  if (t.project?.executing_department?.id) ids.push(t.project.executing_department.id);
  return ids;
});

const localUsers = ref([]);
const assignableUsers = computed(() => (props.users?.length ? props.users : localUsers.value));

const plannedDaysCompact = computed(() => compactDays(datesForm.start_date, datesForm.end_date));
const actualDaysCompact = computed(() => compactDays(datesForm.actual_start_date, datesForm.actual_end_date));
const plannedRangeInvalid = computed(() => Boolean(datesForm.start_date && datesForm.end_date && !plannedDaysCompact.value));
const actualRangeInvalid = computed(() => Boolean(datesForm.actual_start_date && datesForm.actual_end_date && !actualDaysCompact.value));
const durationLabel = computed(() => daysBetween(datesForm.start_date, datesForm.end_date));
const actualDurationLabel = computed(() => daysBetween(datesForm.actual_start_date, datesForm.actual_end_date));

const peopleStory = computed(() => {
  const nameOf = (id) => assignableUsers.value.find((u) => String(u.id) === String(id))?.name;
  const assignee = nameOf(peopleForm.assignee_id) || 'Chưa chọn người thực hiện';
  const manager = nameOf(peopleForm.manager_id) || 'chưa chọn người giao việc';
  return `${assignee} làm việc, ${manager} giao.`;
});

const canSubmitDocuments = computed(() => pendingFiles.value.length > 0 && !saving.value);
const canSubmitDates = computed(() => !plannedRangeInvalid.value && !actualRangeInvalid.value);
const isQuantityProgress = computed(() => props.task?.progress_type === 'quantity');
const canSubmitProgress = computed(() => {
  if (!isQuantityProgress.value) return true;
  const total = Number(progressForm.progress_total);
  return progressForm.progress_total !== '' && total > 0;
});

function compactDays(start, end) {
  const n = dayCount(start, end);
  return n == null ? '' : `${n}d`;
}

function daysBetween(start, end) {
  const n = dayCount(start, end);
  return n == null ? null : `${n} ngày`;
}

function dayCount(start, end) {
  if (!start || !end) return null;
  const a = new Date(`${start}T00:00:00`);
  const b = new Date(`${end}T00:00:00`);
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime()) || b < a) return null;
  return Math.round((b - a) / 86400000) + 1;
}

function toHi(value) {
  if (!value) return '';
  return String(value).slice(0, 5);
}

function resetForms(task) {
  peopleForm.assignee_id = task.assignee_id || '';
  peopleForm.manager_id = task.manager_id || '';
  peopleForm.watcher_ids = (task.watchers || []).map((person) => person.id);
  peopleForm.collaborator_ids = (task.collaborators || []).map((person) => person.id);
  datesForm.start_date = task.start_date || '';
  datesForm.end_date = task.end_date || '';
  datesForm.start_time = toHi(task.start_time);
  datesForm.due_time = toHi(task.due_time);
  datesForm.actual_start_date = task.actual_start_date || '';
  datesForm.actual_end_date = task.actual_end_date || '';
  moveForm.parent_id = task.parent_id || '';
  progressForm.progress_percent = task.progress_percent != null ? String(task.progress_percent) : '';
  progressForm.progress_number = task.progress_number != null ? String(task.progress_number) : '';
  progressForm.progress_total = task.progress_total != null ? String(task.progress_total) : '';
  progressForm.unit = task.unit || '';
  const score = task.task_score;
  scoreForm.rating_score = score?.rating_score ?? '';
  scoreForm.rating_result = score?.rating_result ?? '';
  scoreForm.is_passed = typeof score?.is_passed === 'boolean' ? score.is_passed : null;
  scoreForm.rating_desc = score?.rating_desc ?? '';
  pendingFiles.value = [];
  dropActive.value = false;
}

async function ensureAssignableUsers() {
  if (props.users?.length || localUsers.value.length) return;
  try {
    const { data } = await window.axios.get('/api/project/assignable-users');
    localUsers.value = data.users ?? [];
  } catch {
    localUsers.value = [];
  }
}

function close() {
  if (saving.value) return;
  emit('close');
}

function onKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    event.preventDefault();
    close();
  }
}

function focusFirst() {
  nextTick(() => {
    if (props.kind !== 'dates') return;
    if (isActualFocus.value) {
      actualStartInput.value?.focus();
      actualBlock.value?.scrollIntoView({ block: 'nearest' });
      return;
    }
    if (dateFocus.value === 'end') endInput.value?.focus();
    else startInput.value?.focus();
    plannedBlock.value?.scrollIntoView({ block: 'nearest' });
  });
}

async function loadScoreQualityLevels(task) {
  scoreQualityLevels.value = [];
  const departmentId = task?.assignee_department_id || task?.assignee?.department_id;
  if (!departmentId) return;
  try {
    const { data } = await window.axios.get('/api/evaluation/score-kit/quality-levels', {
      params: { department_id: departmentId },
    });
    scoreQualityLevels.value = Array.isArray(data?.quality_levels) ? data.quality_levels : [];
  } catch {
    scoreQualityLevels.value = [];
  }
}

watch(
  () => [props.kind, props.task?.id],
  async ([kind]) => {
    document.removeEventListener('keydown', onKeydown);
    if (!kind || kind === 'duplicate' || !props.task) return;
    document.addEventListener('keydown', onKeydown);
    resetForms(props.task);
    focusFirst();
    if (kind === 'members') await ensureAssignableUsers();
    if (kind === 'evaluate') await loadScoreQualityLevels(props.task);
  },
);

watch(
  () => props.extra?.focus,
  () => {
    if (props.kind === 'dates') focusFirst();
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});

function addFiles(fileList) {
  const next = [...pendingFiles.value];
  for (const file of fileList || []) {
    if (!file) continue;
    next.push(file);
  }
  pendingFiles.value = next;
}

function onFileChange(event) {
  addFiles(event.target.files);
  event.target.value = '';
}

function onDrop(event) {
  dropActive.value = false;
  addFiles(event.dataTransfer?.files);
}

function removePendingFile(index) {
  pendingFiles.value = pendingFiles.value.filter((_, i) => i !== index);
}

function formatFileSize(bytes) {
  const n = Number(bytes);
  if (!n) return '';
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

async function submitMembers() {
  const { data } = await window.axios.put(`/api/project/tasks/${props.task.id}`, {
    assignee_id: peopleForm.assignee_id || null,
    manager_id: peopleForm.manager_id || null,
    watcher_ids: peopleForm.watcher_ids,
    collaborator_ids: peopleForm.collaborator_ids,
  });
  emit('updated', data.task);
  showClientToast('success', 'Đã cập nhật người thực hiện.');
}

async function submitDates() {
  const { data } = await window.axios.put(`/api/project/tasks/${props.task.id}`, {
    start_date: datesForm.start_date || null,
    end_date: datesForm.end_date || null,
    start_time: datesForm.start_time || null,
    due_time: datesForm.due_time || null,
    actual_start_date: datesForm.actual_start_date || null,
    actual_end_date: datesForm.actual_end_date || null,
  });
  emit('updated', data.task);
  showClientToast('success', 'Đã cập nhật thời gian.');
}

async function submitMove() {
  const { data } = await window.axios.put(`/api/project/tasks/${props.task.id}`, {
    parent_id: moveForm.parent_id || null,
  });
  emit('updated', data.task);
  showClientToast('success', 'Đã chuyển công việc.');
}

async function submitProgress() {
  const payload = isQuantityProgress.value
    ? {
        progress_number: progressForm.progress_number === '' ? 0 : Number(progressForm.progress_number),
        progress_total: Number(progressForm.progress_total),
        unit: progressForm.unit || null,
      }
    : {
        progress_percent: progressForm.progress_percent === '' ? null : Number(progressForm.progress_percent),
      };
  const { data } = await window.axios.put(`/api/project/tasks/${props.task.id}`, payload);
  emit('updated', data.task);
  showClientToast('success', 'Đã cập nhật tiến độ.');
}

async function submitEvaluate() {
  const payload = {
    rating_score: scoreForm.rating_score === '' ? null : Number(scoreForm.rating_score),
    rating_result: scoreForm.rating_result || null,
    is_passed: scoreForm.is_passed,
    rating_desc: scoreForm.rating_desc || null,
  };
  const { data } = await window.axios.put(`/api/project/tasks/${props.task.id}/score`, payload);
  emit('updated', {
    ...props.task,
    task_score: data.task_score,
    failed_review_count: payload.is_passed === false
      ? Number(props.task.failed_review_count || 0) + 1
      : props.task.failed_review_count,
  });
  showClientToast('success', 'Đã lưu đánh giá.');
}

async function submitDocuments() {
  let lastTask = props.task;
  for (const file of pendingFiles.value) {
    const fd = new FormData();
    fd.append('file', file);
    await window.axios.post(`/api/project/tasks/${props.task.id}/attachments`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  }
  const count = pendingFiles.value.length;
  lastTask = {
    ...props.task,
    attachments_count: Number(props.task.attachments_count || 0) + count,
  };
  emit('updated', lastTask);
  showClientToast('success', count === 1 ? 'Đã tải lên 1 tài liệu.' : `Đã tải lên ${count} tài liệu.`);
}

async function confirmDuplicate() {
  if (!props.task || saving.value) return;
  saving.value = true;
  try {
    const source = props.task;
    const payload = {
      project_id: source.project_id || null,
      parent_id: source.parent_id || null,
      type: source.type || 'task',
      title: `${source.title || 'Công việc'} (bản sao)`,
      description: source.description || null,
      status: 'not_started',
      priority: source.priority || null,
      start_date: source.start_date || null,
      start_time: toHi(source.start_time) || null,
      end_date: source.end_date || null,
      due_time: toHi(source.due_time) || null,
      assignee_id: source.assignee_id || null,
      manager_id: source.manager_id || null,
      watcher_ids: (source.watchers || []).map((person) => person.id),
      collaborator_ids: (source.collaborators || []).map((person) => person.id),
      progress_type: source.progress_type || 'percent',
      estimated_hours: source.estimated_hours ?? null,
      weight: source.weight ?? null,
      constrain_child_dates: source.constrain_child_dates ?? false,
      hide_cross_tasks_from_assignees: source.hide_cross_tasks_from_assignees ?? false,
      hide_from_parent_assignees: source.hide_from_parent_assignees ?? false,
      hide_from_parent_followers: source.hide_from_parent_followers ?? false,
      hide_child_tasks_from_followers: source.hide_child_tasks_from_followers ?? false,
      allow_child_people_view_parent: source.allow_child_people_view_parent ?? false,
      report_complete_action: source.report_complete_action || null,
      completed_interaction_policy: source.completed_interaction_policy || null,
      report_description_requirement: source.report_description_requirement || null,
      report_attachment_requirement: source.report_attachment_requirement || null,
    };
    if (payload.progress_type === 'quantity') {
      payload.progress_number = source.progress_number ?? 0;
      payload.progress_total = source.progress_total;
      payload.unit = source.unit || null;
    } else {
      payload.progress_percent = source.progress_percent ?? 0;
    }
    const { data } = await window.axios.post('/api/project/tasks', payload);
    emit('duplicated', data.task);
    showClientToast('success', 'Đã nhân bản công việc.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không nhân bản được công việc.');
  } finally {
    saving.value = false;
  }
}

async function confirmDelete() {
  if (!props.task || saving.value) return;
  saving.value = true;
  try {
    await window.axios.delete(`/api/project/tasks/${props.task.id}`);
    showClientToast('success', 'Đã xoá công việc.');
    emit('deleted', props.task);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được công việc — có thể còn công việc con.');
  } finally {
    saving.value = false;
  }
}

async function submit() {
  if (!props.task || saving.value) return;
  if (props.kind === 'dates' && !canSubmitDates.value) return;
  if (props.kind === 'documents' && !canSubmitDocuments.value) return;
  if (props.kind === 'progress' && !canSubmitProgress.value) return;
  saving.value = true;
  try {
    if (props.kind === 'members') await submitMembers();
    else if (props.kind === 'dates') await submitDates();
    else if (props.kind === 'move') await submitMove();
    else if (props.kind === 'progress') await submitProgress();
    else if (props.kind === 'evaluate') await submitEvaluate();
    else if (props.kind === 'documents') await submitDocuments();
    emit('close');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được thay đổi.');
  } finally {
    saving.value = false;
  }
}

const primaryLabel = computed(() => {
  if (props.kind === 'documents') return pendingFiles.value.length ? `Tải lên (${pendingFiles.value.length})` : 'Tải lên';
  if (props.kind === 'evaluate') return 'Lưu đánh giá';
  if (props.kind === 'move') return 'Chuyển';
  if (props.kind === 'progress') return 'Cập nhật';
  return 'Lưu';
});
</script>

<template>
  <ConfirmDialog
    :open="kind === 'duplicate' && Boolean(task)"
    title="Nhân bản công việc"
    :description="task ? `Tạo bản sao của “${task.title}”? Bản sao giữ người thực hiện, thời gian và công việc cha.` : ''"
    confirm-label="Nhân bản"
    cancel-label="Huỷ bỏ"
    :loading="saving"
    @update:open="(v) => !v && close()"
    @confirm="confirmDuplicate"
  />

  <ConfirmDialog
    :open="kind === 'delete' && Boolean(task)"
    title="Xoá công việc"
    :description="task ? `Bạn có chắc muốn xoá công việc “${task.title}”? Thao tác này không thể hoàn tác.` : ''"
    confirm-label="Xoá"
    cancel-label="Huỷ bỏ"
    :loading="saving"
    danger
    @update:open="(v) => !v && close()"
    @confirm="confirmDelete"
  />

  <Teleport to="body">
    <div
      v-if="isOpen"
      class="task-qa"
      role="presentation"
      @mousedown.self="close"
    >
      <div
        class="task-qa__panel"
        :class="[panelClass, `task-qa__panel--${dialogMeta.tone}`, { 'task-qa__panel--float': kind === 'members' || kind === 'move' }]"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`task-qa-title-${kind}`"
      >
        <div class="task-qa__head">
          <span class="task-qa__icon" aria-hidden="true">
            <AppIcon :name="dialogMeta.icon" :size="22" :stroke-width="1.75" />
          </span>
          <div class="task-qa__head-copy">
            <h2 :id="`task-qa-title-${kind}`" class="task-qa__title">
              {{ dialogMeta.title }}
              <GuideHelp
                v-if="kind === 'evaluate'"
                :guide="SCORE_FACTOR_GUIDES.quality"
                tone="gold"
              />
              <GuideHelp
                v-else-if="kind === 'dates'"
                :guide="SCORE_FACTOR_GUIDES.progress"
                tone="secondary"
              />
              <GuideHelp
                v-else-if="kind === 'progress'"
                :guide="SCORE_FACTOR_GUIDES.progress"
                tone="secondary"
              />
            </h2>
            <p class="task-qa__sub">{{ taskLabel }}</p>
          </div>
          <button type="button" class="task-qa__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <form class="task-qa__body hide-scrollbar" @submit.prevent="submit">
          <div v-if="task" class="task-qa__banner" :class="`task-qa__banner--${statusTone}`">
            <div
              v-for="item in bannerMeta"
              :key="item.key"
              class="task-qa__meta"
            >
              <span class="task-qa__meta-label">{{ item.label }}</span>
              <span class="task-qa__meta-value" :class="{ 'task-qa__meta-value--status': item.status }">
                <span v-if="item.status" class="task-qa__meta-dot" aria-hidden="true" />
                {{ item.value }}
              </span>
            </div>
          </div>

          <div v-if="kind === 'members'" class="task-qa__grid">
            <p class="task-qa__story">{{ peopleStory }}</p>
            <div class="task-qa__field">
              <span class="task-qa__label">Người thực hiện</span>
              <ProjectUserPicker
                v-model="peopleForm.assignee_id"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                :disabled="saving"
                search-label="Tìm người thực hiện"
                placeholder="Tìm tên, email hoặc phòng ban"
                remove-aria-label="Bỏ người thực hiện"
              />
            </div>
            <div class="task-qa__field">
              <span class="task-qa__label">Người giao việc</span>
              <ProjectUserPicker
                v-model="peopleForm.manager_id"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                :disabled="saving"
                search-label="Tìm người giao việc"
                placeholder="Tìm tên, email hoặc phòng ban"
                remove-aria-label="Bỏ người giao việc"
              />
            </div>
            <div class="task-qa__field">
              <span class="task-qa__label">
                Người theo dõi
                <em>{{ peopleForm.watcher_ids.length }}</em>
              </span>
              <ProjectMemberPicker
                v-model="peopleForm.watcher_ids"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                :disabled="saving"
                search-label="Tìm người theo dõi"
                placeholder="Tìm người theo dõi"
                remove-aria-label="Bỏ người theo dõi"
                tone="tertiary"
              />
            </div>
            <div class="task-qa__field">
              <span class="task-qa__label">
                Người phối hợp
                <em>{{ peopleForm.collaborator_ids.length }}</em>
              </span>
              <ProjectMemberPicker
                v-model="peopleForm.collaborator_ids"
                :users="assignableUsers"
                :preferred-department-ids="preferredDeptIds"
                :disabled="saving"
                search-label="Tìm người phối hợp"
                placeholder="Tìm người phối hợp"
                remove-aria-label="Bỏ người phối hợp"
                tone="gold"
              />
            </div>
          </div>

          <div v-else-if="kind === 'dates'" class="task-qa__dates">
            <section
              ref="plannedBlock"
              class="task-qa__block"
              :class="{ 'task-qa__block--active': !isActualFocus }"
            >
              <h3 class="task-qa__block-title">Thời gian kế hoạch</h3>
              <div class="task-qa__dates-row">
                <label class="task-qa__field">
                  <span class="task-qa__label">Bắt đầu</span>
                  <input ref="startInput" v-model="datesForm.start_date" type="date" class="task-qa__input">
                </label>
                <label class="task-qa__field">
                  <span class="task-qa__label">Kết thúc</span>
                  <input ref="endInput" v-model="datesForm.end_date" type="date" class="task-qa__input">
                </label>
                <label class="task-qa__field">
                  <span class="task-qa__label">Ngày</span>
                  <input
                    :value="plannedDaysCompact"
                    type="text"
                    class="task-qa__input"
                    disabled
                    :aria-label="durationLabel || 'Số ngày kế hoạch'"
                  >
                </label>
              </div>
              <div class="task-qa__dates-row">
                <label class="task-qa__field">
                  <span class="task-qa__label">Giờ bắt đầu</span>
                  <input v-model="datesForm.start_time" type="time" class="task-qa__input">
                </label>
                <label class="task-qa__field">
                  <span class="task-qa__label">Giờ hạn</span>
                  <input v-model="datesForm.due_time" type="time" class="task-qa__input">
                </label>
              </div>
              <div v-if="plannedRangeInvalid" class="task-qa__stat task-qa__stat--warn">
                Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.
              </div>
            </section>

            <section
              ref="actualBlock"
              class="task-qa__block task-qa__block--actual"
              :class="{ 'task-qa__block--active': isActualFocus }"
            >
              <h3 class="task-qa__block-title">Thời gian thực tế</h3>
              <div class="task-qa__dates-row">
                <label class="task-qa__field">
                  <span class="task-qa__label">BĐ thực tế</span>
                  <input
                    ref="actualStartInput"
                    v-model="datesForm.actual_start_date"
                    type="date"
                    class="task-qa__input"
                  >
                </label>
                <label class="task-qa__field">
                  <span class="task-qa__label">KT thực tế</span>
                  <input
                    ref="actualEndInput"
                    v-model="datesForm.actual_end_date"
                    type="date"
                    class="task-qa__input"
                  >
                </label>
                <label class="task-qa__field">
                  <span class="task-qa__label">Ngày</span>
                  <input
                    :value="actualDaysCompact"
                    type="text"
                    class="task-qa__input"
                    disabled
                    :aria-label="actualDurationLabel || 'Số ngày thực tế'"
                  >
                </label>
              </div>
              <div v-if="actualRangeInvalid" class="task-qa__stat task-qa__stat--warn">
                Ngày kết thúc thực tế phải sau hoặc bằng ngày bắt đầu thực tế.
              </div>
            </section>
          </div>

          <div v-else-if="kind === 'move'" class="task-qa__grid task-qa__grid--stack">
            <label class="task-qa__field">
              <span class="task-qa__label">Dự án</span>
              <input
                :value="task?.project?.name || 'Công việc thường xuyên'"
                type="text"
                class="task-qa__input"
                disabled
              >
            </label>
            <div class="task-qa__field">
              <span class="task-qa__label">Công việc cha</span>
              <TaskParentPicker
                v-model="moveForm.parent_id"
                :project-id="task?.project_id || ''"
                :disabled="saving"
              />
            </div>
          </div>

          <div v-else-if="kind === 'progress'" class="task-qa__grid task-qa__grid--stack">
            <template v-if="isQuantityProgress">
              <div class="task-qa__field">
                <span class="task-qa__label">Khối lượng đã hoàn thành</span>
                <input v-model="progressForm.progress_number" type="number" min="0" step="0.01" class="task-qa__input" placeholder="Ví dụ: 6">
              </div>
              <div class="task-qa__field">
                <span class="task-qa__label">Khối lượng cần hoàn thành</span>
                <input v-model="progressForm.progress_total" type="number" min="0.01" step="0.01" class="task-qa__input" placeholder="Ví dụ: 10">
              </div>
              <div class="task-qa__field">
                <span class="task-qa__label">Đơn vị</span>
                <input v-model="progressForm.unit" type="text" maxlength="50" class="task-qa__input" placeholder="Ví dụ: hạng mục, trang, m²">
              </div>
            </template>
            <div v-else class="task-qa__field">
              <span class="task-qa__label">Tiến độ (%)</span>
              <input v-model="progressForm.progress_percent" type="number" min="0" max="100" step="1" class="task-qa__input" placeholder="Ví dụ: 70">
            </div>
          </div>

          <div v-else-if="kind === 'evaluate'" class="task-qa__grid">
            <label class="task-qa__field">
              <span class="task-qa__label">Kết quả</span>
              <span class="task-qa__choice-list" role="radiogroup" aria-label="Kết quả đánh giá">
                <button
                  type="button"
                  class="task-qa__choice task-qa__choice--success"
                  :class="{ 'task-qa__choice--on': scoreForm.is_passed === true }"
                  role="radio"
                  :aria-checked="scoreForm.is_passed === true ? 'true' : 'false'"
                  @click="scoreForm.is_passed = true"
                >
                  Đạt
                </button>
                <button
                  type="button"
                  class="task-qa__choice task-qa__choice--danger"
                  :class="{ 'task-qa__choice--on': scoreForm.is_passed === false }"
                  role="radio"
                  :aria-checked="scoreForm.is_passed === false ? 'true' : 'false'"
                  @click="scoreForm.is_passed = false"
                >
                  Không đạt
                </button>
              </span>
            </label>
            <label class="task-qa__field">
              <span class="task-qa__label">Điểm số</span>
              <input v-model="scoreForm.rating_score" type="number" min="0" step="0.1" class="task-qa__input" placeholder="Ví dụ: 8.5">
            </label>
            <label class="task-qa__field task-qa__field--full">
              <span class="task-qa__label">
                Kết quả đánh giá
                <GuideHelp :guide="SCORE_FACTOR_GUIDES.quality" tone="gold" />
              </span>
              <select v-if="scoreQualityLevels.length" v-model="scoreForm.rating_result" class="task-qa__input">
                <option value="">Chưa chọn mức</option>
                <option v-for="level in scoreQualityLevels" :key="level.code || level.label" :value="level.label || level.code">
                  {{ level.label || level.code }}
                </option>
                <option
                  v-if="scoreForm.rating_result && !scoreQualityLevels.some((l) => (l.label || l.code) === scoreForm.rating_result)"
                  :value="scoreForm.rating_result"
                >
                  {{ scoreForm.rating_result }} (giá trị cũ)
                </option>
              </select>
              <template v-else>
                <input
                  v-model="scoreForm.rating_result"
                  type="text"
                  class="task-qa__input"
                  list="task-qa-score-suggestions"
                  maxlength="100"
                  placeholder="Ví dụ: Đạt, Xuất sắc"
                >
                <datalist id="task-qa-score-suggestions">
                  <option v-for="item in TASK_SCORE_RESULT_SUGGESTIONS" :key="item" :value="item" />
                </datalist>
              </template>
            </label>
            <label class="task-qa__field task-qa__field--full">
              <span class="task-qa__label">Ý kiến đánh giá</span>
              <textarea v-model="scoreForm.rating_desc" class="task-qa__input task-qa__textarea" rows="3" maxlength="5000" />
            </label>
          </div>

          <div v-else-if="kind === 'documents'" class="task-qa__docs">
            <input
              ref="fileInput"
              type="file"
              class="task-qa__file-input"
              multiple
              @change="onFileChange"
            >
            <button
              type="button"
              class="task-qa__drop"
              :class="{ 'task-qa__drop--active': dropActive }"
              @click="fileInput?.click()"
              @dragenter.prevent="dropActive = true"
              @dragover.prevent="dropActive = true"
              @dragleave.prevent="dropActive = false"
              @drop.prevent="onDrop"
            >
              <AppIcon name="fileUp" :size="22" :stroke-width="1.75" />
              <span>Chọn file hoặc kéo thả vào đây</span>
            </button>
            <ul v-if="pendingFiles.length" class="task-qa__file-list">
              <li v-for="(file, index) in pendingFiles" :key="`${file.name}-${index}`" class="task-qa__file">
                <span class="task-qa__file-name">{{ file.name }}</span>
                <span class="task-qa__file-size">{{ formatFileSize(file.size) }}</span>
                <button type="button" class="task-qa__file-remove" aria-label="Bỏ file" :disabled="saving" @click="removePendingFile(index)">
                  <AppIcon name="close" :size="14" />
                </button>
              </li>
            </ul>
          </div>
        </form>

        <div class="task-qa__actions">
          <button type="button" class="task-qa__btn task-qa__btn--ghost" :disabled="saving" @click="close">
            Huỷ bỏ
          </button>
          <button
            type="button"
            class="task-qa__btn task-qa__btn--primary"
            :disabled="saving || (kind === 'documents' && !canSubmitDocuments) || (kind === 'dates' && !canSubmitDates) || (kind === 'progress' && !canSubmitProgress)"
            @click="submit"
          >
            {{ saving ? 'Đang lưu…' : primaryLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.task-qa {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.task-qa__panel {
  --qa-tone: var(--color-primary);
  --qa-tone-fg: var(--color-primary-900);
  --qa-tone-surface: var(--color-primary-surface);
  --qa-on: var(--color-on-primary);

  display: flex;
  flex-direction: column;
  overflow: hidden;
  width: min(54rem, calc(100vw - 2.5rem));
  max-width: calc(100vw - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.task-qa__panel--md {
  width: min(40rem, calc(100vw - 2.5rem));
}

.task-qa__panel--lg {
  width: min(54rem, calc(100vw - 2.5rem));
}

.task-qa__panel--xl {
  width: min(64rem, calc(100vw - 2.5rem));
}

.task-qa__panel--tertiary {
  --qa-tone: var(--color-tertiary);
  --qa-tone-fg: var(--color-tertiary-800);
  --qa-tone-surface: var(--color-tertiary-surface);
  --qa-on: var(--color-on-tertiary);
}

.task-qa__panel--gold {
  --qa-tone: var(--color-gold-600);
  --qa-tone-fg: var(--color-gold-800);
  --qa-tone-surface: var(--color-gold-surface);
  --qa-on: var(--color-on-gold);
}

.task-qa__panel--info {
  --qa-tone: var(--color-info);
  --qa-tone-fg: var(--color-info-tint-fg);
  --qa-tone-surface: var(--color-info-tint-bg);
  --qa-on: #ffffff;
}

.task-qa__panel--secondary {
  --qa-tone: var(--color-secondary);
  --qa-tone-fg: var(--color-secondary-800);
  --qa-tone-surface: var(--color-secondary-surface);
  --qa-on: var(--color-on-secondary);
}

.task-qa__panel--float {
  overflow: visible;
}

.task-qa__panel--float .task-qa__body {
  overflow: visible;
}

.task-qa__head,
.task-qa__actions {
  flex-shrink: 0;
}

.task-qa__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-qa__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: var(--qa-tone-surface);
  color: var(--qa-tone);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--qa-tone) 18%, transparent);
}

.task-qa__head-copy {
  flex: 1;
  min-width: 0;
}

.task-qa__title {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.task-qa__sub {
  margin: 0.125rem 0 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.4;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-qa__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--qa-tone);
  cursor: pointer;
}

.task-qa__close:hover {
  background: var(--qa-tone-surface);
}

.task-qa__body {
  flex: 1;
  min-height: 0;
  padding: 1.25rem;
  overflow: auto;
}

.task-qa__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
}

.task-qa__grid--stack {
  grid-template-columns: minmax(0, 1fr);
}

.task-qa__story {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  font-style: italic;
}

.task-qa__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.375rem;
}

.task-qa__field--full {
  grid-column: 1 / -1;
}

.task-qa__label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.task-qa__label em {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  padding: 0 0.375rem;
  border-radius: var(--radius-full);
  background: var(--qa-tone-surface);
  color: var(--qa-tone-fg);
  font-size: 0.6875rem;
  font-style: normal;
  font-weight: 700;
}

.task-qa__input {
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

.task-qa__input:focus {
  outline: 2px solid color-mix(in srgb, var(--qa-tone) 35%, transparent);
  outline-offset: 1px;
}

.task-qa__input:disabled {
  color: var(--color-text);
  background: var(--color-surface-muted);
  cursor: default;
}

.task-qa__textarea {
  resize: vertical;
  min-height: 6rem;
}

.task-qa__banner {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem 1.5rem;
  margin-bottom: var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-qa__banner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.task-qa__banner--primary::before { background: var(--color-primary); }
.task-qa__banner--success::before { background: var(--color-success); }
.task-qa__banner--gold::before { background: var(--color-gold); }
.task-qa__banner--umber::before { background: var(--color-umber); }
.task-qa__banner--tertiary::before { background: var(--color-tertiary); }
.task-qa__banner--info::before { background: var(--color-info); }

.task-qa__meta {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.task-qa__meta-label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.task-qa__meta-value {
  color: var(--color-text);
  font-size: 0.875rem;
  font-style: italic;
}

.task-qa__meta-value--status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-style: normal;
  font-weight: 600;
}

.task-qa__meta-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: currentColor;
}

.task-qa__banner--primary .task-qa__meta-dot { background: var(--color-primary); }
.task-qa__banner--success .task-qa__meta-dot { background: var(--color-success); }
.task-qa__banner--gold .task-qa__meta-dot { background: var(--color-gold); }
.task-qa__banner--umber .task-qa__meta-dot { background: var(--color-umber); }
.task-qa__banner--tertiary .task-qa__meta-dot { background: var(--color-tertiary); }
.task-qa__banner--info .task-qa__meta-dot { background: var(--color-info); }

.task-qa__dates {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.task-qa__dates-row {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(5.5rem, 0.55fr);
  gap: var(--space-3);
}

.task-qa__block {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-4) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-qa__block::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-info);
}

.task-qa__block--actual::before {
  background: var(--color-success);
}

.task-qa__block--active {
  box-shadow: var(--shadow-sm), 0 0 0 1px color-mix(in srgb, var(--qa-tone) 28%, transparent);
}

.task-qa__block-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.task-qa__stat {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-md);
  background: var(--qa-tone-surface);
  color: var(--qa-tone-fg);
  font-size: 0.875rem;
}

.task-qa__stat--warn {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-qa__choice-list {
  display: flex;
  gap: 0.5rem;
}

.task-qa__choice {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.task-qa__choice--success.task-qa__choice--on {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  border-color: transparent;
}

.task-qa__choice--danger.task-qa__choice--on {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
  border-color: transparent;
}

.task-qa__docs {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.task-qa__file-input {
  display: none;
}

.task-qa__drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 8rem;
  padding: var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  cursor: pointer;
  outline: 1px dashed var(--color-border);
  outline-offset: -1px;
}

.task-qa__drop--active {
  background: var(--qa-tone-surface);
  color: var(--qa-tone-fg);
  outline-color: var(--qa-tone);
}

.task-qa__file-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.task-qa__file {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-qa__file-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-qa__file-size {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.task-qa__file-remove {
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

.task-qa__file-remove:hover {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.task-qa__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.875rem 1.25rem 1.125rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.task-qa__btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-qa__btn--primary {
  background: var(--qa-tone);
  color: var(--qa-on);
}

.task-qa__btn--primary:hover {
  filter: brightness(0.95);
}

.task-qa__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.task-qa__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.task-qa__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 768px) {
  .task-qa__grid,
  .task-qa__dates-row {
    grid-template-columns: minmax(0, 1fr);
  }

  .task-qa__field--full {
    grid-column: 1 / -1;
  }
}
</style>
