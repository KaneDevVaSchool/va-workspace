<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDatePicker from '@/components/AppDatePicker.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { personnelEvaluationScoringRoute } from '../constants/report.js';

const STEPS = [
  { id: 1, key: 'period', title: 'Kỳ báo cáo', icon: 'calendar', color: 'primary' },
  { id: 2, key: 'scope', title: 'Phạm vi nhân sự', icon: 'users', color: 'gold' },
  { id: 3, key: 'display', title: 'Cột và tiêu chí', icon: 'columns', color: 'secondary' },
  { id: 4, key: 'viewers', title: 'Người được xem', icon: 'eye', color: 'tertiary' },
  { id: 5, key: 'confirm', title: 'Xem lại', icon: 'clipboardCheck', color: 'umber' },
];

/** Cột cố định trên ma trận Đánh giá nhân sự — trừ Nhân sự (luôn hiện). */
const COLUMN_OPTIONS = [
  { key: 'tasks', label: 'Việc' },
  { key: 'start_score', label: 'Khởi đầu' },
  { key: 'task_adjustment', label: 'Từ việc' },
  { key: 'bonus', label: 'Cộng' },
  { key: 'penalty', label: 'Trừ' },
  { key: 'final_score', label: 'Cuối' },
  { key: 'classification', label: 'Xếp loại' },
];

const DISPLAY_TABS = [
  { key: 'columns', label: 'Cột điểm', icon: 'columns' },
  { key: 'criteria', label: 'Cột tiêu chí', icon: 'listChecks' },
];

const router = useRouter();
const auth = useAuthStore();

const saving = ref(false);
const loadingOptions = ref(false);
const members = ref([]);
const criteria = ref([]);
const formErrors = ref({});
const step = ref(1);
const stepDirection = ref(1);

const submitted = ref(false);
const leaveConfirm = ref(null);

const memberQuery = ref('');
const viewerQuery = ref('');
const criteriaQuery = ref('');
const displayTab = ref('columns');

const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const acknowledgedMissing = ref(false);

const stepTransitionName = computed(() =>
  stepDirection.value >= 0 ? 'rpt-wizard-forward' : 'rpt-wizard-back',
);

const wizardProgressPct = computed(() => (step.value / STEPS.length) * 100);

const hasMissingPreview = computed(() => Number(preview.value?.summary?.missing_total) > 0);
const canCreate = computed(
  () =>
    !saving.value &&
    !previewLoading.value &&
    (!hasMissingPreview.value || acknowledgedMissing.value),
);

watch(preview, () => {
  acknowledgedMissing.value = false;
});

const today = new Date();
const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

const form = reactive({
  title: `Báo cáo đánh giá nhân sự tháng ${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`,
  period_type: 'month',
  period_from: firstOfMonth.toISOString().slice(0, 10),
  period_to: lastOfMonth.toISOString().slice(0, 10),
  filter_user_ids: [],
  viewer_user_ids: [],
  column_keys: COLUMN_OPTIONS.map((col) => col.key),
  criterion_ids: [],
});

const currentStep = computed(() => STEPS.find((item) => item.id === step.value) ?? STEPS[0]);
const isLastStep = computed(() => step.value === STEPS.length);

const periodReady = computed(() => {
  return (
    Boolean(form.title.trim()) &&
    Boolean(form.period_from) &&
    Boolean(form.period_to) &&
    form.period_from <= form.period_to
  );
});

const canAdvanceFromCurrentStep = computed(() => {
  if (step.value === 1) return periodReady.value;
  if (step.value === 3) return form.column_keys.length > 0;
  return true;
});

const step1MissingFields = computed(() => {
  if (step.value !== 1) return [];
  const missing = [];
  if (!form.title.trim()) missing.push('Tên báo cáo');
  if (!form.period_from || !form.period_to) missing.push('Khoảng ngày');
  else if (form.period_from > form.period_to) missing.push('Ngày kết thúc (phải sau ngày bắt đầu)');
  return missing;
});

const showStep1ReadyNote = computed(
  () => step.value === 1 && canAdvanceFromCurrentStep.value && !saving.value,
);

const periodDays = computed(() => {
  if (!form.period_from || !form.period_to) return null;
  const start = new Date(form.period_from);
  const end = new Date(form.period_to);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  const diff = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;
  return diff > 0 ? diff : null;
});

const scopeText = computed(() =>
  form.filter_user_ids.length === 0
    ? 'Toàn bộ nhân sự phòng ban'
    : `${form.filter_user_ids.length} nhân sự được chọn`,
);

const viewerText = computed(() =>
  form.viewer_user_ids.length === 0
    ? 'Chưa chia sẻ cho ai ngoài người quản lý phòng ban'
    : `${form.viewer_user_ids.length} người được xem`,
);

const criteriaText = computed(() => {
  if (criteria.value.length === 0) return 'Chưa có tiêu chí';
  if (form.criterion_ids.length === 0) return `Toàn bộ ${criteria.value.length} tiêu chí`;
  return `${form.criterion_ids.length}/${criteria.value.length} tiêu chí`;
});

const columnText = computed(() =>
  COLUMN_OPTIONS.filter((col) => form.column_keys.includes(col.key))
    .map((col) => col.label)
    .join(', '),
);

const periodRangeText = computed(() => {
  if (!form.period_from || !form.period_to) return '—';
  return `${formatDateVi(form.period_from)} — ${formatDateVi(form.period_to)}`;
});

function formatDateVi(iso) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  if (!match) return iso;
  return `${match[3]}/${match[2]}/${match[1]}`;
}

function applyPeriodType(type) {
  form.period_type = type;
  const now = new Date();

  if (type === 'month') {
    form.period_from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    form.period_to = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    return;
  }

  if (type === 'quarter') {
    const quarterStartMonth = Math.floor(now.getMonth() / 3) * 3;
    form.period_from = new Date(now.getFullYear(), quarterStartMonth, 1).toISOString().slice(0, 10);
    form.period_to = new Date(now.getFullYear(), quarterStartMonth + 3, 0).toISOString().slice(0, 10);
  }
}

function toggleInList(list, value) {
  const index = list.indexOf(value);
  if (index === -1) {
    list.push(value);
  } else {
    list.splice(index, 1);
  }
}

function searchable(text) {
  return String(text ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase();
}

function filterMembers(list, keyword) {
  const needle = searchable(keyword).trim();
  if (!needle) return list;
  return list.filter(
    (member) => searchable(member.name).includes(needle) || searchable(member.email).includes(needle),
  );
}

const filteredMembers = computed(() => filterMembers(members.value, memberQuery.value));
const filteredViewers = computed(() => filterMembers(members.value, viewerQuery.value));

const isPeopleStep = computed(
  () => currentStep.value.key === 'scope' || currentStep.value.key === 'viewers',
);

const isFillStep = computed(() => isPeopleStep.value || currentStep.value.key === 'display');

function displayTabCount(key) {
  if (key === 'columns') {
    return `${form.column_keys.length}/${COLUMN_OPTIONS.length}`;
  }
  if (criteria.value.length === 0) return '0';
  if (form.criterion_ids.length === 0) return String(criteria.value.length);
  return `${form.criterion_ids.length}/${criteria.value.length}`;
}

const brokenAvatars = ref(new Set());

function memberInitial(name) {
  return String(name ?? '').trim().charAt(0).toUpperCase() || '?';
}

function memberPhoto(member) {
  return Boolean(member?.avatar_url) && !brokenAvatars.value.has(member.id);
}

function onMemberAvatarError(id) {
  if (brokenAvatars.value.has(id)) return;
  const next = new Set(brokenAvatars.value);
  next.add(id);
  brokenAvatars.value = next;
}

function allVisibleSelected(selectedIds, visible) {
  return visible.length > 0 && visible.every((member) => selectedIds.includes(member.id));
}

function someVisibleSelected(selectedIds, visible) {
  if (visible.length === 0) return false;
  const count = visible.filter((member) => selectedIds.includes(member.id)).length;
  return count > 0 && count < visible.length;
}

function toggleVisibleSelection(selectedIds, visible) {
  if (saving.value || visible.length === 0) return;
  const turnOn = !allVisibleSelected(selectedIds, visible);
  const ids = new Set(visible.map((member) => member.id));
  if (turnOn) {
    for (const id of ids) {
      if (!selectedIds.includes(id)) selectedIds.push(id);
    }
    return;
  }
  for (let i = selectedIds.length - 1; i >= 0; i -= 1) {
    if (ids.has(selectedIds[i])) selectedIds.splice(i, 1);
  }
}

function criteriaTypeLabel(item) {
  return String(item.criterion_type_name ?? '').trim() || 'Tiêu chí khác';
}

const criteriaGroups = computed(() => {
  const map = new Map();
  for (const item of criteria.value) {
    const label = criteriaTypeLabel(item);
    if (!map.has(label)) map.set(label, []);
    map.get(label).push(item);
  }
  return [...map.entries()].map(([label, items]) => ({ label, items }));
});

const filteredCriteriaGroups = computed(() => {
  const needle = searchable(criteriaQuery.value).trim();
  const groups = criteriaGroups.value;
  if (!needle) return groups;
  return groups
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) =>
          searchable(item.name).includes(needle) || searchable(item.criterion_type_name).includes(needle),
      ),
    }))
    .filter((group) => group.items.length > 0);
});

function groupScoringTone(items) {
  let pos = false;
  let neg = false;
  for (const item of items) {
    for (const level of item.levels ?? []) {
      const score = Number(level.score) || 0;
      if (score > 0) pos = true;
      if (score < 0) neg = true;
      if (pos && neg) return 'mixed';
    }
  }
  if (pos) return 'ok';
  if (neg) return 'cut';
  return '';
}

function pickerGroupClass(group) {
  const tone = groupScoringTone(group.items);
  const allOn = group.items.length > 0 && groupShownCount(group.items) === group.items.length;
  return {
    [`rpt-form__matrix-group--${tone}`]: Boolean(tone),
    'rpt-form__matrix-group--all': allOn,
  };
}

function columnOn(key) {
  return form.column_keys.includes(key);
}

function toggleColumn(key) {
  if (key === 'final_score' && columnOn(key) && form.column_keys.length === 1) return;
  toggleInList(form.column_keys, key);
  if (form.column_keys.length === 0) {
    form.column_keys = ['final_score'];
  }
}

function criterionOn(id) {
  if (form.criterion_ids.length === 0) return true;
  return form.criterion_ids.includes(id);
}

function groupShownCount(items) {
  return items.filter((item) => criterionOn(item.id)).length;
}

function setCriterion(id, on) {
  const allIds = criteria.value.map((item) => item.id);
  let next;
  if (form.criterion_ids.length === 0) {
    next = on ? [...allIds] : allIds.filter((itemId) => itemId !== id);
  } else if (on) {
    next = form.criterion_ids.includes(id) ? [...form.criterion_ids] : [...form.criterion_ids, id];
  } else {
    next = form.criterion_ids.filter((itemId) => itemId !== id);
  }
  form.criterion_ids = next.length === allIds.length ? [] : next;
}

function toggleCriterion(id) {
  setCriterion(id, !criterionOn(id));
}

function setGroupCriteria(items, on) {
  const ids = new Set(items.map((item) => item.id));
  const allIds = criteria.value.map((item) => item.id);
  let current = form.criterion_ids.length === 0 ? [...allIds] : [...form.criterion_ids];
  if (on) {
    for (const id of ids) {
      if (!current.includes(id)) current.push(id);
    }
  } else {
    current = current.filter((id) => !ids.has(id));
  }
  form.criterion_ids = current.length === allIds.length ? [] : current;
}

function toggleGroupCriteria(items) {
  setGroupCriteria(items, groupShownCount(items) < items.length);
}

function selectAllMembers() {
  form.filter_user_ids = members.value.map((member) => member.id);
}

function clearMembers() {
  form.filter_user_ids = [];
}

function selectAllViewers() {
  form.viewer_user_ids = members.value.map((member) => member.id);
}

function clearViewers() {
  form.viewer_user_ids = [];
}

function selectAllCriteria() {
  form.criterion_ids = [];
}

function selectAllColumns() {
  form.column_keys = COLUMN_OPTIONS.map((col) => col.key);
}

function clearColumns() {
  form.column_keys = ['final_score'];
}

function goToStep(next) {
  if (next < 1 || next > STEPS.length) return;
  if (next > step.value && !periodReady.value) {
    showClientToast('error', 'Vui lòng nhập tên báo cáo và khoảng ngày hợp lệ trước khi sang bước tiếp.');
    return;
  }
  if (next > 3 && form.column_keys.length === 0) {
    showClientToast('error', 'Vui lòng chọn ít nhất một cột hiển thị.');
    return;
  }
  if (next === step.value) return;
  stepDirection.value = next > step.value ? 1 : -1;
  step.value = next;
  if (next === STEPS.length) {
    loadPreview();
  }
}

async function loadPreview() {
  previewLoading.value = true;
  previewError.value = '';
  try {
    const { data } = await window.axios.post('/api/report/personnel-evaluation/preview', {
      period_from: form.period_from,
      period_to: form.period_to,
      filter_user_ids: form.filter_user_ids,
    });
    preview.value = data;
  } catch (error) {
    preview.value = null;
    previewError.value =
      error?.response?.data?.message ?? 'Chưa xem trước được số liệu. Bạn vẫn tạo báo cáo bình thường.';
  } finally {
    previewLoading.value = false;
  }
}

async function loadOptions() {
  loadingOptions.value = true;
  try {
    const { data } = await window.axios.get('/api/evaluation/events');
    members.value = data.members ?? [];
    criteria.value = data.criteria ?? [];
  } catch {
    members.value = [];
    criteria.value = [];
  } finally {
    loadingOptions.value = false;
  }
}

async function submit() {
  if (saving.value) return;

  saving.value = true;
  formErrors.value = {};
  try {
    const { data } = await window.axios.post('/api/report/personnel-evaluation', { ...form });
    submitted.value = true;
    showClientToast('success', 'Đã tạo báo cáo. Mở bảng chấm điểm.');
    const report = data?.report;
    if (auth.can('evaluation.manage_department')) {
      router.push(
        personnelEvaluationScoringRoute(
          report?.period_from ?? form.period_from,
          report?.period_to ?? form.period_to,
          report?.id,
        ),
      );
    } else {
      router.push({ name: 'manager.reports.list' });
    }
  } catch (error) {
    formErrors.value = error?.response?.data?.errors ?? {};
    showClientToast('error', error?.response?.data?.message ?? 'Không tạo được báo cáo.');
  } finally {
    saving.value = false;
  }
}

const isDirty = computed(
  () =>
    step.value > 1 ||
    form.filter_user_ids.length > 0 ||
    form.viewer_user_ids.length > 0 ||
    form.criterion_ids.length > 0,
);

function requestLeave() {
  if (!isDirty.value || submitted.value) {
    router.push({ name: 'manager.reports.list' });
    return;
  }
  leaveConfirm.value = { to: { name: 'manager.reports.list' } };
}

function confirmLeave() {
  const target = leaveConfirm.value;
  leaveConfirm.value = null;
  submitted.value = true;
  if (target?.next) {
    target.next();
    return;
  }
  router.push(target?.to ?? { name: 'manager.reports.list' });
}

function cancelLeave() {
  const next = leaveConfirm.value?.next;
  leaveConfirm.value = null;
  next?.(false);
}

onBeforeRouteLeave((to, from, next) => {
  if (!isDirty.value || submitted.value) {
    next();
    return;
  }
  leaveConfirm.value = { next };
});

onMounted(loadOptions);
</script>

<template>
  <section class="rpt-edit">
    <svg class="rpt-edit__wm-defs" aria-hidden="true" focusable="false">
      <filter id="rpt-edit-watermark-boost" color-interpolation-filters="sRGB">
        <feColorMatrix type="matrix" values="0 0 0 0 0.604  0 0 0 0 0  0 0 0 0 0.212  0 0 0 20 0" />
      </filter>
    </svg>
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="rpt-edit__watermark"
      aria-hidden="true"
      :style="{ filter: 'url(#rpt-edit-watermark-boost)' }"
    />

    <PageHeader
      title="Thêm báo cáo đánh giá"
      icon="plusCircle"
      description="Tạo báo cáo đánh giá nhân sự cho phòng ban."
    >
      <template #title>
        <span class="rpt-edit__title">Thêm báo cáo đánh giá</span>
      </template>
      <template #actions>
        <button type="button" class="rpt-edit__header-btn" @click="requestLeave">
          <AppIcon name="chevronLeft" :size="16" />
          Về danh sách báo cáo
        </button>
      </template>
    </PageHeader>

    <div class="rpt-edit__layout">
        <nav class="rpt-edit__rail hide-scrollbar" aria-label="Các bước tạo báo cáo">
        <div
          class="rpt-edit__rail-flow"
          role="progressbar"
          :aria-valuenow="step"
          aria-valuemin="1"
          :aria-valuemax="STEPS.length"
          :aria-valuetext="`Bước ${step} trên ${STEPS.length}`"
        >
          <div class="rpt-edit__rail-flow-head">
            <span class="rpt-edit__rail-flow-label">Luồng tạo báo cáo</span>
            <span class="rpt-edit__rail-flow-step">Bước {{ step }}/{{ STEPS.length }}</span>
          </div>
          <div class="rpt-edit__rail-flow-track">
            <div class="rpt-edit__rail-flow-fill" :style="{ width: `${wizardProgressPct}%` }" />
          </div>
        </div>

        <ol class="rpt-edit__rail-list">
          <li
            v-for="item in STEPS"
            :key="item.id"
            class="rpt-edit__step"
            :class="[
              `rpt-edit__step--${item.color}`,
              {
                'rpt-edit__step--current': step === item.id,
                'rpt-edit__step--done': step > item.id,
                'rpt-edit__step--next-hint': step === 1 && item.id === 2 && showStep1ReadyNote,
              },
            ]"
          >
            <button type="button" class="rpt-edit__step-btn" @click="goToStep(item.id)">
              <span class="rpt-edit__step-track" aria-hidden="true">
                <span class="rpt-edit__step-dot">
                  <AppIcon v-if="step > item.id" name="check" :size="17" :stroke-width="2.5" />
                  <AppIcon v-else :name="item.icon" :size="17" :stroke-width="2" />
                </span>
                <span v-if="item.id < STEPS.length" class="rpt-edit__step-line">
                  <span class="rpt-edit__step-line-fill" />
                </span>
              </span>
              <span class="rpt-edit__step-title">{{ item.title }}</span>
            </button>
          </li>
        </ol>
      </nav>

      <div
        class="rpt-edit__body hide-scrollbar"
        :class="{ 'rpt-edit__body--fill': isFillStep }"
      >
        <div class="rpt-edit__body-stage">
          <Transition :name="stepTransitionName" mode="out-in">
            <div
              :key="step"
              class="rpt-edit__step-panel"
              :class="{ 'rpt-edit__step-panel--fill': isFillStep }"
            >
              <div v-if="currentStep.key === 'period'" class="rpt-form">
                <section class="rpt-form__section">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--primary">
                      <AppIcon name="fileText" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Thông tin báo cáo</h2>
                  </header>
                  <div class="rpt-form__grid">
                    <div class="rpt-form__field rpt-form__field--wide">
                      <label class="rpt-form__label" for="create-title">
                        Tên báo cáo <span class="rpt-form__required">*</span>
                      </label>
                      <input
                        id="create-title"
                        v-model="form.title"
                        type="text"
                        class="rpt-form__input"
                        :class="{ 'rpt-form__input--error': Boolean(formErrors.title) }"
                        maxlength="255"
                        :disabled="saving"
                      />
                      <span v-if="formErrors.title" class="rpt-form__error">{{ formErrors.title[0] }}</span>
                    </div>
                  </div>
                </section>

                <section class="rpt-form__section">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--primary">
                      <AppIcon name="calendar" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Kỳ báo cáo</h2>
                  </header>
                  <div class="rpt-form__grid rpt-form__grid--4col">
                    <div class="rpt-form__field">
                      <label class="rpt-form__label" for="create-period-type">Kiểu kỳ</label>
                      <select
                        id="create-period-type"
                        :value="form.period_type"
                        class="rpt-form__input"
                        :disabled="saving"
                        @change="applyPeriodType($event.target.value)"
                      >
                        <option value="month">Theo tháng</option>
                        <option value="quarter">Theo quý</option>
                        <option value="custom">Tự chọn khoảng ngày</option>
                      </select>
                    </div>
                    <div class="rpt-form__field">
                      <label class="rpt-form__label" for="create-from">Từ ngày</label>
                      <AppDatePicker
                        id="create-from"
                        v-model="form.period_from"
                        :error="Boolean(formErrors.period_from)"
                        :disabled="saving"
                      />
                      <span v-if="formErrors.period_from" class="rpt-form__error">{{
                        formErrors.period_from[0]
                      }}</span>
                    </div>
                    <div class="rpt-form__field">
                      <label class="rpt-form__label" for="create-to">Đến ngày</label>
                      <AppDatePicker
                        id="create-to"
                        v-model="form.period_to"
                        :min="form.period_from"
                        :error="Boolean(formErrors.period_to)"
                        :disabled="saving"
                      />
                      <span v-if="formErrors.period_to" class="rpt-form__error">{{ formErrors.period_to[0] }}</span>
                    </div>
                    <div class="rpt-form__field">
                      <span class="rpt-form__label">Số ngày trong kỳ</span>
                      <span class="rpt-form__static rpt-form__static--duration">
                        <AppIcon name="clock" :size="14" :stroke-width="1.75" />
                        {{ periodDays ? `${periodDays} ngày` : '—' }}
                      </span>
                    </div>
                  </div>
                </section>

                <p
                  v-if="!showStep1ReadyNote && step1MissingFields.length === 1"
                  class="rpt-edit__step-hint"
                >
                  Còn thiếu: {{ step1MissingFields[0] }} (bắt buộc để sang bước 2).
                </p>
              </div>

              <div v-else-if="currentStep.key === 'scope'" class="rpt-form rpt-form--fill">
                <section class="rpt-form__section rpt-form__section--list">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--gold">
                      <AppIcon name="users" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Phạm vi nhân sự</h2>
                    <span class="rpt-form__section-note">{{ scopeText }}</span>
                    <div class="rpt-form__tools rpt-form__tools--inline">
                      <div class="rpt-form__search-field">
                        <AppIcon name="search" :size="16" :stroke-width="2" aria-hidden="true" />
                        <input
                          v-model="memberQuery"
                          type="search"
                          class="rpt-form__search-input"
                          placeholder="Tìm theo tên hoặc email…"
                          autocomplete="off"
                          spellcheck="false"
                          aria-label="Tìm nhân sự theo tên hoặc email"
                          :disabled="saving"
                        />
                        <button
                          v-if="memberQuery"
                          type="button"
                          class="rpt-form__search-clear"
                          aria-label="Xoá tìm kiếm"
                          @click="memberQuery = ''"
                        >
                          <AppIcon name="close" :size="14" />
                        </button>
                      </div>
                      <button
                        type="button"
                        class="rpt-form__quick rpt-form__quick--gold"
                        :disabled="saving || members.length === 0"
                        @click="selectAllMembers"
                      >
                        Chọn tất cả
                      </button>
                      <button
                        type="button"
                        class="rpt-form__quick rpt-form__quick--gold"
                        :disabled="saving"
                        @click="clearMembers"
                      >
                        Bỏ chọn
                      </button>
                    </div>
                  </header>

                  <p class="rpt-form__story rpt-form__story--gold rpt-form__story--compact">
                    Không chọn ai thì báo cáo tính cho
                    <strong class="rpt-form__story-val">toàn bộ nhân sự đang hoạt động</strong>
                    của phòng ban.
                  </p>

                  <p v-if="loadingOptions" class="rpt-form__empty">Đang tải danh sách nhân sự…</p>
                  <p v-else-if="members.length === 0" class="rpt-form__empty">
                    Phòng ban chưa có nhân sự nào đang hoạt động.
                  </p>
                  <p v-else-if="filteredMembers.length === 0" class="rpt-form__empty">
                    Không có nhân sự nào khớp với từ khoá đang tìm.
                  </p>
                  <div v-else class="rpt-form__people rpt-form__people--gold">
                    <table class="rpt-form__people-table">
                      <thead>
                        <tr>
                          <th class="rpt-form__people-check">
                            <label class="rpt-form__people-check-lab">
                              <input
                                type="checkbox"
                                class="rpt-form__sr"
                                :checked="allVisibleSelected(form.filter_user_ids, filteredMembers)"
                                :indeterminate="someVisibleSelected(form.filter_user_ids, filteredMembers)"
                                :disabled="saving"
                                aria-label="Chọn tất cả nhân sự đang hiện"
                                @change="toggleVisibleSelection(form.filter_user_ids, filteredMembers)"
                              />
                              <span
                                class="rpt-form__pick-tick"
                                :class="{
                                  'rpt-form__pick-tick--on': allVisibleSelected(
                                    form.filter_user_ids,
                                    filteredMembers,
                                  ),
                                  'rpt-form__pick-tick--mixed': someVisibleSelected(
                                    form.filter_user_ids,
                                    filteredMembers,
                                  ),
                                }"
                                aria-hidden="true"
                              />
                            </label>
                          </th>
                          <th>Nhân sự</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="member in filteredMembers"
                          :key="member.id"
                          class="rpt-form__people-row rpt-form__people-row--gold"
                          :class="{ 'rpt-form__people-row--on': form.filter_user_ids.includes(member.id) }"
                          @click="saving ? null : toggleInList(form.filter_user_ids, member.id)"
                        >
                          <td class="rpt-form__people-check" @click.stop>
                            <label class="rpt-form__people-check-lab">
                              <input
                                type="checkbox"
                                class="rpt-form__sr"
                                :checked="form.filter_user_ids.includes(member.id)"
                                :disabled="saving"
                                :aria-label="`Chọn ${member.name}`"
                                @change="toggleInList(form.filter_user_ids, member.id)"
                              />
                              <span
                                class="rpt-form__pick-tick"
                                :class="{
                                  'rpt-form__pick-tick--on': form.filter_user_ids.includes(member.id),
                                }"
                                aria-hidden="true"
                              />
                            </label>
                          </td>
                          <td>
                            <span class="rpt-form__person">
                              <span class="rpt-form__person-avatar" aria-hidden="true">
                                <img
                                  v-if="memberPhoto(member)"
                                  :src="member.avatar_url"
                                  alt=""
                                  class="rpt-form__person-avatar-img"
                                  referrerpolicy="no-referrer"
                                  @error="onMemberAvatarError(member.id)"
                                />
                                <template v-else>{{ memberInitial(member.name) }}</template>
                              </span>
                              <span class="rpt-form__person-text">
                                <span class="rpt-form__person-name">{{ member.name }}</span>
                                <span v-if="member.email" class="rpt-form__person-email">{{
                                  member.email
                                }}</span>
                              </span>
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </section>
              </div>

              <div v-else-if="currentStep.key === 'display'" class="rpt-form rpt-form--fill">
                <section class="rpt-form__section rpt-form__section--picker rpt-form__section--display">
                  <div class="rpt-tabs" role="tablist" aria-label="Cột điểm hoặc tiêu chí">
                    <button
                      v-for="tab in DISPLAY_TABS"
                      :key="tab.key"
                      type="button"
                      role="tab"
                      class="rpt-tabs__btn"
                      :class="{ 'rpt-tabs__btn--on': displayTab === tab.key }"
                      :aria-selected="displayTab === tab.key ? 'true' : 'false'"
                      :aria-controls="`rpt-display-${tab.key}`"
                      :tabindex="displayTab === tab.key ? 0 : -1"
                      :disabled="saving"
                      @click="displayTab = tab.key"
                    >
                      <AppIcon :name="tab.icon" :size="16" :stroke-width="1.75" />
                      <span>{{ tab.label }}</span>
                      <span class="rpt-tabs__count">{{ displayTabCount(tab.key) }}</span>
                    </button>
                  </div>

                  <div
                    v-if="displayTab === 'columns'"
                    id="rpt-display-columns"
                    class="rpt-form__tab-pane"
                    role="tabpanel"
                  >
                    <div class="rpt-form__tab-bar">
                      <span class="rpt-form__section-note">Nhân sự luôn hiện đầu bảng</span>
                      <div class="rpt-form__tools rpt-form__tools--inline">
                        <button
                          type="button"
                          class="rpt-form__quick rpt-form__quick--secondary"
                          :disabled="saving"
                          @click="selectAllColumns"
                        >
                          Hiện tất cả
                        </button>
                        <button
                          type="button"
                          class="rpt-form__quick rpt-form__quick--secondary"
                          :disabled="saving"
                          @click="clearColumns"
                        >
                          Chỉ giữ cột cuối
                        </button>
                      </div>
                    </div>
                    <div class="rpt-form__picks rpt-form__picks--cols">
                      <label
                        v-for="col in COLUMN_OPTIONS"
                        :key="col.key"
                        class="rpt-form__pick rpt-form__pick--secondary"
                        :class="{ 'rpt-form__pick--on': columnOn(col.key) }"
                      >
                        <input
                          type="checkbox"
                          class="rpt-form__sr"
                          :checked="columnOn(col.key)"
                          :disabled="saving"
                          @change="toggleColumn(col.key)"
                        />
                        <span class="rpt-form__pick-tick" aria-hidden="true" />
                        <span class="rpt-form__pick-name">{{ col.label }}</span>
                      </label>
                    </div>
                  </div>

                  <div
                    v-else
                    id="rpt-display-criteria"
                    class="rpt-form__tab-pane rpt-form__tab-pane--matrix"
                    role="tabpanel"
                  >
                    <div v-if="criteria.length > 0" class="rpt-form__tab-bar">
                      <span class="rpt-form__section-note">{{ criteriaText }}</span>
                      <div class="rpt-form__tools rpt-form__tools--inline">
                        <div class="rpt-form__search-field">
                          <AppIcon name="search" :size="16" :stroke-width="2" aria-hidden="true" />
                          <input
                            v-model="criteriaQuery"
                            type="search"
                            class="rpt-form__search-input"
                            placeholder="Tìm tên hoặc loại…"
                            autocomplete="off"
                            spellcheck="false"
                            aria-label="Tìm tiêu chí"
                            :disabled="saving"
                          />
                          <button
                            v-if="criteriaQuery"
                            type="button"
                            class="rpt-form__search-clear"
                            aria-label="Xoá tìm kiếm"
                            @click="criteriaQuery = ''"
                          >
                            <AppIcon name="close" :size="14" />
                          </button>
                        </div>
                        <button
                          type="button"
                          class="rpt-form__quick rpt-form__quick--secondary"
                          :disabled="saving"
                          @click="selectAllCriteria"
                        >
                          Hiện tất cả
                        </button>
                      </div>
                    </div>
                    <p v-if="loadingOptions" class="rpt-form__empty">Đang tải danh sách tiêu chí…</p>
                    <p v-else-if="criteria.length === 0" class="rpt-form__empty">
                      Phòng ban chưa có tiêu chí cộng / trừ điểm theo hành vi nào.
                    </p>
                    <p v-else-if="filteredCriteriaGroups.length === 0" class="rpt-form__empty">
                      Không có tiêu chí khớp «{{ criteriaQuery }}».
                    </p>
                    <div v-else class="rpt-form__matrix">
                      <section
                        v-for="group in filteredCriteriaGroups"
                        :key="group.label"
                        class="rpt-form__matrix-group"
                        :class="pickerGroupClass(group)"
                      >
                        <button
                          type="button"
                          class="rpt-form__matrix-head"
                          :disabled="saving"
                          :aria-label="`Hiện hoặc ẩn cả nhóm ${group.label}`"
                          @click="toggleGroupCriteria(group.items)"
                        >
                          <span class="rpt-form__matrix-title">{{ group.label }}</span>
                          <span class="rpt-form__matrix-count">
                            {{ groupShownCount(group.items) }}/{{ group.items.length }}
                          </span>
                        </button>
                        <div class="rpt-form__matrix-list">
                          <label
                            v-for="item in group.items"
                            :key="item.id"
                            class="rpt-form__matrix-item"
                            :class="{ 'rpt-form__matrix-item--on': criterionOn(item.id) }"
                          >
                            <input
                              type="checkbox"
                              class="rpt-form__sr"
                              :checked="criterionOn(item.id)"
                              :disabled="saving"
                              @change="toggleCriterion(item.id)"
                            />
                            <span class="rpt-form__matrix-tick" aria-hidden="true" />
                            <span class="rpt-form__matrix-name">{{ item.name }}</span>
                          </label>
                        </div>
                      </section>
                    </div>
                  </div>
                </section>
              </div>

              <div v-else-if="currentStep.key === 'viewers'" class="rpt-form rpt-form--fill">
                <section class="rpt-form__section rpt-form__section--list">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--tertiary">
                      <AppIcon name="eye" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Người được xem</h2>
                    <span class="rpt-form__section-note">{{ viewerText }}</span>
                    <div v-if="members.length > 0" class="rpt-form__tools rpt-form__tools--inline">
                      <div class="rpt-form__search-field">
                        <AppIcon name="search" :size="16" :stroke-width="2" aria-hidden="true" />
                        <input
                          v-model="viewerQuery"
                          type="search"
                          class="rpt-form__search-input"
                          placeholder="Tìm theo tên hoặc email…"
                          autocomplete="off"
                          spellcheck="false"
                          aria-label="Tìm người được xem theo tên hoặc email"
                          :disabled="saving"
                        />
                        <button
                          v-if="viewerQuery"
                          type="button"
                          class="rpt-form__search-clear"
                          aria-label="Xoá tìm kiếm"
                          @click="viewerQuery = ''"
                        >
                          <AppIcon name="close" :size="14" />
                        </button>
                      </div>
                      <button
                        type="button"
                        class="rpt-form__quick rpt-form__quick--tertiary"
                        :disabled="saving"
                        @click="selectAllViewers"
                      >
                        Chọn tất cả
                      </button>
                      <button
                        type="button"
                        class="rpt-form__quick rpt-form__quick--tertiary"
                        :disabled="saving"
                        @click="clearViewers"
                      >
                        Bỏ chọn
                      </button>
                    </div>
                  </header>
                  <p class="rpt-form__story rpt-form__story--tertiary rpt-form__story--compact">
                    Người quản lý phòng ban
                    <strong class="rpt-form__story-val">luôn xem được</strong>.
                    Chọn thêm những người khác cần xem báo cáo này.
                  </p>

                  <p v-if="loadingOptions" class="rpt-form__empty">Đang tải danh sách nhân sự…</p>
                  <p v-else-if="members.length === 0" class="rpt-form__empty">
                    Phòng ban chưa có nhân sự nào đang hoạt động.
                  </p>
                  <p v-else-if="filteredViewers.length === 0" class="rpt-form__empty">
                    Không có nhân sự nào khớp với từ khoá đang tìm.
                  </p>
                  <div v-else class="rpt-form__people rpt-form__people--tertiary">
                    <table class="rpt-form__people-table">
                      <thead>
                        <tr>
                          <th class="rpt-form__people-check">
                            <label class="rpt-form__people-check-lab">
                              <input
                                type="checkbox"
                                class="rpt-form__sr"
                                :checked="allVisibleSelected(form.viewer_user_ids, filteredViewers)"
                                :indeterminate="someVisibleSelected(form.viewer_user_ids, filteredViewers)"
                                :disabled="saving"
                                aria-label="Chọn tất cả người đang hiện"
                                @change="toggleVisibleSelection(form.viewer_user_ids, filteredViewers)"
                              />
                              <span
                                class="rpt-form__pick-tick"
                                :class="{
                                  'rpt-form__pick-tick--on': allVisibleSelected(
                                    form.viewer_user_ids,
                                    filteredViewers,
                                  ),
                                  'rpt-form__pick-tick--mixed': someVisibleSelected(
                                    form.viewer_user_ids,
                                    filteredViewers,
                                  ),
                                }"
                                aria-hidden="true"
                              />
                            </label>
                          </th>
                          <th>Nhân sự</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="member in filteredViewers"
                          :key="member.id"
                          class="rpt-form__people-row rpt-form__people-row--tertiary"
                          :class="{ 'rpt-form__people-row--on': form.viewer_user_ids.includes(member.id) }"
                          @click="saving ? null : toggleInList(form.viewer_user_ids, member.id)"
                        >
                          <td class="rpt-form__people-check" @click.stop>
                            <label class="rpt-form__people-check-lab">
                              <input
                                type="checkbox"
                                class="rpt-form__sr"
                                :checked="form.viewer_user_ids.includes(member.id)"
                                :disabled="saving"
                                :aria-label="`Chọn ${member.name}`"
                                @change="toggleInList(form.viewer_user_ids, member.id)"
                              />
                              <span
                                class="rpt-form__pick-tick"
                                :class="{
                                  'rpt-form__pick-tick--on': form.viewer_user_ids.includes(member.id),
                                }"
                                aria-hidden="true"
                              />
                            </label>
                          </td>
                          <td>
                            <span class="rpt-form__person">
                              <span class="rpt-form__person-avatar" aria-hidden="true">
                                <img
                                  v-if="memberPhoto(member)"
                                  :src="member.avatar_url"
                                  alt=""
                                  class="rpt-form__person-avatar-img"
                                  referrerpolicy="no-referrer"
                                  @error="onMemberAvatarError(member.id)"
                                />
                                <template v-else>{{ memberInitial(member.name) }}</template>
                              </span>
                              <span class="rpt-form__person-text">
                                <span class="rpt-form__person-name">{{ member.name }}</span>
                                <span v-if="member.email" class="rpt-form__person-email">{{
                                  member.email
                                }}</span>
                              </span>
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </section>
              </div>

              <div v-else class="rpt-form">
                <section class="rpt-form__section">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--umber">
                      <AppIcon name="clipboardCheck" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Tóm tắt lựa chọn</h2>
                  </header>
                  <div class="rpt-form__rows">
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Tên báo cáo</span>
                      <span class="rpt-form__row-value">{{ form.title }}</span>
                    </div>
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Kỳ báo cáo</span>
                      <span class="rpt-form__row-value">{{ periodRangeText }}</span>
                    </div>
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Phạm vi nhân sự</span>
                      <span class="rpt-form__row-value">{{ scopeText }}</span>
                    </div>
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Cột hiển thị</span>
                      <span class="rpt-form__row-value">{{ columnText }}</span>
                    </div>
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Tiêu chí trên bảng</span>
                      <span class="rpt-form__row-value">{{ criteriaText }}</span>
                    </div>
                    <div class="rpt-form__row">
                      <span class="rpt-form__row-label">Người được xem</span>
                      <span class="rpt-form__row-value">{{ viewerText }}</span>
                    </div>
                  </div>
                </section>

                <section class="rpt-form__section">
                  <header class="rpt-form__section-head">
                    <span class="rpt-form__section-icon rpt-form__section-icon--umber">
                      <AppIcon name="barChart" :size="16" :stroke-width="1.75" />
                    </span>
                    <h2 class="rpt-form__section-title">Xem trước số liệu</h2>
                  </header>

                  <p v-if="previewLoading" class="rpt-form__empty">Đang tính thử số liệu…</p>
                  <p v-else-if="previewError" class="rpt-form__empty">{{ previewError }}</p>
                  <template v-else-if="preview">
                    <p class="rpt-form__story rpt-form__story--umber">
                      Tính thử theo phiên bản khung chấm điểm số
                      <strong class="rpt-form__story-val">{{ preview.version_no }}</strong>.
                      Sau khi tạo, bạn vào bảng chấm điểm đúng kỳ này — bấm tên nhân sự hoặc ô tiêu chí để ghi nhận điểm.
                    </p>

                    <div class="rpt-form__stats">
                      <div class="rpt-form__stat">
                        <span class="rpt-form__stat-label">Tổng nhân sự</span>
                        <span class="rpt-form__stat-value">{{ preview.summary.total_people }}</span>
                      </div>
                      <div class="rpt-form__stat">
                        <span class="rpt-form__stat-label">Điểm trung bình</span>
                        <span class="rpt-form__stat-value">{{ preview.summary.average_score }}</span>
                      </div>
                      <div class="rpt-form__stat">
                        <span class="rpt-form__stat-label">Cao nhất</span>
                        <span class="rpt-form__stat-value">{{ preview.summary.highest_score }}</span>
                      </div>
                      <div class="rpt-form__stat">
                        <span class="rpt-form__stat-label">Thấp nhất</span>
                        <span class="rpt-form__stat-value">{{ preview.summary.lowest_score }}</span>
                      </div>
                    </div>

                    <div v-if="hasMissingPreview" class="rpt-form__warning">
                      <p>
                        Có {{ preview.summary.missing_total }} công việc chưa đủ dữ liệu nên điểm thực = 0.
                        Những việc này vẫn nằm trong mẫu số hiệu suất.
                      </p>
                      <label class="rpt-form__pick rpt-form__pick--umber" :class="{ 'rpt-form__pick--on': acknowledgedMissing }">
                        <input v-model="acknowledgedMissing" type="checkbox" class="rpt-form__sr" />
                        <span class="rpt-form__pick-tick" aria-hidden="true" />
                        <span class="rpt-form__pick-name">Tôi đã hiểu và vẫn muốn tạo báo cáo với số liệu này.</span>
                      </label>
                    </div>

                    <div v-if="preview.rows.length" class="rpt-form__rows">
                      <div v-for="row in preview.rows" :key="row.user_id" class="rpt-form__row">
                        <span class="rpt-form__row-label">{{ row.user_name }}</span>
                        <span class="rpt-form__row-value">
                          {{ row.final_score }}
                          <template v-if="row.classification_label">
                            · {{ row.classification_label }}
                          </template>
                        </span>
                      </div>
                    </div>
                    <p v-else class="rpt-form__empty">Chưa có nhân sự nào trong phạm vi đã chọn.</p>
                  </template>
                </section>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <Transition name="rpt-wizard-ready">
      <div v-if="showStep1ReadyNote" class="rpt-edit__flow-banner" role="status">
        <span class="rpt-edit__flow-banner-icon" aria-hidden="true">
          <AppIcon name="check" :size="20" :stroke-width="2.5" />
        </span>
        <div class="rpt-edit__flow-banner-copy">
          <p class="rpt-edit__flow-banner-title">Đã đủ thông tin bắt buộc ở bước 1</p>
          <p class="rpt-edit__flow-banner-desc">
            Tên báo cáo và kỳ báo cáo đã được nhập. Chọn
            <strong>{{ STEPS[1].title }}</strong>
            ở cột trái hoặc bấm nút bên phải để sang bước 2.
          </p>
        </div>
        <div class="rpt-edit__flow-banner-actions">
          <button type="button" class="rpt-edit__btn rpt-edit__btn--ghost" :disabled="saving" @click="requestLeave">
            Huỷ
          </button>
          <button type="button" class="rpt-edit__flow-banner-btn" :disabled="saving" @click="goToStep(2)">
            Sang bước 2: {{ STEPS[1].title }}
            <AppIcon name="chevronRight" :size="16" :stroke-width="2" />
          </button>
        </div>
      </div>
    </Transition>

    <div v-if="!showStep1ReadyNote" class="rpt-edit__actions">
      <button type="button" class="rpt-edit__btn rpt-edit__btn--ghost" :disabled="saving" @click="requestLeave">
        Huỷ
      </button>
      <button
        v-if="step > 1"
        type="button"
        class="rpt-edit__btn rpt-edit__btn--ghost"
        :disabled="saving"
        @click="goToStep(step - 1)"
      >
        Quay lại
      </button>
      <button
        v-if="!isLastStep"
        type="button"
        class="rpt-edit__btn"
        :class="{ 'rpt-edit__btn--flow-hint': step !== 1 && canAdvanceFromCurrentStep && !saving }"
        :disabled="saving"
        @click="goToStep(step + 1)"
      >
        Tiếp tục
      </button>
      <button v-else type="button" class="rpt-edit__btn" :disabled="!canCreate" @click="submit">
        {{ saving ? 'Đang tạo…' : 'Tạo báo cáo' }}
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="leaveConfirm"
        class="rpt-edit__confirm"
        role="alertdialog"
        aria-modal="true"
        aria-label="Xác nhận rời trang"
      >
        <div class="rpt-edit__confirm-backdrop" @click="cancelLeave" />
        <div class="rpt-edit__confirm-panel">
          <h2 class="rpt-edit__confirm-title">Rời khỏi trang tạo báo cáo?</h2>
          <p class="rpt-edit__confirm-text">Những gì bạn đã chọn sẽ mất và phải làm lại từ bước đầu.</p>
          <div class="rpt-edit__confirm-foot">
            <button type="button" class="rpt-edit__btn rpt-edit__btn--ghost" @click="cancelLeave">
              Ở lại tiếp tục
            </button>
            <button type="button" class="rpt-edit__btn" @click="confirmLeave">Rời đi và bỏ dữ liệu</button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.rpt-edit {
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: var(--space-4) var(--space-5) var(--space-4) var(--space-3);
  overflow: hidden;
  position: relative;
  isolation: isolate;
  background: var(--color-surface-muted);
  --rpt-wizard-duration: 0.52s;
  --rpt-wizard-ease: cubic-bezier(0.22, 1, 0.36, 1);
}

.rpt-edit__wm-defs {
  position: absolute;
  width: 0;
  height: 0;
  overflow: hidden;
}

.rpt-edit__watermark {
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

.rpt-edit__title {
  background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary-700) 100%);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  font-weight: 700;
}

.rpt-edit__header-btn {
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

.rpt-edit__header-btn:hover {
  background: var(--color-surface-muted);
}

.rpt-edit__layout {
  position: relative;
  z-index: 1;
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: var(--space-4);
  margin-top: var(--space-3);
}

.rpt-edit__rail {
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
  overflow-x: hidden;
  overflow-y: auto;
}

.rpt-edit__rail-flow-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.rpt-edit__rail-flow-label {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.rpt-edit__rail-flow-step {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-primary);
  transition: color var(--rpt-wizard-duration) var(--rpt-wizard-ease);
}

.rpt-edit__rail-flow-track {
  height: 4px;
  margin-top: var(--space-2);
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.rpt-edit__rail-flow-fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-primary) 0%, var(--color-primary-700) 100%);
  transition: width var(--rpt-wizard-duration) var(--rpt-wizard-ease);
  will-change: width;
}

.rpt-edit__step--next-hint .rpt-edit__step-dot {
  animation: rpt-edit-next-step-hint 1.6s ease-in-out infinite;
  box-shadow: 0 0 0 3px var(--step-surface);
}

@keyframes rpt-edit-next-step-hint {
  0%,
  100% {
    transform: scale(1);
  }

  50% {
    transform: scale(1.08);
  }
}

.rpt-edit__rail-list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.rpt-edit__step {
  --step-color: var(--color-primary);
  --step-surface: var(--color-primary-surface);
}

.rpt-edit__step--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.rpt-edit__step--secondary {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);
}

.rpt-edit__step--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.rpt-edit__step--umber {
  --step-color: var(--color-umber);
  --step-surface: var(--color-umber-surface);
}

.rpt-edit__step-btn {
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

.rpt-edit__step-track {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.rpt-edit__step-dot {
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
    background-color var(--rpt-wizard-duration) var(--rpt-wizard-ease),
    color var(--rpt-wizard-duration) var(--rpt-wizard-ease),
    box-shadow var(--rpt-wizard-duration) var(--rpt-wizard-ease),
    transform var(--rpt-wizard-duration) var(--rpt-wizard-ease);
}

.rpt-edit__step-line {
  position: relative;
  flex: 1;
  width: 2px;
  min-height: var(--space-4);
  margin: 2px 0;
  border-radius: var(--radius-full);
  background: var(--color-border);
  overflow: hidden;
}

.rpt-edit__step-line-fill {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: var(--step-color);
  opacity: 0.4;
  transform: scaleY(0);
  transform-origin: top center;
  transition: transform calc(var(--rpt-wizard-duration) * 1.15) var(--rpt-wizard-ease);
}

.rpt-edit__step--done .rpt-edit__step-line-fill {
  transform: scaleY(1);
}

.rpt-edit__step-btn:hover .rpt-edit__step-dot {
  box-shadow: 0 0 0 3px var(--step-surface);
}

.rpt-edit__step--current .rpt-edit__step-dot {
  background: var(--step-color);
  color: var(--color-on-primary, #fff);
  box-shadow: 0 0 0 4px var(--step-surface);
}

.rpt-edit__step--done .rpt-edit__step-dot {
  background: var(--step-surface);
  color: var(--step-color);
  box-shadow: none;
  animation: rpt-edit-step-done-pop calc(var(--rpt-wizard-duration) * 1.35) var(--rpt-wizard-ease);
}

@keyframes rpt-edit-step-done-pop {
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

.rpt-edit__step-title {
  flex: 1;
  padding: 0.5rem 0 var(--space-5);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.35;
  transition:
    color var(--rpt-wizard-duration) var(--rpt-wizard-ease),
    font-weight var(--rpt-wizard-duration) var(--rpt-wizard-ease);
}

.rpt-edit__step--current .rpt-edit__step-title,
.rpt-edit__step--done .rpt-edit__step-title {
  color: var(--color-text);
}

.rpt-edit__step--current .rpt-edit__step-title {
  color: var(--step-color);
  font-weight: 700;
}

.rpt-edit__step-btn:hover .rpt-edit__step-title {
  color: var(--step-color);
}

.rpt-edit__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: var(--space-1) var(--space-1) var(--space-2);
}

.rpt-edit__body--fill {
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.rpt-edit__body-stage {
  position: relative;
  min-height: 0;
}

.rpt-edit__body--fill .rpt-edit__body-stage {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.rpt-edit__step-panel {
  width: 100%;
  padding-bottom: var(--space-2);
}

.rpt-edit__step-panel--fill {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding-bottom: 0;
}

.rpt-edit__flow-banner {
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

.rpt-edit__flow-banner::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-success);
}

.rpt-edit__flow-banner-icon {
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

.rpt-edit__flow-banner-copy {
  flex: 1;
  min-width: min(100%, 16rem);
}

.rpt-edit__flow-banner-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-2);
  flex-shrink: 0;
  margin-left: auto;
}

.rpt-edit__flow-banner-title {
  margin: 0 0 var(--space-1);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.rpt-edit__flow-banner-desc {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.rpt-edit__flow-banner-desc strong {
  font-weight: 600;
  color: var(--color-text);
}

.rpt-edit__flow-banner-btn {
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

.rpt-edit__flow-banner-btn:hover {
  background: var(--color-primary-hover);
}

.rpt-edit__step-hint {
  margin: var(--space-4) 0 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.rpt-edit__actions {
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

.rpt-edit__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.rpt-edit__btn:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.rpt-edit__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rpt-edit__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.rpt-edit__btn--ghost:hover:not(:disabled) {
  background: var(--color-border);
}

.rpt-edit__btn--flow-hint:not(:disabled) {
  animation: rpt-edit-continue-hint 2.2s ease-in-out infinite;
}

@keyframes rpt-edit-continue-hint {
  0%,
  100% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary) 0%, transparent);
  }

  50% {
    box-shadow: 0 0 0 4px var(--color-primary-surface);
  }
}

.rpt-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.rpt-form--fill {
  flex: 1;
  min-height: 0;
}

.rpt-form__section {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.rpt-form__section--picker {
  padding: var(--space-3);
}

.rpt-form__section--display {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  gap: var(--space-3);
  overflow: hidden;
}

.rpt-tabs {
  display: flex;
  flex-shrink: 0;
  gap: 0.25rem;
  padding: 0.25rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.rpt-tabs__btn {
  display: inline-flex;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease,
    box-shadow 0.15s ease;
}

.rpt-tabs__btn :deep(svg) {
  flex-shrink: 0;
}

.rpt-tabs__btn:hover:not(:disabled):not(.rpt-tabs__btn--on) {
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-secondary) 8%, var(--color-surface));
}

.rpt-tabs__btn:focus-visible {
  outline: 2px solid var(--color-secondary-200);
  outline-offset: 1px;
}

.rpt-tabs__btn--on {
  background: var(--color-surface);
  color: var(--color-secondary);
  box-shadow: var(--shadow-sm);
}

.rpt-tabs__btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.rpt-tabs__count {
  display: inline-flex;
  min-width: 1.25rem;
  align-items: center;
  justify-content: center;
  padding: 0.0625rem 0.375rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface));
  color: var(--color-secondary-700);
  font-size: 0.6875rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.rpt-tabs__btn--on .rpt-tabs__count {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.rpt-form__tab-pane {
  display: flex;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
  gap: var(--space-2);
}

.rpt-form__tab-pane--matrix {
  flex: 1;
  min-height: 0;
}

.rpt-form__tab-bar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.rpt-form__tab-bar .rpt-form__tools--inline {
  flex: 1 1 12rem;
}

.rpt-form__tab-bar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.rpt-form__tab-bar .rpt-form__tools--inline {
  flex: 1 1 12rem;
}

.rpt-form__section--list {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-3);
}

.rpt-form__section-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.rpt-form__section--picker .rpt-form__section-head,
.rpt-form__section--list .rpt-form__section-head {
  margin-bottom: var(--space-2);
  flex-shrink: 0;
}

.rpt-form__section-note {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.rpt-form__section-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
}

.rpt-form__section-icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.rpt-form__section-icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.rpt-form__section-icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.rpt-form__section-icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.rpt-form__section-icon--umber {
  background: var(--color-umber-surface);
  color: var(--color-umber);
}

.rpt-form__section-title {
  margin: 0;
  flex-shrink: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.rpt-form__grid {
  display: grid;
  min-width: 0;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
  align-content: start;
}

.rpt-form__grid--4col {
  grid-template-columns: repeat(4, 1fr);
}

.rpt-form__field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.rpt-form__field :deep(.app-date) {
  width: 100%;
}

.rpt-form__field--wide {
  grid-column: 1 / -1;
}

.rpt-form__label {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.rpt-form__required {
  color: var(--color-primary);
}

.rpt-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

.rpt-form__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.rpt-form__input:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.rpt-form__input:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.rpt-form__input--error {
  box-shadow: 0 0 0 1px var(--color-danger);
}

.rpt-form__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.rpt-form__static {
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.875rem;
}

.rpt-form__static--duration {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  color: var(--color-primary);
  font-weight: 600;
}

.rpt-form__story {
  position: relative;
  margin: 0 0 var(--space-3);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 500;
  line-height: 1.55;
}

.rpt-form__story--compact {
  margin-bottom: var(--space-2);
  padding: 0.5rem 0.75rem;
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  font-size: 0.8125rem;
  line-height: 1.4;
}

.rpt-form__story::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-primary);
}

.rpt-form__story--compact::before {
  top: 0.375rem;
  bottom: 0.375rem;
}

.rpt-form__story--gold {
  background: var(--color-gold-surface);
}

.rpt-form__story--gold::before {
  background: var(--color-gold-600);
}

.rpt-form__story--gold .rpt-form__story-val {
  color: var(--color-gold-700);
}

.rpt-form__story--secondary {
  background: var(--color-secondary-surface);
}

.rpt-form__story--secondary::before {
  background: var(--color-secondary);
}

.rpt-form__story--secondary .rpt-form__story-val {
  color: var(--color-secondary-700);
}

.rpt-form__story--tertiary {
  background: var(--color-tertiary-surface);
}

.rpt-form__story--tertiary::before {
  background: var(--color-tertiary);
}

.rpt-form__story--umber {
  background: var(--color-umber-surface);
}

.rpt-form__story--umber::before {
  background: var(--color-umber);
}

.rpt-form__story-val {
  color: var(--color-primary);
  font-weight: 700;
}

.rpt-form__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.rpt-form__tools--inline {
  flex: 1 1 16rem;
  justify-content: flex-end;
  min-width: 0;
  margin: 0 0 0 auto;
}

.rpt-form__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.rpt-form__search-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1 1 12rem;
  min-width: 10rem;
  max-width: 18rem;
  height: 2rem;
  padding: 0 0.25rem 0 0.625rem;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.rpt-form__search-field:focus-within {
  box-shadow: inset 0 0 0 1.5px var(--color-primary);
  color: var(--color-text);
}

.rpt-form__search-input {
  flex: 1;
  min-width: 0;
  min-height: 0;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  line-height: 1.25;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
}

.rpt-form__search-input::-webkit-search-decoration,
.rpt-form__search-input::-webkit-search-cancel-button,
.rpt-form__search-input::-webkit-search-results-button,
.rpt-form__search-input::-webkit-search-results-decoration {
  -webkit-appearance: none;
  appearance: none;
  display: none;
}

.rpt-form__search-input::placeholder {
  color: var(--color-text-muted);
}

.rpt-form__search-input:disabled {
  cursor: not-allowed;
}

.rpt-form__search-clear {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.rpt-form__search-clear:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.rpt-form__quick {
  height: 2rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.rpt-form__quick:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-surface));
}

.rpt-form__quick--gold:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-gold-600) 18%, var(--color-surface));
}

.rpt-form__quick--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary-700);
}

.rpt-form__quick--secondary:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-secondary) 18%, var(--color-surface));
  color: var(--color-secondary-800);
}

.rpt-form__quick--tertiary:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-tertiary) 18%, var(--color-surface));
}

.rpt-form__quick:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.rpt-form__picks {
  --rpt-pick-visible: 5;
  --rpt-pick-h: 2.25rem;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-2);
  align-content: start;
  max-height: calc(
    (var(--rpt-pick-h) * var(--rpt-pick-visible)) + (var(--space-2) * (var(--rpt-pick-visible) - 1))
  );
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: none;
}

.rpt-form__picks::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.rpt-form__picks--cols {
  grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
}

.rpt-form__pick {
  --pick-color: var(--color-primary);
  --pick-surface: var(--color-primary-surface);
  --pick-on: var(--color-on-primary);

  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  min-height: 2.25rem;
  padding: 0.375rem 0.5rem;
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: var(--color-text);
  font-size: 0.8125rem;
  cursor: pointer;
}

.rpt-form__pick::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.rpt-form__pick:hover {
  background: color-mix(in srgb, var(--pick-color) 8%, var(--color-surface));
}

.rpt-form__pick--on:hover {
  background: var(--pick-surface);
}

.rpt-form__pick:focus-within {
  box-shadow: var(--shadow-sm), 0 0 0 2px color-mix(in srgb, var(--pick-color) 35%, transparent);
}

.rpt-form__pick--gold {
  --pick-color: var(--color-gold-600);
  --pick-surface: var(--color-gold-surface);
  --pick-on: var(--color-on-gold);
}

.rpt-form__pick--secondary {
  --pick-color: var(--color-secondary);
  --pick-surface: var(--color-secondary-surface);
  --pick-on: var(--color-on-secondary);
}

.rpt-form__pick--tertiary {
  --pick-color: var(--color-tertiary);
  --pick-surface: var(--color-tertiary-surface);
  --pick-on: var(--color-on-tertiary);
}

.rpt-form__pick--umber {
  --pick-color: var(--color-umber);
  --pick-surface: var(--color-umber-surface);
  --pick-on: var(--color-on-umber);
}

.rpt-form__pick--on {
  background: var(--pick-surface);
}

.rpt-form__pick--on::before {
  background: var(--pick-color);
}

.rpt-form__pick-tick {
  flex-shrink: 0;
  width: 0.875rem;
  height: 0.875rem;
  border-radius: 0.1875rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.rpt-form__pick--on .rpt-form__pick-tick,
.rpt-form__pick-tick--on {
  background: var(--pick-color);
  box-shadow: none;
}

.rpt-form__pick--on .rpt-form__pick-tick::after,
.rpt-form__pick-tick--on::after {
  content: '';
  display: block;
  width: 0.25rem;
  height: 0.4375rem;
  margin: 0.0625rem auto 0;
  border: solid var(--pick-on);
  border-width: 0 1.5px 1.5px 0;
  transform: rotate(45deg);
}

.rpt-form__pick-tick--mixed {
  background: var(--pick-color);
  box-shadow: none;
}

.rpt-form__pick-tick--mixed::after {
  content: '';
  display: block;
  width: 0.5rem;
  height: 1.5px;
  margin: 0.375rem auto 0;
  background: var(--pick-on);
}

.rpt-form__pick-name {
  min-width: 0;
  overflow-wrap: anywhere;
  line-height: 1.3;
}

.rpt-form__people {
  --pick-color: var(--color-primary);
  --pick-surface: var(--color-primary-surface);
  --pick-on: var(--color-on-primary);

  flex: 1;
  min-height: 0;
  overflow: auto;
  scrollbar-width: none;
}

.rpt-form__people::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.rpt-form__people--gold {
  --pick-color: var(--color-gold-600);
  --pick-surface: var(--color-gold-surface);
  --pick-on: var(--color-on-gold);
}

.rpt-form__people--tertiary {
  --pick-color: var(--color-tertiary);
  --pick-surface: var(--color-tertiary-surface);
  --pick-on: var(--color-on-tertiary);
}

.rpt-form__people-table {
  width: 100%;
  min-width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
}

.rpt-form__people-table th,
.rpt-form__people-table td {
  padding: 0.5rem 0.75rem;
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
}

.rpt-form__people-table thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  box-shadow: 0 1px 0 var(--color-border);
}

.rpt-form__people-check {
  position: relative;
  width: 2.75rem;
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
}

.rpt-form__people-check-lab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  cursor: pointer;
}

.rpt-form__people-row {
  cursor: pointer;
}

.rpt-form__people-row td {
  box-shadow: 0 1px 0 var(--color-border);
}

.rpt-form__people-row td.rpt-form__people-check::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.rpt-form__people-row:hover td {
  background: color-mix(in srgb, var(--pick-color) 8%, var(--color-surface));
}

.rpt-form__people-row--on td {
  background: var(--pick-surface);
}

.rpt-form__people-row--on:hover td {
  background: var(--pick-surface);
}

.rpt-form__people-row--on td.rpt-form__people-check::before {
  background: var(--pick-color);
}

.rpt-form__people-row:focus-within td {
  box-shadow: 0 1px 0 var(--color-border), 0 0 0 2px color-mix(in srgb, var(--pick-color) 35%, transparent);
}

.rpt-form__person {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  min-width: 0;
}

.rpt-form__person-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--pick-surface);
  color: var(--pick-color);
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1;
}

.rpt-form__person-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rpt-form__person-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.rpt-form__person-name,
.rpt-form__person-email {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rpt-form__person-name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.rpt-form__person-email {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.35;
}

.rpt-form__section--list .rpt-form__story,
.rpt-form__section--list .rpt-form__empty {
  flex-shrink: 0;
}

.rpt-form__matrix {
  display: grid;
  flex: 1;
  min-height: 0;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-auto-rows: minmax(12rem, 1fr);
  gap: var(--space-3);
  align-content: stretch;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-width: none;
}

.rpt-form__matrix::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.rpt-form__matrix-group {
  position: relative;
  display: flex;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.rpt-form__matrix-group::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  z-index: 1;
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.rpt-form__matrix-group--ok::before {
  background: var(--color-secondary);
}

.rpt-form__matrix-group--cut::before {
  background: var(--color-danger);
}

.rpt-form__matrix-group--mixed::before {
  background: var(--color-gold);
}

.rpt-form__matrix-head {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  width: 100%;
  padding: 0.75rem 0.75rem 0.75rem calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1.3;
  text-align: left;
  cursor: pointer;
  box-shadow: 0 1px 0 var(--color-border);
}

.rpt-form__matrix-head:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-secondary) 6%, var(--color-surface));
}

.rpt-form__matrix-head:focus-visible {
  background: color-mix(in srgb, var(--color-secondary) 6%, var(--color-surface));
  box-shadow: 0 1px 0 var(--color-border), inset 0 0 0 1.5px var(--color-secondary);
}

.rpt-form__matrix-head:disabled {
  cursor: not-allowed;
}

.rpt-form__matrix-title {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rpt-form__matrix-count {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  min-width: 2.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.rpt-form__matrix-group--all .rpt-form__matrix-count {
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface));
  color: var(--color-secondary);
}

.rpt-form__matrix-list {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-1);
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-width: none;
}

.rpt-form__matrix-list::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.rpt-form__matrix-item {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  min-height: 2.125rem;
  padding: 0.5rem 0.625rem 0.5rem calc(var(--space-2) + 3px + var(--space-2));
  border-radius: calc(var(--radius-md) - 2px);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
  cursor: pointer;
}

.rpt-form__matrix-item:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.rpt-form__matrix-item:has(:focus-visible) {
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1.5px var(--color-secondary);
}

.rpt-form__matrix-item--on {
  background: color-mix(in srgb, var(--color-secondary) 8%, var(--color-surface));
  color: var(--color-text);
}

.rpt-form__matrix-item--on:hover {
  background: color-mix(in srgb, var(--color-secondary) 12%, var(--color-surface));
}

.rpt-form__matrix-tick {
  flex-shrink: 0;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.0625rem;
  border-radius: 0.25rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1.5px var(--color-border);
}

.rpt-form__matrix-item--on .rpt-form__matrix-tick {
  background: var(--color-secondary);
  box-shadow: none;
}

.rpt-form__matrix-item--on .rpt-form__matrix-tick::after {
  content: '';
  display: block;
  width: 0.3125rem;
  height: 0.5625rem;
  margin: 0.125rem auto 0;
  border: solid var(--color-on-secondary);
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
}

.rpt-form__matrix-name {
  min-width: 0;
  overflow-wrap: anywhere;
}

.rpt-form__empty {
  margin: 0;
  padding: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.rpt-form__rows {
  display: flex;
  flex-direction: column;
}

.rpt-form__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.rpt-form__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.rpt-form__row-label::after {
  content: ':';
}

.rpt-form__row-value {
  color: var(--color-text);
  font-style: italic;
  text-align: right;
  overflow-wrap: anywhere;
}

.rpt-form__stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.rpt-form__stat {
  position: relative;
  padding: var(--space-3);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.rpt-form__stat::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-umber);
}

.rpt-form__stat-label {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.rpt-form__stat-value {
  display: block;
  margin-top: var(--space-1);
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.rpt-form__warning {
  position: relative;
  display: grid;
  gap: var(--space-2);
  margin: 0 0 var(--space-3);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
  font-size: 0.8125rem;
}

.rpt-form__warning::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-warning);
}

.rpt-form__warning p {
  margin: 0;
}

.rpt-edit__confirm {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.rpt-edit__confirm-backdrop {
  position: absolute;
  inset: 0;
  background: var(--color-sidebar-overlay);
}

.rpt-edit__confirm-panel {
  position: relative;
  z-index: 1;
  width: min(28rem, 100%);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.rpt-edit__confirm-title {
  margin: 0 0 var(--space-2);
  font-size: 1rem;
  font-weight: 600;
}

.rpt-edit__confirm-text {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.5;
}

.rpt-edit__confirm-foot {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
}

@media (max-width: 1279px) {
  .rpt-form__grid,
  .rpt-form__grid--4col,
  .rpt-form__stats {
    grid-template-columns: repeat(2, 1fr);
  }

  .rpt-form__matrix {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: minmax(12rem, 1fr);
  }
}

@media (max-width: 768px) {
  .rpt-edit {
    padding: var(--space-3);
  }

  .rpt-edit__layout {
    flex-direction: column;
    gap: var(--space-3);
  }

  .rpt-edit__rail {
    width: 100%;
    padding: var(--space-2) var(--space-3);
  }

  .rpt-edit__rail-list {
    flex-direction: row;
    justify-content: space-between;
  }

  .rpt-edit__step-btn {
    flex-direction: column;
    align-items: center;
    gap: var(--space-1);
    text-align: center;
  }

  .rpt-edit__step-track {
    flex-direction: row;
    width: 100%;
  }

  .rpt-edit__step-line {
    height: 2px;
    width: auto;
    min-height: 0;
    margin: 0 2px;
  }

  .rpt-edit__step-line-fill {
    transform: scaleX(0);
    transform-origin: left center;
  }

  .rpt-edit__step--done .rpt-edit__step-line-fill {
    transform: scaleX(1);
  }

  .rpt-edit__step-title {
    padding: 0;
    font-size: 0.6875rem;
  }

  .rpt-edit__flow-banner {
    flex-direction: column;
    align-items: stretch;
  }

  .rpt-edit__flow-banner-actions {
    margin-left: 0;
    width: 100%;
    flex-direction: column-reverse;
  }

  .rpt-edit__flow-banner-actions .rpt-edit__btn,
  .rpt-edit__flow-banner-btn {
    width: 100%;
    justify-content: center;
  }

  .rpt-form__section {
    padding: var(--space-3);
  }

  .rpt-form__grid,
  .rpt-form__grid--4col,
  .rpt-form__stats {
    grid-template-columns: 1fr;
  }

  .rpt-form__tools--inline {
    flex-basis: 100%;
    margin-left: 0;
  }

  .rpt-form__tools--inline .rpt-form__search-field {
    max-width: none;
  }

  .rpt-tabs__btn {
    padding: 0.4375rem 0.5rem;
    font-size: 0.8125rem;
  }

  .rpt-form__matrix {
    grid-template-columns: minmax(0, 1fr);
    grid-auto-rows: minmax(11rem, 1fr);
  }
}

@media (max-width: 480px) {
  .rpt-edit {
    padding: var(--space-2);
  }
}

@media (prefers-reduced-motion: reduce) {
  .rpt-edit {
    --rpt-wizard-duration: 0.01ms;
  }

  .rpt-edit__step--done .rpt-edit__step-dot,
  .rpt-edit__step--next-hint .rpt-edit__step-dot {
    animation: none;
  }

  .rpt-edit__btn--flow-hint:not(:disabled) {
    animation: none;
  }
}
</style>

<style>
.rpt-edit {
  --rpt-wizard-ease: cubic-bezier(0.22, 1, 0.36, 1);
  --rpt-wizard-duration: 0.52s;
}

.rpt-edit .rpt-wizard-forward-enter-active,
.rpt-edit .rpt-wizard-forward-leave-active,
.rpt-edit .rpt-wizard-back-enter-active,
.rpt-edit .rpt-wizard-back-leave-active {
  transition:
    opacity var(--rpt-wizard-duration) var(--rpt-wizard-ease),
    transform var(--rpt-wizard-duration) var(--rpt-wizard-ease);
}

.rpt-edit .rpt-wizard-forward-leave-active,
.rpt-edit .rpt-wizard-back-leave-active {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 0;
}

.rpt-edit .rpt-wizard-forward-enter-active,
.rpt-edit .rpt-wizard-back-enter-active {
  z-index: 1;
}

.rpt-edit .rpt-wizard-forward-enter-from {
  opacity: 0;
  transform: translateX(2.25rem);
}

.rpt-edit .rpt-wizard-forward-leave-to {
  opacity: 0;
  transform: translateX(-2.25rem);
}

.rpt-edit .rpt-wizard-back-enter-from {
  opacity: 0;
  transform: translateX(-2.25rem);
}

.rpt-edit .rpt-wizard-back-leave-to {
  opacity: 0;
  transform: translateX(2.25rem);
}

.rpt-edit .rpt-wizard-ready-enter-active,
.rpt-edit .rpt-wizard-ready-leave-active {
  transition:
    opacity 0.4s var(--rpt-wizard-ease),
    transform 0.4s var(--rpt-wizard-ease);
}

.rpt-edit .rpt-wizard-ready-enter-from,
.rpt-edit .rpt-wizard-ready-leave-to {
  opacity: 0;
  transform: translateY(0.5rem);
}

@media (prefers-reduced-motion: reduce) {
  .rpt-edit {
    --rpt-wizard-duration: 0.01ms;
  }

  .rpt-edit .rpt-wizard-forward-enter-active,
  .rpt-edit .rpt-wizard-forward-leave-active,
  .rpt-edit .rpt-wizard-back-enter-active,
  .rpt-edit .rpt-wizard-back-leave-active,
  .rpt-edit .rpt-wizard-ready-enter-active,
  .rpt-edit .rpt-wizard-ready-leave-active,
  .rpt-edit .rpt-wizard-forward-enter-from,
  .rpt-edit .rpt-wizard-forward-leave-to,
  .rpt-edit .rpt-wizard-back-enter-from,
  .rpt-edit .rpt-wizard-back-leave-to,
  .rpt-edit .rpt-wizard-ready-enter-from,
  .rpt-edit .rpt-wizard-ready-leave-to {
    transition: none;
    transform: none;
  }
}
</style>
