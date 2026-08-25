<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const props = defineProps({
  open: { type: Boolean, default: false },
  group: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => Boolean(props.group?.id));
const saving = ref(false);
const errors = ref({});
const coverFile = ref(null);
const coverPreview = ref('');
const fileInput = ref(null);

const form = reactive({
  name: '',
  description: '',
  visibility: 'public',
});

function resetForm() {
  form.name = props.group?.name ?? '';
  form.description = props.group?.description ?? '';
  form.visibility = props.group?.visibility ?? 'public';
  coverFile.value = null;
  coverPreview.value = props.group?.cover_url ?? '';
  errors.value = {};
}

watch(() => props.open, (isOpen) => {
  if (isOpen) resetForm();
});

function onCoverChosen(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  coverFile.value = file;
  coverPreview.value = URL.createObjectURL(file);
}

function close() {
  if (saving.value) return;
  emit('close');
}

async function submit() {
  if (form.name.trim().length < 3) {
    errors.value = { name: ['Tên nhóm phải có ít nhất 3 ký tự.'] };
    return;
  }

  saving.value = true;
  errors.value = {};
  try {
    const data = new FormData();
    data.append('name', form.name.trim());
    data.append('description', form.description.trim());
    data.append('visibility', form.visibility);
    if (coverFile.value) data.append('cover', coverFile.value);
    if (isEdit.value) data.append('_method', 'PUT');

    const url = isEdit.value ? `/api/social/groups/${props.group.id}` : '/api/social/groups';
    const { data: response } = await window.axios.post(url, data, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    showClientToast('success', isEdit.value ? 'Đã cập nhật nhóm.' : 'Đã tạo nhóm mới.');
    emit('saved', response.group);
  } catch (error) {
    if (error?.response?.status === 422) {
      errors.value = error.response.data?.errors ?? {};
    }
    showClientToast('error', error?.response?.data?.message ?? 'Không thể lưu nhóm.');
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="group-dialog-fade">
      <div
        v-if="open"
        class="group-dialog"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="group-dialog__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="social-group-form-title"
        >
          <div class="group-dialog__head">
            <span class="group-dialog__icon" aria-hidden="true">
              <AppIcon :name="isEdit ? 'pencil' : 'users'" :size="22" :stroke-width="1.75" />
            </span>
            <div class="group-dialog__head-copy">
              <h2 id="social-group-form-title" class="group-dialog__title">
                {{ isEdit ? 'Sửa thông tin nhóm' : 'Tạo nhóm mới' }}
              </h2>
            </div>
            <button
              type="button"
              class="group-dialog__close"
              aria-label="Đóng"
              :disabled="saving"
              @click="close"
            >
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="group-dialog__body hide-scrollbar">
            <div class="group-dialog__form">
              <div class="group-dialog__field group-dialog__field--name">
                <label class="group-dialog__label" for="social-group-name">
                  Tên nhóm <span class="group-dialog__required">*</span>
                </label>
                <input
                  id="social-group-name"
                  v-model="form.name"
                  type="text"
                  class="group-dialog__input"
                  :class="{ 'group-dialog__input--error': errors.name }"
                  maxlength="150"
                  :disabled="saving"
                />
                <span v-if="errors.name" class="group-dialog__field-error">
                  {{ Array.isArray(errors.name) ? errors.name[0] : errors.name }}
                </span>
              </div>

              <div class="group-dialog__field group-dialog__field--visibility">
                <label class="group-dialog__label" for="social-group-visibility">Quyền riêng tư</label>
                <select
                  id="social-group-visibility"
                  v-model="form.visibility"
                  class="group-dialog__input"
                  :disabled="saving"
                >
                  <option value="public">Công khai — ai cũng xem và tham gia ngay</option>
                  <option value="private">Bảo mật — chỉ thành viên xem, người khác phải gửi yêu cầu</option>
                </select>
              </div>

              <div class="group-dialog__field group-dialog__field--desc">
                <label class="group-dialog__label" for="social-group-description">Mô tả nhóm</label>
                <textarea
                  id="social-group-description"
                  v-model="form.description"
                  class="group-dialog__input group-dialog__textarea"
                  rows="4"
                  maxlength="2000"
                  :disabled="saving"
                />
                <span v-if="errors.description" class="group-dialog__field-error">
                  {{ Array.isArray(errors.description) ? errors.description[0] : errors.description }}
                </span>
              </div>

              <div class="group-dialog__field group-dialog__field--cover">
                <label class="group-dialog__label">Ảnh bìa</label>
                <div class="group-dialog__cover-row">
                  <div class="group-dialog__cover-preview">
                    <img v-if="coverPreview" :src="coverPreview" alt="" />
                    <AppIcon v-else name="fileUp" :size="24" />
                  </div>
                  <button
                    type="button"
                    class="group-dialog__cover-btn"
                    :disabled="saving"
                    @click="fileInput?.click()"
                  >
                    Chọn ảnh bìa
                  </button>
                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="group-dialog__file-input"
                    @change="onCoverChosen"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="group-dialog__actions">
            <button type="button" class="group-dialog__btn" :disabled="saving" @click="close">
              Huỷ
            </button>
            <button type="button" class="group-dialog__btn group-dialog__btn--primary" :disabled="saving" @click="submit">
              {{ saving ? 'Đang lưu...' : (isEdit ? 'Lưu thay đổi' : 'Tạo nhóm') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.group-dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.group-dialog__panel {
  width: min(56rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: 1.5rem 1.75rem 1.25rem;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.group-dialog__head,
.group-dialog__actions {
  flex-shrink: 0;
}

.group-dialog__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.group-dialog__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.group-dialog__head-copy {
  flex: 1;
  min-width: 0;
}

.group-dialog__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.35;
}

.group-dialog__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.group-dialog__close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.group-dialog__close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.group-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.group-dialog__form {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-template-areas:
    'name name visibility'
    'desc desc desc'
    'cover cover cover';
  gap: var(--space-3) var(--space-4);
}

.group-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  min-width: 0;
}

.group-dialog__field--name { grid-area: name; }
.group-dialog__field--visibility { grid-area: visibility; }
.group-dialog__field--desc { grid-area: desc; }
.group-dialog__field--cover { grid-area: cover; }

.group-dialog__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.group-dialog__required {
  color: var(--color-primary);
}

.group-dialog__input {
  width: 100%;
  padding: 0.4375rem 0.625rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.group-dialog__input--error {
  border-color: var(--color-danger, #dc2626);
}

.group-dialog__textarea {
  resize: vertical;
  min-height: 5rem;
}

.group-dialog__field-error {
  font-size: 0.75rem;
  color: var(--color-danger, #dc2626);
}

.group-dialog__cover-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.group-dialog__cover-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 5rem;
  height: 5rem;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.group-dialog__cover-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.group-dialog__cover-btn {
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.group-dialog__cover-btn:hover:not(:disabled) {
  background: var(--color-surface);
}

.group-dialog__file-input {
  display: none;
}

.group-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding-top: var(--space-2);
  box-shadow: 0 -1px 0 var(--color-border);
}

.group-dialog__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.group-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  box-shadow: var(--shadow-sm);
}

.group-dialog__btn:hover:not(:disabled) {
  filter: brightness(0.95);
}

.group-dialog__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.group-dialog-fade-enter-active,
.group-dialog-fade-leave-active {
  transition: opacity 0.2s ease;
}

.group-dialog-fade-enter-from,
.group-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .group-dialog__form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-template-areas:
      'name name'
      'visibility visibility'
      'desc desc'
      'cover cover';
  }
}

@media (max-width: 480px) {
  .group-dialog__panel {
    padding: 1.25rem 1.25rem 1rem;
  }

  .group-dialog__form {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'name'
      'visibility'
      'desc'
      'cover';
  }
}
</style>
