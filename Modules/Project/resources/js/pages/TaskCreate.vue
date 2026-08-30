<script setup>
//
// manager/project/tasks/create — Wizard 4 bước: thông tin → người tham
// gia → cài đặt khác → cài đặt nâng cao. Cùng khung trang với
// ProjectCreate (rail trái, watermark, banner bước 1).
//
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { toYmd } from '../constants/task.js';
import TaskFormFields from '../components/TaskFormFields.vue';

const STEPS = [
  { id: 1, title: 'Thông tin công việc', icon: 'clipboardCheck', color: 'primary' },
  { id: 2, title: 'Người tham gia', icon: 'users', color: 'gold' },
  { id: 3, title: 'Cài đặt khác', icon: 'settings', color: 'secondary' },
  { id: 4, title: 'Cài đặt nâng cao', icon: 'shield', color: 'tertiary' },
];

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const loadingMeta = ref(true);
const saving = ref(false);
const step = ref(1);
const stepDirection = ref(1);
const users = ref([]);
const importanceOptions = ref([]);
const importanceCriterion = ref(null);
const selectedParent = ref(null);
const formErrors = ref({});

const form = reactive({
  project_id: route.query.project_id ? String(route.query.project_id) : '',
  parent_id: '',
  title: '',
  description: '',
  start_date: toYmd(new Date()),
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
  priority: 'important',
  progress_type: 'average',
  progress_number: '0',
  progress_total: '',
  unit: '',
  hide_cross_tasks_from_assignees: false,
  hide_from_parent_assignees: false,
  hide_from_parent_followers: false,
  hide_child_tasks_from_followers: false,
  allow_child_people_view_parent: false,
  auto_complete_on_report: false,
  completed_interaction_policy: 'inherit',
  report_description_requirement: 'none',
  report_attachment_requirement: 'none',
});

const stepTransitionName = computed(() =>
  stepDirection.value >= 0 ? 'proj-wizard-forward' : 'proj-wizard-back',
);

const wizardProgressPct = computed(() => (step.value / STEPS.length) * 100);

const parentDateRange = computed(() => {
  if (!selectedParent.value) return '';
  const start = formatDate(selectedParent.value.start_date);
  const end = formatDate(selectedParent.value.end_date);
  if (!start && !end) return '';
  return `${start || 'không giới hạn'} đến ${end || 'không giới hạn'}`;
});

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

const canAdvanceFromCurrentStep = computed(() => {
  if (step.value === 1) {
    return form.title.trim() !== '' && Boolean(form.start_date) && Boolean(form.end_date);
  }
  if (step.value === 3 && isQuantity.value) {
    return Number(form.progress_total) > 0;
  }
  return true;
});

const step1MissingFields = computed(() => {
  if (step.value !== 1) return [];
  const missing = [];
  if (!form.title.trim()) missing.push('Tên công việc');
  if (!form.start_date) missing.push('Ngày bắt đầu');
  if (!form.end_date) missing.push('Ngày kết thúc');
  return missing;
});

const showStep1ReadyNote = computed(
  () => step.value === 1 && canAdvanceFromCurrentStep.value && !saving.value,
);

function updateField(field, value) {
  form[field] = value;
}

function updateParentItem(item) {
  selectedParent.value = item;
  if (!item) return;
  if (item.start_date && form.start_date && form.start_date < item.start_date) form.start_date = '';
  if (item.end_date && form.end_date && form.end_date > item.end_date) form.end_date = '';
}

function formatDate(value) {
  if (!value) return '';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
}

function goToStep(next) {
  if (next < 1 || next > STEPS.length) return;
  if (next > step.value && !form.title.trim()) {
    showClientToast('error', 'Vui lòng nhập Tên công việc trước khi sang bước tiếp.');
    return;
  }
  if (next > step.value && !form.start_date) {
    showClientToast('error', 'Vui lòng chọn Ngày bắt đầu trước khi sang bước tiếp.');
    return;
  }
  if (next > step.value && !form.end_date) {
    showClientToast('error', 'Vui lòng chọn Ngày kết thúc trước khi sang bước tiếp.');
    return;
  }
  if (next === step.value) return;
  stepDirection.value = next > step.value ? 1 : -1;
  step.value = next;
}

function presentAuthUser(user) {
  if (!user?.id) return null;
  return {
    id: user.id,
    name: user.name,
    email: user.email || null,
    avatar_url: user.avatar_url || null,
    status: user.status || 'active',
    department: user.department
      ? { id: user.department.id, name: user.department.name }
      : null,
  };
}

function mergeUsers(list) {
  const current = presentAuthUser(auth.user);
  if (!current) return list;
  const idx = list.findIndex((u) => String(u.id) === String(current.id));
  if (idx === -1) return [current, ...list];
  const existing = list[idx];
  return list.map((u, i) =>
    i === idx
      ? {
          ...existing,
          avatar_url: existing.avatar_url || current.avatar_url,
          name: existing.name || current.name,
          email: existing.email || current.email,
          department: existing.department || current.department,
        }
      : u,
  );
}

function currentUserId() {
  if (!auth.user?.id) return null;
  const fromList = users.value.find((u) => String(u.id) === String(auth.user.id));
  return fromList?.id ?? auth.user.id;
}

function applyDefaultManager() {
  if (form.manager_id) return;
  const id = currentUserId();
  if (id) form.manager_id = id;
}

function applyDefaultWatcher() {
  if (form.watcher_ids.length) return;
  const id = currentUserId();
  if (id) form.watcher_ids = [id];
}

function applyDefaultImportance() {
  if (form.priority && importanceOptions.value.some((opt) => opt.value === form.priority)) return;
  const preferred = importanceOptions.value.find((opt) => opt.value === 'important');
  form.priority = preferred?.value || importanceOptions.value[0]?.value || 'important';
}

async function loadImportance(departmentId) {
  const params = departmentId ? { department_id: departmentId } : {};
  const { data } = await window.axios.get('/api/project/tasks/options', { params });
  importanceOptions.value = data.importance ?? [];
  importanceCriterion.value = data.criterion ?? null;
  applyDefaultImportance();
}

function onProjectItem(item) {
  const departmentId = item?.owner_department?.id || item?.executing_department?.id || auth.user?.department?.id;
  loadImportance(departmentId).catch(() => {
    showClientToast('error', 'Không tải được mức độ quan trọng của phòng ban.');
  });
}

async function loadMeta() {
  loadingMeta.value = true;
  try {
    const [usersRes] = await Promise.all([
      window.axios.get('/api/project/assignable-users'),
      loadImportance(auth.user?.department?.id),
    ]);
    users.value = mergeUsers(usersRes.data.users ?? []);
    applyDefaultManager();
    applyDefaultWatcher();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được dữ liệu tạo công việc.');
  } finally {
    loadingMeta.value = false;
  }
}

watch(
  () => auth.user?.id,
  (id) => {
    if (!id) return;
    users.value = mergeUsers(users.value);
    applyDefaultManager();
    applyDefaultWatcher();
  },
  { immediate: true },
);

function validateBeforeSubmit() {
  if (!form.title.trim() || !form.start_date || !form.end_date) {
    step.value = 1;
    showClientToast('error', 'Vui lòng nhập tên và thời gian thực hiện công việc.');
    return false;
  }
  if (form.end_date < form.start_date) {
    step.value = 1;
    showClientToast('error', 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.');
    return false;
  }
  const parent = selectedParent.value;
  if (
    parent &&
    ((parent.start_date && form.start_date < parent.start_date) ||
      (parent.end_date && form.end_date > parent.end_date))
  ) {
    step.value = 1;
    showClientToast('error', 'Thời gian công việc con phải nằm trong khoảng thời gian của công việc cha.');
    return false;
  }
  if (isQuantity.value && Number(form.progress_total) <= 0) {
    step.value = 3;
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
    project_id: form.project_id || null,
    parent_id: form.parent_id || null,
    title: form.title.trim(),
    description: form.description.trim() || null,
    start_date: form.start_date,
    end_date: form.end_date,
    start_time: form.assign_by_time ? form.start_time : null,
    due_time: form.assign_by_time ? form.due_time : null,
    constrain_child_dates: Boolean(form.constrain_child_dates),
    assignee_id: form.assignee_id || null,
    manager_id: form.manager_id || null,
    watcher_ids: form.watcher_ids,
    collaborator_ids: form.collaborator_ids,
    type: form.type,
    priority: form.priority || null,
    progress_type: form.progress_type,
    hide_cross_tasks_from_assignees: Boolean(form.hide_cross_tasks_from_assignees),
    hide_from_parent_assignees: Boolean(form.hide_from_parent_assignees),
    hide_from_parent_followers: Boolean(form.hide_from_parent_followers),
    hide_child_tasks_from_followers: Boolean(form.hide_child_tasks_from_followers),
    allow_child_people_view_parent: Boolean(form.allow_child_people_view_parent),
    auto_complete_on_report: Boolean(form.auto_complete_on_report),
    completed_interaction_policy: form.completed_interaction_policy,
    report_description_requirement: form.report_description_requirement,
    report_attachment_requirement: form.report_attachment_requirement,
    ...(isQuantity.value
      ? {
          progress_number: Number(form.progress_number || 0),
          progress_total: Number(form.progress_total),
          unit: form.unit.trim() || null,
        }
      : form.progress_type === 'percent'
        ? { progress_percent: 0 }
        : {}),
  };

  try {
    const { data } = await window.axios.post('/api/project/tasks', payload);
    showClientToast('success', `Đã tạo công việc "${data.task.title}".`);
    router.push({ name: 'manager.project.tasks' });
  } catch (error) {
    if (error?.response?.status === 422) {
      formErrors.value = error.response.data?.errors ?? {};
    }
    showClientToast('error', error?.response?.data?.message || 'Không tạo được công việc.');
  } finally {
    saving.value = false;
  }
}

function goBack() {
  router.push({ name: 'manager.project.tasks' });
}

onMounted(loadMeta);
</script>

<template>
  <section class="proj-edit">
    <svg class="proj-edit__wm-defs" aria-hidden="true" focusable="false">
      <filter id="task-create-watermark-boost" color-interpolation-filters="sRGB">
        <feColorMatrix type="matrix" values="0 0 0 0 0.604  0 0 0 0 0  0 0 0 0 0.212  0 0 0 20 0" />
      </filter>
    </svg>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="proj-edit__watermark"
      aria-hidden="true"
      :style="{ filter: 'url(#task-create-watermark-boost)' }"
    />

    <PageHeader icon="plusCircle" description="Tạo và giao một công việc mới.">
      <template #title>
        <span class="proj-edit__title">Tạo công việc</span>
      </template>
      <template #actions>
        <button type="button" class="proj-edit__header-btn" @click="goBack">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách công việc
        </button>
      </template>
    </PageHeader>

    <div v-if="loadingMeta" class="proj-edit__loading">Đang tải dữ liệu…</div>

    <template v-else>
      <div class="proj-edit__layout">
        <nav class="proj-edit__rail" aria-label="Các bước tạo công việc">
          <div
            class="proj-edit__rail-flow"
            role="progressbar"
            :aria-valuenow="step"
            aria-valuemin="1"
            :aria-valuemax="STEPS.length"
            :aria-valuetext="`Bước ${step} trên ${STEPS.length}`"
          >
            <div class="proj-edit__rail-flow-head">
              <span class="proj-edit__rail-flow-label">Luồng tạo công việc</span>
              <span class="proj-edit__rail-flow-step">Bước {{ step }}/{{ STEPS.length }}</span>
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
                <TaskFormFields
                  :form="form"
                  :errors="formErrors"
                  :users="users"
                  :importance-options="importanceOptions"
                  :importance-criterion="importanceCriterion"
                  :disabled="saving"
                  :step="step"
                  :duration-days="durationDays"
                  :selected-parent="selectedParent"
                  :parent-date-range="parentDateRange"
                  @update:field="updateField"
                  @update:parent-item="updateParentItem"
                  @update:project-item="onProjectItem"
                />

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

      <Transition name="proj-wizard-ready">
        <div v-if="showStep1ReadyNote" class="proj-edit__flow-banner" role="status">
          <span class="proj-edit__flow-banner-icon" aria-hidden="true">
            <AppIcon name="check" :size="20" :stroke-width="2.5" />
          </span>
          <div class="proj-edit__flow-banner-copy">
            <p class="proj-edit__flow-banner-title">Đã đủ thông tin bắt buộc ở bước 1</p>
            <p class="proj-edit__flow-banner-desc">
              Tên và thời gian thực hiện đã được nhập. Chọn
              <strong>{{ STEPS[1].title }}</strong>
              ở cột trái hoặc bấm nút bên phải để sang bước 2.
            </p>
          </div>
          <div class="proj-edit__flow-banner-actions">
            <button type="button" class="proj-page__btn proj-page__btn--ghost" :disabled="saving" @click="goBack">
              Huỷ
            </button>
            <button type="button" class="proj-edit__flow-banner-btn" :disabled="saving" @click="goToStep(2)">
              Sang bước 2: {{ STEPS[1].title }}
              <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
            </button>
          </div>
        </div>
      </Transition>

      <div v-if="!showStep1ReadyNote" class="proj-edit__actions">
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
          :class="{ 'proj-page__btn--flow-hint': step !== 1 && canAdvanceFromCurrentStep && !saving }"
          :disabled="saving"
          @click="goToStep(step + 1)"
        >
          Tiếp tục
        </button>
        <button v-else type="button" class="proj-page__btn" :disabled="!canSubmit" @click="submitForm">
          {{ saving ? 'Đang lưu…' : 'Tạo công việc' }}
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
  min-height: 0;
}

.proj-edit__step-panel {
  width: 100%;
  padding-bottom: var(--space-2);
}

.proj-edit__flow-banner {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-3);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-4) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-edit__flow-banner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-success);
}

.proj-edit__flow-banner-icon {
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

.proj-edit__flow-banner-copy {
  flex: 1;
  min-width: min(100%, 16rem);
}

.proj-edit__flow-banner-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-2);
  flex-shrink: 0;
  margin-left: auto;
}

.proj-edit__flow-banner-title {
  margin: 0 0 var(--space-1);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.proj-edit__flow-banner-desc {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.proj-edit__flow-banner-desc strong {
  font-weight: 600;
  color: var(--color-text);
}

.proj-edit__flow-banner-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  flex-shrink: 0;
  padding: 0.5rem 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-edit__flow-banner-btn:hover {
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

  .proj-edit__flow-banner {
    flex-direction: column;
    align-items: stretch;
  }

  .proj-edit__flow-banner-actions {
    margin-left: 0;
    width: 100%;
    flex-direction: column-reverse;
  }

  .proj-edit__flow-banner-actions .proj-page__btn,
  .proj-edit__flow-banner-btn {
    width: 100%;
    justify-content: center;
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
