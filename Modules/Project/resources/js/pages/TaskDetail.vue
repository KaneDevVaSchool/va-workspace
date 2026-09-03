<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DualProgressBar from '@/components/DualProgressBar.vue';
import UserAvatarTip from '@/components/UserAvatarTip.vue';
import { showClientToast } from '@/lib/clientToast';
import { computeExpectedProgress } from '@/lib/progress';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectMemberPicker from '../components/ProjectMemberPicker.vue';
import ProjectUserPicker from '../components/ProjectUserPicker.vue';
import {
  TASK_PRIORITY_LABELS,
  TASK_PROGRESS_TYPE_LABELS,
  TASK_STATUS_LABELS,
  TASK_STATUS_TONES,
  TASK_STATUSES,
  TASK_TYPE_LABELS,
} from '../constants/task.js';

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

const editing = ref(false);
const saving = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);
const attachmentUploading = ref(false);
const attachmentInput = ref(null);
const worklogFormOpen = ref(false);
const worklogSaving = ref(false);
const confirmingDeleteWorklog = ref(null);
const statusSaving = ref(false);
const statusMenuOpen = ref(false);
const statusMenuRoot = ref(null);
const peopleOpen = ref(false);
const peopleSaving = ref(false);

const worklogForm = reactive({ work_date: '', hours: '', note: '' });
const peopleForm = reactive({
  assignee_id: '',
  manager_id: '',
  watcher_ids: [],
  collaborator_ids: [],
});
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
  manager_id: '',
  progress_percent: '',
  description: '',
  estimated_hours: '',
  progress_type: 'percent',
  progress_number: '',
  progress_total: '',
  unit: '',
  weight: '',
});

const openSections = reactive({
  info: true,
  progress: true,
  score: true,
  worklog: true,
  files: true,
});

const taskId = computed(() => Number(route.params.id || 0));
const canEdit = computed(() => auth.can('task.create'));
const statusTone = computed(() => TASK_STATUS_TONES[task.value?.status] || 'tertiary');
const statusChoices = computed(() => TASK_STATUSES.filter((item) => item.value));
const hasScore = computed(() => Boolean(task.value?.task_score));
const followers = computed(() => {
  const seen = new Map();
  for (const person of [...(task.value?.watchers || []), ...(task.value?.collaborators || [])]) {
    if (person?.id && !seen.has(person.id)) seen.set(person.id, person);
  }
  return Array.from(seen.values());
});
const headerPeople = computed(() => {
  const seen = new Map();
  for (const person of [task.value?.assignee, task.value?.manager, ...followers.value]) {
    if (person?.id && !seen.has(person.id)) seen.set(person.id, person);
  }
  return Array.from(seen.values());
});
const actualTimeLabel = computed(() => {
  if (!task.value) return '--';
  if (task.value.actual_start_date && task.value.actual_end_date) {
    return `${formatDate(task.value.actual_start_date)} - ${formatDate(task.value.actual_end_date)}`;
  }
  return formatDate(task.value.actual_start_date || task.value.actual_end_date);
});
const editFormEstimatedPercent = computed(() => {
  const number = Number(editForm.progress_number);
  const total = Number(editForm.progress_total);
  if (!editForm.progress_number || !total || total <= 0) return null;
  return Math.round((number / total) * 100);
});
const priorityChoices = computed(() =>
  importanceOptions.value.length
    ? importanceOptions.value
    : Object.entries(TASK_PRIORITY_LABELS).map(([value, label]) => ({ value, label })),
);
const expectedProgress = computed(() =>
  task.value ? computeExpectedProgress(task.value.start_date, task.value.end_date) : null,
);

function formatDate(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleDateString('vi-VN');
}

function formatDateTime(value) {
  if (!value) return '--';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '--';
  return date.toLocaleString('vi-VN');
}

function formatFileSize(bytes) {
  if (!bytes) return '';
  const kb = bytes / 1024;
  if (kb < 1024) return `${kb.toFixed(0)} KB`;
  return `${(kb / 1024).toFixed(1)} MB`;
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

function toggleSection(key) {
  openSections[key] = !openSections[key];
}

function applyTask(next) {
  task.value = next;
  if (next?.task_score) openSections.score = true;
}

async function loadOptions() {
  try {
    const [usersRes, optionsRes] = await Promise.all([
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/tasks/options'),
    ]);
    users.value = usersRes.data.users ?? [];
    importanceOptions.value = optionsRes.data.importance ?? [];
  } catch {
    users.value = [];
  }
}

async function loadTask() {
  if (!taskId.value) return;
  loading.value = true;
  notFound.value = false;
  try {
    const [{ data: detail }, { data: files }, { data: logs }] = await Promise.all([
      window.axios.get(`/api/project/tasks/${taskId.value}`),
      window.axios.get(`/api/project/tasks/${taskId.value}/attachments`),
      window.axios.get(`/api/project/tasks/${taskId.value}/worklogs`),
    ]);
    applyTask(detail.task || null);
    attachments.value = files.attachments || [];
    worklogs.value = logs.worklogs || [];
    openSections.score = Boolean(detail.task?.task_score);
    openSections.worklog = (logs.worklogs || []).length > 0;
    openSections.files = (files.attachments || []).length > 0;
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

function startEdit() {
  if (!task.value) return;
  editForm.title = task.value.title || '';
  editForm.status = task.value.status || 'not_started';
  editForm.priority = task.value.priority || '';
  editForm.start_date = task.value.start_date || '';
  editForm.start_time = task.value.start_time || '';
  editForm.end_date = task.value.end_date || '';
  editForm.due_time = task.value.due_time || '';
  editForm.actual_start_date = task.value.actual_start_date || '';
  editForm.actual_end_date = task.value.actual_end_date || '';
  editForm.assignee_id = task.value.assignee_id || '';
  editForm.manager_id = task.value.manager_id || '';
  editForm.progress_percent = task.value.progress_percent ?? '';
  editForm.description = task.value.description || '';
  editForm.estimated_hours = task.value.estimated_hours ?? '';
  editForm.progress_type = task.value.progress_type || 'percent';
  editForm.progress_number = task.value.progress_number ?? '';
  editForm.progress_total = task.value.progress_total ?? '';
  editForm.unit = task.value.unit || '';
  editForm.weight = task.value.weight ?? '';
  editing.value = true;
}

function cancelEdit() {
  if (!saving.value) editing.value = false;
}

async function saveEdit() {
  if (!task.value) return;
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
      manager_id: editForm.manager_id || null,
      description: editForm.description || null,
      estimated_hours: editForm.estimated_hours === '' ? null : Number(editForm.estimated_hours),
      progress_type: editForm.progress_type,
      weight: editForm.weight === '' ? null : Number(editForm.weight),
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
    const { data } = await window.axios.put(`/api/project/tasks/${task.value.id}`, payload);
    applyTask(data.task);
    editing.value = false;
    showClientToast('success', 'Đã cập nhật công việc.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được công việc.');
  } finally {
    saving.value = false;
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

function openPeopleModal() {
  if (!task.value) return;
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
    openSections.files = true;
    showClientToast('success', 'Đã tải lên tệp đính kèm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải lên được tệp đính kèm.');
  } finally {
    attachmentUploading.value = false;
  }
}

async function removeAttachment(attachment) {
  try {
    await window.axios.delete(`/api/project/tasks/attachments/${attachment.id}`);
    attachments.value = attachments.value.filter((item) => item.id !== attachment.id);
    showClientToast('success', 'Đã xoá tệp đính kèm.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được tệp đính kèm.');
  }
}

function openWorklogForm() {
  worklogForm.work_date = new Date().toISOString().slice(0, 10);
  worklogForm.hours = '';
  worklogForm.note = '';
  worklogFormOpen.value = true;
  openSections.worklog = true;
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
  if (statusMenuOpen.value && statusMenuRoot.value && !statusMenuRoot.value.contains(event.target)) {
    statusMenuOpen.value = false;
  }
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') statusMenuOpen.value = false;
}

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
});
</script>

<template>
  <section class="task-detail">
    <PageHeader
      :title="task?.title || 'Chi tiết công việc'"
      icon="layoutList"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Tất cả công việc', to: { name: 'manager.project.tasks' } },
        { label: task?.title || 'Chi tiết' },
      ]"
    >
      <template #actions>
        <div v-if="task" ref="statusMenuRoot" class="task-detail__status-wrap">
          <button
            type="button"
            class="task-detail__status"
            :class="[`task-detail__status--${statusTone}`, { 'task-detail__status--open': statusMenuOpen }]"
            :disabled="!canEdit || statusSaving"
            :aria-expanded="statusMenuOpen"
            aria-haspopup="menu"
            aria-controls="task-detail-status-menu"
            @click="statusMenuOpen = !statusMenuOpen"
          >
            <span class="task-detail__status-dot" />
            <span>{{ statusSaving ? 'Đang đổi…' : statusLabel(task.status) }}</span>
            <AppIcon name="chevronDown" :size="14" :stroke-width="1.75" />
          </button>
          <div
            v-if="statusMenuOpen && canEdit"
            id="task-detail-status-menu"
            role="menu"
            class="task-detail__menu"
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
        </div>
        <button
          v-if="canEdit && task"
          type="button"
          class="task-detail__btn task-detail__btn--ghost"
          @click="openPeopleModal"
        >
          <AppIcon name="userPlus" :size="15" :stroke-width="1.75" />
          Thêm người
        </button>
        <button type="button" class="task-detail__btn task-detail__btn--ghost" @click="goBack">
          Quay lại
        </button>
        <button v-if="canEdit && task" type="button" class="task-detail__btn task-detail__btn--ghost" @click="startEdit">
          Chỉnh sửa
        </button>
        <button
          v-if="canEdit && task"
          type="button"
          class="task-detail__btn task-detail__btn--danger"
          @click="confirmingDelete = true"
        >
          Xoá
        </button>
      </template>
    </PageHeader>

    <p v-if="loading" class="task-detail__empty">Đang tải chi tiết công việc…</p>
    <p v-else-if="notFound" class="task-detail__empty">Không tìm thấy công việc.</p>

    <div v-else-if="task" class="task-detail__page">
      <header class="task-detail__hero">
        <div class="task-detail__hero-copy">
          <p class="task-detail__kicker">
            <span>{{ task.code || `CV-${task.id}` }}</span>
            <span>{{ task.project?.name || 'Công việc thường xuyên' }}</span>
            <span>{{ typeLabel(task.type) }}</span>
          </p>
          <h2 class="task-detail__heading">{{ task.title }}</h2>
          <p v-if="task.description" class="task-detail__lead">{{ task.description }}</p>
        </div>

        <div v-if="headerPeople.length" class="task-detail__avatars">
          <UserAvatarTip
            v-for="person in headerPeople.slice(0, 5)"
            :key="person.id"
            :user="person"
            :label="person.name"
          />
          <span v-if="headerPeople.length > 5" class="task-detail__avatar-more">+{{ headerPeople.length - 5 }}</span>
        </div>
      </header>

      <section class="task-detail__card">
        <button
          type="button"
          class="task-detail__collapse"
          :aria-expanded="openSections.info"
          @click="toggleSection('info')"
        >
          <AppIcon
            name="chevronRight"
            :size="16"
            :stroke-width="1.75"
            class="task-detail__chevron"
            :class="{ 'task-detail__chevron--open': openSections.info }"
          />
          <span class="task-detail__collapse-copy">
            <span class="task-detail__title">Thông tin chung</span>
            <span class="task-detail__hint">Thời gian, người phụ trách và mức độ quan trọng</span>
          </span>
        </button>
        <div class="task-detail__fold" :class="{ 'task-detail__fold--open': openSections.info }">
          <div class="task-detail__fold-inner">
            <div class="task-detail__grid">
              <div class="task-detail__row">
                <span>Dự án</span>
                <strong>{{ task.project?.name || 'Công việc thường xuyên' }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Mã công việc</span>
                <strong>{{ task.code || task.id }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Loại công việc</span>
                <strong>{{ typeLabel(task.type) }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Mức độ quan trọng</span>
                <strong>{{ priorityLabel(task.priority) }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Thời gian dự kiến</span>
                <strong>{{ formatDate(task.start_date) }} - {{ formatDate(task.end_date) }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Thời gian thực tế</span>
                <strong>{{ actualTimeLabel }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Người giao việc</span>
                <strong class="task-detail__person">
                  <UserAvatarTip v-if="task.manager" :user="task.manager" label="Người giao việc" />
                  {{ task.manager?.name || '--' }}
                </strong>
              </div>
              <div class="task-detail__row">
                <span>Người thực hiện</span>
                <strong class="task-detail__person">
                  <UserAvatarTip v-if="task.assignee" :user="task.assignee" label="Người thực hiện" />
                  {{ task.assignee?.name || '--' }}
                </strong>
              </div>
              <div class="task-detail__row">
                <span>Theo dõi</span>
                <strong v-if="task.watchers?.length" class="task-detail__people">
                  <span v-for="person in task.watchers" :key="`w-${person.id}`" class="task-detail__person">
                    <UserAvatarTip :user="person" label="Người theo dõi" />
                    {{ person.name }}
                  </span>
                </strong>
                <strong v-else>--</strong>
              </div>
              <div class="task-detail__row">
                <span>Phối hợp</span>
                <strong v-if="task.collaborators?.length" class="task-detail__people">
                  <span v-for="person in task.collaborators" :key="`c-${person.id}`" class="task-detail__person">
                    <UserAvatarTip :user="person" label="Người phối hợp" />
                    {{ person.name }}
                  </span>
                </strong>
                <strong v-else>--</strong>
              </div>
              <div class="task-detail__row">
                <span>Cách tính tiến độ</span>
                <strong>{{ progressTypeLabel(task.progress_type) }}</strong>
              </div>
              <div v-if="task.progress_type === 'quantity'" class="task-detail__row">
                <span>Khối lượng</span>
                <strong>{{ task.progress_number }} / {{ task.progress_total }} {{ task.unit }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Số giờ dự kiến</span>
                <strong>{{ task.estimated_hours != null ? `${task.estimated_hours} giờ` : '--' }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Thời gian đã làm</span>
                <strong>{{ task.worklog_hours ? `${task.worklog_hours} giờ` : '--' }}</strong>
              </div>
              <div v-if="task.weight != null" class="task-detail__row">
                <span>Tỷ trọng</span>
                <strong>{{ task.weight }}%</strong>
              </div>
              <div class="task-detail__row task-detail__row--wide">
                <span>Mô tả</span>
                <strong>{{ task.description || '--' }}</strong>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="task-detail__card">
        <button
          type="button"
          class="task-detail__collapse"
          :aria-expanded="openSections.progress"
          @click="toggleSection('progress')"
        >
          <AppIcon
            name="chevronRight"
            :size="16"
            :stroke-width="1.75"
            class="task-detail__chevron"
            :class="{ 'task-detail__chevron--open': openSections.progress }"
          />
          <span class="task-detail__collapse-copy">
            <span class="task-detail__title">Tiến độ</span>
            <span class="task-detail__hint">
              {{ task.progress_percent != null ? `Đã hoàn thành ${task.progress_percent}%` : 'Chưa cập nhật tiến độ' }}
            </span>
          </span>
        </button>
        <div class="task-detail__fold" :class="{ 'task-detail__fold--open': openSections.progress }">
          <div class="task-detail__fold-inner">
            <div v-if="task.progress_percent != null" class="task-detail__progress">
              <div class="task-detail__progress-meta">
                <span>Thực tế {{ task.progress_percent }}%</span>
                <span v-if="expectedProgress != null">Kỳ vọng {{ expectedProgress }}%</span>
              </div>
              <DualProgressBar
                :actual="task.progress_percent"
                :expected="expectedProgress"
                size="md"
              />
            </div>
            <p v-else class="task-detail__empty">Chưa có số liệu tiến độ.</p>
          </div>
        </div>
      </section>

      <section class="task-detail__card">
        <button
          type="button"
          class="task-detail__collapse"
          :aria-expanded="openSections.score"
          @click="toggleSection('score')"
        >
          <AppIcon
            name="chevronRight"
            :size="16"
            :stroke-width="1.75"
            class="task-detail__chevron"
            :class="{ 'task-detail__chevron--open': openSections.score }"
          />
          <span class="task-detail__collapse-copy">
            <span class="task-detail__title">Kết quả đánh giá</span>
            <span class="task-detail__hint">
              {{ hasScore ? 'Đã có kết quả từ lần chấm điểm' : 'Sẽ hiện khi công việc được đánh giá' }}
            </span>
          </span>
        </button>
        <div class="task-detail__fold" :class="{ 'task-detail__fold--open': openSections.score }">
          <div class="task-detail__fold-inner">
            <div v-if="hasScore" class="task-detail__grid">
              <div class="task-detail__row">
                <span>Điểm đánh giá</span>
                <strong>{{ task.task_score.rating_score ?? '--' }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Kết quả</span>
                <strong>{{ task.task_score.rating_result || '--' }}</strong>
              </div>
              <div class="task-detail__row">
                <span>Người đánh giá</span>
                <strong class="task-detail__person">
                  <UserAvatarTip v-if="task.task_score.scorer" :user="task.task_score.scorer" label="Người đánh giá" />
                  {{ task.task_score.scorer?.name || '--' }}
                </strong>
              </div>
              <div class="task-detail__row">
                <span>Thời gian đánh giá</span>
                <strong>{{ formatDateTime(task.task_score.scored_at) }}</strong>
              </div>
              <div class="task-detail__row task-detail__row--wide">
                <span>Ý kiến đánh giá</span>
                <strong>{{ task.task_score.rating_desc || '--' }}</strong>
              </div>
            </div>
            <p v-else class="task-detail__empty">
              Chưa có kết quả. Phần này tự hiện khi công việc được chấm điểm, không điền tại đây.
            </p>
          </div>
        </div>
      </section>

      <section class="task-detail__card">
        <div class="task-detail__collapse-row">
          <button
            type="button"
            class="task-detail__collapse"
            :aria-expanded="openSections.worklog"
            @click="toggleSection('worklog')"
          >
            <AppIcon
              name="chevronRight"
              :size="16"
              :stroke-width="1.75"
              class="task-detail__chevron"
              :class="{ 'task-detail__chevron--open': openSections.worklog }"
            />
            <span class="task-detail__collapse-copy">
              <span class="task-detail__title">Nhật ký giờ làm</span>
              <span class="task-detail__hint">{{ worklogs.length ? `${worklogs.length} dòng ghi nhận` : 'Chưa có giờ làm' }}</span>
            </span>
          </button>
          <button
            v-if="canEdit && !worklogFormOpen"
            type="button"
            class="task-detail__btn task-detail__btn--ghost"
            @click="openWorklogForm"
          >
            Thêm giờ làm
          </button>
        </div>
        <div class="task-detail__fold" :class="{ 'task-detail__fold--open': openSections.worklog }">
          <div class="task-detail__fold-inner">
            <form v-if="worklogFormOpen" class="task-detail__form task-detail__form--compact" @submit.prevent="saveWorklog">
              <label class="task-detail__field">
                <span>Ngày làm</span>
                <input v-model="worklogForm.work_date" type="date" required />
              </label>
              <label class="task-detail__field">
                <span>Số giờ</span>
                <input v-model="worklogForm.hours" type="number" min="0.25" max="24" step="0.25" required />
              </label>
              <label class="task-detail__field task-detail__field--wide">
                <span>Ghi chú</span>
                <input v-model="worklogForm.note" type="text" />
              </label>
              <div class="task-detail__form-actions">
                <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="worklogSaving" @click="worklogFormOpen = false">Huỷ</button>
                <button type="submit" class="task-detail__btn" :disabled="worklogSaving">{{ worklogSaving ? 'Đang lưu…' : 'Lưu' }}</button>
              </div>
            </form>
            <ul v-if="worklogs.length" class="task-detail__list">
              <li v-for="log in worklogs" :key="log.id">
                <span class="task-detail__person">
                  <UserAvatarTip v-if="log.user" :user="log.user" label="Người ghi giờ" />
                  {{ log.user?.name || '--' }}
                </span>
                <span>{{ formatDate(log.work_date) }} · {{ log.hours }} giờ</span>
                <span>{{ log.note || '--' }}</span>
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
        </div>
      </section>

      <section class="task-detail__card">
        <div class="task-detail__collapse-row">
          <button
            type="button"
            class="task-detail__collapse"
            :aria-expanded="openSections.files"
            @click="toggleSection('files')"
          >
            <AppIcon
              name="chevronRight"
              :size="16"
              :stroke-width="1.75"
              class="task-detail__chevron"
              :class="{ 'task-detail__chevron--open': openSections.files }"
            />
            <span class="task-detail__collapse-copy">
              <span class="task-detail__title">Tệp đính kèm</span>
              <span class="task-detail__hint">{{ attachments.length ? `${attachments.length} tệp` : 'Chưa có tệp' }}</span>
            </span>
          </button>
          <button
            v-if="canEdit"
            type="button"
            class="task-detail__btn task-detail__btn--ghost"
            :disabled="attachmentUploading"
            @click="triggerAttachmentInput"
          >
            {{ attachmentUploading ? 'Đang tải…' : 'Tải file' }}
          </button>
        </div>
        <input ref="attachmentInput" type="file" class="task-detail__hidden" @change="onAttachmentChange" />
        <div class="task-detail__fold" :class="{ 'task-detail__fold--open': openSections.files }">
          <div class="task-detail__fold-inner">
            <div v-if="attachments.length" class="task-detail__files">
              <div v-for="att in attachments" :key="att.id" class="task-detail__file">
                <AppIcon name="fileText" :size="16" />
                <a :href="att.file_url" target="_blank" rel="noopener">{{ att.file_name }}</a>
                <span>{{ formatFileSize(att.file_size) }}</span>
                <button
                  v-if="canEdit"
                  type="button"
                  class="task-detail__icon-btn"
                  aria-label="Xoá tệp đính kèm"
                  @click="removeAttachment(att)"
                >
                  <AppIcon name="trash" :size="14" />
                </button>
              </div>
            </div>
            <p v-else class="task-detail__empty">Chưa có tệp đính kèm.</p>
          </div>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="peopleOpen" class="task-detail__dialog" role="presentation" @mousedown.self="closePeopleModal">
        <div class="task-detail__dialog-panel" role="dialog" aria-modal="true" aria-labelledby="task-detail-people-title">
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon" aria-hidden="true">
              <AppIcon name="userPlus" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-people-title" class="task-detail__dialog-title">Thêm người</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="peopleSaving" @click="closePeopleModal">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <div class="task-detail__dialog-body hide-scrollbar">
            <div class="task-detail__people-grid">
              <article class="task-detail__people-card">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="user" :size="16" :stroke-width="1.75" />
                  </span>
                  <div>
                    <h3>Người thực hiện</h3>
                    <p>Người chịu trách nhiệm hoàn thành việc này.</p>
                  </div>
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
              <article class="task-detail__people-card">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="eye" :size="16" :stroke-width="1.75" />
                  </span>
                  <div>
                    <h3>Người giao việc</h3>
                    <p>Ai giao và theo dõi việc này.</p>
                  </div>
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
              <article class="task-detail__people-card">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="eye" :size="16" :stroke-width="1.75" />
                  </span>
                  <div>
                    <h3>Người theo dõi</h3>
                    <p>Ai được xem tiến độ, không phải người làm.</p>
                  </div>
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
              <article class="task-detail__people-card">
                <div class="task-detail__people-head">
                  <span class="task-detail__people-icon">
                    <AppIcon name="users" :size="16" :stroke-width="1.75" />
                  </span>
                  <div>
                    <h3>Người phối hợp</h3>
                    <p>Ai hỗ trợ người thực hiện.</p>
                  </div>
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
              {{ peopleSaving ? 'Đang lưu…' : 'Lưu người tham gia' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="editing" class="task-detail__dialog" role="presentation" @mousedown.self="cancelEdit">
        <div class="task-detail__dialog-panel" role="dialog" aria-modal="true" aria-labelledby="task-detail-edit-title">
          <div class="task-detail__dialog-head">
            <span class="task-detail__dialog-icon" aria-hidden="true">
              <AppIcon name="pencil" :size="22" :stroke-width="1.75" />
            </span>
            <div class="task-detail__dialog-head-copy">
              <h2 id="task-detail-edit-title" class="task-detail__dialog-title">Sửa công việc</h2>
            </div>
            <button type="button" class="task-detail__icon-btn" aria-label="Đóng" :disabled="saving" @click="cancelEdit">
              <AppIcon name="close" :size="16" />
            </button>
          </div>
          <form class="task-detail__dialog-body hide-scrollbar" @submit.prevent="saveEdit">
            <div class="task-detail__form">
              <label class="task-detail__field task-detail__field--wide">
                <span>Tên công việc</span>
                <input v-model="editForm.title" type="text" required maxlength="255" />
              </label>
              <label class="task-detail__field">
                <span>Trạng thái</span>
                <select v-model="editForm.status">
                  <option v-for="item in statusChoices" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
              </label>
              <label class="task-detail__field">
                <span>Mức độ quan trọng</span>
                <select v-model="editForm.priority">
                  <option value="">Chưa đặt</option>
                  <option v-for="opt in priorityChoices" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </label>
              <label class="task-detail__field">
                <span>Người thực hiện</span>
                <select v-model="editForm.assignee_id">
                  <option value="">Chưa gán</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
              </label>
              <label class="task-detail__field">
                <span>Người giao việc</span>
                <select v-model="editForm.manager_id">
                  <option value="">Chưa gán</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
              </label>
              <label class="task-detail__field">
                <span>Cách tính tiến độ</span>
                <select v-model="editForm.progress_type">
                  <option v-for="(label, value) in TASK_PROGRESS_TYPE_LABELS" :key="value" :value="value">{{ label }}</option>
                </select>
              </label>
              <label class="task-detail__field">
                <span>Tiến độ (%)</span>
                <input v-model="editForm.progress_percent" type="number" min="0" max="100" :disabled="editForm.progress_type === 'quantity'" />
              </label>
              <template v-if="editForm.progress_type === 'quantity'">
                <label class="task-detail__field">
                  <span>Khối lượng đã hoàn thành</span>
                  <input v-model="editForm.progress_number" type="number" min="0" step="0.01" />
                </label>
                <label class="task-detail__field">
                  <span>Khối lượng cần hoàn thành</span>
                  <input v-model="editForm.progress_total" type="number" min="0.01" step="0.01" />
                </label>
                <label class="task-detail__field">
                  <span>Đơn vị</span>
                  <input v-model="editForm.unit" type="text" maxlength="50" />
                </label>
                <p v-if="editFormEstimatedPercent != null" class="task-detail__meta">Ước tính {{ editFormEstimatedPercent }}%</p>
              </template>
              <label class="task-detail__field">
                <span>Ngày bắt đầu</span>
                <input v-model="editForm.start_date" type="date" />
              </label>
              <label class="task-detail__field">
                <span>Ngày kết thúc</span>
                <input v-model="editForm.end_date" type="date" />
              </label>
              <label class="task-detail__field">
                <span>Bắt đầu thực tế</span>
                <input v-model="editForm.actual_start_date" type="date" />
              </label>
              <label class="task-detail__field">
                <span>Kết thúc thực tế</span>
                <input v-model="editForm.actual_end_date" type="date" />
              </label>
              <label class="task-detail__field">
                <span>Thời gian dự kiến (giờ)</span>
                <input v-model="editForm.estimated_hours" type="number" min="0" step="0.5" />
              </label>
              <label class="task-detail__field">
                <span>Tỷ trọng (%)</span>
                <input v-model="editForm.weight" type="number" min="0" max="100" step="0.1" />
              </label>
              <label class="task-detail__field task-detail__field--wide">
                <span>Mô tả</span>
                <textarea v-model="editForm.description" rows="3" />
              </label>
            </div>
          </form>
          <div class="task-detail__dialog-actions">
            <button type="button" class="task-detail__btn task-detail__btn--ghost" :disabled="saving" @click="cancelEdit">Huỷ</button>
            <button type="button" class="task-detail__btn" :disabled="saving" @click="saveEdit">{{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}</button>
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
      :open="Boolean(confirmingDeleteWorklog)"
      title="Xoá nhật ký giờ làm"
      description="Bạn có chắc muốn xoá dòng nhật ký giờ làm này? Thao tác này không thể hoàn tác."
      confirm-label="Xoá"
      danger
      @confirm="confirmDeleteWorklog"
      @update:open="confirmingDeleteWorklog = $event ? confirmingDeleteWorklog : null"
    />
  </section>
</template>

<style scoped>
.task-detail {
  min-height: 100%;
  padding: 0 var(--space-5) var(--space-4);
}

.task-detail__page {
  display: grid;
  gap: var(--space-4);
}

.task-detail__hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__hero-copy {
  min-width: 0;
  flex: 1;
}

.task-detail__kicker {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem 0.75rem;
  margin: 0 0 var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.task-detail__heading {
  margin: 0;
  color: var(--color-text);
  font-size: var(--text-h2);
  font-weight: 700;
  line-height: var(--text-h2-line-height);
  letter-spacing: var(--text-h2-letter-spacing);
}

.task-detail__lead {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.task-detail__hero-actions {
  display: flex;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-2);
}

.task-detail__status-wrap {
  position: relative;
}

.task-detail__status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.task-detail__status:disabled {
  cursor: default;
  opacity: 0.85;
}

.task-detail__status--open,
.task-detail__status:hover:not(:disabled) {
  background: var(--color-tertiary-surface-strong);
}

.task-detail__status--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.task-detail__status--primary.task-detail__status--open,
.task-detail__status--primary:hover:not(:disabled) {
  background: var(--color-primary-surface-strong);
}

.task-detail__status--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}

.task-detail__status--gold.task-detail__status--open,
.task-detail__status--gold:hover:not(:disabled) {
  background: var(--color-gold-surface-strong);
}

.task-detail__status--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.task-detail__status--umber {
  background: var(--color-umber-tint-bg);
  color: var(--color-umber-tint-fg);
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
  z-index: 20;
  min-width: 14rem;
  padding: 0.375rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
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

.task-detail__avatars {
  display: flex;
  align-items: center;
}

.task-detail__avatars :deep(.user-avatar-tip) {
  margin-left: -0.375rem;
}

.task-detail__avatars :deep(.user-avatar-tip:first-child) {
  margin-left: 0;
}

.task-detail__avatar-more {
  margin-left: 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.task-detail__card {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-detail__collapse-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}

.task-detail__collapse {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: flex-start;
  gap: var(--space-2);
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.task-detail__chevron {
  flex-shrink: 0;
  margin-top: 0.2rem;
  color: var(--color-text-muted);
  transition: transform 200ms ease;
}

.task-detail__chevron--open {
  transform: rotate(90deg);
}

.task-detail__collapse-copy {
  display: grid;
  gap: 0.125rem;
  min-width: 0;
}

.task-detail__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 700;
}

.task-detail__hint {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__fold {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 200ms ease;
}

.task-detail__fold--open {
  grid-template-rows: 1fr;
}

.task-detail__fold-inner {
  overflow: hidden;
}

.task-detail__fold--open .task-detail__fold-inner {
  padding-top: var(--space-3);
}

.task-detail__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.task-detail__row {
  display: grid;
  gap: 0.25rem;
  min-width: 0;
}

.task-detail__row--wide {
  grid-column: 1 / -1;
}

.task-detail__row > span {
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__row > span::after {
  content: ':';
}

.task-detail__row > strong {
  font-weight: 400;
  font-style: italic;
  color: var(--color-text);
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

.task-detail__progress-meta {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.task-detail__list,
.task-detail__files {
  display: grid;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.task-detail__list li,
.task-detail__file {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) auto minmax(0, 1fr) auto;
  gap: 0.5rem;
  align-items: center;
  padding: 0.625rem 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.task-detail__file {
  grid-template-columns: auto minmax(0, 1fr) auto auto;
}

.task-detail__file a {
  color: var(--color-text);
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-detail__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.task-detail__form {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.task-detail__form--compact {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-bottom: var(--space-3);
}

.task-detail__field {
  display: grid;
  gap: 0.375rem;
  min-width: 0;
}

.task-detail__field--wide,
.task-detail__form-actions {
  grid-column: 1 / -1;
}

.task-detail__field span {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
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
}

.task-detail__form-actions,
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
  background: var(--color-primary);
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
  background: var(--color-primary-surface);
  color: var(--color-primary);
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

.task-detail__people-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.task-detail__people-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-detail__people-head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.task-detail__people-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.task-detail__people-head h3 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
}

.task-detail__people-head p {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.task-detail__people-count {
  margin-left: auto;
  min-width: 1.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}

@media (max-width: 1100px) {
  .task-detail__hero,
  .task-detail__grid,
  .task-detail__form,
  .task-detail__form--compact,
  .task-detail__people-grid {
    grid-template-columns: 1fr;
  }

  .task-detail__hero {
    flex-direction: column;
  }

  .task-detail__hero-actions {
    justify-content: flex-start;
  }
}
</style>
