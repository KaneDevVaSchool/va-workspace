<script setup>
//
// Trường tùy biến trên Mẫu đánh giá — chỉ 2 loại:
//   text  — chữ (nhận xét, ghi chú)
//   bonus — điểm phụ thêm, cộng ngoài 100% trọng số tiêu chí
//
import AppIcon from '@/components/AppIcon.vue';

const FIELD_TYPES = [
  { value: 'text', label: 'Chữ' },
  { value: 'bonus', label: 'Điểm phụ thêm' },
];

const props = defineProps({
  modelValue: { type: Array, required: true }, // [{ label, field_type, options, is_required }]
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function updateField(index, patch) {
  const next = props.modelValue.map((f, i) => (i === index ? { ...f, ...patch } : f));
  emit('update:modelValue', next);
}

function addField() {
  emit('update:modelValue', [
    ...props.modelValue,
    { label: '', field_type: 'text', options: [], is_required: false },
  ]);
}

function removeField(index) {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
}

function onTypeChange(index, fieldType) {
  updateField(index, { field_type: fieldType, options: [] });
}
</script>

<template>
  <div class="evtpl-custom-fields">
    <p v-if="modelValue.length === 0" class="evtpl-custom-fields__empty">
      Chưa có trường tùy biến.
    </p>

    <div
      v-for="(field, index) in modelValue"
      :key="index"
      class="evtpl-custom-field"
      :class="{
        'evtpl-custom-field--bonus': field.field_type === 'bonus',
        'evtpl-custom-field--text': field.field_type !== 'bonus',
      }"
    >
      <div class="evtpl-custom-field__cell evtpl-custom-field__cell--type">
        <span :id="`evtpl-cf-type-${index}`" class="evtpl-custom-field__caption">Loại trường</span>
        <div
          class="evtpl-custom-field__type"
          role="group"
          :aria-labelledby="`evtpl-cf-type-${index}`"
        >
          <button
            v-for="opt in FIELD_TYPES"
            :key="opt.value"
            type="button"
            class="evtpl-custom-field__type-btn"
            :class="{ 'evtpl-custom-field__type-btn--on': field.field_type === opt.value }"
            :disabled="disabled"
            :aria-pressed="field.field_type === opt.value ? 'true' : 'false'"
            @click="onTypeChange(index, opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <label class="evtpl-custom-field__cell evtpl-custom-field__cell--label">
        <span class="evtpl-custom-field__caption">Nhãn hiển thị</span>
        <input
          type="text"
          class="evtpl-page__input"
          :placeholder="field.field_type === 'bonus' ? 'VD: Điểm sáng kiến…' : 'VD: Nhận xét của quản lý…'"
          maxlength="255"
          :value="field.label"
          :disabled="disabled"
          @input="updateField(index, { label: $event.target.value })"
        />
      </label>

      <div class="evtpl-custom-field__cell evtpl-custom-field__cell--required">
        <span :id="`evtpl-cf-req-${index}`" class="evtpl-custom-field__caption">Bắt buộc nhập</span>
        <div class="evtpl-custom-field__switch-row">
          <button
            type="button"
            class="evtpl-form__switch"
            :class="{ 'evtpl-form__switch--on': field.is_required }"
            role="switch"
            :aria-labelledby="`evtpl-cf-req-${index}`"
            :aria-checked="field.is_required ? 'true' : 'false'"
            :disabled="disabled"
            @click="updateField(index, { is_required: !field.is_required })"
          >
            <span class="evtpl-form__switch-thumb" aria-hidden="true" />
          </button>
          <span class="evtpl-form__switch-text">{{ field.is_required ? 'Có' : 'Không' }}</span>
        </div>
      </div>

      <button
        type="button"
        class="evtpl-page__icon-btn evtpl-page__icon-btn--danger"
        aria-label="Bỏ trường tùy biến này"
        :disabled="disabled"
        @click="removeField(index)"
      >
        <AppIcon name="trash2" :size="14" />
      </button>
    </div>

    <button type="button" class="evtpl-custom-fields__add-btn" :disabled="disabled" @click="addField">
      <AppIcon name="plus" :size="14" :stroke-width="1.75" />
      <span>Thêm trường</span>
    </button>
  </div>
</template>

<style scoped>
.evtpl-custom-fields {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.evtpl-custom-fields__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.evtpl-custom-field {
  position: relative;
  display: grid;
  grid-template-columns: minmax(14rem, 16rem) minmax(0, 1fr) minmax(8.5rem, auto) 2rem;
  grid-template-areas: 'type label required remove';
  align-items: start;
  column-gap: var(--space-4);
  row-gap: var(--space-3);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.evtpl-custom-field::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.evtpl-custom-field--text::before {
  background: var(--color-info);
}

.evtpl-custom-field--bonus::before {
  background: var(--color-success);
}

.evtpl-custom-field__cell {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.evtpl-custom-field__cell--type { grid-area: type; }
.evtpl-custom-field__cell--label { grid-area: label; }
.evtpl-custom-field__cell--required { grid-area: required; }

.evtpl-custom-field__caption {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.evtpl-custom-field__type {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px;
  min-height: 2.375rem;
  padding: 2px;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.evtpl-custom-field__type-btn {
  min-width: 0;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  cursor: pointer;
}

.evtpl-custom-field__type-btn--on {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.evtpl-custom-field__type-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-custom-field__switch-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.375rem;
}

.evtpl-custom-fields__add-btn {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: var(--space-1);
  padding: 0.375rem 0.75rem;
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  cursor: pointer;
}

.evtpl-custom-fields__add-btn:hover:not(:disabled) {
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.evtpl-custom-fields__add-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-form__switch {
  position: relative;
  flex-shrink: 0;
  width: 2.25rem;
  height: 1.25rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.evtpl-form__switch--on {
  background: var(--color-primary);
}

.evtpl-form__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.evtpl-form__switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-form__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1rem;
  height: 1rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.evtpl-form__switch--on .evtpl-form__switch-thumb {
  transform: translateX(1rem);
}

@media (prefers-reduced-motion: reduce) {
  .evtpl-form__switch-thumb {
    transition: none;
  }
}

.evtpl-form__switch-text {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
  white-space: nowrap;
}

.evtpl-page__input {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
}

.evtpl-page__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.evtpl-page__icon-btn {
  grid-area: remove;
  align-self: end;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2.375rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.evtpl-page__icon-btn:hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.evtpl-page__icon-btn--danger:hover {
  color: var(--color-danger);
}

.evtpl-page__icon-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 900px) {
  .evtpl-custom-field {
    grid-template-columns: minmax(14rem, 1fr) minmax(8.5rem, auto) 2rem;
    grid-template-areas:
      'type required remove'
      'label label label';
  }
}

@media (max-width: 640px) {
  .evtpl-custom-field {
    grid-template-columns: minmax(0, 1fr) 2rem;
    grid-template-areas:
      'type remove'
      'label label'
      'required required';
  }
}
</style>
