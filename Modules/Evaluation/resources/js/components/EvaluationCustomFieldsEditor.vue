<script setup>
//
// Trường tùy biến (PR5 giai đoạn C) — chỉ định nghĩa field, chưa có giá trị
// thật (chờ phiếu đánh giá Giai đoạn D). Tách từ EvaluationTemplateList.vue.
//
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // [{ label, field_type, options, is_required }]
  disabled: { type: Boolean, default: false },
  fieldTypes: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

function updateField(index, patch) {
  const next = props.modelValue.map((f, i) => (i === index ? { ...f, ...patch } : f));
  emit('update:modelValue', next);
}

function addField() {
  emit('update:modelValue', [...props.modelValue, { label: '', field_type: 'text', options: [], is_required: false }]);
}

function removeField(index) {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
}

function onTypeChange(index, fieldType) {
  const field = props.modelValue[index];
  const options = fieldType !== 'select' ? [] : field.options.length === 0 ? [''] : field.options;
  updateField(index, { field_type: fieldType, options });
}

function addOption(index) {
  const field = props.modelValue[index];
  updateField(index, { options: [...field.options, ''] });
}

function updateOption(index, optIndex, value) {
  const field = props.modelValue[index];
  const options = field.options.map((o, i) => (i === optIndex ? value : o));
  updateField(index, { options });
}

function removeOption(index, optIndex) {
  const field = props.modelValue[index];
  updateField(index, { options: field.options.filter((_, i) => i !== optIndex) });
}
</script>

<template>
  <div class="evtpl-custom-fields">
    <div v-for="(field, index) in modelValue" :key="index" class="evtpl-custom-field-row">
      <div class="evtpl-custom-field-row__main">
        <label class="evtpl-custom-field-row__field">
          <span>Nhãn hiển thị</span>
          <input
            type="text"
            class="evtpl-page__input"
            placeholder="VD: Nhận xét thêm của quản lý…"
            maxlength="255"
            :value="field.label"
            :disabled="disabled"
            @input="updateField(index, { label: $event.target.value })"
          />
        </label>
        <label class="evtpl-custom-field-row__field">
          <span>Loại trường</span>
          <select
            :value="field.field_type"
            class="evtpl-page__input"
            :disabled="disabled"
            @change="onTypeChange(index, $event.target.value)"
          >
            <option v-for="opt in fieldTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </label>
        <label class="evtpl-criteria-row__checkbox">
          <input
            type="checkbox"
            :checked="field.is_required"
            :disabled="disabled"
            @change="updateField(index, { is_required: $event.target.checked })"
          />
          <span>Bắt buộc nhập</span>
        </label>
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

      <div v-if="field.field_type === 'select'" class="evtpl-custom-field-row__options">
        <div v-for="(opt, optIndex) in field.options" :key="optIndex" class="evtpl-custom-field-row__option">
          <input
            type="text"
            class="evtpl-page__input"
            placeholder="Tùy chọn…"
            maxlength="255"
            :value="opt"
            :disabled="disabled"
            @input="updateOption(index, optIndex, $event.target.value)"
          />
          <button
            type="button"
            class="evtpl-page__icon-btn"
            aria-label="Bỏ tùy chọn này"
            :disabled="disabled"
            @click="removeOption(index, optIndex)"
          >
            <AppIcon name="close" :size="13" />
          </button>
        </div>
        <button type="button" class="evtpl-position-picker__add" :disabled="disabled" @click="addOption(index)">
          <AppIcon name="plus" :size="13" :stroke-width="1.75" />
          <span>Thêm tùy chọn</span>
        </button>
      </div>
    </div>

    <button type="button" class="evtpl-position-picker__add" :disabled="disabled" @click="addField">
      <AppIcon name="plus" :size="14" :stroke-width="1.75" />
      <span>Thêm trường tùy biến</span>
    </button>
  </div>
</template>

<style scoped>
.evtpl-custom-fields {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.evtpl-custom-field-row {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.evtpl-custom-field-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--space-2);
}

.evtpl-custom-field-row__field {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  min-width: 12rem;
  flex: 1;
}

.evtpl-custom-field-row__options {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-left: var(--space-4);
}

.evtpl-custom-field-row__option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.evtpl-custom-field-row__option .evtpl-page__input {
  flex: 1;
}

.evtpl-criteria-row__checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.evtpl-position-picker__add {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.375rem 0.75rem;
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  cursor: pointer;
  align-self: flex-start;
}

.evtpl-position-picker__add:hover {
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.evtpl-page__input {
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
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
</style>
