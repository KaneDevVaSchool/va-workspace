<script setup>
//
// Picker chọn "Vị trí đánh giá" cho form Mẫu đánh giá — tách từ
// EvaluationTemplateList.vue. Hiện 2 nhóm riêng theo `kind`: Chức danh
// (kind='position') và Phòng ban (kind='department') — cả 2 ghi chung vào
// 1 modelValue (position_ids). Xem migration
// 2026_08_26_140001_add_kind_to_evaluation_positions_table.php.
//
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // position_ids
  positions: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'request-create']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));

const positionKindItems = computed(() => props.positions.filter((p) => p.kind !== 'department'));
const departmentKindItems = computed(() => props.positions.filter((p) => p.kind === 'department'));

function toggle(positionId) {
  const id = String(positionId);
  if (selectedIds.value.has(id)) {
    emit('update:modelValue', props.modelValue.filter((pid) => String(pid) !== id));
  } else {
    emit('update:modelValue', [...props.modelValue, positionId]);
  }
}
</script>

<template>
  <div class="evtpl-position-groups">
    <div class="evtpl-position-group">
      <span class="evtpl-position-group__title">Chức danh</span>
      <div class="evtpl-position-picker">
        <label
          v-for="position in positionKindItems"
          :key="position.id"
          class="evtpl-position-picker__chip"
          :class="{ 'evtpl-position-picker__chip--on': selectedIds.has(String(position.id)) }"
        >
          <input
            type="checkbox"
            :checked="selectedIds.has(String(position.id))"
            :disabled="disabled"
            @change="toggle(position.id)"
          />
          <span>{{ position.name }}</span>
        </label>
        <p v-if="positionKindItems.length === 0" class="evtpl-criteria-picker__empty">Chưa có chức danh nào.</p>
      </div>
    </div>

    <div class="evtpl-position-group">
      <span class="evtpl-position-group__title">Phòng ban</span>
      <div class="evtpl-position-picker">
        <label
          v-for="position in departmentKindItems"
          :key="position.id"
          class="evtpl-position-picker__chip"
          :class="{ 'evtpl-position-picker__chip--on': selectedIds.has(String(position.id)) }"
        >
          <input
            type="checkbox"
            :checked="selectedIds.has(String(position.id))"
            :disabled="disabled"
            @change="toggle(position.id)"
          />
          <span>{{ position.name }}</span>
        </label>
        <p v-if="departmentKindItems.length === 0" class="evtpl-criteria-picker__empty">Chưa có phòng ban nào.</p>
      </div>
    </div>

    <button type="button" class="evtpl-position-picker__add" :disabled="disabled" @click="emit('request-create')">
      <AppIcon name="plus" :size="14" :stroke-width="1.75" />
      <span>Thêm vị trí</span>
    </button>
  </div>
</template>

<style scoped>
.evtpl-position-groups {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.evtpl-position-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.evtpl-position-group__title {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.evtpl-position-picker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.evtpl-position-picker__chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  cursor: pointer;
}

.evtpl-position-picker__chip input {
  /* Giữ checkbox thật cho a11y nhưng ẩn hình, trạng thái thể hiện qua chip */
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.evtpl-position-picker__chip--on {
  border-color: var(--color-primary);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
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

.evtpl-criteria-picker__empty {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  margin: 0;
}
</style>
