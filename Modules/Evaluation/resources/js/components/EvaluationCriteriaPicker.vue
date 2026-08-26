<script setup>
//
// Picker chọn tiêu chí đánh giá cho form Mẫu đánh giá — tách từ
// EvaluationTemplateList.vue để dùng chung cho dialog Tạo mới và Sửa
// (2 dialog riêng biệt, không share state form, nhưng share block UI này).
//
// Luôn hiện tên phòng ban nguồn cạnh mỗi tiêu chí (kể cả mẫu không dùng
// chung) và gợi ý thang điểm/mức điểm ngắn gọn — trước đây chỉ hiện tên nên
// nhiều tiêu chí trùng tên giữa các phòng ban (do seed demo) trông như bị
// trùng lặp và không rõ điểm cần có.
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  modelValue: { type: Array, required: true }, // [{ evaluation_criteria_id, weight_label, required_score, count_in_total }]
  pool: { type: Array, required: true },
  isGlobal: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  weightOptions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');

const availableCriteria = computed(() => {
  const q = query.value.trim().toLowerCase();
  return props.pool.filter((c) => {
    if (!c.is_active) return false;
    if (!q) return true;
    return c.name.toLowerCase().includes(q);
  });
});

const selectedIds = computed(() => new Set(props.modelValue.map((row) => String(row.evaluation_criteria_id))));

function criterionById(id) {
  return props.pool.find((c) => String(c.id) === String(id)) ?? null;
}

/** Gợi ý ngắn gọn thang điểm/mức điểm — dùng chung cho ô picker và danh sách đã chọn. */
function criterionScoreHint(criterion) {
  if (!criterion) return '';
  const levels = criterion.levels ?? [];
  if (criterion.type === 'scale') {
    return `Điểm tối đa: ${criterion.max_score ?? '—'}`;
  }
  if (levels.length === 0) return '';
  const shown = levels.slice(0, 2).map((l) => {
    const score = Number(l.score ?? 0);
    const sign = score > 0 ? '+' : '';
    return `${l.label} (${sign}${score})`;
  });
  return levels.length > 2 ? `${shown.join(', ')}, …` : shown.join(', ');
}

function toggleCriterion(criterionId) {
  const id = String(criterionId);
  const idx = props.modelValue.findIndex((row) => String(row.evaluation_criteria_id) === id);
  if (idx !== -1) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== idx));
    return;
  }
  emit('update:modelValue', [
    ...props.modelValue,
    { evaluation_criteria_id: criterionId, weight_label: 'quan_trong', required_score: null, count_in_total: true },
  ]);
}

function removeRow(criterionId) {
  const id = String(criterionId);
  emit('update:modelValue', props.modelValue.filter((row) => String(row.evaluation_criteria_id) !== id));
}

function updateRow(criterionId, patch) {
  const id = String(criterionId);
  emit(
    'update:modelValue',
    props.modelValue.map((row) => (String(row.evaluation_criteria_id) === id ? { ...row, ...patch } : row)),
  );
}
</script>

<template>
  <div class="evtpl-criteria-picker">
    <div class="evtpl-criteria-picker__list hide-scrollbar">
      <input
        v-model="query"
        type="search"
        class="evtpl-page__input"
        placeholder="Tìm tiêu chí…"
        :disabled="disabled"
      />
      <ul class="evtpl-criteria-picker__options hide-scrollbar" role="listbox" aria-label="Chọn tiêu chí">
        <li v-if="loading" class="evtpl-criteria-picker__empty">Đang tải…</li>
        <li v-else-if="availableCriteria.length === 0" class="evtpl-criteria-picker__empty">
          {{ isGlobal ? 'Chưa có tiêu chí đang hoạt động nào trong hệ thống.' : 'Phòng ban chưa có tiêu chí đang hoạt động nào.' }}
        </li>
        <li v-for="criterion in availableCriteria" :key="criterion.id" class="evtpl-criteria-picker__option">
          <label class="evtpl-criteria-picker__checkbox">
            <input
              type="checkbox"
              :checked="selectedIds.has(String(criterion.id))"
              :disabled="disabled"
              @change="toggleCriterion(criterion.id)"
            />
            <span class="evtpl-criteria-picker__copy">
              <span>
                {{ criterion.name }}
                <span class="evtpl-criteria-picker__dept">— {{ criterion.department?.name || '—' }}</span>
              </span>
              <span v-if="criterionScoreHint(criterion)" class="evtpl-criteria-picker__hint">
                {{ criterionScoreHint(criterion) }}
              </span>
            </span>
          </label>
        </li>
      </ul>
    </div>

    <div class="evtpl-criteria-picker__selected hide-scrollbar">
      <p v-if="modelValue.length === 0" class="evtpl-criteria-picker__empty">
        Chưa chọn tiêu chí nào. Chọn ở danh sách bên trái.
      </p>
      <div v-for="row in modelValue" :key="row.evaluation_criteria_id" class="evtpl-criteria-row">
        <div class="evtpl-criteria-row__head">
          <span class="evtpl-criteria-row__name">
            {{ criterionById(row.evaluation_criteria_id)?.name ?? '—' }}
            <span class="evtpl-criteria-picker__dept">
              — {{ criterionById(row.evaluation_criteria_id)?.department?.name || '—' }}
            </span>
            <span
              v-if="criterionScoreHint(criterionById(row.evaluation_criteria_id))"
              class="evtpl-criteria-picker__hint"
            >
              {{ criterionScoreHint(criterionById(row.evaluation_criteria_id)) }}
            </span>
          </span>
          <button
            type="button"
            class="evtpl-page__icon-btn evtpl-page__icon-btn--danger"
            aria-label="Bỏ tiêu chí này khỏi mẫu"
            :disabled="disabled"
            @click="removeRow(row.evaluation_criteria_id)"
          >
            <AppIcon name="close" :size="13" />
          </button>
        </div>
        <div class="evtpl-criteria-row__fields">
          <label class="evtpl-criteria-row__field">
            <span>Trọng số</span>
            <select
              :value="row.weight_label"
              class="evtpl-page__input"
              :disabled="disabled"
              @change="updateRow(row.evaluation_criteria_id, { weight_label: $event.target.value })"
            >
              <option v-for="opt in weightOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <label class="evtpl-criteria-row__field">
            <span>Điểm yêu cầu</span>
            <input
              type="number"
              min="0"
              class="evtpl-page__input"
              placeholder="Không bắt buộc"
              :value="row.required_score"
              :disabled="disabled"
              @input="updateRow(row.evaluation_criteria_id, { required_score: $event.target.value === '' ? null : Number($event.target.value) })"
            />
          </label>
          <label class="evtpl-criteria-row__checkbox">
            <input
              type="checkbox"
              :checked="row.count_in_total"
              :disabled="disabled"
              @change="updateRow(row.evaluation_criteria_id, { count_in_total: $event.target.checked })"
            />
            <span>Tính vào tổng điểm</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.evtpl-criteria-picker {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-4);
  min-height: 16rem;
}

.evtpl-criteria-picker__list,
.evtpl-criteria-picker__selected {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 0;
  max-height: 24rem;
  overflow-y: auto;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.evtpl-criteria-picker__options {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  overflow-y: auto;
}

.evtpl-criteria-picker__option {
  padding: var(--space-1) 0;
}

.evtpl-criteria-picker__checkbox {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  font-size: 0.875rem;
  color: var(--color-text);
  cursor: pointer;
}

.evtpl-criteria-picker__checkbox input[type='checkbox'] {
  margin-top: 0.1875rem;
}

.evtpl-criteria-picker__copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

/* Tên phòng ban nguồn — luôn hiện để phân biệt tiêu chí cùng tên giữa các phòng ban */
.evtpl-criteria-picker__dept {
  color: var(--color-text-muted);
  font-weight: 400;
  font-size: 0.8125rem;
}

/* Gợi ý thang điểm/mức điểm — điểm cần có của tiêu chí */
.evtpl-criteria-picker__hint {
  display: block;
  color: var(--color-text-muted);
  font-weight: 400;
  font-size: 0.75rem;
}

.evtpl-criteria-picker__empty {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  margin: 0;
}

.evtpl-criteria-row {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.evtpl-criteria-row__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.evtpl-criteria-row__name {
  display: flex;
  flex-direction: column;
  gap: 1px;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--color-text);
}

.evtpl-criteria-row__fields {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  align-items: flex-end;
}

.evtpl-criteria-row__field {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.75rem;
  color: var(--color-text-muted);
  min-width: 8rem;
}

.evtpl-criteria-row__checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
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

@media (max-width: 1279px) {
  .evtpl-criteria-picker {
    grid-template-columns: 1fr;
  }
}
</style>
