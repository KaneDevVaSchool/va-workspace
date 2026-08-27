<script setup>
//
// Toàn bộ field của form Dự án (dùng chung cho 2 trang ProjectCreate.vue /
// ProjectEdit.vue). Lưới ngang nhiều cột theo section, mỗi section có icon
// + màu riêng (primary/tertiary/gold/secondary) chỉ để phân nhóm thị giác —
// không phải trạng thái/badge. Component "câm": nhận form + errors qua
// props, phát 'update:field' khi người dùng sửa — trang cha giữ state thật.
//
import AppIcon from '@/components/AppIcon.vue';
import ProjectMemberPicker from './ProjectMemberPicker.vue';
import ProjectScopePicker from './ProjectScopePicker.vue';
import ProjectLabelPicker from './ProjectLabelPicker.vue';

const props = defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  options: { type: Object, required: true },
  departments: { type: Array, required: true },
  assignableUsers: { type: Array, required: true },
  allLabels: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
  isCreate: { type: Boolean, default: false },
  durationDays: { type: [Number, null], default: null },
  progressMethodDescription: { type: String, default: '' },
  avatarPreviewUrl: { type: String, default: '' },
  // Cài đặt quyền — dùng CHUNG toàn hệ thống (xem trang /manager/project/settings),
  // hiển thị ở đây chỉ để xem nhanh, không sửa được tại form này.
  sharedRules: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:field', 'label-created', 'avatar-selected', 'open-settings']);

function set(field, value) {
  emit('update:field', field, value);
}

function fieldError(field) {
  const e = props.errors?.[field];
  return Array.isArray(e) ? e[0] : e;
}

function onAvatarChange(event) {
  const file = event.target.files?.[0] || null;
  if (file) emit('avatar-selected', file);
}

function onAvatarDrop(event) {
  const file = event.dataTransfer?.files?.[0] || null;
  if (file) emit('avatar-selected', file);
}
</script>

<template>
  <div class="proj-form">
    <section class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--primary">
          <AppIcon name="fileText" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Thông tin chung</h2>
      </header>

      <div class="proj-form__avatar-row">
        <label
          class="proj-form__avatar-drop"
          :class="{ 'proj-form__avatar-drop--disabled': disabled }"
          @dragover.prevent
          @drop.prevent="onAvatarDrop"
        >
          <img v-if="avatarPreviewUrl" :src="avatarPreviewUrl" alt="" class="proj-form__avatar-img" />
          <span v-else class="proj-form__avatar-placeholder">
            <AppIcon name="camera" :size="20" :stroke-width="1.6" />
          </span>
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="proj-form__avatar-input"
            :disabled="disabled"
            @change="onAvatarChange"
          />
        </label>
        <div class="proj-form__avatar-copy">
          <span class="proj-form__label">Ảnh đại diện dự án</span>
          <p class="proj-form__hint">Kéo thả ảnh vào đây hoặc bấm để chọn tệp. Định dạng JPG, PNG, WEBP — tối đa 5MB.</p>
        </div>
      </div>

      <div class="proj-form__grid">
        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-code">Mã dự án</label>
          <input
            id="proj-form-code"
            :value="form.code"
            type="text"
            class="proj-page__input"
            placeholder="Tự động sinh khi lưu"
            readonly
            disabled
          />
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-type">
            Loại dự án <span class="proj-form__required">*</span>
          </label>
          <select
            id="proj-form-type"
            :value="form.type"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('type', $event.target.value)"
          >
            <option value="" disabled>Chọn loại dự án…</option>
            <option v-for="opt in options.type" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <div class="proj-form__field proj-form__field--wide">
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

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-lead">Phụ trách chính</label>
          <select
            id="proj-form-lead"
            :value="form.lead_user_id"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('lead_user_id', $event.target.value)"
          >
            <option value="">Chưa chọn</option>
            <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-exec-dept">Giao phòng ban khác thực hiện</label>
          <select
            id="proj-form-exec-dept"
            :value="form.executing_department_id"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('executing_department_id', $event.target.value)"
          >
            <option value="">Không giao — phòng ban sở hữu tự thực hiện</option>
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
          </select>
        </div>

        <div class="proj-form__field proj-form__field--wide">
          <label class="proj-form__label" for="proj-form-desc">Mô tả</label>
          <textarea
            id="proj-form-desc"
            :value="form.description"
            class="proj-page__textarea"
            rows="3"
            maxlength="5000"
            placeholder="Mô tả ngắn gọn mục tiêu, phạm vi công việc của dự án…"
            :disabled="disabled"
            @input="set('description', $event.target.value)"
          />
        </div>
      </div>
    </section>

    <section class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--tertiary">
          <AppIcon name="calendar" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Tiến độ &amp; thời gian</h2>
      </header>

      <div class="proj-form__grid">
        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-start">Ngày bắt đầu</label>
          <input
            id="proj-form-start"
            :value="form.start_date"
            type="date"
            class="proj-page__input"
            placeholder="dd/mm/yyyy"
            :disabled="disabled"
            @input="set('start_date', $event.target.value)"
          />
        </div>

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-end">Ngày kết thúc</label>
          <input
            id="proj-form-end"
            :value="form.end_date"
            type="date"
            class="proj-page__input"
            placeholder="dd/mm/yyyy"
            :disabled="disabled"
            @input="set('end_date', $event.target.value)"
          />
        </div>

        <div class="proj-form__field">
          <span class="proj-form__label">Số ngày thực hiện</span>
          <span class="proj-form__static">{{ durationDays ? `${durationDays} ngày` : '—' }}</span>
        </div>

        <div class="proj-form__field">
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

        <div class="proj-form__field">
          <label class="proj-form__label" for="proj-form-importance">Mức độ quan trọng</label>
          <select
            id="proj-form-importance"
            :value="form.importance"
            class="proj-page__input"
            :disabled="disabled"
            @change="set('importance', $event.target.value)"
          >
            <option v-for="opt in options.importance" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <p v-if="progressMethodDescription" class="proj-form__hint proj-form__field--wide">
          {{ progressMethodDescription }}
        </p>
      </div>
    </section>

    <section class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--gold">
          <AppIcon name="users" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Người thực hiện &amp; phạm vi</h2>
      </header>

      <div class="proj-form__grid proj-form__grid--stack">
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
          <span class="proj-form__label">Phạm vi triển khai</span>
          <ProjectScopePicker
            :model-value="form.scopes"
            :scope-type-options="options.scope_type"
            :departments="departments"
            :disabled="disabled"
            @update:model-value="set('scopes', $event)"
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

    <section v-if="sharedRules.length" class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--tertiary">
          <AppIcon name="shield" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Cài đặt quyền</h2>
      </header>

      <p class="proj-form__hint proj-form__rules-note">
        Áp dụng chung cho toàn bộ dự án trong hệ thống — sửa tại trang Cài đặt dự án.
      </p>

      <ul class="proj-form__rules">
        <li v-for="rule in sharedRules" :key="rule.key" class="proj-form__rules-item">
          <span
            class="proj-form__rules-dot"
            :class="{ 'proj-form__rules-dot--on': rule.enabled }"
            aria-hidden="true"
          />
          <span class="proj-form__rules-text">{{ rule.title }}</span>
          <span class="proj-form__rules-value">{{ rule.enabled ? 'Đang bật' : 'Đang tắt' }}</span>
        </li>
      </ul>

      <button type="button" class="proj-form__rules-link" @click="emit('open-settings')">
        <AppIcon name="externalLink" :size="14" :stroke-width="1.75" />
        Mở trang Cài đặt dự án
      </button>
    </section>

    <section class="proj-form__section">
      <header class="proj-form__section-head">
        <span class="proj-form__section-icon proj-form__section-icon--secondary">
          <AppIcon name="bookmark" :size="16" :stroke-width="1.75" />
        </span>
        <h2 class="proj-form__section-title">Nhãn</h2>
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
  gap: var(--space-4);
}

.proj-form__section {
  padding: var(--space-4) var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.proj-form__avatar-row {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  margin-bottom: var(--space-4);
}

.proj-form__avatar-drop {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 4.5rem;
  height: 4.5rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
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
  display: inline-flex;
  color: var(--color-text-muted);
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

.proj-form__avatar-copy {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.proj-form__rules-note {
  margin-bottom: var(--space-3);
}

.proj-form__rules {
  display: flex;
  flex-direction: column;
  margin: 0 0 var(--space-3);
  padding: 0;
  list-style: none;
}

.proj-form__rules-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.proj-form__rules-item:last-child {
  box-shadow: none;
}

.proj-form__rules-dot {
  flex-shrink: 0;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.proj-form__rules-dot--on {
  background: var(--color-primary);
}

.proj-form__rules-text {
  flex: 1;
  min-width: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
}

.proj-form__rules-value {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.proj-form__rules-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-form__rules-link:hover {
  text-decoration: underline;
}

.proj-form__section-head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
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
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-4);
  align-content: start;
}

.proj-form__grid--stack {
  grid-template-columns: 1fr;
}

.proj-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.proj-form__field--wide {
  grid-column: 1 / -1;
}

.proj-form__label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-form__required {
  color: var(--color-primary);
}

.proj-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

.proj-form__hint {
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

@media (max-width: 1279px) {
  .proj-form__grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .proj-form__section {
    padding: var(--space-4);
  }

  .proj-form__grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .proj-form__avatar-row {
    gap: var(--space-3);
  }

  .proj-form__avatar-drop {
    width: 3.5rem;
    height: 3.5rem;
  }
}
</style>
