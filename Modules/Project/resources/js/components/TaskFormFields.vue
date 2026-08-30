<script setup>
//
// Field form tạo công việc — nhóm theo bước wizard, cùng ngôn ngữ hình
// ảnh với ProjectFormFields (section card, nhãn chữ hoa, picker người).
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDatePicker from '@/components/AppDatePicker.vue';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';
import TaskParentPicker from './TaskParentPicker.vue';
import TaskProjectPicker from './TaskProjectPicker.vue';
import OptionPicker from './OptionPicker.vue';
import { TASK_PROGRESS_METHOD_OPTIONS } from '../constants/task.js';

const STEP_SECTIONS = {
  1: ['general', 'schedule'],
  2: ['people'],
  3: ['settings'],
  4: ['rules', 'policy'],
};

const FALLBACK_IMPORTANCE = [
  { value: 'support', label: 'Phụ trợ', description: 'Tác động nhỏ. Trọng số x1.', weight: 1, code: '' },
  { value: 'assist', label: 'Hỗ trợ', description: 'Tác động gián tiếp. Trọng số x2.', weight: 2, code: '' },
  { value: 'important', label: 'Quan trọng', description: 'Ảnh hưởng đến vận hành thường xuyên, trực tiếp. Trọng số x3.', weight: 3, code: '' },
  { value: 'high_priority', label: 'Ưu tiên cao', description: 'Ảnh hưởng đến kết quả phòng ban chức năng, hoặc khách hàng nội bộ/bên ngoài. Trọng số x4.', weight: 4, code: '' },
  { value: 'strategic', label: 'Chiến lược / Sống còn', description: 'Ảnh hưởng trực tiếp đến thương hiệu, pháp lý, định hướng dài hạn, doanh thu, uy tín công ty. Trọng số x5.', weight: 5, code: '' },
];

const PRIORITY_COLOR = {
  support: 'umber',
  assist: 'tertiary',
  important: 'secondary',
  high_priority: 'gold',
  strategic: 'primary',
  low: 'umber',
  medium: 'tertiary',
  high: 'gold',
  urgent: 'primary',
};

const RULE_DEFS = [
  {
    key: 'hide_cross_tasks_from_assignees',
    icon: 'eyeOff',
    tone: 'secondary',
    label: 'Ẩn việc chéo',
    title: 'Không cho phép người thực hiện công việc này xem được công việc chéo thuộc cùng một công việc cha',
  },
  {
    key: 'hide_from_parent_assignees',
    icon: 'eyeOff',
    tone: 'gold',
    label: 'Ẩn với người làm việc cha',
    title: 'Không cho phép người thực hiện công việc cha xem được công việc này',
  },
  {
    key: 'hide_from_parent_followers',
    icon: 'eye',
    tone: 'tertiary',
    label: 'Ẩn với người theo dõi việc cha',
    title: 'Không cho phép người theo dõi công việc cha xem được công việc này',
  },
  {
    key: 'hide_child_tasks_from_followers',
    icon: 'eye',
    tone: 'primary',
    label: 'Ẩn việc con',
    title: 'Không cho phép người theo dõi xem được các công việc con thuộc công việc cha',
  },
  {
    key: 'allow_child_people_view_parent',
    icon: 'users',
    tone: 'secondary',
    label: 'Xem việc cha',
    title: 'Cho phép người thực hiện, người theo dõi các công việc con xem công việc cha',
  },
];

const INTERACTION_OPTIONS = [
  { value: 'allow', label: 'Cho phép' },
  { value: 'deny', label: 'Không cho phép' },
  { value: 'inherit', label: 'Biến động theo cài đặt' },
];

const REQUIREMENT_OPTIONS = [
  { value: 'none', label: 'Không yêu cầu' },
  { value: 'on_report', label: 'Yêu cầu khi báo cáo' },
  { value: 'on_completion', label: 'Yêu cầu khi báo cáo hoàn thành' },
];

const POLICY_DEFS = [
  {
    key: 'completed_interaction_policy',
    icon: 'messageCircle',
    tone: 'tertiary',
    label: 'Thảo luận và file sau hoàn thành',
    options: INTERACTION_OPTIONS,
  },
  {
    key: 'report_description_requirement',
    icon: 'fileText',
    tone: 'secondary',
    label: 'Mô tả khi báo cáo',
    options: REQUIREMENT_OPTIONS,
  },
  {
    key: 'report_attachment_requirement',
    icon: 'paperclip',
    tone: 'gold',
    label: 'File khi báo cáo',
    options: REQUIREMENT_OPTIONS,
  },
];

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  users: { type: Array, required: true },
  importanceOptions: { type: Array, default: () => [] },
  importanceCriterion: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
  step: { type: Number, default: 0 },
  durationDays: { type: [Number, null], default: null },
  selectedParent: { type: Object, default: null },
  parentDateRange: { type: String, default: '' },
});

const emit = defineEmits(['update:field', 'update:parent-item', 'update:project-item']);

const selectedProject = ref(null);

const projectName = computed(() => selectedProject.value?.name || '');

function set(field, value) {
  emit('update:field', field, value);
}

function fieldError(field) {
  const e = props.errors?.[field];
  return Array.isArray(e) ? e[0] : e;
}

function showSection(key) {
  if (!props.step) return true;
  return (STEP_SECTIONS[props.step] || []).includes(key);
}

function userName(id) {
  if (!id) return '';
  return props.users.find((u) => String(u.id) === String(id))?.name || '';
}

function formatNamedList(ids) {
  const names = (ids || [])
    .map((id) => userName(id))
    .filter(Boolean);
  if (!names.length) return '';
  if (names.length <= 2) return names.join(', ');
  return `${names[0]}, ${names[1]} và ${names.length - 2} người nữa`;
}

function onProjectId(value) {
  set('project_id', value === '' || value == null ? '' : String(value));
}

function onProjectItem(item) {
  selectedProject.value = item;
  emit('update:project-item', item);
}

function onParentId(value) {
  set('parent_id', value === '' || value == null ? '' : String(value));
}

const assigneeStory = computed(() => userName(props.form.assignee_id));
const managerStory = computed(() => userName(props.form.manager_id));
const watcherStory = computed(() => formatNamedList(props.form.watcher_ids));
const collaboratorStory = computed(() => formatNamedList(props.form.collaborator_ids));

const enabledRulesCount = computed(
  () => RULE_DEFS.filter((rule) => Boolean(props.form[rule.key])).length,
);

function toggleRule(key) {
  if (props.disabled) return;
  set(key, !props.form[key]);
}

function policyLabel(options, value) {
  return options.find((option) => option.value === value)?.label || '';
}

function priorityColor(value) {
  return PRIORITY_COLOR[value] || 'umber';
}

const importanceTitle = computed(
  () => props.importanceCriterion?.name || 'Loại công việc',
);

const importanceRows = computed(() => {
  const rows = (props.importanceOptions || []).map((opt) => ({
    value: opt.value,
    label: opt.label,
    description: opt.description || '',
    weight: opt.weight,
    code: opt.code || '',
  }));
  return rows.length ? rows : FALLBACK_IMPORTANCE;
});
</script>

<template>
  <div class="proj-form">
    <section v-if="showSection('general')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="clipboardCheck" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thông tin chung</h2>
      </header>

      <div class="proj-form__grid proj-form__grid--2col">
        <div class="proj-form__field">
          <label class="proj-form__label" id="task-form-project-label">Dự án</label>
          <TaskProjectPicker
            :model-value="form.project_id"
            :disabled="disabled"
            aria-labelledby="task-form-project-label"
            @update:model-value="onProjectId"
            @update:item="onProjectItem($event)"
          />
          <span v-if="fieldError('project_id')" class="proj-form__error">{{ fieldError('project_id') }}</span>
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" id="task-form-parent-label">Công việc cha</label>
          <TaskParentPicker
            :model-value="form.parent_id"
            :project-id="form.project_id"
            :disabled="disabled"
            aria-labelledby="task-form-parent-label"
            @update:model-value="onParentId"
            @update:item="emit('update:parent-item', $event)"
          />
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <label class="proj-form__label" for="task-form-title">
            Tên công việc <span class="proj-form__required">*</span>
          </label>
          <input
            id="task-form-title"
            :value="form.title"
            type="text"
            class="proj-page__input"
            :class="{ 'proj-page__input--error': fieldError('title') }"
            maxlength="255"
            placeholder="Ví dụ: Soạn đề kiểm tra giữa kỳ"
            :disabled="disabled"
            @input="set('title', $event.target.value)"
          />
          <span v-if="fieldError('title')" class="proj-form__error">{{ fieldError('title') }}</span>
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <label class="proj-form__label" for="task-form-desc">Mô tả</label>
          <textarea
            id="task-form-desc"
            :value="form.description"
            class="proj-page__textarea"
            rows="3"
            maxlength="5000"
            placeholder="Nội dung, kết quả mong đợi hoặc lưu ý thực hiện…"
            :disabled="disabled"
            @input="set('description', $event.target.value)"
          />
        </div>
      </div>
    </section>

    <section v-if="showSection('schedule')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="calendar" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thời gian thực hiện</h2>
      </header>

      <div v-if="selectedParent && parentDateRange" class="task-form__range">
        <AppIcon name="calendar" :size="16" :stroke-width="1.75" />
        Công việc con phải nằm trong khoảng {{ parentDateRange }}.
      </div>

      <div class="proj-form__grid proj-form__grid--4col">
        <div class="proj-form__field">
          <label class="proj-form__label" for="task-form-start">
            Ngày bắt đầu <span class="proj-form__required">*</span>
          </label>
          <AppDatePicker
            id="task-form-start"
            :model-value="form.start_date"
            :min="selectedParent?.start_date || ''"
            :max="selectedParent?.end_date || ''"
            :error="Boolean(fieldError('start_date'))"
            :disabled="disabled"
            @update:model-value="set('start_date', $event)"
          />
          <span v-if="fieldError('start_date')" class="proj-form__error">{{ fieldError('start_date') }}</span>
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" for="task-form-end">
            Ngày kết thúc <span class="proj-form__required">*</span>
          </label>
          <AppDatePicker
            id="task-form-end"
            :model-value="form.end_date"
            :min="form.start_date || selectedParent?.start_date || ''"
            :max="selectedParent?.end_date || ''"
            :error="Boolean(fieldError('end_date'))"
            :disabled="disabled"
            @update:model-value="set('end_date', $event)"
          />
          <span v-if="fieldError('end_date')" class="proj-form__error">{{ fieldError('end_date') }}</span>
        </div>

        <div class="proj-form__field">
          <span class="proj-form__label">Số ngày thực hiện</span>
          <span class="proj-form__static proj-form__static--duration">
            <AppIcon name="clock" :size="14" :stroke-width="1.75" />
            {{ durationDays ? `${durationDays} ngày` : '—' }}
          </span>
        </div>

        <div class="proj-form__field">
          <span class="proj-form__label">Thuộc dự án</span>
          <span class="proj-form__static">{{ projectName || 'Công việc thường xuyên' }}</span>
        </div>
      </div>

      <div class="task-form__time-grid">
        <label class="task-form__check">
          <input
            :checked="form.assign_by_time"
            type="checkbox"
            :disabled="disabled"
            @change="set('assign_by_time', $event.target.checked)"
          />
          <span>
            <strong>Giao việc theo giờ</strong>
            <small>Khung giờ mặc định từ 07:30 đến 17:00.</small>
          </span>
        </label>
        <label class="task-form__check">
          <input
            :checked="form.constrain_child_dates"
            type="checkbox"
            :disabled="disabled"
            @change="set('constrain_child_dates', $event.target.checked)"
          />
          <span>
            <strong>Khóa thời gian việc con</strong>
            <small>Công việc con tạo sau không được vượt khoảng thời gian này.</small>
          </span>
        </label>
        <div class="proj-form__field">
          <label class="proj-form__label" for="task-form-start-time">Giờ bắt đầu</label>
          <input
            id="task-form-start-time"
            :value="form.start_time"
            type="time"
            class="proj-page__input"
            :disabled="disabled || !form.assign_by_time"
            @input="set('start_time', $event.target.value)"
          />
        </div>
        <div class="proj-form__field">
          <label class="proj-form__label" for="task-form-due-time">Giờ kết thúc</label>
          <input
            id="task-form-due-time"
            :value="form.due_time"
            type="time"
            class="proj-page__input"
            :disabled="disabled || !form.assign_by_time"
            @input="set('due_time', $event.target.value)"
          />
        </div>
      </div>
    </section>

    <section v-if="showSection('people')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--gold">
          <AppIcon name="users" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Người tham gia</h2>
      </header>

      <p class="proj-people__story">
        <span :class="assigneeStory ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ assigneeStory || 'Người thực hiện' }}
        </span>
        làm việc,
        <span :class="managerStory ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ managerStory || 'người giao việc' }}
        </span>
        giao,
        <span :class="watcherStory ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ watcherStory || 'người theo dõi' }}
        </span>
        theo dõi,
        <span :class="collaboratorStory ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ collaboratorStory || 'người phối hợp' }}
        </span>
        hỗ trợ.
      </p>

      <div class="proj-people">
        <article class="proj-people__card">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="userPlus" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Người thực hiện</h3>
              <p class="proj-people__hint">Ai được giao làm công việc này.</p>
            </div>
          </div>
          <ProjectUserPicker
            :model-value="form.assignee_id"
            :users="users"
            :disabled="disabled"
            search-label="Tìm người thực hiện"
            placeholder="Tìm tên, email hoặc phòng ban"
            remove-aria-label="Bỏ người thực hiện"
            @update:model-value="set('assignee_id', $event)"
          />
        </article>

        <article class="proj-people__card proj-people__card--gold">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="user" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Người giao việc</h3>
              <p class="proj-people__hint">Ai giao và theo dõi việc này.</p>
            </div>
          </div>
          <ProjectUserPicker
            :model-value="form.manager_id"
            :users="users"
            :disabled="disabled"
            search-label="Tìm người giao việc"
            placeholder="Tìm tên, email hoặc phòng ban"
            remove-aria-label="Bỏ người giao việc"
            @update:model-value="set('manager_id', $event)"
          />
        </article>

        <article class="proj-people__card proj-people__card--tertiary">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="eye" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Người theo dõi</h3>
              <p class="proj-people__hint">Ai được xem tiến độ, không phải người làm.</p>
            </div>
            <span class="proj-people__count">{{ form.watcher_ids.length }}</span>
          </div>
          <ProjectMemberPicker
            :model-value="form.watcher_ids"
            :users="users"
            :disabled="disabled"
            search-label="Tìm người theo dõi"
            placeholder="Tìm người theo dõi"
            remove-aria-label="Bỏ người theo dõi"
            tone="tertiary"
            @update:model-value="set('watcher_ids', $event)"
          />
        </article>

        <article class="proj-people__card proj-people__card--gold">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="users" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Người phối hợp</h3>
              <p class="proj-people__hint">Ai hỗ trợ người thực hiện.</p>
            </div>
            <span class="proj-people__count">{{ form.collaborator_ids.length }}</span>
          </div>
          <ProjectMemberPicker
            :model-value="form.collaborator_ids"
            :users="users"
            :disabled="disabled"
            search-label="Tìm người phối hợp"
            placeholder="Tìm người phối hợp"
            remove-aria-label="Bỏ người phối hợp"
            tone="gold"
            @update:model-value="set('collaborator_ids', $event)"
          />
        </article>
      </div>
    </section>

    <section v-if="showSection('settings')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--secondary">
          <AppIcon name="settings" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Loại, mức độ quan trọng và tiến độ</h2>
      </header>

      <div class="proj-form__grid">
        <div class="proj-form__field proj-form__field--wide">
          <span id="task-form-type-label" class="proj-form__label">{{ importanceTitle }}</span>
          <div class="proj-form__importance" role="radiogroup" aria-labelledby="task-form-type-label">
            <button
              v-for="opt in importanceRows"
              :key="opt.value"
              type="button"
              class="proj-form__importance-row"
              :class="[
                `proj-form__importance-row--${priorityColor(opt.value)}`,
                { 'proj-form__importance-row--on': form.priority === opt.value },
              ]"
              role="radio"
              :aria-checked="form.priority === opt.value ? 'true' : 'false'"
              :disabled="disabled"
              @click="set('priority', opt.value)"
            >
              <span class="proj-form__importance-dot" aria-hidden="true" />
              <span class="proj-form__importance-name">
                <template v-if="opt.code">{{ opt.code }} </template>{{ opt.label }}
              </span>
              <span class="proj-form__importance-desc">{{ opt.description }}</span>
              <span v-if="opt.weight" class="proj-form__importance-weight">Trọng số x{{ opt.weight }}</span>
            </button>
          </div>
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <span id="task-form-progress-label" class="proj-form__label">Cách tính tiến độ dự án</span>
          <OptionPicker
            :model-value="form.progress_type"
            :options="TASK_PROGRESS_METHOD_OPTIONS"
            :disabled="disabled"
            labelled-by="task-form-progress-label"
            placeholder="Chọn cách tính tiến độ"
            @update:model-value="set('progress_type', $event)"
          />
        </div>
      </div>
    </section>

    <section v-if="showSection('rules')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--tertiary">
          <AppIcon name="shield" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Cài đặt quyền bổ sung</h2>
        <span
          class="proj-form__section-note"
          :class="{ 'proj-form__section-note--on': enabledRulesCount > 0 }"
        >
          {{ enabledRulesCount }}/{{ RULE_DEFS.length }} đang bật
        </span>
      </header>

      <p class="proj-rules__story">
        Người thực hiện
        <span :class="form.hide_cross_tasks_from_assignees ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_cross_tasks_from_assignees ? 'không xem' : 'được xem' }}
        </span>
        việc chéo, người làm việc cha
        <span :class="form.hide_from_parent_assignees ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_from_parent_assignees ? 'không xem' : 'được xem' }}
        </span>
        việc này, người theo dõi việc cha
        <span :class="form.hide_from_parent_followers ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_from_parent_followers ? 'không xem' : 'được xem' }}
        </span>
        việc này, người theo dõi
        <span :class="form.hide_child_tasks_from_followers ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_child_tasks_from_followers ? 'không xem' : 'được xem' }}
        </span>
        việc con, người ở việc con
        <span :class="form.allow_child_people_view_parent ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.allow_child_people_view_parent ? 'được xem' : 'không xem' }}
        </span>
        việc cha.
      </p>

      <div class="proj-rules">
        <article
          v-for="rule in RULE_DEFS"
          :key="rule.key"
          class="proj-rules__card"
          :class="[
            `proj-rules__card--${rule.tone}`,
            {
              'proj-rules__card--on': Boolean(form[rule.key]),
              'proj-rules__card--disabled': disabled,
            },
          ]"
          @click="toggleRule(rule.key)"
        >
          <div class="proj-rules__head">
            <span class="proj-rules__icon">
              <AppIcon :name="rule.icon" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-rules__head-text">
              <h3 class="proj-rules__label">{{ rule.label }}</h3>
              <p :id="`task-form-rule-${rule.key}`" class="proj-rules__title">{{ rule.title }}</p>
            </div>
            <button
              type="button"
              class="proj-rules__switch"
              :class="{ 'proj-rules__switch--on': Boolean(form[rule.key]) }"
              role="switch"
              :aria-checked="form[rule.key] ? 'true' : 'false'"
              :aria-labelledby="`task-form-rule-${rule.key}`"
              :disabled="disabled"
              @click.stop="toggleRule(rule.key)"
            >
              <span class="proj-rules__switch-thumb" aria-hidden="true" />
            </button>
          </div>
        </article>
      </div>
    </section>

    <section v-if="showSection('policy')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="clipboardCheck" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Báo cáo và hoàn thành</h2>
      </header>

      <p class="proj-rules__story proj-rules__story--primary">
        Sau báo cáo hoàn thành, công việc
        <span :class="form.auto_complete_on_report ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.auto_complete_on_report ? 'tự chuyển' : 'không tự chuyển' }}
        </span>
        sang Hoàn thành. Thảo luận và file:
        <span class="proj-rules__story-val">{{ policyLabel(INTERACTION_OPTIONS, form.completed_interaction_policy) }}</span>.
        Mô tả khi báo cáo:
        <span class="proj-rules__story-val">{{ policyLabel(REQUIREMENT_OPTIONS, form.report_description_requirement) }}</span>.
        File khi báo cáo:
        <span class="proj-rules__story-val">{{ policyLabel(REQUIREMENT_OPTIONS, form.report_attachment_requirement) }}</span>.
      </p>

      <div class="task-form__policy">
        <article
          class="proj-rules__card proj-rules__card--primary task-form__policy-complete"
          :class="{
            'proj-rules__card--on': form.auto_complete_on_report,
            'proj-rules__card--disabled': disabled,
          }"
          @click="toggleRule('auto_complete_on_report')"
        >
          <div class="proj-rules__head">
            <span class="proj-rules__icon">
              <AppIcon name="check" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-rules__head-text">
              <h3 class="proj-rules__label">Hoàn thành công việc</h3>
              <p id="task-form-rule-auto-complete" class="proj-rules__title">
                Sau khi người thực hiện báo cáo hoàn thành, công việc sẽ chuyển trạng thái về Hoàn thành
              </p>
            </div>
            <button
              type="button"
              class="proj-rules__switch"
              :class="{ 'proj-rules__switch--on': form.auto_complete_on_report }"
              role="switch"
              :aria-checked="form.auto_complete_on_report ? 'true' : 'false'"
              aria-labelledby="task-form-rule-auto-complete"
              :disabled="disabled"
              @click.stop="toggleRule('auto_complete_on_report')"
            >
              <span class="proj-rules__switch-thumb" aria-hidden="true" />
            </button>
          </div>
        </article>

        <article
          v-for="policy in POLICY_DEFS"
          :key="policy.key"
          class="task-form__policy-card"
          :class="[`task-form__policy-card--${policy.tone}`, { 'task-form__policy-card--disabled': disabled }]"
        >
          <div class="task-form__policy-head">
            <span class="task-form__policy-icon">
              <AppIcon :name="policy.icon" :size="16" :stroke-width="1.75" />
            </span>
            <h3 class="task-form__policy-label">{{ policy.label }}</h3>
          </div>
          <div
            class="task-form__choice-list"
            role="radiogroup"
            :aria-label="policy.label"
          >
            <button
              v-for="option in policy.options"
              :key="`${policy.key}-${option.value}`"
              type="button"
              class="task-form__choice"
              :class="{ 'task-form__choice--on': form[policy.key] === option.value }"
              role="radio"
              :aria-checked="form[policy.key] === option.value ? 'true' : 'false'"
              :disabled="disabled"
              @click="set(policy.key, option.value)"
            >
              <span class="task-form__choice-dot" aria-hidden="true" />
              <span class="task-form__choice-name">{{ option.label }}</span>
            </button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.proj-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.proj-form__section {
  position: relative;
  z-index: 0;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-form__section:focus-within {
  z-index: 4;
}

.proj-form__section-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.proj-form__section-note {
  margin-left: auto;
  padding: 0.125rem 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.proj-form__section-note--on {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.proj-form__section-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
}

.proj-form__section-icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.proj-form__section-icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.proj-form__section-icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold-600);
}

.proj-form__section-icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.proj-form__section-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
}

.proj-form__grid {
  display: grid;
  min-width: 0;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
  align-content: start;
  isolation: isolate;
}

.proj-form__grid--2col {
  grid-template-columns: repeat(2, 1fr);
}

.proj-form__grid--4col {
  grid-template-columns: repeat(4, 1fr);
}

.proj-form__field {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.proj-form__field:focus-within {
  z-index: 3;
}

.proj-form__field--wide {
  grid-column: 1 / -1;
}

.proj-form__field--span2 {
  grid-column: span 2;
}

.proj-form__label {
  display: block;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-form__required {
  color: var(--color-primary);
}

.proj-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

.proj-form__static {
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 0.875rem;
}

.proj-form__static--duration {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  color: var(--color-primary);
  font-weight: 600;
}

.task-form__range {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-size: 0.8125rem;
}

.task-form__time-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  margin-top: var(--space-3);
}

.task-form__check {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.task-form__check span {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.task-form__check strong {
  color: var(--color-text);
  font-size: 0.8125rem;
}

.task-form__check small {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.4;
}

.proj-people__story {
  position: relative;
  margin: 0 0 var(--space-4);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-gold-surface);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 500;
  line-height: 1.55;
  overflow-wrap: anywhere;
}

.proj-people__story::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-gold-600);
}

.proj-people__story-val {
  color: var(--color-gold-700);
  font-weight: 700;
}

.proj-people__story-gap {
  color: var(--color-text-muted);
  font-style: italic;
  font-weight: 500;
}

.proj-people {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-people__card {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);

  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-people__card:focus-within {
  z-index: 3;
}

.proj-people__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--step-color);
}

.proj-people__card--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.proj-people__card--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.proj-people__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.proj-people__head-text {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
  flex: 1;
}

.proj-people__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
  background: var(--step-surface);
  color: var(--step-color);
}

.proj-people__title {
  margin: 0;
  color: var(--step-color);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-people__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.proj-people__count {
  flex-shrink: 0;
  min-width: 1.5rem;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
  background: var(--step-surface);
  color: var(--step-color);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.4;
  text-align: center;
}

.proj-form__importance {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-form__importance-row {
  --row-color: var(--color-umber);
  --row-surface: var(--color-umber-surface);

  position: relative;
  display: grid;
  grid-template-columns: auto minmax(9rem, auto) 1fr auto;
  align-items: center;
  gap: var(--space-3);
  padding: 0.625rem var(--space-3) 0.625rem calc(var(--space-3) + 3px);
  border: none;
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border), inset 3px 0 0 var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.proj-form__importance-row:last-child {
  box-shadow: inset 3px 0 0 var(--color-border);
}

.proj-form__importance-row:hover:not(:disabled):not(.proj-form__importance-row--on) {
  background: var(--color-surface-muted);
}

.proj-form__importance-row--tertiary {
  --row-color: var(--color-tertiary);
  --row-surface: var(--color-tertiary-surface);
}

.proj-form__importance-row--gold {
  --row-color: var(--color-gold-600);
  --row-surface: var(--color-gold-surface);
}

.proj-form__importance-row--primary {
  --row-color: var(--color-primary);
  --row-surface: var(--color-primary-surface);
}

.proj-form__importance-row--secondary {
  --row-color: var(--color-secondary);
  --row-surface: var(--color-secondary-surface);
}

.proj-form__importance-row--on {
  background: var(--row-surface);
  box-shadow: 0 1px 0 var(--color-border), inset 3px 0 0 var(--row-color);
}

.proj-form__importance-row--on:last-child {
  box-shadow: inset 3px 0 0 var(--row-color);
}

.proj-form__importance-row:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-form__importance-dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--row-color);
}

.proj-form__importance-name {
  font-size: 0.8125rem;
  font-weight: 700;
}

.proj-form__importance-row--on .proj-form__importance-name {
  color: var(--row-color);
}

.proj-form__importance-weight {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.proj-form__importance-desc {
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.proj-rules__story {
  position: relative;
  margin: 0 0 var(--space-4);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-tertiary-surface);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 500;
  line-height: 1.55;
  overflow-wrap: anywhere;
}

.proj-rules__story::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-tertiary);
}

.proj-rules__story--primary {
  background: var(--color-primary-surface);
}

.proj-rules__story--primary::before {
  background: var(--color-primary);
}

.proj-rules__story-val {
  color: var(--color-tertiary-700);
  font-weight: 700;
}

.proj-rules__story--primary .proj-rules__story-val {
  color: var(--color-primary);
}

.proj-rules__story-gap {
  color: var(--color-text-muted);
  font-style: italic;
  font-weight: 500;
}

.proj-rules {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-rules__card {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);

  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.proj-rules__card:focus-within {
  z-index: 3;
}

.proj-rules__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--step-color);
}

.proj-rules__card--secondary {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);
}

.proj-rules__card--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.proj-rules__card--primary {
  --step-color: var(--color-primary);
  --step-surface: var(--color-primary-surface);
}

.proj-rules__card--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.proj-rules__card--on {
  background: color-mix(in srgb, var(--step-surface) 55%, var(--color-surface));
}

.proj-rules__card:hover:not(.proj-rules__card--disabled):not(.proj-rules__card--on) {
  background: color-mix(in srgb, var(--step-surface) 22%, var(--color-surface));
}

.proj-rules__card--disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.proj-rules__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.proj-rules__head-text {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
  flex: 1;
}

.proj-rules__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
  background: var(--step-surface);
  color: var(--step-color);
}

.proj-rules__label {
  margin: 0;
  color: var(--step-color);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-rules__title {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.45;
}

.proj-rules__switch {
  position: relative;
  flex-shrink: 0;
  align-self: start;
  margin-top: 0.125rem;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.proj-rules__switch--on {
  background: var(--step-color);
}

.proj-rules__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.proj-rules__switch:focus-visible {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 2px;
}

.proj-rules__switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-rules__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.proj-rules__switch--on .proj-rules__switch-thumb {
  transform: translateX(1.25rem);
}

.task-form__policy {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.task-form__policy-complete {
  grid-column: 1 / -1;
}

.task-form__policy-card {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);

  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.task-form__policy-card:focus-within {
  z-index: 3;
}

.task-form__policy-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--step-color);
}

.task-form__policy-card--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.task-form__policy-card--secondary {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);
}

.task-form__policy-card--gold {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);
}

.task-form__policy-card--disabled {
  opacity: 0.7;
}

.task-form__policy-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.task-form__policy-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-md);
  background: var(--step-surface);
  color: var(--step-color);
}

.task-form__policy-label {
  margin: 0;
  color: var(--step-color);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  line-height: 1.35;
  text-transform: uppercase;
}

.task-form__choice-list {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.task-form__choice {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: 0.625rem var(--space-3) 0.625rem calc(var(--space-3) + 3px);
  border: none;
  background: var(--color-surface);
  box-shadow: 0 1px 0 var(--color-border), inset 3px 0 0 var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.task-form__choice:last-child {
  box-shadow: inset 3px 0 0 var(--color-border);
}

.task-form__choice:hover:not(:disabled):not(.task-form__choice--on) {
  background: var(--color-surface-muted);
}

.task-form__choice--on {
  background: var(--step-surface);
  box-shadow: 0 1px 0 var(--color-border), inset 3px 0 0 var(--step-color);
}

.task-form__choice--on:last-child {
  box-shadow: inset 3px 0 0 var(--step-color);
}

.task-form__choice:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.task-form__choice:focus-visible {
  z-index: 1;
  outline: 2px solid var(--color-primary-200);
  outline-offset: -2px;
}

.task-form__choice-dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.task-form__choice--on .task-form__choice-dot {
  background: var(--step-color);
}

.task-form__choice-name {
  min-width: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
}

.task-form__choice--on .task-form__choice-name {
  color: var(--step-color);
  font-weight: 700;
}

.proj-form input[type='checkbox'],
.proj-form input[type='radio'] {
  flex-shrink: 0;
  margin-top: 0.12rem;
  accent-color: var(--color-primary);
}

.proj-page__input {
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

.proj-page__input:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.proj-page__input--error {
  box-shadow: 0 0 0 1px var(--color-danger);
}

.proj-page__input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.proj-page__textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  resize: vertical;
}

.proj-page__textarea:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

@media (max-width: 1279px) {
  .proj-form__grid,
  .proj-form__grid--2col,
  .proj-form__grid--4col {
    grid-template-columns: repeat(2, 1fr);
  }

  .task-form__policy {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .proj-form__section {
    padding: var(--space-3);
  }

  .proj-form__grid,
  .proj-form__grid--2col,
  .proj-form__grid--4col,
  .proj-people,
  .proj-rules,
  .task-form__time-grid,
  .task-form__policy {
    grid-template-columns: 1fr;
  }

  .proj-form__field--span2 {
    grid-column: auto;
  }

  .proj-form__importance-row {
    grid-template-columns: auto 1fr;
  }

  .proj-form__importance-desc,
  .proj-form__importance-weight {
    grid-column: 2 / -1;
  }
}
</style>
