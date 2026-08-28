<script setup>
//
// Toàn bộ field của form Dự án (dùng chung cho 2 trang ProjectCreate.vue /
// ProjectEdit.vue). Khi isCreate, trang cha truyền `step` để hiện đúng
// nhóm field của bước wizard. Component "câm": nhận form + errors qua
// props, phát 'update:field' khi người dùng sửa.
//
import { computed, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDatePicker from '@/components/AppDatePicker.vue';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectScopePicker from './ProjectScopePicker.vue';
import ProjectLabelPicker from './ProjectLabelPicker.vue';
import ProjectDepartmentPicker from './ProjectDepartmentPicker.vue';
import ProjectUserPicker from './ProjectUserPicker.vue';
import ProjectTypeSelect from './ProjectTypeSelect.vue';

const RULE_DEFS = [
  {
    key: 'shift_task_dates_with_project',
    icon: 'calendar',
    tone: 'gold',
    label: 'Tịnh tiến thời gian',
    title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo',
    example:
      'Khi bật cài đặt này thì, ví dụ: Thời gian thực hiện dự án là 10/03/2020 - 20/03/2020, công việc X thuộc dự án A có thời gian thực hiện là 11/03/2020 - 15/03/2020. Khi dự án A được tịnh tiến 3 ngày, tức thời gian thực hiện là 13/03/2020 – 20/03/2020, thì thời gian công việc X cũng tịnh tiến thêm 3 ngày, tức là 14/03/2020 - 15/03/2020.',
    note: 'Chỉ áp dụng với công việc đang thực hiện, chờ thực hiện',
  },
  {
    key: 'hide_cross_tasks_from_assignees',
    icon: 'eyeOff',
    tone: 'secondary',
    label: 'Ẩn xem chéo',
    title: 'Không cho phép người thực hiện công việc xem chéo các công việc khác',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người thực hiện công việc B sẽ không được xem công việc C nếu người đó không phải là người thực hiện công việc C',
  },
  {
    key: 'hide_child_tasks_from_followers',
    icon: 'eye',
    tone: 'tertiary',
    label: 'Ẩn việc con',
    title: 'Không cho phép người theo dõi xem được các công việc con',
    example:
      'Khi bật cài đặt này thì, ví dụ: Dự án A gồm 2 công việc B và C, người theo dõi dự án A sẽ không được xem công việc B và C nếu người đó không phải là người theo dõi công việc B và C',
  },
  {
    key: 'constrain_task_dates_to_project',
    icon: 'clock',
    tone: 'primary',
    label: 'Ràng buộc thời gian',
    title: 'Thời gian dự kiến thực hiện công việc phải nằm trong khoảng thời gian của dự án',
    example:
      'Khi bật cài đặt này, thời gian dự kiến thực hiện công việc sẽ phải nằm trong khoảng thời gian của dự án.',
  },
];

const STEP_SECTIONS = {
  1: ['general', 'schedule'],
  2: ['org'],
  3: ['people'],
  4: ['rules'],
};

// Mức độ quan trọng tăng dần → tô màu tăng dần theo 5 màu chủ đạo của theme
// (trung tính → xanh dương → xanh ngọc → vàng đồng → đỏ chính) thay vì badge
// bo tròn nền màu (cấm theo quy tắc UI). Chỉ dùng 1 chấm màu + viền nhấn trái.
const IMPORTANCE_COLOR = {
  support: 'umber',
  assist: 'tertiary',
  important: 'secondary',
  high_priority: 'gold',
  strategic: 'primary',
};

function importanceColor(value) {
  return IMPORTANCE_COLOR[value] || 'umber';
}

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  options: { type: Object, required: true },
  departments: { type: Array, required: true },
  assignableUsers: { type: Array, required: true },
  allLabels: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  isCreate: { type: Boolean, default: false },
  step: { type: Number, default: 0 },
  durationDays: { type: [Number, null], default: null },
  progressMethodDescription: { type: String, default: '' },
  avatarPreviewUrl: { type: String, default: '' },
  ownerDepartment: { type: Object, default: null },
  // Super admin / giám đốc điều hành: chọn thẳng phòng ban giao thay vì bị
  // khoá theo phòng ban của người tạo (mục C).
  canChooseOwnerDepartment: { type: Boolean, default: false },
});

const emit = defineEmits(['update:field', 'label-created', 'type-created', 'avatar-selected', 'avatar-removed']);

function set(field, value) {
  emit('update:field', field, value);
}

const enabledRulesCount = computed(
  () => RULE_DEFS.filter((rule) => Boolean(props.form[rule.key])).length,
);

function fieldError(field) {
  const e = props.errors?.[field];
  return Array.isArray(e) ? e[0] : e;
}

function showSection(key) {
  if (!props.isCreate || !props.step) return true;
  return (STEP_SECTIONS[props.step] || []).includes(key);
}

function onAvatarChange(event) {
  const file = event.target.files?.[0] || null;
  if (file) emit('avatar-selected', file);
}

function onAvatarDrop(event) {
  const file = event.dataTransfer?.files?.[0] || null;
  if (file) emit('avatar-selected', file);
}

function onAvatarRemove(event) {
  event.preventDefault();
  event.stopPropagation();
  if (props.disabled) return;
  emit('avatar-removed');
}

const executingDepartmentIds = computed(() =>
  Array.isArray(props.form.executing_department_ids) ? props.form.executing_department_ids : [],
);

const leadDepartmentIds = computed(() =>
  props.form.lead_department_id ? [props.form.lead_department_id] : [],
);

const ownerDepartmentIds = computed(() =>
  props.form.owner_department_id ? [props.form.owner_department_id] : [],
);

function onOwnerDepartmentIds(ids) {
  set('owner_department_id', ids[0] || '');
}

function onLeadChange(userId) {
  set('lead_user_id', userId || '');
  const user = props.assignableUsers.find((u) => String(u.id) === String(userId));
  if (user?.department?.id) {
    set('lead_department_id', user.department.id);
  }
}

function onLeadDepartmentIds(ids) {
  set('lead_department_id', ids[0] || '');
}

function onExecutingIds(ids) {
  set('executing_department_ids', ids);
}

const resolvedOwnerDepartmentId = computed(() => {
  if (props.canChooseOwnerDepartment) {
    return props.form.owner_department_id || '';
  }
  return props.ownerDepartment?.id || '';
});

watch(
  resolvedOwnerDepartmentId,
  (nextOwner, prevOwner) => {
    if (!props.isCreate || !nextOwner) return;
    const current = (props.form.executing_department_ids || []).map((id) => String(id));
    const onlyPreviousOwner =
      prevOwner && current.length === 1 && current[0] === String(prevOwner);
    if (current.length === 0 || onlyPreviousOwner) {
      set('executing_department_ids', [nextOwner]);
    }
  },
  { immediate: true },
);

function departmentNameById(id) {
  if (!id) return '';
  return props.departments.find((d) => String(d.id) === String(id))?.name || '';
}

const ownerStoryName = computed(() => {
  if (props.canChooseOwnerDepartment) {
    return departmentNameById(props.form.owner_department_id);
  }
  return props.ownerDepartment?.name || '';
});

const executingStoryName = computed(() => {
  const ids = new Set(executingDepartmentIds.value.map((id) => String(id)));
  return props.departments
    .filter((d) => ids.has(String(d.id)))
    .map((d) => d.name)
    .filter(Boolean)
    .join(', ');
});

const scopeStoryName = computed(() => {
  const row = Array.isArray(props.form.scopes) ? props.form.scopes[0] : null;
  if (!row?.scope_type) return '';
  if (row.scope_type === 'department') {
    return (
      departmentNameById(row.department_id)
      || props.options.scope_type?.find((o) => o.value === 'department')?.label
      || ''
    );
  }
  return props.options.scope_type?.find((o) => o.value === row.scope_type)?.label || '';
});

const leadStoryName = computed(() => {
  if (!props.form.lead_user_id) return '';
  return props.assignableUsers.find((u) => String(u.id) === String(props.form.lead_user_id))?.name || '';
});

function formatNamedList(items, extraSuffix) {
  const names = items.map((item) => item.name).filter(Boolean);
  if (!names.length) return '';
  if (names.length <= 2) return names.join(', ');
  return `${names[0]}, ${names[1]} và ${names.length - 2} ${extraSuffix}`;
}

const memberStoryName = computed(() => {
  const ids = new Set((props.form.member_ids || []).map((id) => String(id)));
  return formatNamedList(
    props.assignableUsers.filter((u) => ids.has(String(u.id))),
    'người nữa',
  );
});

const followerStoryName = computed(() => {
  const ids = new Set((props.form.follower_ids || []).map((id) => String(id)));
  return formatNamedList(
    props.assignableUsers.filter((u) => ids.has(String(u.id))),
    'người nữa',
  );
});

const labelStoryName = computed(() => {
  const ids = new Set((props.form.label_ids || []).map((id) => String(id)));
  return formatNamedList(
    props.allLabels.filter((l) => ids.has(String(l.id))),
    'nhãn nữa',
  );
});

const executingMemberCandidates = computed(() => {
  const deptIds = new Set(executingDepartmentIds.value.map((id) => String(id)));
  if (!deptIds.size) return [];
  const selected = new Set((props.form.member_ids || []).map((id) => String(id)));
  return props.assignableUsers.filter(
    (u) => u.department?.id && deptIds.has(String(u.department.id)) && !selected.has(String(u.id)),
  );
});

function onMemberIds(ids) {
  set('member_ids', ids);
  const memberSet = new Set(ids.map((id) => String(id)));
  const followers = (props.form.follower_ids || []).filter((id) => !memberSet.has(String(id)));
  if (followers.length !== (props.form.follower_ids || []).length) {
    set('follower_ids', followers);
  }
}

function onFollowerIds(ids) {
  set('follower_ids', ids);
  const followerSet = new Set(ids.map((id) => String(id)));
  const members = (props.form.member_ids || []).filter((id) => !followerSet.has(String(id)));
  if (members.length !== (props.form.member_ids || []).length) {
    set('member_ids', members);
  }
}

function addExecutingMembers() {
  if (props.disabled || !executingMemberCandidates.value.length) return;
  onMemberIds([...props.form.member_ids, ...executingMemberCandidates.value.map((u) => u.id)]);
}

function toggleRule(key) {
  if (props.disabled) return;
  set(key, !props.form[key]);
}
</script>

<template>
  <div class="proj-form">
    <section v-if="showSection('general')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="fileText" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thông tin chung</h2>
        <span v-if="!isCreate" class="proj-form__section-note">Mã dự án: {{ form.code }}</span>
        <span v-else class="proj-form__section-note">Mã dự án sẽ tự sinh khi lưu</span>
      </header>

      <div class="proj-form__general">
        <div class="proj-form__avatar-wrap">
          <label
            class="proj-form__avatar-drop"
            :class="{ 'proj-form__avatar-drop--disabled': disabled }"
            @dragover.prevent
            @drop.prevent="onAvatarDrop"
          >
            <img v-if="avatarPreviewUrl" :src="avatarPreviewUrl" alt="" class="proj-form__avatar-img" />
            <span v-else class="proj-form__avatar-placeholder">
              <AppIcon name="camera" :size="18" :stroke-width="1.6" />
              <span>Ảnh đại diện</span>
            </span>
            <input
              type="file"
              accept="image/png,image/jpeg,image/webp"
              class="proj-form__avatar-input"
              :disabled="disabled"
              @change="onAvatarChange"
            />
          </label>
          <button
            v-if="avatarPreviewUrl"
            type="button"
            class="proj-form__avatar-remove"
            aria-label="Gỡ ảnh đại diện"
            :disabled="disabled"
            @click="onAvatarRemove"
          >
            <AppIcon name="close" :size="12" :stroke-width="2.5" />
          </button>
        </div>

        <div class="proj-form__grid">
          <div class="proj-form__field">
            <label class="proj-form__label" for="proj-form-type">
              Loại dự án <span class="proj-form__required">*</span>
            </label>
            <ProjectTypeSelect
              input-id="proj-form-type"
              :model-value="form.type"
              :options="options.type"
              :disabled="disabled"
              @update:model-value="set('type', $event)"
              @created="emit('type-created', $event)"
            />
          </div>

          <div class="proj-form__field proj-form__field--span2">
            <label class="proj-form__label" for="proj-form-name">
              Tên dự án <span class="proj-form__required">*</span>
            </label>
            <input
              id="proj-form-name"
              :value="form.name"
              type="text"
              class="proj-page__input"
              :class="{ 'proj-page__input--error': fieldError('name') }"
              placeholder="Ví dụ: Xây dựng chương trình đào tạo học kỳ 2"
              maxlength="255"
              :disabled="disabled"
              @input="set('name', $event.target.value)"
            />
            <span v-if="fieldError('name')" class="proj-form__error">{{ fieldError('name') }}</span>
          </div>

          <div class="proj-form__field proj-form__field--wide">
            <label class="proj-form__label" for="proj-form-desc">Mô tả</label>
            <textarea
              id="proj-form-desc"
              :value="form.description"
              class="proj-page__textarea"
              rows="2"
              maxlength="5000"
              placeholder="Mô tả ngắn gọn mục tiêu, phạm vi công việc của dự án…"
              :disabled="disabled"
              @input="set('description', $event.target.value)"
            />
          </div>
        </div>
      </div>
    </section>

    <section v-if="showSection('schedule')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="calendar" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thời gian &amp; mức độ</h2>
      </header>

      <div class="proj-form__grid proj-form__grid--4col">
        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-start">Ngày bắt đầu</label>
          <AppDatePicker
            id="proj-form-start"
            :model-value="form.start_date"
            :error="Boolean(fieldError('start_date'))"
            :disabled="disabled"
            @update:model-value="set('start_date', $event)"
          />
          <span v-if="fieldError('start_date')" class="proj-form__error">{{ fieldError('start_date') }}</span>
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-end">Ngày kết thúc</label>
          <AppDatePicker
            id="proj-form-end"
            :model-value="form.end_date"
            :min="form.start_date"
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
          <label class="proj-form__label" for="proj-form-status">Trạng thái dự án</label>
          <select
            id="proj-form-status"
            :value="form.status"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('status', $event.target.value)"
          >
            <option v-for="opt in options.status" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </div>

      <div class="proj-form__grid">
        <div v-if="!isCreate" class="proj-form__field">
          <label class="proj-form__label" for="proj-form-progress">Phương pháp tính tiến độ</label>
          <select
            id="proj-form-progress"
            :value="form.progress_method"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('progress_method', $event.target.value)"
          >
            <option v-for="opt in options.progress_method" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
          <span v-if="progressMethodDescription" class="proj-form__caption">{{ progressMethodDescription }}</span>
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <span class="proj-form__label">Mức độ quan trọng</span>
          <div class="proj-form__importance" role="radiogroup" aria-label="Mức độ quan trọng">
            <button
              v-for="opt in options.importance"
              :key="opt.value"
              type="button"
              class="proj-form__importance-row"
              :class="[
                `proj-form__importance-row--${importanceColor(opt.value)}`,
                { 'proj-form__importance-row--on': form.importance === opt.value },
              ]"
              role="radio"
              :aria-checked="form.importance === opt.value ? 'true' : 'false'"
              :disabled="disabled"
              @click="set('importance', opt.value)"
            >
              <span class="proj-form__importance-dot" aria-hidden="true" />
              <span class="proj-form__importance-name">{{ opt.label }}</span>
              <span class="proj-form__importance-desc">{{ opt.description }}</span>
              <span class="proj-form__importance-weight">Trọng số x{{ opt.weight ?? '' }}</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="showSection('org')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--gold">
          <AppIcon name="building" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Tổ chức</h2>
      </header>

      <p class="proj-org__story">
        <span :class="ownerStoryName ? 'proj-org__story-val' : 'proj-org__story-gap'">
          {{ ownerStoryName || 'Phòng ban giao' }}
        </span>
        giao cho
        <span :class="executingStoryName ? 'proj-org__story-val' : 'proj-org__story-gap'">
          {{ executingStoryName || 'phòng ban thực hiện' }}
        </span>
        làm, áp dụng
        <span :class="scopeStoryName ? 'proj-org__story-val' : 'proj-org__story-gap'">
          {{ scopeStoryName || 'phạm vi' }}
        </span>, do
        <span :class="leadStoryName ? 'proj-org__story-val' : 'proj-org__story-gap'">
          {{ leadStoryName || 'người phụ trách' }}
        </span>
        theo dõi.
      </p>

      <ol class="proj-org" aria-label="Luồng tổ chức dự án">
        <li class="proj-org__item proj-org__item--gold">
          <div class="proj-org__track" aria-hidden="true">
            <span class="proj-org__num">1</span>
            <span class="proj-org__line" />
          </div>
          <div class="proj-org__card">
            <div class="proj-org__head">
              <span class="proj-org__icon">
                <AppIcon name="building" :size="16" :stroke-width="1.75" />
              </span>
              <div class="proj-org__head-text">
                <h3 class="proj-org__title">Phòng ban giao</h3>
                <p class="proj-org__hint">Ai giao dự án này.</p>
              </div>
            </div>
            <ProjectDepartmentPicker
              v-if="canChooseOwnerDepartment"
              :model-value="ownerDepartmentIds"
              :departments="departments"
              :disabled="disabled"
              :multiple="false"
              search-label="Tìm phòng ban giao"
              placeholder="Tìm phòng ban giao"
              @update:model-value="onOwnerDepartmentIds"
            />
            <div v-else-if="ownerDepartment" class="proj-org__locked">
              <span class="proj-org__locked-dot" aria-hidden="true" />
              <span class="proj-org__locked-name">{{ ownerDepartment.name || '—' }}</span>
              <span v-if="ownerDepartment.code" class="proj-org__locked-meta">{{ ownerDepartment.code }}</span>
              <AppIcon name="lock" :size="14" class="proj-org__locked-icon" />
            </div>
          </div>
        </li>

        <li class="proj-org__item proj-org__item--secondary">
          <div class="proj-org__track" aria-hidden="true">
            <span class="proj-org__num">2</span>
            <span class="proj-org__line" />
          </div>
          <div class="proj-org__card">
            <div class="proj-org__head">
              <span class="proj-org__icon">
                <AppIcon name="layers" :size="16" :stroke-width="1.75" />
              </span>
              <div class="proj-org__head-text">
                <h3 class="proj-org__title">Phòng ban thực hiện</h3>
                <p class="proj-org__hint">Ai được giao làm — có thể chọn nhiều phòng.</p>
              </div>
            </div>
            <ProjectDepartmentPicker
              :model-value="executingDepartmentIds"
              :departments="departments"
              :disabled="disabled"
              search-label="Tìm phòng ban thực hiện"
              placeholder="Tìm phòng ban thực hiện"
              @update:model-value="onExecutingIds"
            />
          </div>
        </li>

        <li class="proj-org__item proj-org__item--tertiary">
          <div class="proj-org__track" aria-hidden="true">
            <span class="proj-org__num">3</span>
            <span class="proj-org__line" />
          </div>
          <div class="proj-org__card">
            <div class="proj-org__head">
              <span class="proj-org__icon">
                <AppIcon name="globe" :size="16" :stroke-width="1.75" />
              </span>
              <div class="proj-org__head-text">
                <h3 class="proj-org__title">Phạm vi</h3>
                <p class="proj-org__hint">Dự án áp dụng ở đâu.</p>
              </div>
            </div>
            <ProjectScopePicker
              :model-value="form.scopes"
              :scope-type-options="options.scope_type"
              :departments="departments"
              :disabled="disabled"
              @update:model-value="set('scopes', $event)"
            />
            <span v-if="fieldError('scopes')" class="proj-form__error">{{ fieldError('scopes') }}</span>
            <span v-if="fieldError('scopes.0.department_id')" class="proj-form__error">{{ fieldError('scopes.0.department_id') }}</span>
          </div>
        </li>

        <li class="proj-org__item proj-org__item--primary">
          <div class="proj-org__track" aria-hidden="true">
            <span class="proj-org__num">4</span>
          </div>
          <div class="proj-org__card">
            <div class="proj-org__head">
              <span class="proj-org__icon">
                <AppIcon name="user" :size="16" :stroke-width="1.75" />
              </span>
              <div class="proj-org__head-text">
                <h3 class="proj-org__title">Phụ trách</h3>
                <p class="proj-org__hint">Ai theo dõi tiến độ.</p>
              </div>
            </div>
            <div class="proj-org__fields">
              <div class="proj-form__field">
                <span class="proj-form__label">Người phụ trách</span>
                <ProjectUserPicker
                  :model-value="form.lead_user_id"
                  :users="assignableUsers"
                  :disabled="disabled"
                  search-label="Tìm người phụ trách"
                  placeholder="Tìm tên hoặc email"
                  :preferred-department-ids="executingDepartmentIds"
                  @update:model-value="onLeadChange"
                />
              </div>
              <div class="proj-form__field">
                <span class="proj-form__label">Phòng ban phụ trách</span>
                <ProjectDepartmentPicker
                  :model-value="leadDepartmentIds"
                  :departments="departments"
                  :disabled="disabled"
                  :multiple="false"
                  search-label="Tìm phòng ban phụ trách"
                  placeholder="Tìm phòng ban phụ trách"
                  @update:model-value="onLeadDepartmentIds"
                />
              </div>
            </div>
          </div>
        </li>
      </ol>
    </section>

    <section v-if="showSection('people')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--secondary">
          <AppIcon name="users" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thành viên</h2>
      </header>

      <p class="proj-people__story">
        <span :class="memberStoryName ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ memberStoryName || 'Người thực hiện' }}
        </span>
        làm dự án,
        <span :class="followerStoryName ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ followerStoryName || 'người theo dõi' }}
        </span>
        theo dõi, gắn
        <span :class="labelStoryName ? 'proj-people__story-val' : 'proj-people__story-gap'">
          {{ labelStoryName || 'nhãn' }}
        </span>.
      </p>

      <div class="proj-people">
        <article class="proj-people__card proj-people__card--secondary">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="userPlus" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Người thực hiện</h3>
              <p class="proj-people__hint">Ai được giao làm công việc trong dự án.</p>
            </div>
            <span class="proj-people__count">{{ form.member_ids.length }}</span>
          </div>
          <button
            v-if="executingMemberCandidates.length"
            type="button"
            class="proj-people__quick"
            :disabled="disabled"
            @click="addExecutingMembers"
          >
            <AppIcon name="plus" :size="14" :stroke-width="2" />
            Thêm {{ executingMemberCandidates.length }} người từ phòng thực hiện
          </button>
          <ProjectMemberPicker
            :model-value="form.member_ids"
            :users="assignableUsers"
            :disabled="disabled"
            tone="secondary"
            search-label="Tìm người thực hiện"
            placeholder="Tìm tên, email hoặc phòng ban"
            remove-aria-label="Bỏ người thực hiện này"
            :preferred-department-ids="executingDepartmentIds"
            @update:model-value="onMemberIds"
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
            <span class="proj-people__count">{{ form.follower_ids.length }}</span>
          </div>
          <ProjectMemberPicker
            :model-value="form.follower_ids"
            :users="assignableUsers"
            :disabled="disabled"
            tone="tertiary"
            search-label="Tìm người theo dõi"
            placeholder="Tìm tên, email hoặc phòng ban"
            remove-aria-label="Bỏ người theo dõi này"
            :preferred-department-ids="executingDepartmentIds"
            @update:model-value="onFollowerIds"
          />
        </article>

        <article class="proj-people__card proj-people__card--gold proj-people__card--labels">
          <div class="proj-people__head">
            <span class="proj-people__icon">
              <AppIcon name="bookmark" :size="16" :stroke-width="1.75" />
            </span>
            <div class="proj-people__head-text">
              <h3 class="proj-people__title">Nhãn dự án</h3>
              <p class="proj-people__hint">Không bắt buộc.</p>
            </div>
            <span class="proj-people__count">{{ form.label_ids.length }}</span>
          </div>
          <ProjectLabelPicker
            :model-value="form.label_ids"
            :labels="allLabels"
            :disabled="disabled"
            placeholder="Tìm hoặc tạo nhãn…"
            @update:model-value="set('label_ids', $event)"
            @created="emit('label-created', $event)"
          />
        </article>
      </div>
    </section>

    <section v-if="showSection('rules')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--tertiary">
          <AppIcon name="shield" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Cài đặt quyền bổ sung</h2>
        <span class="proj-form__section-note">{{ enabledRulesCount }}/{{ RULE_DEFS.length }} đang bật</span>
      </header>

      <p class="proj-rules__story">
        Công việc
        <span :class="form.shift_task_dates_with_project ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.shift_task_dates_with_project ? 'sẽ tịnh tiến theo' : 'không tịnh tiến theo' }}
        </span>
        thời gian dự án, người thực hiện
        <span :class="form.hide_cross_tasks_from_assignees ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_cross_tasks_from_assignees ? 'không xem chéo' : 'được xem chéo' }}
        </span>
        công việc khác, người theo dõi
        <span :class="form.hide_child_tasks_from_followers ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.hide_child_tasks_from_followers ? 'không xem' : 'được xem' }}
        </span>
        công việc con, thời gian công việc
        <span :class="form.constrain_task_dates_to_project ? 'proj-rules__story-val' : 'proj-rules__story-gap'">
          {{ form.constrain_task_dates_to_project ? 'phải nằm trong' : 'không bị ràng buộc bởi' }}
        </span>
        khoảng thời gian dự án.
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
              <p :id="`proj-form-rule-${rule.key}`" class="proj-rules__title">{{ rule.title }}</p>
            </div>
            <button
              type="button"
              class="proj-rules__switch"
              :class="{ 'proj-rules__switch--on': Boolean(form[rule.key]) }"
              role="switch"
              :aria-checked="form[rule.key] ? 'true' : 'false'"
              :aria-labelledby="`proj-form-rule-${rule.key}`"
              :disabled="disabled"
              @click.stop="toggleRule(rule.key)"
            >
              <span class="proj-rules__switch-thumb" aria-hidden="true" />
            </button>
          </div>
          <p v-if="rule.note" class="proj-rules__note">{{ rule.note }}</p>
          <p class="proj-rules__example">{{ rule.example }}</p>
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
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-form__general {
  display: flex;
  align-items: flex-start;
  gap: var(--space-4);
}

.proj-form__avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.proj-form__avatar-drop {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 5.5rem;
  height: 5.5rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  transition: box-shadow 0.12s ease;
}

.proj-form__avatar-remove {
  position: absolute;
  top: -0.375rem;
  right: -0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  padding: 0;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: 0 0 0 1px var(--color-border), var(--shadow-sm);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background-color 0.12s ease, color 0.12s ease;
}

.proj-form__avatar-remove:hover:not(:disabled) {
  background: var(--color-danger);
  color: #fff;
}

.proj-form__avatar-remove:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-form__avatar-drop:hover:not(.proj-form__avatar-drop--disabled) {
  box-shadow: inset 0 0 0 1px var(--color-primary-300);
}

.proj-form__avatar-drop--disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-form__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.proj-form__avatar-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  color: var(--color-text-muted);
  font-size: 0.625rem;
  text-align: center;
}

.proj-form__avatar-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.proj-form__avatar-input:disabled {
  cursor: not-allowed;
}

.proj-form__section-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.proj-form__section-note {
  margin-left: auto;
  padding-left: var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  white-space: nowrap;
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
  flex: 1;
  min-width: 0;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
  align-content: start;
}

.proj-form__grid + .proj-form__grid {
  margin-top: var(--space-3);
}

.proj-form__grid--stack {
  grid-template-columns: 1fr;
}

.proj-form__grid--4col {
  grid-template-columns: repeat(4, 1fr);
}

.proj-form__field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.proj-form__field--wide {
  grid-column: 1 / -1;
}

.proj-form__field--span2 {
  grid-column: span 2;
}

/* ---- Nhãn field: đồng nhất kiểu chữ hoa nhỏ, giãn chữ nhẹ, tách bạch
   rõ với giá trị bên dưới — dùng thống nhất cho mọi field trong form. ---- */
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

.proj-form__hint,
.proj-form__caption {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
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

.proj-org__story {
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

.proj-org__story::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-gold-600);
}

.proj-org__story-val {
  color: var(--color-gold-700);
  font-weight: 700;
}

.proj-org__story-gap {
  color: var(--color-text-muted);
  font-style: italic;
  font-weight: 500;
}

.proj-org {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: var(--space-3);
  list-style: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-org__item {
  --step-color: var(--color-gold-600);
  --step-surface: var(--color-gold-surface);

  display: grid;
  grid-template-columns: 2.25rem minmax(0, 1fr);
  gap: var(--space-3);
  align-items: stretch;
}

.proj-org__item--secondary {
  --step-color: var(--color-secondary);
  --step-surface: var(--color-secondary-surface);
}

.proj-org__item--tertiary {
  --step-color: var(--color-tertiary);
  --step-surface: var(--color-tertiary-surface);
}

.proj-org__item--primary {
  --step-color: var(--color-primary);
  --step-surface: var(--color-primary-surface);
}

.proj-org__track {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.proj-org__num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-full);
  background: var(--step-color);
  color: var(--color-on-primary);
  font-size: 0.875rem;
  font-weight: 700;
}

.proj-org__line {
  flex: 1;
  width: 2px;
  min-height: var(--space-4);
  margin: 4px 0;
  border-radius: var(--radius-full);
  background: var(--step-color);
  opacity: 0.28;
}

.proj-org__card {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  margin-bottom: var(--space-3);
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.proj-org__item:last-child .proj-org__card {
  margin-bottom: 0;
}

.proj-org__card:focus-within {
  z-index: 3;
}

.proj-org__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--step-color);
}

.proj-org__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.proj-org__head-text {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.proj-org__icon {
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

.proj-org__title {
  margin: 0;
  color: var(--step-color);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-org__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.proj-org__fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  min-width: 0;
}

.proj-org__locked {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.proj-org__locked-dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: var(--radius-full);
  background: var(--color-gold-600);
}

.proj-org__locked-name {
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.proj-org__locked-meta {
  margin-left: auto;
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.proj-org__locked-icon {
  flex-shrink: 0;
  color: var(--color-gold-600);
}

.proj-people__story {
  position: relative;
  margin: 0 0 var(--space-4);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-secondary-surface);
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
  background: var(--color-secondary);
}

.proj-people__story-val {
  color: var(--color-secondary-700);
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

.proj-people__card--labels {
  grid-column: 1 / -1;
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

.proj-people__quick {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  align-self: flex-start;
  padding: 0.375rem 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--step-surface);
  color: var(--step-color);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-people__quick:hover:not(:disabled) {
  background: color-mix(in srgb, var(--step-color) 18%, var(--color-surface));
}

.proj-people__quick:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/*
 * Mức độ quan trọng — danh sách dọc kiểu "select" thay vì badge/pill:
 * mỗi dòng có 1 chấm màu nhỏ trước tên (quy tắc 14), viền trái nhấn màu
 * riêng theo mức (tăng dần độ "nóng" từ trung tính đến đỏ chính), và
 * trọng số hiện thành chữ thường ở cuối dòng thay vì badge nổi bật.
 */
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
  transition: background-color 0.12s ease, box-shadow 0.12s ease;
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

.proj-form__importance-row--secondary {
  --row-color: var(--color-secondary);
  --row-surface: var(--color-secondary-surface);
}

.proj-form__importance-row--gold {
  --row-color: var(--color-gold-600);
  --row-surface: var(--color-gold-surface);
}

.proj-form__importance-row--primary {
  --row-color: var(--color-primary);
  --row-surface: var(--color-primary-surface);
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
  white-space: nowrap;
}

.proj-form__importance-row--on .proj-form__importance-name {
  color: var(--row-color);
}

.proj-form__importance-desc {
  min-width: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.proj-form__importance-weight {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
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

.proj-rules__story-val {
  color: var(--color-tertiary-700);
  font-weight: 700;
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
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
}

.proj-rules__note {
  margin: 0;
  color: var(--step-color);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.4;
}

.proj-rules__example {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-style: italic;
  line-height: 1.55;
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
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.proj-page__input:hover:not(:disabled) {
  border-color: var(--color-border-strong);
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
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.proj-page__textarea:hover:not(:disabled) {
  border-color: var(--color-border-strong);
}

.proj-page__textarea:focus {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

@media (max-width: 1279px) {
  .proj-form__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .proj-form__section {
    padding: var(--space-3);
  }

  .proj-org__fields {
    grid-template-columns: 1fr;
  }

  .proj-people,
  .proj-rules {
    grid-template-columns: 1fr;
  }

  .proj-form__grid {
    grid-template-columns: 1fr;
  }

  .proj-form__importance-row {
    grid-template-columns: auto 1fr;
    row-gap: 2px;
  }

  .proj-form__importance-desc {
    grid-column: 2 / -1;
  }

  .proj-form__importance-weight {
    grid-column: 2 / -1;
  }
}

@media (max-width: 480px) {
  .proj-form__general {
    flex-direction: column;
    align-items: center;
    gap: var(--space-2);
  }

  .proj-form__avatar-drop {
    width: 3.5rem;
    height: 3.5rem;
  }

  .proj-form__section-note {
    display: none;
  }
}
</style>
