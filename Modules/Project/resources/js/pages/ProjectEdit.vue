<script setup>
//
// manager/project/:id/edit — Trang riêng (không phải modal) để sửa dự án.
// Cùng bố cục với ProjectCreate.vue, dùng chung ProjectFormFields.vue.
// Nạp dữ liệu dự án hiện tại theo :id trong route trước khi hiện form.
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectFormFields from '../components/ProjectFormFields.vue';

const route = useRoute();
const router = useRouter();

const projectId = computed(() => Number(route.params.id));

const saving = ref(false);
const loadingMeta = ref(true);
const notFound = ref(false);
const formErrors = ref({});

const options = reactive({
  type: [],
  status: [],
  importance: [],
  progress_method: [],
  scope_type: [],
});
const departments = ref([]);
const assignableUsers = ref([]);
const allLabels = ref([]);
const ownerDepartment = ref(null);

// Ảnh đại diện: dự án đã tồn tại nên upload ngay khi chọn ảnh (không đợi
// bấm "Lưu thay đổi") — patch trực tiếp avatar_url từ response API.
const uploadingAvatar = ref(false);
const avatarPreviewUrl = ref('');

const form = reactive({
  code: '',
  type: '',
  name: '',
  lead_user_id: '',
  lead_department_id: '',
  executing_department_ids: [],
  start_date: '',
  end_date: '',
  progress_method: 'average',
  status: 'planning',
  importance: 'important',
  description: '',
  member_ids: [],
  follower_ids: [],
  scopes: [],
  label_ids: [],
  shift_task_dates_with_project: false,
  hide_cross_tasks_from_assignees: false,
  hide_child_tasks_from_followers: false,
  constrain_task_dates_to_project: false,
});

function updateField(field, value) {
  form[field] = value;
}

function onLabelCreated(label) {
  if (!allLabels.value.some((l) => l.id === label.id)) {
    allLabels.value.push(label);
  }
}

function onTypeCreated(type) {
  if (!options.type.some((t) => t.value === type.value)) {
    options.type.push(type);
  }
}

async function onAvatarSelected(file) {
  uploadingAvatar.value = true;
  const avatarForm = new FormData();
  avatarForm.append('avatar', file);
  try {
    const { data } = await window.axios.post(`/api/project/${projectId.value}/avatar`, avatarForm);
    avatarPreviewUrl.value = data.project.avatar_url || '';
    showClientToast('success', 'Đã cập nhật ảnh đại diện.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tải được ảnh đại diện.');
  } finally {
    uploadingAvatar.value = false;
  }
}

async function onAvatarRemoved() {
  uploadingAvatar.value = true;
  try {
    const { data } = await window.axios.delete(`/api/project/${projectId.value}/avatar`);
    avatarPreviewUrl.value = data.project.avatar_url || '';
    showClientToast('success', 'Đã gỡ ảnh đại diện.');
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không gỡ được ảnh đại diện.');
  } finally {
    uploadingAvatar.value = false;
  }
}

const durationDays = computed(() => {
  if (!form.start_date || !form.end_date) return null;
  const start = new Date(form.start_date);
  const end = new Date(form.end_date);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
  return diff > 0 ? diff : null;
});

const progressMethodDescription = computed(
  () => options.progress_method.find((o) => o.value === form.progress_method)?.description || '',
);

const canSubmit = computed(() => Boolean(form.type) && form.name.trim() !== '' && !saving.value);

function applyProject(project) {
  const executingIds = (project.executing_departments || [])
    .map((d) => d?.id)
    .filter(Boolean);
  if (executingIds.length === 0 && project.executing_department?.id) {
    executingIds.push(project.executing_department.id);
  }

  Object.assign(form, {
    code: project.code,
    type: project.type,
    name: project.name,
    lead_user_id: project.lead_user_id || '',
    lead_department_id: project.lead_department?.id || '',
    executing_department_ids: executingIds,
    start_date: project.start_date || '',
    end_date: project.end_date || '',
    progress_method: project.progress_method,
    status: project.status,
    importance: project.importance,
    description: project.description || '',
    member_ids: (project.members || []).map((m) => m.id),
    follower_ids: (project.followers || []).map((f) => f.id),
    scopes: (project.scopes || []).slice(0, 1).map((s) => ({
      scope_type: s.scope_type,
      department_id: s.department?.id ?? null,
      weight_percent: s.weight_percent ?? 100,
    })),
    label_ids: (project.labels || []).map((l) => l.id),
    shift_task_dates_with_project: Boolean(project.shift_task_dates_with_project),
    hide_cross_tasks_from_assignees: Boolean(project.hide_cross_tasks_from_assignees),
    hide_child_tasks_from_followers: Boolean(project.hide_child_tasks_from_followers),
    constrain_task_dates_to_project: Boolean(project.constrain_task_dates_to_project),
  });
  ownerDepartment.value = project.owner_department || null;
  avatarPreviewUrl.value = project.avatar_url || '';
}

async function loadMeta() {
  loadingMeta.value = true;
  notFound.value = false;
  try {
    const [optionsRes, departmentsRes, usersRes, labelsRes, projectRes] = await Promise.all([
      window.axios.get('/api/project/options'),
      window.axios.get('/manager/departments'),
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/labels'),
      window.axios.get(`/api/project/${projectId.value}`),
    ]);
    options.type = optionsRes.data.type ?? [];
    options.status = optionsRes.data.status ?? [];
    options.importance = optionsRes.data.importance ?? [];
    options.progress_method = optionsRes.data.progress_method ?? [];
    options.scope_type = optionsRes.data.scope_type ?? [];
    departments.value = departmentsRes.data.departments ?? [];
    assignableUsers.value = usersRes.data.users ?? [];
    allLabels.value = labelsRes.data.labels ?? [];
    applyProject(projectRes.data.project);
  } catch (err) {
    if (err?.response?.status === 404) {
      notFound.value = true;
    } else {
      showClientToast('error', 'Không tải được dữ liệu dự án.');
    }
  } finally {
    loadingMeta.value = false;
  }
}

function goBack() {
  router.push({ name: 'manager.project.index' });
}

async function submitForm() {
  if (!form.type || !form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập đủ Loại dự án và Tên dự án.');
    return;
  }

  formErrors.value = {};
  saving.value = true;

  const payload = {
    type: form.type,
    name: form.name,
    lead_user_id: form.lead_user_id || null,
    lead_department_id: form.lead_department_id || null,
    executing_department_ids: form.executing_department_ids,
    start_date: form.start_date || null,
    end_date: form.end_date || null,
    progress_method: form.progress_method,
    status: form.status,
    importance: form.importance,
    description: form.description || null,
    member_ids: form.member_ids,
    follower_ids: form.follower_ids,
    scopes: form.scopes,
    label_ids: form.label_ids,
    shift_task_dates_with_project: Boolean(form.shift_task_dates_with_project),
    hide_cross_tasks_from_assignees: Boolean(form.hide_cross_tasks_from_assignees),
    hide_child_tasks_from_followers: Boolean(form.hide_child_tasks_from_followers),
    constrain_task_dates_to_project: Boolean(form.constrain_task_dates_to_project),
  };

  try {
    const { data } = await window.axios.put(`/api/project/${projectId.value}`, payload);
    showClientToast('success', `Đã cập nhật dự án "${data.project.name}".`);
    router.push({ name: 'manager.project.index' });
  } catch (err) {
    if (err?.response?.status === 422) {
      formErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không lưu được dự án.');
    }
  } finally {
    saving.value = false;
  }
}

onMounted(loadMeta);
</script>

<template>
  <section class="proj-edit">
    <PageHeader title="Sửa dự án" icon="pencil" :description="form.name ? `Đang sửa: ${form.name}` : 'Cập nhật thông tin dự án.'">
      <template #actions>
        <button type="button" class="proj-edit__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách dự án
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingMeta" class="proj-edit__loading">Đang tải dữ liệu…</div>
    <div v-else-if="notFound" class="proj-edit__loading">Không tìm thấy dự án này.</div>

    <template v-else>
      <div class="proj-edit__body hide-scrollbar">
        <ProjectFormFields
          :form="form"
          :errors="formErrors"
          :options="options"
          :departments="departments"
          :assignable-users="assignableUsers"
          :all-labels="allLabels"
          :disabled="saving || uploadingAvatar"
          :duration-days="durationDays"
          :progress-method-description="progressMethodDescription"
          :avatar-preview-url="avatarPreviewUrl"
          :owner-department="ownerDepartment"
          @update:field="updateField"
          @label-created="onLabelCreated"
          @type-created="onTypeCreated"
          @avatar-selected="onAvatarSelected"
          @avatar-removed="onAvatarRemoved"
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
