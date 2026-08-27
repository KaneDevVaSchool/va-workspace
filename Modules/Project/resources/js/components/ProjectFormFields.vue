<script setup>
//
// Toàn bộ field của form Dự án (dùng chung cho 2 trang ProjectCreate.vue /
// ProjectEdit.vue). Khi isCreate, trang cha truyền `step` để hiện đúng
// nhóm field của bước wizard. Component "câm": nhận form + errors qua
// props, phát 'update:field' khi người dùng sửa.
//
import { computed, ref } from 'vue';
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
    title: 'Khi thời gian thực hiện dự án thay đổi thì thời gian công việc thay đổi theo',
    example:
      'Thời gian dự án 10/03 – 20/03, công việc X là 11/03 – 15/03. Khi dự án tịnh tiến 3 ngày thì công việc X cũng tịnh tiến thêm 3 ngày.',
  },
  {
    key: 'hide_cross_tasks_from_assignees',
    title: 'Không cho phép người thực hiện công việc xem chéo các công việc khác',
    example:
      'Dự án có công việc B và C. Người thực hiện B không xem được C nếu không phải người thực hiện C.',
  },
  {
    key: 'hide_child_tasks_from_followers',
    title: 'Không cho phép người theo dõi xem được các công việc con',
    example:
      'Người theo dõi dự án không xem được công việc con nếu không phải người theo dõi từng công việc đó.',
  },
  {
    key: 'constrain_task_dates_to_project',
    title: 'Thời gian dự kiến thực hiện công việc phải nằm trong khoảng thời gian của dự án',
    example: 'Ngày bắt đầu/kết thúc của công việc không được nằm ngoài khoảng ngày của dự án.',
  },
];

const STEP_SECTIONS = {
  1: ['general', 'schedule'],
  2: ['org'],
  3: ['people', 'labels'],
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

const rulesOpen = ref(true);

function set(field, value) {
  emit('update:field', field, value);
}

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
        <h2 class="proj-form__section-title">Tổ chức &amp; phụ trách</h2>
      </header>

      <p class="proj-form__section-desc">
        Xác định phòng ban sở hữu dự án sẽ giao cho phòng ban nào thực hiện, và ai là người phụ trách chính theo dõi tiến độ.
      </p>

      <!-- Sơ đồ luồng giao việc: 3 mốc nối tiếp, đọc từ trái sang phải
           (từ trên xuống ở mobile). Mỗi mốc là 1 thẻ độc lập không giới
           hạn số dòng chữ, tránh rớt/tràn tên phòng ban hoặc tên người dài. -->
      <ol class="proj-form__flow" aria-label="Luồng giao việc của dự án">
        <li class="proj-form__flow-step">
          <span class="proj-form__flow-badge">1</span>
          <div class="proj-form__flow-card">
            <span class="proj-form__flow-label">Phòng ban giao</span>
            <ProjectDepartmentPicker
              v-if="canChooseOwnerDepartment"
              :model-value="ownerDepartmentIds"
              :departments="departments"
              :disabled="disabled"
              :multiple="false"
              placeholder="Gõ tên phòng ban giao…"
              empty-text="Chưa chọn — mặc định là phòng ban của bạn."
              @update:model-value="onOwnerDepartmentIds"
            />
            <span v-else class="proj-form__flow-value">{{ ownerDepartment?.name || 'Phòng ban của người tạo' }}</span>
          </div>
        </li>

        <li class="proj-form__flow-arrow" aria-hidden="true">
          <AppIcon name="arrowRight" :size="18" :stroke-width="2" />
        </li>

        <li class="proj-form__flow-step">
          <span class="proj-form__flow-badge proj-form__flow-badge--secondary">2</span>
          <div class="proj-form__flow-card">
            <span class="proj-form__flow-label">Phòng ban thực hiện</span>
            <ProjectDepartmentPicker
              :model-value="executingDepartmentIds"
              :departments="departments"
              :disabled="disabled"
              placeholder="Gõ tên phòng ban được giao thực hiện…"
              empty-text="Không giao — phòng ban sở hữu tự thực hiện."
              @update:model-value="onExecutingIds"
            />
          </div>
        </li>

        <li class="proj-form__flow-arrow" aria-hidden="true">
          <AppIcon name="arrowRight" :size="18" :stroke-width="2" />
        </li>

        <li class="proj-form__flow-step">
          <span class="proj-form__flow-badge proj-form__flow-badge--primary">3</span>
          <div class="proj-form__flow-card">
            <span class="proj-form__flow-label">Phụ trách chính</span>
            <ProjectUserPicker
              :model-value="form.lead_user_id"
              :users="assignableUsers"
              :disabled="disabled"
              @update:model-value="onLeadChange"
            />
          </div>
        </li>
      </ol>

      <div class="proj-form__grid">
        <div class="proj-form__field proj-form__field--wide">
          <span class="proj-form__label">Phòng ban phụ trách</span>
          <span class="proj-form__caption">Phòng ban theo dõi chính, thường trùng với phòng ban của người phụ trách ở trên.</span>
          <ProjectDepartmentPicker
            :model-value="leadDepartmentIds"
            :departments="departments"
            :disabled="disabled"
            :multiple="false"
            placeholder="Gõ tên phòng ban phụ trách…"
            empty-text="Chưa chọn phòng ban phụ trách."
            @update:model-value="onLeadDepartmentIds"
          />
        </div>
      </div>
    </section>

    <section v-if="showSection('people')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--secondary">
          <AppIcon name="users" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Phạm vi &amp; thành viên</h2>
      </header>

      <div class="proj-form__grid proj-form__grid--stack">
        <div class="proj-form__field proj-form__field--wide">
          <span class="proj-form__label">Phạm vi triển khai</span>
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

        <div class="proj-form__field proj-form__field--wide">
          <span class="proj-form__label">Người thực hiện</span>
          <ProjectMemberPicker
            :model-value="form.member_ids"
            :users="assignableUsers"
            :disabled="disabled"
            @update:model-value="set('member_ids', $event)"
          />
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <span class="proj-form__label">Người theo dõi</span>
          <ProjectMemberPicker
            :model-value="form.follower_ids"
            :users="assignableUsers"
            :disabled="disabled"
            placeholder="Gõ tên hoặc email để thêm người theo dõi…"
            empty-text="Chưa chọn người theo dõi nào."
            @update:model-value="set('follower_ids', $event)"
          />
        </div>
      </div>
    </section>

    <section v-if="showSection('rules')" class="proj-form__section">
      <header class="proj-form__section-head">
        <button
          type="button"
          class="proj-form__rules-toggle"
          :aria-expanded="rulesOpen ? 'true' : 'false'"
          @click="rulesOpen = !rulesOpen"
        >
          <AppIcon name="chevronDown" :size="16" :stroke-width="2" class="proj-form__chevron" :class="{ 'proj-form__chevron--closed': !rulesOpen }" />
          <span class="proj-form__section-icon proj-form__section-icon--tertiary">
            <AppIcon name="shield" :size="16" :stroke-width="1.75" />
          </span>
          <h2 class="proj-form__section-title">Cài đặt quyền</h2>
        </button>
      </header>

      <ul v-show="rulesOpen" class="proj-form__rules">
        <li v-for="rule in RULE_DEFS" :key="rule.key" class="proj-form__rules-item">
          <label class="proj-form__check">
            <input
              type="checkbox"
              class="proj-form__check-input"
              :checked="Boolean(form[rule.key])"
              :disabled="disabled"
              @change="toggleRule(rule.key)"
            />
            <span class="proj-form__check-box" aria-hidden="true">
              <AppIcon v-if="form[rule.key]" name="check" :size="12" :stroke-width="2.5" />
            </span>
            <span class="proj-form__check-copy">
              <span class="proj-form__check-text">{{ rule.title }}</span>
              <span class="proj-form__caption">{{ rule.example }}</span>
            </span>
          </label>
        </li>
      </ul>
    </section>

    <section v-if="showSection('labels')" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--secondary">
          <AppIcon name="bookmark" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Nhãn dự án</h2>
      </header>

      <div class="proj-form__grid">
        <div class="proj-form__field proj-form__field--wide">
          <ProjectLabelPicker
            :model-value="form.label_ids"
            :labels="allLabels"
            :disabled="disabled"
            @update:model-value="set('label_ids', $event)"
            @created="emit('label-created', $event)"
          />
        </div>
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

.proj-form__section-desc {
  margin: calc(var(--space-3) * -1) 0 var(--space-3);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.5;
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

/*
 * Sơ đồ luồng giao việc (bước "Tổ chức"): 3 thẻ nối tiếp bằng mũi tên,
 * đọc trái→phải trên desktop, xếp dọc trên mobile. Mỗi thẻ min-width: 0
 * + overflow-wrap để tên phòng ban/người dài tự xuống dòng, không rớt
 * chữ ra ngoài card hay đẩy vỡ layout ngang.
 */
.proj-form__flow {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto minmax(0, 1fr);
  gap: var(--space-2);
  align-items: stretch;
  margin: 0 0 var(--space-4);
  padding: 0;
  list-style: none;
}

.proj-form__flow-step {
  position: relative;
  min-width: 0;
}

.proj-form__flow-card {
  display: flex;
  flex-direction: column;
  gap: 0.3125rem;
  min-width: 0;
  height: 100%;
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-3) + 0.75rem);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-form__flow-badge {
  position: absolute;
  top: -0.5rem;
  left: -0.5rem;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-full);
  background: var(--color-gold-600);
  color: var(--color-on-primary, #fff);
  font-size: 0.75rem;
  font-weight: 700;
  box-shadow: 0 0 0 2px var(--color-surface);
}

.proj-form__flow-badge--secondary {
  background: var(--color-secondary);
}

.proj-form__flow-badge--primary {
  background: var(--color-primary);
}

.proj-form__flow-label {
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.proj-form__flow-value {
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
}

.proj-form__flow-card :deep(.proj-page__input) {
  font-size: 0.8125rem;
}

.proj-form__flow-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--color-gold-600);
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

.proj-form__rules-toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary);
  cursor: pointer;
  text-align: left;
}

.proj-form__rules-toggle .proj-form__section-title {
  color: var(--color-tertiary);
}

.proj-form__rules-toggle :deep(.proj-form__chevron) {
  color: var(--color-tertiary);
  transition: transform 0.15s ease;
}

.proj-form__rules-toggle :deep(.proj-form__chevron--closed) {
  transform: rotate(-90deg);
}

.proj-form__rules {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.proj-form__rules-item {
  padding: var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-form__rules-item:last-child {
  box-shadow: none;
}

.proj-form__check {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  min-width: 0;
  cursor: pointer;
}

.proj-form__check-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.proj-form__check-box {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.125rem;
  height: 1.125rem;
  margin-top: 0.125rem;
  border-radius: 0.25rem;
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1.5px var(--color-border);
  color: #fff;
  transition: background-color 0.12s ease, box-shadow 0.12s ease;
}

.proj-form__check:hover .proj-form__check-box {
  box-shadow: inset 0 0 0 1.5px var(--color-tertiary);
}

.proj-form__check-input:checked + .proj-form__check-box {
  background: var(--color-tertiary);
  box-shadow: none;
}

.proj-form__check-copy {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.proj-form__check-text {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
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

  .proj-form__flow {
    grid-template-columns: 1fr;
  }

  .proj-form__flow-arrow {
    transform: rotate(90deg);
    padding: 0.125rem 0;
  }
}

@media (max-width: 768px) {
  .proj-form__section {
    padding: var(--space-3);
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
