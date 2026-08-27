<script setup>
//
// Picker chọn "Vị trí đánh giá" cho form Mẫu đánh giá — tách từ
// EvaluationTemplateList.vue. Hiện 2 nhóm riêng theo `kind`: Chức danh
// (kind='position') và Phòng ban (kind='department') — cả 2 ghi chung vào
// 1 modelValue (position_ids). Xem migration
// 2026_08_26_140001_add_kind_to_evaluation_positions_table.php.
//
// Mỗi nhóm là 1 ô input gõ-tìm (autocomplete) trong danh sách nội bộ
// `evaluation_positions` đã load sẵn (props.positions, không phân trang —
// xem EvaluationPositionController::index()); các mục đã chọn hiện thành
// chip có nút xoá bên dưới ô input. Không còn tạo tay ở đây — danh mục
// vị trí đánh giá sẽ nối API VA-HRM sau này (định danh HRM lưu vào cột
// hrm_position_uuid đã có sẵn ở bảng evaluation_positions).
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // position_ids
  positions: { type: Array, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => new Set(props.modelValue.map((id) => String(id))));

const positionKindItems = computed(() => props.positions.filter((p) => p.kind !== 'department'));
const departmentKindItems = computed(() => props.positions.filter((p) => p.kind === 'department'));

const positionQuery = ref('');
const departmentQuery = ref('');
const positionQueryFocused = ref(false);
const departmentQueryFocused = ref(false);

function matches(items, query) {
  const q = query.trim().toLowerCase();
  if (!q) return [];
  return items.filter((p) => p.name.toLowerCase().includes(q) && !selectedIds.value.has(String(p.id)));
}

const positionMatches = computed(() => matches(positionKindItems.value, positionQuery.value));
const departmentMatches = computed(() => matches(departmentKindItems.value, departmentQuery.value));

const selectedPositionItems = computed(() => positionKindItems.value.filter((p) => selectedIds.value.has(String(p.id))));
const selectedDepartmentItems = computed(() => departmentKindItems.value.filter((p) => selectedIds.value.has(String(p.id))));

function select(positionId) {
  const id = String(positionId);
  if (!selectedIds.value.has(id)) {
    emit('update:modelValue', [...props.modelValue, positionId]);
  }
}

function remove(positionId) {
  const id = String(positionId);
  emit('update:modelValue', props.modelValue.filter((pid) => String(pid) !== id));
}

function pickPosition(item) {
  select(item.id);
  positionQuery.value = '';
}

function pickDepartment(item) {
  select(item.id);
  departmentQuery.value = '';
}
</script>

<template>
  <div class="evtpl-position-groups">
    <div class="evtpl-position-group">
      <span class="evtpl-position-group__title">Chức danh</span>
      <div class="evtpl-position-autocomplete">
        <input
          v-model="positionQuery"
          type="search"
          class="evtpl-page__input"
          placeholder="Gõ để tìm chức danh…"
          :disabled="disabled"
          @focus="positionQueryFocused = true"
          @blur="positionQueryFocused = false"
        />
        <ul
          v-if="positionQueryFocused && positionQuery.trim()"
          class="evtpl-position-autocomplete__list hide-scrollbar"
          role="listbox"
        >
          <li
            v-for="item in positionMatches"
            :key="item.id"
            class="evtpl-position-autocomplete__option"
            @mousedown.prevent="pickPosition(item)"
          >
            {{ item.name }}
          </li>
          <li v-if="positionMatches.length === 0" class="evtpl-position-autocomplete__empty">
            Không tìm thấy chức danh khớp «{{ positionQuery }}».
          </li>
        </ul>
      </div>
      <div class="evtpl-position-picker">
        <span
          v-for="item in selectedPositionItems"
          :key="item.id"
          class="evtpl-position-picker__chip evtpl-position-picker__chip--on"
        >
          <span>{{ item.name }}</span>
          <button
            type="button"
            class="evtpl-position-picker__chip-remove"
            aria-label="Bỏ chức danh này"
            :disabled="disabled"
            @click="remove(item.id)"
          >
            <AppIcon name="close" :size="11" />
          </button>
        </span>
        <p v-if="positionKindItems.length === 0" class="evtpl-criteria-picker__empty">Chưa có chức danh nào.</p>
      </div>
    </div>

    <div class="evtpl-position-group">
      <span class="evtpl-position-group__title">Phòng ban</span>
      <div class="evtpl-position-autocomplete">
        <input
          v-model="departmentQuery"
          type="search"
          class="evtpl-page__input"
          placeholder="Gõ để tìm phòng ban…"
          :disabled="disabled"
          @focus="departmentQueryFocused = true"
          @blur="departmentQueryFocused = false"
        />
        <ul
          v-if="departmentQueryFocused && departmentQuery.trim()"
          class="evtpl-position-autocomplete__list hide-scrollbar"
          role="listbox"
        >
          <li
            v-for="item in departmentMatches"
            :key="item.id"
            class="evtpl-position-autocomplete__option"
            @mousedown.prevent="pickDepartment(item)"
          >
            {{ item.name }}
          </li>
          <li v-if="departmentMatches.length === 0" class="evtpl-position-autocomplete__empty">
            Không tìm thấy phòng ban khớp «{{ departmentQuery }}».
          </li>
        </ul>
      </div>
      <div class="evtpl-position-picker">
        <span
          v-for="item in selectedDepartmentItems"
          :key="item.id"
          class="evtpl-position-picker__chip evtpl-position-picker__chip--on"
        >
          <span>{{ item.name }}</span>
          <button
            type="button"
            class="evtpl-position-picker__chip-remove"
            aria-label="Bỏ phòng ban này"
            :disabled="disabled"
            @click="remove(item.id)"
          >
            <AppIcon name="close" :size="11" />
          </button>
        </span>
        <p v-if="departmentKindItems.length === 0" class="evtpl-criteria-picker__empty">Chưa có phòng ban nào.</p>
      </div>
    </div>
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

.evtpl-position-autocomplete {
  position: relative;
}

.evtpl-position-autocomplete__list {
  position: absolute;
  z-index: 10;
  top: calc(100% + 0.25rem);
  left: 0;
  right: 0;
  max-height: 12rem;
  overflow-y: auto;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.12));
}

.evtpl-position-autocomplete__option {
  padding: 0.4375rem 0.625rem;
  border-radius: var(--radius-sm);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.evtpl-position-autocomplete__option:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.evtpl-position-autocomplete__empty {
  padding: 0.4375rem 0.625rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
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
  padding: 0.375rem 0.625rem 0.375rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.evtpl-position-picker__chip--on {
  border-color: var(--color-primary);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.evtpl-position-picker__chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.125rem;
  height: 1.125rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.evtpl-position-picker__chip-remove:hover {
  background: color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.evtpl-position-picker__chip-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.evtpl-criteria-picker__empty {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  margin: 0;
}

/* Tái dùng input chung của trang cha (khai báo lại vì scoped style không kế thừa) */
.evtpl-page__input {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  width: 100%;
}

.evtpl-page__input:focus {
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}
</style>
