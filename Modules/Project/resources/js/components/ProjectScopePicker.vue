<script setup>
//
// Danh sách động "Phạm vi triển khai" cho form Dự án. Mỗi dòng: chọn phạm
// vi (Hội Sở/HT/KV/Phòng Ban), nếu chọn "Phòng Ban" hiện thêm select chọn
// phòng ban cụ thể, input % tỷ trọng KPI, nút xoá dòng. Tổng % KHÔNG bắt
// buộc phải bằng 100 (theo ProjectService — chỉ validate 0..100 từng dòng).
//
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // [{ scope_type, department_id, weight_percent }]
  scopeTypeOptions: { type: Array, required: true }, // [{ value, label }]
  departments: { type: Array, required: true }, // [{ id, name }]
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function addRow() {
  emit('update:modelValue', [
    ...props.modelValue,
    { scope_type: props.scopeTypeOptions[0]?.value || '', department_id: null, weight_percent: 0 },
  ]);
}

function removeRow(index) {
  emit(
    'update:modelValue',
    props.modelValue.filter((_, i) => i !== index),
  );
}

function updateRow(index, patch) {
  const next = props.modelValue.map((row, i) => (i === index ? { ...row, ...patch } : row));
  emit('update:modelValue', next);
}

function onScopeTypeChange(index, value) {
  const patch = { scope_type: value };
  if (value !== 'department') {
    patch.department_id = null;
  }
  updateRow(index, patch);
}
</script>

<template>
  <div class="proj-scope-picker">
    <div v-for="(row, index) in modelValue" :key="index" class="proj-scope-picker__row">
      <div class="proj-scope-picker__field proj-scope-picker__field--type">
        <label class="proj-scope-picker__label" :for="`proj-scope-type-${index}`">Phạm vi</label>
        <select
          :id="`proj-scope-type-${index}`"
          class="proj-page__input"
          :value="row.scope_type"
          :disabled="disabled"
          @change="onScopeTypeChange(index, $event.target.value)"
        >
          <option v-for="opt in scopeTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div v-if="row.scope_type === 'department'" class="proj-scope-picker__field proj-scope-picker__field--dept">
        <label class="proj-scope-picker__label" :for="`proj-scope-dept-${index}`">Phòng ban</label>
        <select
          :id="`proj-scope-dept-${index}`"
          class="proj-page__input"
          :value="row.department_id ?? ''"
          :disabled="disabled"
          @change="updateRow(index, { department_id: $event.target.value ? Number($event.target.value) : null })"
        >
          <option value="">Chọn phòng ban…</option>
          <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
        </select>
      </div>

      <div class="proj-scope-picker__field proj-scope-picker__field--weight">
        <label class="proj-scope-picker__label" :for="`proj-scope-weight-${index}`">Tỷ trọng % KPI</label>
        <input
          :id="`proj-scope-weight-${index}`"
          type="number"
          min="0"
          max="100"
          step="0.5"
          class="proj-page__input"
          :value="row.weight_percent"
          :disabled="disabled"
          @input="updateRow(index, { weight_percent: $event.target.value === '' ? 0 : Number($event.target.value) })"
        />
      </div>

      <button
        type="button"
        class="proj-scope-picker__remove"
        aria-label="Xoá phạm vi này"
        :disabled="disabled"
        @click="removeRow(index)"
      >
        <AppIcon name="trash" :size="16" />
      </button>
    </div>

    <p v-if="modelValue.length === 0" class="proj-scope-picker__hint">Chưa có phạm vi triển khai nào.</p>

    <button type="button" class="proj-scope-picker__add" :disabled="disabled" @click="addRow">
      <AppIcon name="plus" :size="14" />
      Thêm phạm vi
    </button>
  </div>
</template>

<style scoped>
.proj-scope-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.proj-scope-picker__row {
  display: grid;
  grid-template-columns: 1fr 1fr 10rem auto;
  align-items: end;
  gap: var(--space-2);
}

.proj-scope-picker__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.proj-scope-picker__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.proj-scope-picker__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-danger);
  cursor: pointer;
}

.proj-scope-picker__remove:hover:not(:disabled) {
  background: color-mix(in srgb, var(--color-danger) 12%, var(--color-surface-muted));
}

.proj-scope-picker__remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-scope-picker__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.proj-scope-picker__add {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.4375rem 0.75rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.proj-scope-picker__add:hover:not(:disabled) {
  background: var(--color-primary-surface);
}

.proj-scope-picker__add:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.proj-page__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  width: 100%;
}

.proj-page__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

@media (max-width: 768px) {
  .proj-scope-picker__row {
    grid-template-columns: 1fr;
  }

  .proj-scope-picker__remove {
    justify-self: flex-end;
  }
}
</style>
