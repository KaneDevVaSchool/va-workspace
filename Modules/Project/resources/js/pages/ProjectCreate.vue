<script setup>
//
// manager/project/create — Wizard 4 bước: thông tin → tổ chức → phạm vi
// & thành viên → cài đặt quyền. Phương pháp tính tiến độ không nằm ở đây
// (thiết lập sau tại Cài đặt dự án).
//
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import ProjectFormFields from '../components/ProjectFormFields.vue';

const STEPS = [
  { id: 1, title: 'Thông tin dự án', icon: 'fileText', color: 'primary' },
  { id: 2, title: 'Tổ chức', icon: 'building', color: 'gold' },
  { id: 3, title: 'Phạm vi & thành viên', icon: 'users', color: 'secondary' },
  { id: 4, title: 'Cài đặt quyền', icon: 'shield', color: 'tertiary' },
];

const router = useRouter();
const auth = useAuthStore();

const saving = ref(false);
const loadingMeta = ref(true);
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

const avatarFile = ref(null);
const avatarPreviewUrl = ref('');

const form = reactive({
  code: '(tự động)',
  type: '',
  name: '',
  lead_user_id: '',
  lead_department_id: '',
  owner_department_id: '',
  executing_department_ids: [],
  start_date: '',
  end_date: '',
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

const ownerDepartment = computed(() => auth.user?.department || null);

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

function onAvatarSelected(file) {
  avatarFile.value = file;
  if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
  avatarPreviewUrl.value = URL.createObjectURL(file);
}

function onAvatarRemoved() {
  if (avatarPreviewUrl.value) URL.revokeObjectURL(avatarPreviewUrl.value);
  avatarFile.value = null;
  avatarPreviewUrl.value = '';
}

const durationDays = computed(() => {
  if (!form.start_date || !form.end_date) return null;
  const start = new Date(form.start_date);
  const end = new Date(form.end_date);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
  return diff > 0 ? diff : null;
});

const canSubmit = computed(() => Boolean(form.type) && form.name.trim() !== '' && !saving.value);

const canAdvanceFromCurrentStep = computed(() => {
  if (step.value === 1) {
    return Boolean(form.type) && form.name.trim() !== '';
  }
  return true;
});

const step1MissingFields = computed(() => {
  if (step.value !== 1) return [];
  const missing = [];
  if (!form.type) missing.push('Loại dự án');
  if (!form.name.trim()) missing.push('Tên dự án');
  return missing;
});

const showStep1ReadyNote = computed(
  () => step.value === 1 && canAdvanceFromCurrentStep.value && !saving.value,
);

function goToStep(next) {
  if (next < 1 || next > STEPS.length) return;
  if (next > step.value && !form.type) {
    showClientToast('error', 'Vui lòng chọn Loại dự án trước khi sang bước tiếp.');
    return;
  }
  if (next > step.value && !form.name.trim()) {
    showClientToast('error', 'Vui lòng nhập Tên dự án trước khi sang bước tiếp.');
    return;
  }
  if (next === step.value) return;
  stepDirection.value = next > step.value ? 1 : -1;
  step.value = next;
}

async function loadMeta() {
  loadingMeta.value = true;
  try {
    const [optionsRes, departmentsRes, usersRes, labelsRes] = await Promise.all([
      window.axios.get('/api/project/options'),
      window.axios.get('/manager/departments'),
      window.axios.get('/api/project/assignable-users'),
      window.axios.get('/api/project/labels'),
    ]);
    options.type = optionsRes.data.type ?? [];
    options.status = optionsRes.data.status ?? [];
    options.importance = optionsRes.data.importance ?? [];
    options.progress_method = optionsRes.data.progress_method ?? [];
    options.scope_type = optionsRes.data.scope_type ?? [];
    canChooseOwnerDepartment.value = Boolean(optionsRes.data.can_choose_owner_department);
    departments.value = departmentsRes.data.departments ?? [];
    assignableUsers.value = usersRes.data.users ?? [];
    allLabels.value = labelsRes.data.labels ?? [];

    try {
      const { data } = await window.axios.get('/api/project/settings/general');
      form.shift_task_dates_with_project = Boolean(data.shift_task_dates_with_project);
      form.hide_cross_tasks_from_assignees = Boolean(data.hide_cross_tasks_from_assignees);
      form.hide_child_tasks_from_followers = Boolean(data.hide_child_tasks_from_followers);
      form.constrain_task_dates_to_project = Boolean(data.constrain_task_dates_to_project);
    } catch {
      // Không có quyền cài đặt — giữ mặc định tắt.
    }
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
    owner_department_id: canChooseOwnerDepartment.value ? form.owner_department_id || null : undefined,
    executing_department_ids: form.executing_department_ids,
    start_date: form.start_date || null,
    end_date: form.end_date || null,
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

    showClientToast('success', `Đã tạo dự án "${data.project.name}". Phương pháp tính tiến độ thiết lập tại Cài đặt dự án.`);
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

    <PageHeader icon="plusCircle" description="Tạo một dự án mới cho tổ chức.">
      <template #title>
        <span class="proj-edit__title">Thêm dự án</span>
      </template>
      <template #actions>
        <button type="button" class="proj-edit__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách dự án
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingMeta" class="proj-edit__loading">Đang tải dữ liệu…</div>

    <template v-else>
      <div class="proj-edit__layout">
        <nav class="proj-edit__rail" aria-label="Các bước tạo dự án">
          <div
            class="proj-edit__rail-flow"
            role="progressbar"
            :aria-valuenow="step"
            aria-valuemin="1"
            :aria-valuemax="STEPS.length"
            :aria-valuetext="`Bước ${step} trên ${STEPS.length}`"
          >
            <div class="proj-edit__rail-flow-head">
              <span class="proj-edit__rail-flow-label">Luồng tạo dự án</span>
              <span class="proj-edit__rail-flow-step">Bước {{ step }}/{{ STEPS.length }}</span>
            </div>
            <div class="proj-edit__rail-flow-track">
              <div class="proj-edit__rail-flow-fill" :style="{ width: `${wizardProgressPct}%` }" />
            </div>
          </div>

          <Transition name="proj-wizard-ready">
            <div v-if="showStep1ReadyNote" class="proj-edit__rail-ready" role="status">
              <span class="proj-edit__rail-ready-icon" aria-hidden="true">
                <AppIcon name="check" :size="16" :stroke-width="2.5" />
              </span>
              <div class="proj-edit__rail-ready-text">
                <strong>Đủ thông tin bước 1</strong>
                <span>Bạn có thể sang bước 2 — {{ STEPS[1].title }}.</span>
              </div>
            </div>
          </Transition>

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
                  'proj-edit__step--next-hint': step === 1 && item.id === 2 && showStep1ReadyNote,
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
                  :disabled="saving"
                  is-create
                  :step="step"
                  :duration-days="durationDays"
                  :avatar-preview-url="avatarPreviewUrl"
                  :owner-department="ownerDepartment"
                  :can-choose-owner-department="canChooseOwnerDepartment"
                  @update:field="updateField"
                  @label-created="onLabelCreated"
                  @type-created="onTypeCreated"
                  @avatar-selected="onAvatarSelected"
                  @avatar-removed="onAvatarRemoved"
                />

                <Transition name="proj-wizard-ready">
                  <div v-if="showStep1ReadyNote" class="proj-edit__step-ready" role="status">
                    <span class="proj-edit__step-ready-icon" aria-hidden="true">
                      <AppIcon name="check" :size="20" :stroke-width="2.5" />
                    </span>
                    <div class="proj-edit__step-ready-copy">
                      <p class="proj-edit__step-ready-title">Đã đủ thông tin bắt buộc ở bước 1</p>
                      <p class="proj-edit__step-ready-desc">
                        Loại dự án và Tên dự án đã được nhập. Bấm
                        <strong>Tiếp tục</strong>
                        bên dưới hoặc chọn
                        <strong>{{ STEPS[1].title }}</strong>
                        ở cột trái để sang bước 2.
                      </p>
                      <button type="button" class="proj-edit__step-ready-btn" @click="goToStep(2)">
                        Sang bước 2: {{ STEPS[1].title }}
                        <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
                      </button>
                    </div>
                  </div>
                </Transition>

                <p
                  v-if="step === 1 && !showStep1ReadyNote && step1MissingFields.length === 1"
                  class="proj-edit__step-hint"
                >
                  Còn thiếu: {{ step1MissingFields[0] }} (bắt buộc để sang bước 2).
                </p>
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
          :class="{ 'proj-page__btn--flow-hint': showStep1ReadyNote || (step !== 1 && canAdvanceFromCurrentStep && !saving) }"
          :disabled="saving"
          @click="goToStep(step + 1)"
        >
          {{ showStep1ReadyNote ? `Tiếp tục — ${STEPS[step].title}` : 'Tiếp tục' }}
        </button>
        <button v-else type="button" class="proj-page__btn" :disabled="!canSubmit" @click="submitForm">
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
  padding: var(--space-4) var(--space-5) var(--space-4) var(--space-3);
  overflow: hidden;
  position: relative;
  isolation: isolate;
  background: var(--color-surface-muted);
  --proj-wizard-duration: 0.52s;
  --proj-wizard-ease: cubic-bezier(0.22, 1, 0.36, 1);
}

/* Watermark logo mờ phủ nền trang — cùng mẫu trang Bảng tin nội bộ
   (SocialFeed.vue): PNG tối trên nền trong suốt, boost alpha + nhuộm
   primary-900 bằng SVG filter (ảnh nguồn alpha thấp, không invert vì
   nền trang này sáng). */
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

/* ---- Rail dạng timeline dọc, bám sát sidebar bên trái ---- */
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
  transition: color var(--proj-wizard-duration) var(--proj-wizard-ease);
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
  will-change: width;
}

.proj-edit__rail-ready {
  display: flex;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, var(--color-border));
}

.proj-edit__rail-ready-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
}

.proj-edit__rail-ready-text {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  font-size: 0.8125rem;
  line-height: 1.35;
  color: var(--color-text);
}

.proj-edit__rail-ready-text strong {
  font-weight: 700;
  color: var(--color-primary);
}

.proj-edit__rail-ready-text span {
  color: var(--color-text-muted);
}

.proj-edit__step--next-hint .proj-edit__step-dot {
  animation: proj-edit-next-step-hint 1.6s ease-in-out infinite;
  box-shadow: 0 0 0 3px var(--step-surface);
}

@keyframes proj-edit-next-step-hint {
  0%,
  100% {
    transform: scale(1);
  }

  50% {
    transform: scale(1.08);
  }
}

.proj-edit__rail-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-edit__step {
  /* Mỗi bước 1 màu riêng trong 5 màu chủ đạo, khớp màu icon section
     tương ứng bên nội dung — giúp rail và form liên kết trực quan. */
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

/* Chấm bước: mặc định đã tô nền màu riêng (surface) + icon màu đậm cùng
   tông — không còn xám chìm trên xám, luôn thấy rõ icon ngay từ đầu. */
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
  font-size: 0.875rem;
  font-weight: 700;
  box-shadow: none;
  transition:
    background-color var(--proj-wizard-duration) var(--proj-wizard-ease),
    color var(--proj-wizard-duration) var(--proj-wizard-ease),
    box-shadow var(--proj-wizard-duration) var(--proj-wizard-ease),
    transform var(--proj-wizard-duration) var(--proj-wizard-ease);
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

.proj-edit__step-btn:hover .proj-edit__step-dot {
  box-shadow: 0 0 0 3px var(--step-surface);
}

.proj-edit__step--current .proj-edit__step-dot {
  background: var(--step-color);
  color: var(--color-on-primary, #fff);
  box-shadow: 0 0 0 4px var(--step-surface);
}

.proj-edit__step--done .proj-edit__step-dot {
  background: var(--step-surface);
  color: var(--step-color);
  box-shadow: none;
  animation: proj-edit-step-done-pop calc(var(--proj-wizard-duration) * 1.35) var(--proj-wizard-ease);
}

@keyframes proj-edit-step-done-pop {
  0% {
    transform: scale(1);
  }

  45% {
    transform: scale(1.14);
  }

  100% {
    transform: scale(1);
  }
}

.proj-edit__step--done .proj-edit__step-line {
  background: var(--color-border);
}

.proj-edit__step-title {
  flex: 1;
  padding: 0.5rem 0 var(--space-5);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.35;
  transition:
    color var(--proj-wizard-duration) var(--proj-wizard-ease),
    font-weight var(--proj-wizard-duration) var(--proj-wizard-ease);
}

.proj-edit__step--current .proj-edit__step-title,
.proj-edit__step--done .proj-edit__step-title {
  color: var(--color-text);
}

.proj-edit__step--current .proj-edit__step-title {
  color: var(--step-color);
  font-weight: 700;
}

.proj-edit__step-btn:hover .proj-edit__step-title {
  color: var(--step-color);
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
  min-height: 12rem;
}

.proj-edit__step-panel {
  width: 100%;
}

.proj-edit__step-ready {
  position: relative;
  display: flex;
  gap: var(--space-3);
  margin-top: var(--space-4);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-4) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-edit__step-ready::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-success);
}

.proj-edit__step-ready-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--color-success-tint-bg);
  color: var(--color-success);
}

.proj-edit__step-ready-title {
  margin: 0 0 var(--space-1);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.proj-edit__step-ready-desc {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.proj-edit__step-ready-desc strong {
  font-weight: 600;
  color: var(--color-text);
}

.proj-edit__step-ready-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  margin-top: var(--space-3);
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-edit__step-ready-btn:hover {
  background: var(--color-primary-hover);
}

.proj-edit__step-hint {
  margin: var(--space-4) 0 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.proj-edit__actions {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-3);
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

.proj-page__btn--flow-hint:not(:disabled) {
  animation: proj-edit-continue-hint 2.2s ease-in-out infinite;
}

@keyframes proj-edit-continue-hint {
  0%,
  100% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary) 0%, transparent);
  }

  50% {
    box-shadow: 0 0 0 4px var(--color-primary-surface);
  }
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

  .proj-edit__step--done .proj-edit__step-dot {
    animation: none;
  }

  .proj-edit__step--next-hint .proj-edit__step-dot {
    animation: none;
  }

  .proj-page__btn--flow-hint:not(:disabled) {
    animation: none;
  }
}
</style>

<!-- Transition Vue gắn class trực tiếp lên panel — khai báo không scoped để chắc chắn khớp -->
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

.proj-edit .proj-wizard-ready-enter-active,
.proj-edit .proj-wizard-ready-leave-active {
  transition:
    opacity 0.4s var(--proj-wizard-ease),
    transform 0.4s var(--proj-wizard-ease);
}

.proj-edit .proj-wizard-ready-enter-from,
.proj-edit .proj-wizard-ready-leave-to {
  opacity: 0;
  transform: translateY(0.5rem);
}

@media (prefers-reduced-motion: reduce) {
  .proj-edit {
    --proj-wizard-duration: 0.01ms;
  }

  .proj-edit .proj-wizard-forward-enter-active,
  .proj-edit .proj-wizard-forward-leave-active,
  .proj-edit .proj-wizard-back-enter-active,
  .proj-edit .proj-wizard-back-leave-active,
  .proj-edit .proj-wizard-ready-enter-active,
  .proj-edit .proj-wizard-ready-leave-active,
  .proj-edit .proj-wizard-forward-enter-from,
  .proj-edit .proj-wizard-forward-leave-to,
  .proj-edit .proj-wizard-back-enter-from,
  .proj-edit .proj-wizard-back-leave-to,
  .proj-edit .proj-wizard-ready-enter-from,
  .proj-edit .proj-wizard-ready-leave-to {
    transition: none;
    transform: none;
  }
}
</style>
