<script setup>
//
// manager/project/create — Trang riêng (không phải modal) để thêm dự án
// mới. Toàn màn hình, lưới ngang nhiều cột theo section (ProjectFormFields.vue
// dùng chung với ProjectEdit.vue). Theo mẫu ProjectSettings.vue: PageHeader
// + nút "Về danh sách", card cuộn ở giữa, thanh hành động cố định dưới.
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectFormFields from '../components/ProjectFormFields.vue';

const RULES = [
  { key: 'shift_task_dates_with_project', title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo' },
  { key: 'hide_cross_tasks_from_assignees', title: 'Không cho phép người thực hiện xem được công việc chéo thuộc cùng một dự án' },
  { key: 'hide_child_tasks_from_followers', title: 'Không cho phép người theo dõi xem được các công việc con thuộc dự án' },
  { key: 'constrain_task_dates_to_project', title: 'Thời gian dự kiến thực hiện công việc phải nằm trong khoảng thời gian của dự án' },
];

const router = useRouter();

const saving = ref(false);
const loadingMeta = ref(true);
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
const sharedRules = ref([]);

// Ảnh đại diện: dự án chưa tồn tại lúc chọn ảnh nên chỉ giữ file + preview ở
// client, upload thật (POST /api/project/{id}/avatar) sau khi tạo xong.
const avatarFile = ref(null);
const avatarPreviewUrl = ref('');

const form = reactive({
  code: '(tự động)',
  type: '',
  name: '',
  lead_user_id: '',
  executing_department_id: '',
  start_date: '',
  end_date: '',
  progress_method: 'average',
  status: 'planning',
  importance: 'medium',
  description: '',
  member_ids: [],
  follower_ids: [],
  scopes: [],
  label_ids: [],
});

function updateField(field, value) {
  form[field] = value;
}

function onLabelCreated(label) {
  if (!allLabels.value.some((l) => l.id === label.id)) {
    allLabels.value.push(label);
  }
}

function onAvatarSelected(file) {
  avatarFile.value = file;
  if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
  avatarPreviewUrl.value = URL.createObjectURL(file);
}

function openSettings() {
  router.push({ name: 'manager.project.settings' });
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

async function loadSharedRules() {
  // project.manage_settings không phải ai tạo dự án cũng có — 403 thì bỏ
  // qua lặng lẽ, không hiện khối "Cài đặt quyền" trong form.
  try {
    const { data } = await window.axios.get('/api/project/settings/general');
    sharedRules.value = RULES.map((r) => ({ ...r, enabled: Boolean(data[r.key]) }));
  } catch {
    sharedRules.value = [];
  }
}

async function loadMeta() {
  loadingMeta.value = true;
  try {
    const [optionsRes, departmentsRes, usersRes, labelsRes] = await Promise.all([
      window.axios.get('/api/project/options'),
      window.axios.get('/manager/departments'),
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/labels'),
      loadSharedRules(),
    ]);
    options.type = optionsRes.data.type ?? [];
    options.status = optionsRes.data.status ?? [];
    options.importance = optionsRes.data.importance ?? [];
    options.progress_method = optionsRes.data.progress_method ?? [];
    options.scope_type = optionsRes.data.scope_type ?? [];
    departments.value = departmentsRes.data.departments ?? [];
    assignableUsers.value = usersRes.data.users ?? [];
    allLabels.value = labelsRes.data.labels ?? [];
  } catch {
    showClientToast('error', 'Không tải được dữ liệu cần thiết cho form.');
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
    executing_department_id: form.executing_department_id || null,
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
  };

  try {
    const { data } = await window.axios.post('/api/project', payload);

    if (avatarFile.value) {
      const avatarForm = new FormData();
      avatarForm.append('avatar', avatarFile.value);
      try {
        await window.axios.post(`/api/project/${data.project.id}/avatar`, avatarForm);
      } catch {
        showClientToast('error', 'Đã tạo dự án nhưng tải ảnh đại diện thất bại, có thể thử lại ở trang sửa.');
      }
    }

    showClientToast('success', `Đã tạo dự án "${data.project.name}".`);
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
    <PageHeader title="Thêm dự án" icon="plusCircle" description="Tạo một dự án mới cho tổ chức.">
      <template #actions>
        <button type="button" class="proj-edit__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách dự án
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingMeta" class="proj-edit__loading">Đang tải dữ liệu…</div>

    <template v-else>
      <div class="proj-edit__body hide-scrollbar">
        <ProjectFormFields
          :form="form"
          :errors="formErrors"
          :options="options"
          :departments="departments"
          :assignable-users="assignableUsers"
          :all-labels="allLabels"
          :disabled="saving"
          is-create
          :duration-days="durationDays"
          :progress-method-description="progressMethodDescription"
          :avatar-preview-url="avatarPreviewUrl"
          :shared-rules="sharedRules"
          @update:field="updateField"
          @label-created="onLabelCreated"
          @avatar-selected="onAvatarSelected"
          @open-settings="openSettings"
        />
      </div>

      <div class="proj-edit__actions">
        <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="saving" @click="goBack">
          Huỷ
        </button>
        <button type="button" class="proj-page__btn" :disabled="!canSubmit" @click="submitForm">
          {{ saving ? 'Đang lưu…' : 'Tạo dự án' }}
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
