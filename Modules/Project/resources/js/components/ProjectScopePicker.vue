<script setup>
//
// Phạm vi triển khai — mỗi dự án chỉ chọn 1 option. Khi chọn
// "Phòng Ban/Bộ Phận" hiện thêm autocomplete chọn đúng 1 phòng ban.
//
import { computed } from 'vue';
import ProjectDepartmentPicker from './ProjectDepartmentPicker.vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  scopeTypeOptions: { type: Array, required: true },
  departments: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const current = computed(() => props.modelValue[0] || null);
const currentType = computed(() => current.value?.scope_type || '');
const currentDepartmentIds = computed(() =>
  current.value?.department_id ? [current.value.department_id] : [],
);

function emitScope(scopeType, departmentId = null) {
  if (!scopeType) {
    emit('update:modelValue', []);
    return;
  }
  emit('update:modelValue', [
    {
      scope_type: scopeType,
      department_id: scopeType === 'department' ? departmentId : null,
      weight_percent: 100,
    },
  ]);
}

function selectType(value) {
  if (currentType.value === value) return;
  emitScope(value, value === 'department' ? current.value?.department_id ?? null : null);
}

function onDepartmentIds(ids) {
  emitScope('department', ids[0] ?? null);
}
</script>

<template>
  <div class="proj-scope-picker">
    <div class="proj-scope-picker__cards" role="radiogroup" aria-label="Phạm vi triển khai">
      <button
        v-for="opt in scopeTypeOptions"
        :key="opt.value"
        type="button"
        class="proj-scope-picker__card"
        :class="{ 'proj-scope-picker__card--on': currentType === opt.value }"
        role="radio"
        :aria-checked="currentType === opt.value ? 'true' : 'false'"
        :disabled="disabled"
        @click="selectType(opt.value)"
      >
        <span class="proj-scope-picker__radio" aria-hidden="true" />
        <span class="proj-scope-picker__card-label">{{ opt.label }}</span>
      </button>
    </div>

    <div v-if="currentType === 'department'" class="proj-scope-picker__dept">
      <span class="proj-scope-picker__dept-label">Phòng ban / bộ phận</span>
      <p class="proj-scope-picker__dept-hint">Chọn 1 phòng ban áp dụng.</p>
      <ProjectDepartmentPicker
        :model-value="currentDepartmentIds"
        :departments="departments"
        :disabled="disabled"
        :multiple="false"
        search-label="Tìm phòng ban / bộ phận"
        placeholder="Tìm phòng ban / bộ phận"
        @update:model-value="onDepartmentIds"
      />
    </div>
  </div>
</template>

<style scoped>
.proj-scope-picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.proj-scope-picker__cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.proj-scope-picker__card {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.proj-scope-picker__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.proj-scope-picker__card--on {
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px var(--color-primary-200);
}

.proj-scope-picker__card--on::before {
  background: var(--color-primary);
}

.proj-scope-picker__card:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.proj-scope-picker__radio {
  flex-shrink: 0;
  width: 1rem;
  height: 1rem;
  margin-top: 0.125rem;
  border-radius: var(--radius-full);
  box-shadow: inset 0 0 0 1.5px var(--color-border);
  background: var(--color-surface);
}

.proj-scope-picker__card--on .proj-scope-picker__radio {
  box-shadow: inset 0 0 0 4px var(--color-primary);
}

.proj-scope-picker__card-label {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.4;
}

.proj-scope-picker__dept {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.proj-scope-picker__dept-label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.proj-scope-picker__dept-hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

@media (max-width: 768px) {
  .proj-scope-picker__cards {
    grid-template-columns: 1fr;
  }
}
</style>
