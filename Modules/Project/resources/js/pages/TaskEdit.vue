<script setup>
//
// manager/project/tasks/:id/edit — Trang riêng (không phải modal) để sửa
// công việc. Cùng bố cục với ProjectEdit.vue: PageHeader + 1 trang cuộn dọc
// dùng chung TaskFormFields.vue (không chia bước wizard — bỏ prop `step`
// để form hiện đủ mọi section liền mạch, phù hợp sửa nhanh 1 công việc
// đã có sẵn dữ liệu thay vì nhập mới tuần tự).
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import TaskFormFields from '../components/TaskFormFields.vue';

const route = useRoute();
const router = useRouter();

const taskId = computed(() => Number(route.params.id));

const saving = ref(false);
const loadingMeta = ref(true);
const notFound = ref(false);
const formErrors = ref({});
const users = ref([]);
const importanceOptions = ref([]);
const importanceCriterion = ref(null);
const lockDifficulty = ref(false);
const scoreKitMode = ref('');
const selectedParent = ref(null);
const task = ref(null);

const form = reactive({
  project_id: '',
  parent_id: '',
  title: '',
  description: '',
  start_date: '',
  end_date: '',
  assign_by_time: false,
  start_time: '07:30',
  due_time: '17:00',
  constrain_child_dates: false,
  assignee_id: '',
  manager_id: '',
  watcher_ids: [],
  collaborator_ids: [],
  type: 'task',
  priority: '',
  progress_type: 'percent',
  progress_percent: '',
  progress_number: '',
  progress_total: '',
  unit: '',
  estimated_hours: '',
  weight: '',
  actual_start_date: '',
  actual_end_date: '',
  hide_cross_tasks_from_assignees: false,
  hide_from_parent_assignees: false,
  hide_from_parent_followers: false,
  hide_child_tasks_from_followers: false,
  allow_child_people_view_parent: false,
  report_complete_action: 'none',
  completed_interaction_policy: 'inherit',
  report_description_requirement: 'none',
  report_attachment_requirement: 'none',
});

function formatClock(value) {
  if (!value) return '';
  return String(value).slice(0, 5);
}

function updateField(field, value) {
  form[field] = value;
}

function updateParentItem(item) {
  selectedParent.value = item;
}

const durationDays = computed(() => {
  if (!form.start_date || !form.end_date) return null;
  const start = new Date(form.start_date);
  const end = new Date(form.end_date);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
  return diff > 0 ? diff : null;
});

const isQuantity = computed(() => form.progress_type === 'quantity');

const canSubmit = computed(
  () =>
    Boolean(form.title.trim()) &&
    Boolean(form.start_date) &&
    Boolean(form.end_date) &&
    (!isQuantity.value || Number(form.progress_total) > 0) &&
    !saving.value,
);

function applyTask(next) {
  task.value = next;
  Object.assign(form, {
    project_id: next.project?.id ? String(next.project.id) : '',
    parent_id: next.parent?.id ? String(next.parent.id) : '',
    title: next.title || '',
    description: next.description || '',
    start_date: next.start_date || '',
    end_date: next.end_date || '',
    assign_by_time: Boolean(next.start_time || next.due_time),
    start_time: formatClock(next.start_time) || '07:30',
    due_time: formatClock(next.due_time) || '17:00',
    constrain_child_dates: Boolean(next.constrain_child_dates),
    assignee_id: next.assignee_id || '',
    manager_id: next.manager_id || '',
    watcher_ids: (next.watchers || []).map((person) => person.id),
    collaborator_ids: (next.collaborators || []).map((person) => person.id),
    type: next.type || 'task',
    priority: next.priority || '',
    progress_type: next.progress_type || 'percent',
    progress_percent: next.progress_percent ?? '',
    progress_number: next.progress_number ?? '',
    progress_total: next.progress_total ?? '',
    unit: next.unit || '',
    estimated_hours: next.estimated_hours ?? '',
    weight: next.weight ?? '',
    actual_start_date: next.actual_start_date || '',
    actual_end_date: next.actual_end_date || '',
    hide_cross_tasks_from_assignees: Boolean(next.hide_cross_tasks_from_assignees),
    hide_from_parent_assignees: Boolean(next.hide_from_parent_assignees),
    hide_from_parent_followers: Boolean(next.hide_from_parent_followers),
    hide_child_tasks_from_followers: Boolean(next.hide_child_tasks_from_followers),
    allow_child_people_view_parent: Boolean(next.allow_child_people_view_parent),
    report_complete_action: next.report_complete_action || 'none',
    completed_interaction_policy: next.completed_interaction_policy || 'inherit',
    report_description_requirement: next.report_description_requirement || 'none',
    report_attachment_requirement: next.report_attachment_requirement || 'none',
  });
  selectedParent.value = next.parent || null;
}

async function loadMeta() {
  loadingMeta.value = true;
  notFound.value = false;
  try {
    const [usersRes, optionsRes, taskRes] = await Promise.all([
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/tasks/options'),
      window.axios.get(`/api/project/tasks/${taskId.value}`),
    ]);
    users.value = usersRes.data.users ?? [];
    importanceOptions.value = optionsRes.data.importance ?? [];
    importanceCriterion.value = optionsRes.data.criterion ?? null;
    lockDifficulty.value = Boolean(optionsRes.data.lock_difficulty);
    scoreKitMode.value = optionsRes.data.mode || '';
    applyTask(taskRes.data.task);
  } catch (err) {
    if (err?.response?.status === 404) {
      notFound.value = true;
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không tải được dữ liệu công việc.');
    }
  } finally {
    loadingMeta.value = false;
  }
}

function goBack() {
  router.push({ name: 'manager.project.tasks.detail', params: { id: taskId.value } });
}

function validateBeforeSubmit() {
  if (!form.title.trim() || !form.start_date || !form.end_date) {
    showClientToast('error', 'Vui lòng nhập tên và thời gian thực hiện công việc.');
    return false;
  }
  if (form.end_date < form.start_date) {
    showClientToast('error', 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.');
    return false;
  }
  const parent = selectedParent.value;
  if (
    parent &&
    ((parent.start_date && form.start_date < parent.start_date) ||
      (parent.end_date && form.end_date > parent.end_date))
  ) {
    showClientToast('error', 'Thời gian công việc con phải nằm trong khoảng thời gian của công việc cha.');
    return false;
  }
  if (isQuantity.value && Number(form.progress_total) <= 0) {
    showClientToast('error', 'Vui lòng nhập khối lượng cần hoàn thành lớn hơn 0.');
    return false;
  }
  return true;
}

async function submitForm() {
  if (!validateBeforeSubmit()) return;
  formErrors.value = {};
  saving.value = true;

  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    type: form.type,
    status: task.value?.status,
    priority: form.priority || null,
    start_date: form.start_date,
    end_date: form.end_date,
    start_time: form.assign_by_time ? form.start_time : null,
    due_time: form.assign_by_time ? form.due_time : null,
    constrain_child_dates: Boolean(form.constrain_child_dates),
    actual_start_date: form.actual_start_date || null,
    actual_end_date: form.actual_end_date || null,
    assignee_id: form.assignee_id || null,
    manager_id: form.manager_id || null,
    watcher_ids: form.watcher_ids,
    collaborator_ids: form.collaborator_ids,
    estimated_hours: form.estimated_hours === '' ? null : Number(form.estimated_hours),
    weight: form.weight === '' ? null : Number(form.weight),
    progress_type: form.progress_type,
    hide_cross_tasks_from_assignees: Boolean(form.hide_cross_tasks_from_assignees),
    hide_from_parent_assignees: Boolean(form.hide_from_parent_assignees),
    hide_from_parent_followers: Boolean(form.hide_from_parent_followers),
    hide_child_tasks_from_followers: Boolean(form.hide_child_tasks_from_followers),
    allow_child_people_view_parent: Boolean(form.allow_child_people_view_parent),
    report_complete_action: form.report_complete_action,
    completed_interaction_policy: form.completed_interaction_policy,
    report_description_requirement: form.report_description_requirement,
    report_attachment_requirement: form.report_attachment_requirement,
    ...(isQuantity.value
      ? {
          progress_number: form.progress_number === '' ? null : Number(form.progress_number),
          progress_total: form.progress_total === '' ? null : Number(form.progress_total),
          unit: form.unit.trim() || null,
        }
      : {
          progress_percent: form.progress_percent === '' ? null : Number(form.progress_percent),
        }),
  };

  try {
    const { data } = await window.axios.put(`/api/project/tasks/${taskId.value}`, payload);
    showClientToast('success', `Đã cập nhật công việc "${data.task.title}".`);
    router.push({ name: 'manager.project.tasks.detail', params: { id: taskId.value } });
  } catch (err) {
    if (err?.response?.status === 422) {
      formErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không lưu được công việc.');
    }
  } finally {
    saving.value = false;
  }
}

onMounted(loadMeta);
</script>

<template>
  <section class="proj-edit">
    <PageHeader
      title="Sửa công việc"
      icon="pencil"
      :description="task?.title ? `Đang sửa: ${task.title}` : 'Cập nhật thông tin công việc.'"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Tất cả công việc', to: { name: 'manager.project.tasks' } },
        { label: task?.title || 'Chi tiết', to: { name: 'manager.project.tasks.detail', params: { id: taskId } } },
        { label: 'Sửa' },
      ]"
    >
      <template #actions>
        <button type="button" class="proj-edit__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về chi tiết công việc
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingMeta" class="proj-edit__loading">Đang tải dữ liệu…</div>
    <div v-else-if="notFound" class="proj-edit__loading">Không tìm thấy công việc này.</div>

    <template v-else>
      <div class="proj-edit__body hide-scrollbar">
        <TaskFormFields
          :form="form"
          :errors="formErrors"
          :users="users"
          :importance-options="importanceOptions"
          :importance-criterion="importanceCriterion"
          :lock-difficulty="lockDifficulty"
          :score-kit-mode="scoreKitMode"
          :disabled="saving"
          :duration-days="durationDays"
          :selected-parent="selectedParent"
          @update:field="updateField"
          @update:parent-item="updateParentItem"
        />
      </div>

      <div class="proj-edit__actions">
        <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="saving" @click="goBack">
          Huỷ
        </button>
        <button type="button" class="proj-page__btn" :disabled="!canSubmit" @click="submitForm">
          {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}
        </button>
      </div>
    </template>
  </section>
</template>

<style scoped>
.proj-edit {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-5);
  overflow: hidden;
}

.proj-edit__header-btn {
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

.proj-edit__header-btn:hover {
  background: var(--color-surface-muted);
}

.proj-edit__loading {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted);
}

.proj-edit__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  margin-top: var(--space-4);
  padding-bottom: var(--space-2);
}

.proj-edit__actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.proj-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-page__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.proj-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-page__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.proj-page__btn--ghost:hover:not(:disabled) {
  background: var(--color-border);
}

@media (max-width: 768px) {
  .proj-edit {
    padding: var(--space-4);
  }
}

@media (max-width: 480px) {
  .proj-edit {
    padding: var(--space-3);
  }
}
</style>
