<script setup>
//
// manager/project/:id/edit — Trang riêng (không phải modal) để sửa dự án.
// Cùng bố cục rail + vùng cuộn với ProjectCreate.vue, dùng chung
// ProjectFormFields.vue. Nạp dữ liệu dự án hiện tại theo :id trong route.
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectFormFields from '../components/ProjectFormFields.vue';
import { collapseDuplicateDepartmentIds } from '../utils/projectDepartments.js';

const STEPS = [
  { id: 1, title: 'Thông tin dự án', icon: 'fileText', color: 'primary' },
  { id: 2, title: 'Tổ chức', icon: 'building', color: 'gold' },
  { id: 3, title: 'Thành viên', icon: 'users', color: 'secondary' },
  { id: 4, title: 'Cài đặt quyền', icon: 'shield', color: 'tertiary' },
];

const route = useRoute();
const router = useRouter();

const projectId = computed(() => Number(route.params.id));

const saving = ref(false);
const loadingMeta = ref(true);
const notFound = ref(false);
const formErrors = ref({});
const step = ref(1);
const stepDirection = ref(1);

const stepTransitionName = computed(() =>
  stepDirection.value >= 0 ? 'proj-wizard-forward' : 'proj-wizard-back',
);

const wizardProgressPct = computed(() => (step.value / STEPS.length) * 100);

const options = reactive({
  type: [],
  status: [],
  importance: [],
  progress_method: [],
  scope_type: [],
});
const canChooseOwnerDepartment = ref(false);
const departments = ref([]);
const assignableUsers = ref([]);
const allLabels = ref([]);
const ownerDepartment = ref(null);

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

const canSubmit = computed(
  () => Boolean(form.type) && form.name.trim() !== '' && !saving.value && !uploadingAvatar.value,
);

const formDisabled = computed(() => saving.value || uploadingAvatar.value);

function mergeDepartmentsFromProject(deptList, project) {
  const byId = new Map(deptList.map((d) => [d.id, d]));
  const extras = [];
  const candidates = [
    project.owner_department,
    project.lead_department,
    ...(project.executing_departments || []),
    ...(project.scopes || []).map((s) => s.department).filter(Boolean),
  ];
  for (const d of candidates) {
    if (!d?.id || byId.has(d.id)) continue;
    extras.push({
      id: d.id,
      name: d.name,
      code: d.code ?? null,
      hrm_code: d.hrm_code ?? d.external_code ?? null,
      company_id: d.company_id ?? null,
      company_code: d.company_code ?? null,
      company_name: d.company_name ?? null,
    });
    byId.set(d.id, true);
  }
  if (!extras.length) return deptList;
  return [...deptList, ...extras].sort((a, b) => (a.name || '').localeCompare(b.name || '', 'vi'));
}

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

  return project;
}

async function loadMeta() {
  loadingMeta.value = true;
  notFound.value = false;
  try {
    const [optionsRes, departmentsRes, usersRes, labelsRes, projectRes] = await Promise.all([
      window.axios.get('/api/project/options'),
      window.axios.get('/manager/departments', { params: { source: 'hrm' } }),
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/labels'),
      window.axios.get(`/api/project/${projectId.value}`),
    ]);
    options.type = optionsRes.data.type ?? [];
    options.status = optionsRes.data.status ?? [];
    options.importance = optionsRes.data.importance ?? [];
    options.progress_method = optionsRes.data.progress_method ?? [];
    options.scope_type = optionsRes.data.scope_type ?? [];
    canChooseOwnerDepartment.value = Boolean(optionsRes.data.can_choose_owner_department);
    assignableUsers.value = usersRes.data.users ?? [];
    allLabels.value = labelsRes.data.labels ?? [];
    const project = projectRes.data.project;
    departments.value = mergeDepartmentsFromProject(departmentsRes.data.departments ?? [], project);
    applyProject(project);
    form.executing_department_ids = collapseDuplicateDepartmentIds(
      form.executing_department_ids,
      departments.value,
    );
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

function goToStep(next) {
  if (next < 1 || next > STEPS.length) return;
  if (next === step.value) return;
  stepDirection.value = next > step.value ? 1 : -1;
  step.value = next;
}

async function submitForm() {
  if (!form.type || !form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập đủ Loại dự án và Tên dự án.');
    step.value = 1;
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
    <svg class="proj-edit__wm-defs" aria-hidden="true" focusable="false">
      <filter id="proj-edit-watermark-boost" color-interpolation-filters="sRGB">
        <feColorMatrix type="matrix" values="0 0 0 0 0.604  0 0 0 0 0  0 0 0 0 0.212  0 0 0 20 0" />
      </filter>
    </svg>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="proj-edit__watermark"
      aria-hidden="true"
      :style="{ filter: 'url(#proj-edit-watermark-boost)' }"
    />

    <PageHeader icon="pencil" :description="form.name ? `Đang sửa: ${form.name}` : 'Cập nhật thông tin dự án.'">
      <template #title>
        <span class="proj-edit__title">Sửa dự án</span>
        <span v-if="form.code" class="proj-edit__code">{{ form.code }}</span>
      </template>
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
      <div class="proj-edit__layout">
        <nav class="proj-edit__rail" aria-label="Nhóm thông tin dự án">
          <div
            class="proj-edit__rail-flow"
            role="progressbar"
            :aria-valuenow="step"
            aria-valuemin="1"
            :aria-valuemax="STEPS.length"
            :aria-valuetext="`Mục ${step} trên ${STEPS.length}`"
          >
            <div class="proj-edit__rail-flow-head">
              <span class="proj-edit__rail-flow-label">Cập nhật dự án</span>
              <span class="proj-edit__rail-flow-step">Mục {{ step }}/{{ STEPS.length }}</span>
            </div>
            <div class="proj-edit__rail-flow-track">
              <div class="proj-edit__rail-flow-fill" :style="{ width: `${wizardProgressPct}%` }" />
            </div>
          </div>

          <ol class="proj-edit__rail-list">
            <li
              v-for="item in STEPS"
              :key="item.id"
              class="proj-edit__step"
              :class="[
                `proj-edit__step--${item.color}`,
                {
                  'proj-edit__step--current': step === item.id,
                  'proj-edit__step--done': step > item.id,
                },
              ]"
            >
              <button type="button" class="proj-edit__step-btn" @click="goToStep(item.id)">
                <span class="proj-edit__step-track" aria-hidden="true">
                  <span class="proj-edit__step-dot">
                    <AppIcon v-if="step > item.id" name="check" :size="17" :stroke-width="2.5" />
                    <AppIcon v-else :name="item.icon" :size="17" :stroke-width="2" />
                  </span>
                  <span v-if="item.id < STEPS.length" class="proj-edit__step-line">
                    <span class="proj-edit__step-line-fill" aria-hidden="true" />
                  </span>
                </span>
                <span class="proj-edit__step-title">{{ item.title }}</span>
              </button>
            </li>
          </ol>
        </nav>

        <div class="proj-edit__body hide-scrollbar">
          <div class="proj-edit__body-stage">
            <Transition :name="stepTransitionName" mode="out-in">
              <div :key="step" class="proj-edit__step-panel">
                <ProjectFormFields
                  :form="form"
                  :errors="formErrors"
                  :options="options"
                  :departments="departments"
                  :assignable-users="assignableUsers"
                  :all-labels="allLabels"
                  :disabled="formDisabled"
                  :step="step"
                  :duration-days="durationDays"
                  :progress-method-description="progressMethodDescription"
                  :avatar-preview-url="avatarPreviewUrl"
                  :owner-department="ownerDepartment"
                  :can-choose-owner-department="canChooseOwnerDepartment"
                  @update:field="updateField"
                  @label-created="onLabelCreated"
                  @type-created="onTypeCreated"
                  @avatar-selected="onAvatarSelected"
                  @avatar-removed="onAvatarRemoved"
                />
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <div class="proj-edit__actions">
        <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="saving" @click="goBack">
          Huỷ
        </button>
        <button
          v-if="step > 1"
          type="button"
          class="proj-page__btn proj-page__btn--ghost"
          :disabled="saving"
          @click="goToStep(step - 1)"
        >
          Quay lại
        </button>
        <button
          v-if="step < STEPS.length"
          type="button"
          class="proj-page__btn"
          :disabled="saving"
          @click="goToStep(step + 1)"
        >
          Tiếp tục
        </button>
        <button v-else type="button" class="proj-page__btn" :disabled="!canSubmit" @click="submitForm">
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
  padding: var(--space-4) var(--space-5) var(--space-4) var(--space-3);
  overflow: hidden;
  position: relative;
  isolation: isolate;
  background: var(--color-surface-muted);
  --proj-wizard-duration: 0.52s;
  --proj-wizard-ease: cubic-bezier(0.22, 1, 0.36, 1);
}

.proj-edit__wm-defs {
  position: absolute;
  width: 0;
  height: 0;
  overflow: hidden;
}

.proj-edit__watermark {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  transform: scale(1.05);
  pointer-events: none;
  opacity: 0.045;
}

.proj-edit__title {
  background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary-700) 100%);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-weight: 700;
}

.proj-edit__code {
  margin-left: var(--space-2);
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  -webkit-text-fill-color: var(--color-text-muted);
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
  position: relative;
  z-index: 1;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted);
}

.proj-edit__layout {
  position: relative;
  z-index: 1;
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-4);
  margin-top: var(--space-3);
}

.proj-edit__rail {
  flex-shrink: 0;
  align-self: stretch;
  width: 14.5rem;
  padding: var(--space-3) var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.proj-edit__rail-flow-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.proj-edit__rail-flow-label {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.proj-edit__rail-flow-step {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-primary);
}

.proj-edit__rail-flow-track {
  height: 4px;
  margin-top: var(--space-2);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.proj-edit__rail-flow-fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary-700) 100%);
  transition: width var(--proj-wizard-duration) var(--proj-wizard-ease);
}

.proj-edit__rail-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-edit__step {
  --step-color: var(--color-primary);
  --step-surface: var(--color-primary-surface);
}

.proj-edit__step--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.proj-edit__step--secondary {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);
}

.proj-edit__step--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.proj-edit__step-btn {
  display: flex;
  align-items: stretch;
  gap: var(--space-3);
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.proj-edit__step-track {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.proj-edit__step-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--step-surface);
  color: var(--step-color);
  transition:
    background-color var(--proj-wizard-duration) var(--proj-wizard-ease),
    color var(--proj-wizard-duration) var(--proj-wizard-ease),
    box-shadow var(--proj-wizard-duration) var(--proj-wizard-ease);
}

.proj-edit__step-line {
  position: relative;
  flex: 1;
  width: 2px;
  min-height: var(--space-4);
  margin: 2px 0;
  border-radius: var(--radius-full);
  background: var(--color-border);
  overflow: hidden;
}

.proj-edit__step-line-fill {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: var(--step-color);
  opacity: 0.4;
  transform: scaleY(0);
  transform-origin: top center;
  transition: transform calc(var(--proj-wizard-duration) * 1.15) var(--proj-wizard-ease);
}

.proj-edit__step--done .proj-edit__step-line-fill {
  transform: scaleY(1);
}

.proj-edit__step--current .proj-edit__step-dot {
  background: var(--step-color);
  color: var(--color-on-primary, #fff);
  box-shadow: 0 0 0 4px var(--step-surface);
}

.proj-edit__step--done .proj-edit__step-dot {
  background: var(--step-surface);
  color: var(--step-color);
}

.proj-edit__step-title {
  flex: 1;
  padding: 0.5rem 0 var(--space-5);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.35;
}

.proj-edit__step--current .proj-edit__step-title {
  color: var(--step-color);
  font-weight: 700;
}

.proj-edit__step--done .proj-edit__step-title {
  color: var(--color-text);
}

.proj-edit__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: var(--space-1) var(--space-1) var(--space-2);
}

.proj-edit__body-stage {
  position: relative;
  min-height: 0;
}

.proj-edit__step-panel {
  width: 100%;
  padding-bottom: var(--space-2);
}

.proj-edit__actions {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-2);
  padding-top: var(--space-3);
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
    padding: var(--space-3);
  }

  .proj-edit__layout {
    flex-direction: column;
    gap: var(--space-3);
  }

  .proj-edit__rail {
    width: 100%;
    padding: var(--space-2) var(--space-3);
  }

  .proj-edit__rail-list {
    flex-direction: row;
    justify-content: space-between;
  }

  .proj-edit__step-btn {
    flex-direction: column;
    align-items: center;
    gap: var(--space-1);
    text-align: center;
  }

  .proj-edit__step-track {
    flex-direction: row;
    width: 100%;
  }

  .proj-edit__step-line {
    height: 2px;
    width: auto;
    min-height: 0;
    margin: 0 2px;
  }

  .proj-edit__step-line-fill {
    transform: scaleX(0);
    transform-origin: left center;
  }

  .proj-edit__step--done .proj-edit__step-line-fill {
    transform: scaleX(1);
  }

  .proj-edit__step-title {
    padding: 0;
    font-size: 0.6875rem;
  }

  .proj-edit__code {
    display: none;
  }
}

@media (max-width: 480px) {
  .proj-edit {
    padding: var(--space-2);
  }
}

@media (prefers-reduced-motion: reduce) {
  .proj-edit {
    --proj-wizard-duration: 0.01ms;
  }
}
</style>

<style>
.proj-edit {
  --proj-wizard-ease: cubic-bezier(0.22, 1, 0.36, 1);
  --proj-wizard-duration: 0.52s;
}

.proj-edit .proj-wizard-forward-enter-active,
.proj-edit .proj-wizard-forward-leave-active,
.proj-edit .proj-wizard-back-enter-active,
.proj-edit .proj-wizard-back-leave-active {
  transition:
    opacity var(--proj-wizard-duration) var(--proj-wizard-ease),
    transform var(--proj-wizard-duration) var(--proj-wizard-ease);
}

.proj-edit .proj-wizard-forward-leave-active,
.proj-edit .proj-wizard-back-leave-active {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 0;
}

.proj-edit .proj-wizard-forward-enter-active,
.proj-edit .proj-wizard-back-enter-active {
  z-index: 1;
}

.proj-edit .proj-wizard-forward-enter-from {
  opacity: 0;
  transform: translateX(2.25rem);
}

.proj-edit .proj-wizard-forward-leave-to {
  opacity: 0;
  transform: translateX(-2.25rem);
}

.proj-edit .proj-wizard-back-enter-from {
  opacity: 0;
  transform: translateX(-2.25rem);
}

.proj-edit .proj-wizard-back-leave-to {
  opacity: 0;
  transform: translateX(2.25rem);
}

@media (prefers-reduced-motion: reduce) {
  .proj-edit {
    --proj-wizard-duration: 0.01ms;
  }

  .proj-edit .proj-wizard-forward-enter-active,
  .proj-edit .proj-wizard-forward-leave-active,
  .proj-edit .proj-wizard-back-enter-active,
  .proj-edit .proj-wizard-back-leave-active,
  .proj-edit .proj-wizard-forward-enter-from,
  .proj-edit .proj-wizard-forward-leave-to,
  .proj-edit .proj-wizard-back-enter-from,
  .proj-edit .proj-wizard-back-leave-to {
    transition: none;
    transform: none;
  }
}
</style>
