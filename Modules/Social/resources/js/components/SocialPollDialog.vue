<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const MAX_OPTIONS = 10;
const MAX_HOURS = 240;
const HOUR_PRESETS = [1, 2, 6, 24];

const props = defineProps({
  open: { type: Boolean, required: true },
  defaultScope: { type: String, default: 'company' },
  departmentName: { type: String, default: '' },
  wallUserId: { type: Number, default: null },
  wallUserName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'posted']);

const title = ref('');
const content = ref('');
const options = ref(['', '']);
const allowMultiple = ref(false);
const durationHours = ref(0);
const imageFile = ref(null);
const imagePreview = ref('');
const imageInput = ref(null);
const submitting = ref(false);

const filledOptions = computed(() => options.value.map((label) => label.trim()).filter(Boolean));
const canSubmit = computed(() => title.value.trim() !== '' && filledOptions.value.length >= 2 && !submitting.value);
const wallLabel = computed(() => {
  if (props.wallUserName && props.defaultScope === 'personal') return `Tường ${props.wallUserName}`;
  if (props.departmentName && props.defaultScope === 'department') return `Tường ${props.departmentName}`;
  return '';
});

function toLocalDateTime(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  const h = String(date.getHours()).padStart(2, '0');
  const min = String(date.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${d}T${h}:${min}`;
}

function endsAtValue() {
  if (!durationHours.value) return '';
  const at = new Date();
  at.setHours(at.getHours() + durationHours.value);
  return toLocalDateTime(at);
}

function clampHours(value) {
  const n = Number.parseInt(String(value), 10);
  if (!Number.isFinite(n) || n <= 0) return 0;
  return Math.min(MAX_HOURS, n);
}

function setHours(value) {
  durationHours.value = clampHours(value);
}

function bumpHours(delta) {
  setHours(durationHours.value + delta);
}

function onHoursInput(event) {
  setHours(event.target.value);
}

function resetForm() {
  title.value = '';
  content.value = '';
  options.value = ['', ''];
  allowMultiple.value = false;
  durationHours.value = 0;
  revokeImage();
}

function revokeImage() {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
  imagePreview.value = '';
  imageFile.value = null;
  if (imageInput.value) imageInput.value.value = '';
}

function close() {
  if (submitting.value) return;
  emit('close');
}

function setOption(index, value) {
  const next = [...options.value];
  next[index] = value;
  options.value = next;
}

function addOption() {
  if (options.value.length >= MAX_OPTIONS) return;
  options.value = [...options.value, ''];
}

function removeOption(index) {
  if (options.value.length <= 2) return;
  options.value = options.value.filter((_, i) => i !== index);
}

function onImageChosen(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    showClientToast('error', 'Chỉ được chọn file ảnh.');
    return;
  }
  revokeImage();
  imageFile.value = file;
  imagePreview.value = URL.createObjectURL(file);
}

function onKey(event) {
  if (event.key === 'Escape') {
    event.preventDefault();
    close();
  }
}

function bindPage() {
  document.addEventListener('keydown', onKey);
  document.body.style.overflow = 'hidden';
}

function unbindPage() {
  document.removeEventListener('keydown', onKey);
  document.body.style.overflow = '';
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm();
      bindPage();
      return;
    }
    unbindPage();
    revokeImage();
  },
);

onBeforeUnmount(() => {
  unbindPage();
  revokeImage();
});

async function submit() {
  if (!title.value.trim()) {
    showClientToast('error', 'Vui lòng nhập tiêu đề bình chọn.');
    return;
  }
  if (filledOptions.value.length < 2) {
    showClientToast('error', 'Bình chọn cần ít nhất 2 phương án.');
    return;
  }
  if (submitting.value) return;

  submitting.value = true;
  try {
    const form = new FormData();
    form.append('post_scope', props.defaultScope);
    if (props.defaultScope === 'personal' && props.wallUserId) {
      form.append('wall_user_id', String(props.wallUserId));
    }
    form.append('poll[title]', title.value.trim());
    if (content.value.trim()) form.append('poll[content]', content.value.trim());
    filledOptions.value.forEach((label) => form.append('poll[options][]', label));
    form.append('poll[allow_multiple]', allowMultiple.value ? '1' : '0');
    const endsAt = endsAtValue();
    if (endsAt) form.append('poll[ends_at]', endsAt);
    if (imageFile.value) form.append('poll[image]', imageFile.value);

    const { data } = await window.axios.post('/api/social/posts', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emit('posted', data.post);
    showClientToast('success', 'Đã đăng bình chọn.');
    emit('close');
  } catch (error) {
    const message = error?.response?.data?.message ?? 'Không thể đăng bình chọn.';
    showClientToast('error', message);
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="poll-dialog-fade">
      <div
        v-if="open"
        class="poll-dialog"
        role="presentation"
        @mousedown.self="close"
      >
        <div
          class="poll-dialog__panel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="poll-dialog-title"
        >
          <div class="poll-dialog__head">
            <span class="poll-dialog__icon" aria-hidden="true">
              <AppIcon name="listChecks" :size="16" :stroke-width="1.75" />
            </span>
            <div class="poll-dialog__head-copy">
              <h2 id="poll-dialog-title" class="poll-dialog__title">Tạo bình chọn</h2>
              <p v-if="wallLabel" class="poll-dialog__sub">{{ wallLabel }}</p>
            </div>
            <button
              type="button"
              class="poll-dialog__close"
              aria-label="Đóng"
              :disabled="submitting"
              @click="close"
            >
              <AppIcon name="close" :size="15" />
            </button>
          </div>

          <div class="poll-dialog__body hide-scrollbar">
            <div class="poll-dialog__form">
              <div class="poll-dialog__field poll-dialog__field--title">
                <label class="poll-dialog__label" for="poll-title">
                  Tiêu đề <span class="poll-dialog__required">*</span>
                </label>
                <input
                  id="poll-title"
                  v-model="title"
                  type="text"
                  class="poll-dialog__input"
                  maxlength="200"
                  placeholder="VD: Nên tổ chức team building ở đâu?"
                  :disabled="submitting"
                />
              </div>

              <div class="poll-dialog__field poll-dialog__field--content">
                <label class="poll-dialog__label" for="poll-content">Mô tả ngắn</label>
                <input
                  id="poll-content"
                  v-model="content"
                  type="text"
                  class="poll-dialog__input"
                  maxlength="2000"
                  placeholder="Thêm ngữ cảnh…"
                  :disabled="submitting"
                />
              </div>

              <div class="poll-dialog__field poll-dialog__field--image">
                <span class="poll-dialog__label">Ảnh</span>
                <div class="poll-dialog__image-wrap">
                  <button
                    type="button"
                    class="poll-dialog__image"
                    :class="{ 'poll-dialog__image--filled': imagePreview }"
                    :disabled="submitting"
                    :aria-label="imagePreview ? 'Đổi ảnh minh hoạ' : 'Chọn ảnh minh hoạ'"
                    @click="imageInput?.click()"
                  >
                    <img
                      v-if="imagePreview"
                      :src="imagePreview"
                      alt=""
                      class="poll-dialog__image-preview"
                    />
                    <span v-else class="poll-dialog__image-empty">
                      <AppIcon name="fileUp" :size="16" />
                    </span>
                  </button>
                  <button
                    v-if="imagePreview"
                    type="button"
                    class="poll-dialog__image-clear"
                    aria-label="Gỡ ảnh"
                    :disabled="submitting"
                    @click="revokeImage"
                  >
                    <AppIcon name="close" :size="12" />
                  </button>
                </div>
                <input
                  ref="imageInput"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="poll-dialog__file"
                  @change="onImageChosen"
                />
              </div>

              <div class="poll-dialog__field poll-dialog__field--multi">
                <span class="poll-dialog__label" id="poll-multi-label">Cách chọn</span>
                <div class="poll-dialog__seg" role="radiogroup" aria-labelledby="poll-multi-label">
                  <label class="poll-dialog__seg-opt" :class="{ 'poll-dialog__seg-opt--on': !allowMultiple }">
                    <input
                      class="poll-dialog__seg-input"
                      type="radio"
                      :checked="!allowMultiple"
                      :disabled="submitting"
                      @change="allowMultiple = false"
                    />
                    Một
                  </label>
                  <label class="poll-dialog__seg-opt" :class="{ 'poll-dialog__seg-opt--on': allowMultiple }">
                    <input
                      class="poll-dialog__seg-input"
                      type="radio"
                      :checked="allowMultiple"
                      :disabled="submitting"
                      @change="allowMultiple = true"
                    />
                    Nhiều
                  </label>
                </div>
              </div>

              <div class="poll-dialog__field poll-dialog__field--deadline">
                <span class="poll-dialog__label" id="poll-hours-label">Hạn (giờ)</span>
                <div class="poll-dialog__hours" role="group" aria-labelledby="poll-hours-label">
                  <div class="poll-dialog__stepper">
                    <button
                      type="button"
                      class="poll-dialog__step"
                      aria-label="Giảm giờ"
                      :disabled="submitting || durationHours <= 0"
                      @click="bumpHours(-1)"
                    >
                      <AppIcon name="minus" :size="13" />
                    </button>
                    <input
                      id="poll-hours"
                      :value="durationHours || ''"
                      type="number"
                      min="0"
                      :max="MAX_HOURS"
                      inputmode="numeric"
                      class="poll-dialog__hours-input"
                      placeholder="0"
                      aria-label="Số giờ hết hạn"
                      :disabled="submitting"
                      @input="onHoursInput"
                    />
                    <span class="poll-dialog__hours-unit">giờ</span>
                    <button
                      type="button"
                      class="poll-dialog__step"
                      aria-label="Tăng giờ"
                      :disabled="submitting || durationHours >= MAX_HOURS"
                      @click="bumpHours(1)"
                    >
                      <AppIcon name="plus" :size="13" />
                    </button>
                  </div>
                  <div class="poll-dialog__presets">
                    <button
                      v-for="hours in HOUR_PRESETS"
                      :key="hours"
                      type="button"
                      class="poll-dialog__preset"
                      :class="{ 'poll-dialog__preset--on': durationHours === hours }"
                      :disabled="submitting"
                      @click="setHours(hours)"
                    >
                      {{ hours }}h
                    </button>
                  </div>
                </div>
              </div>

              <section class="poll-dialog__options" aria-label="Phương án bình chọn">
                <div class="poll-dialog__options-head">
                  <span class="poll-dialog__label">
                    Phương án <span class="poll-dialog__required">*</span>
                  </span>
                  <span class="poll-dialog__options-count">{{ options.length }}/{{ MAX_OPTIONS }}</span>
                </div>

                <div class="poll-dialog__options-grid">
                  <div v-for="(option, index) in options" :key="index" class="poll-dialog__option">
                    <span class="poll-dialog__option-index" aria-hidden="true">{{ index + 1 }}</span>
                    <input
                      :value="option"
                      type="text"
                      maxlength="200"
                      class="poll-dialog__input poll-dialog__input--option"
                      :placeholder="`Phương án ${index + 1}`"
                      :aria-label="`Phương án ${index + 1}`"
                      :disabled="submitting"
                      @input="setOption(index, $event.target.value)"
                    />
                    <button
                      v-if="options.length > 2"
                      type="button"
                      class="poll-dialog__option-remove"
                      aria-label="Xoá phương án"
                      :disabled="submitting"
                      @click="removeOption(index)"
                    >
                      <AppIcon name="close" :size="12" />
                    </button>
                  </div>
                </div>

                <button
                  v-if="options.length < MAX_OPTIONS"
                  type="button"
                  class="poll-dialog__add"
                  :disabled="submitting"
                  @click="addOption"
                >
                  <AppIcon name="plus" :size="13" />
                  Thêm
                </button>
              </section>
            </div>
          </div>

          <div class="poll-dialog__actions">
            <button type="button" class="poll-dialog__btn poll-dialog__btn--ghost" :disabled="submitting" @click="close">
              Huỷ
            </button>
            <button
              type="button"
              class="poll-dialog__btn poll-dialog__btn--primary"
              :disabled="!canSubmit"
              @click="submit"
            >
              {{ submitting ? 'Đang đăng...' : 'Đăng' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.poll-dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.poll-dialog__panel {
  width: min(40rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.poll-dialog__head,
.poll-dialog__actions {
  flex-shrink: 0;
}

.poll-dialog__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.7rem 0.9rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.poll-dialog__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.85rem;
  height: 1.85rem;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.poll-dialog__head-copy {
  flex: 1;
  min-width: 0;
}

.poll-dialog__title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.25;
}

.poll-dialog__sub {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
}

.poll-dialog__close {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.poll-dialog__close:hover:not(:disabled) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.poll-dialog__close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.poll-dialog__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow-y: auto;
  padding: 0.85rem 0.9rem;
}

.poll-dialog__form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 5.5rem;
  grid-template-areas:
    'title title image'
    'content content image'
    'multi deadline deadline'
    'options options options';
  gap: var(--space-2);
  align-content: start;
}

.poll-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.poll-dialog__field--title { grid-area: title; }
.poll-dialog__field--content { grid-area: content; }
.poll-dialog__field--image { grid-area: image; align-self: stretch; }
.poll-dialog__field--multi { grid-area: multi; }
.poll-dialog__field--deadline { grid-area: deadline; }

.poll-dialog__label {
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.poll-dialog__required {
  color: var(--color-primary);
}

.poll-dialog__input {
  width: 100%;
  min-width: 0;
  min-height: 2.125rem;
  padding: 0.35rem 0.65rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.8125rem;
  line-height: 1.35;
}

.poll-dialog__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
  border-color: var(--color-primary);
}

.poll-dialog__input:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  background: var(--color-surface-muted);
}

.poll-dialog__seg {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.12rem;
  padding: 0.12rem;
  min-height: 2.125rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.poll-dialog__seg-opt {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: calc(var(--radius-md) - 2px);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.poll-dialog__seg-opt--on {
  background: var(--color-surface);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.poll-dialog__seg-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.poll-dialog__hours {
  display: flex;
  align-items: stretch;
  gap: var(--space-2);
  min-width: 0;
}

.poll-dialog__stepper {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  min-height: 2.125rem;
  padding: 0.12rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.poll-dialog__step {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.85rem;
  height: 1.85rem;
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.poll-dialog__step:hover:not(:disabled) {
  color: var(--color-primary);
}

.poll-dialog__step:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.poll-dialog__hours-input {
  flex: 1;
  min-width: 0;
  width: 2.5rem;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 700;
  text-align: center;
  appearance: textfield;
  -moz-appearance: textfield;
}

.poll-dialog__hours-input::-webkit-outer-spin-button,
.poll-dialog__hours-input::-webkit-inner-spin-button {
  appearance: none;
  margin: 0;
}

.poll-dialog__hours-input:focus {
  outline: none;
}

.poll-dialog__hours-unit {
  flex-shrink: 0;
  padding-right: 0.15rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.poll-dialog__presets {
  display: flex;
  flex-shrink: 0;
  align-items: stretch;
  gap: 0.2rem;
}

.poll-dialog__preset {
  min-width: 2.15rem;
  padding: 0 0.4rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.poll-dialog__preset:hover:not(:disabled) {
  color: var(--color-text);
}

.poll-dialog__preset--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 22%, transparent);
}

.poll-dialog__image-wrap {
  position: relative;
  flex: 1;
  min-height: 4.75rem;
}

.poll-dialog__image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-height: 4.75rem;
  padding: 0;
  overflow: hidden;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.poll-dialog__image:hover:not(:disabled) {
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
}

.poll-dialog__image:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.poll-dialog__image-preview {
  width: 100%;
  height: 100%;
  min-height: 4.75rem;
  object-fit: cover;
}

.poll-dialog__image-empty {
  display: flex;
}

.poll-dialog__image-clear {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.poll-dialog__file {
  display: none;
}

.poll-dialog__options {
  grid-area: options;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  padding: 0.55rem 0.65rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.poll-dialog__options-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.poll-dialog__options-count {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.poll-dialog__options-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.4rem;
}

.poll-dialog__option {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  min-width: 0;
}

.poll-dialog__option-index {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.poll-dialog__input--option {
  min-height: 2rem;
  padding: 0.25rem 0.5rem;
}

.poll-dialog__option-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.poll-dialog__option-remove:hover:not(:disabled) {
  color: var(--color-text);
}

.poll-dialog__add {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}

.poll-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.65rem 0.9rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.poll-dialog__btn {
  padding: 0.4rem 0.85rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.poll-dialog__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.poll-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.poll-dialog__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.poll-dialog__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.poll-dialog__btn--ghost:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.poll-dialog-fade-enter-active,
.poll-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.poll-dialog-fade-enter-from,
.poll-dialog-fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .poll-dialog {
    padding: var(--space-4);
    align-items: flex-end;
  }

  .poll-dialog__panel {
    width: 100%;
    max-height: min(92vh, calc(100vh - 2rem));
  }

  .poll-dialog__form {
    grid-template-columns: minmax(0, 1fr) 5.25rem;
    grid-template-areas:
      'title image'
      'content content'
      'multi deadline'
      'options options';
  }

  .poll-dialog__hours {
    flex-wrap: wrap;
  }

  .poll-dialog__options-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 420px) {
  .poll-dialog__form {
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
      'title'
      'content'
      'multi'
      'deadline'
      'image'
      'options';
  }
}

@media (prefers-reduced-motion: reduce) {
  .poll-dialog-fade-enter-active,
  .poll-dialog-fade-leave-active {
    transition: none;
  }
}
</style>
