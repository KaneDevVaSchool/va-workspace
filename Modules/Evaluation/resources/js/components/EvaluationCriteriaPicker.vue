<script setup>
//
// Picker chọn tiêu chí đánh giá cho form Mẫu đánh giá — tách từ
// EvaluationTemplateList.vue để dùng chung cho dialog Tạo mới và Sửa
// (2 dialog riêng biệt, không share state form, nhưng share block UI này).
//
// Mẫu dùng chung (isGlobal): hiện tên phòng ban nguồn cạnh mỗi tiêu chí để
// phân biệt tiêu chí trùng tên giữa các phòng. Mẫu của 1 phòng ban thì
// không hiện — mọi tiêu chí đã thuộc phòng đang tạo. Gợi ý thang điểm/mức
// điểm ngắn gọn luôn hiện.
//
// Trọng số (weight_percent): % từ 10-100, bước 10 — CHỈ các dòng
// count_in_total = true cộng vào tổng điểm, và tổng nhóm đó phải = 100%.
// Dòng tắt "Tính vào tổng điểm" vẫn nằm trên mẫu (ghi nhận) nhưng weight = 0.
// "Điểm yêu cầu" là select liệt kê đúng các mức điểm hợp lệ của từng tiêu chí
// (0..max_score cho type=scale, hoặc levels[].score cho type=behavior).
//
import { computed, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const WEIGHT_PERCENT_OPTIONS = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

const props = defineProps({
  modelValue: { type: Array, required: true }, // [{ evaluation_criteria_id, weight_percent, required_score, count_in_total }]
  pool: { type: Array, required: true },
  isGlobal: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
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

const countedRows = computed(() => props.modelValue.filter((row) => row.count_in_total));
const skippedCount = computed(() => props.modelValue.length - countedRows.value.length);
const totalWeightPercent = computed(() =>
  countedRows.value.reduce((sum, row) => sum + (Number(row.weight_percent) || 0), 0),
);
const weightIsBalanced = computed(() => {
  if (props.modelValue.length === 0) return true;
  if (countedRows.value.length === 0) return false;
  return totalWeightPercent.value === 100;
});

function criterionById(id) {
  return props.pool.find((c) => String(c.id) === String(id)) ?? null;
}

/** Tên phòng ban nguồn — chỉ hiện khi mẫu dùng chung (pool nhiều phòng). */
function criterionDeptLabel(criterion) {
  if (!props.isGlobal || !criterion?.department?.name) return '';
  return `— ${criterion.department.name}`;
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

/** Danh sách option hợp lệ cho "Điểm yêu cầu" của 1 tiêu chí — value luôn là số hoặc null. */
function requiredScoreOptions(criterion) {
  if (!criterion) return [];
  if (criterion.type === 'scale') {
    const max = Math.max(0, Math.round(Number(criterion.max_score ?? 0)));
    return Array.from({ length: max + 1 }, (_, score) => ({ value: score, label: String(score) }));
  }
  return (criterion.levels ?? []).map((l) => {
    const score = Number(l.score ?? 0);
    const sign = score > 0 ? '+' : '';
    return { value: score, label: `${l.label} (${sign}${score})` };
  });
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
    { evaluation_criteria_id: criterionId, weight_percent: 10, required_score: null, count_in_total: true },
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

function setCountInTotal(criterionId, countInTotal) {
  if (countInTotal) {
    updateRow(criterionId, { count_in_total: true, weight_percent: 10 });
    return;
  }
  updateRow(criterionId, { count_in_total: false, weight_percent: 0 });
}

/** Chia đều 100% cho các tiêu chí TÍNH VÀO TỔNG ĐIỂM, bước 10 — dòng cuối nhận phần dư. */
function distributeEvenly() {
  const countedIds = new Set(countedRows.value.map((row) => String(row.evaluation_criteria_id)));
  const count = countedIds.size;
  if (count === 0) return;

  const base = Math.max(10, Math.floor(100 / count / 10) * 10);
  let countedIndex = 0;
  const rows = props.modelValue.map((row) => {
    if (!countedIds.has(String(row.evaluation_criteria_id))) {
      return { ...row, weight_percent: 0, count_in_total: false };
    }
    const isLast = countedIndex === count - 1;
    const weight_percent = isLast ? 100 - base * (count - 1) : base;
    countedIndex += 1;
    return { ...row, weight_percent };
  });
  emit('update:modelValue', rows);
}
</script>

<template>
  <div class="evtpl-criteria-picker">
    <div class="evtpl-criteria-picker__list hide-scrollbar">
      <span class="evtpl-criteria-picker__pane-title">Kho tiêu chí</span>
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
                <span v-if="criterionDeptLabel(criterion)" class="evtpl-criteria-picker__dept">
                  {{ criterionDeptLabel(criterion) }}
                </span>
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
      <span class="evtpl-criteria-picker__pane-title">Tiêu chí trên mẫu</span>
      <div v-if="modelValue.length > 0" class="evtpl-criteria-picker__summary">
        <div class="evtpl-criteria-picker__summary-copy">
          <span :class="['evtpl-criteria-picker__total', { 'evtpl-criteria-picker__total--warn': !weightIsBalanced }]">
            Tổng trọng số: {{ totalWeightPercent }}% / 100%
          </span>
          <span class="evtpl-criteria-picker__count-meta">
            {{ countedRows.length }} tính vào tổng điểm
            <template v-if="skippedCount > 0"> · {{ skippedCount }} không cộng điểm</template>
          </span>
        </div>
        <button
          type="button"
          class="evtpl-page__btn evtpl-page__btn--ghost evtpl-page__btn--sm"
          :disabled="disabled || countedRows.length === 0"
          @click="distributeEvenly"
        >
          Chia đều 100%
        </button>
      </div>

      <p v-if="modelValue.length === 0" class="evtpl-criteria-picker__empty">
        Chưa chọn tiêu chí nào. Chọn ở danh sách bên trái.
      </p>
      <div
        v-for="row in modelValue"
        :key="row.evaluation_criteria_id"
        class="evtpl-criteria-row"
        :class="{ 'evtpl-criteria-row--skipped': !row.count_in_total }"
      >
        <div class="evtpl-criteria-row__head">
          <span class="evtpl-criteria-row__name">
            {{ criterionById(row.evaluation_criteria_id)?.name ?? '—' }}
            <span
              v-if="criterionDeptLabel(criterionById(row.evaluation_criteria_id))"
              class="evtpl-criteria-picker__dept"
            >
              {{ criterionDeptLabel(criterionById(row.evaluation_criteria_id)) }}
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
          <label v-if="row.count_in_total" class="evtpl-criteria-row__field">
            <span>Trọng số</span>
            <select
              :value="row.weight_percent"
              class="evtpl-page__input"
              :disabled="disabled"
              @change="updateRow(row.evaluation_criteria_id, { weight_percent: Number($event.target.value) })"
            >
              <option v-for="opt in WEIGHT_PERCENT_OPTIONS" :key="opt" :value="opt">{{ opt }}%</option>
            </select>
          </label>
          <span v-else class="evtpl-criteria-row__skipped-weight">Không cộng điểm</span>
          <label class="evtpl-criteria-row__field">
            <span>Điểm yêu cầu</span>
            <select
              :value="row.required_score === null || row.required_score === undefined ? '' : row.required_score"
              class="evtpl-page__input"
              :disabled="disabled"
              @change="updateRow(row.evaluation_criteria_id, { required_score: $event.target.value === '' ? null : Number($event.target.value) })"
            >
              <option value="">Không bắt buộc</option>
              <option
                v-for="opt in requiredScoreOptions(criterionById(row.evaluation_criteria_id))"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </label>
          <div class="evtpl-criteria-row__count">
            <span :id="`evtpl-count-${row.evaluation_criteria_id}`" class="evtpl-criteria-row__count-label">
              Tính vào tổng điểm
            </span>
            <div class="evtpl-criteria-row__count-control">
              <button
                type="button"
                class="evtpl-form__switch"
                :class="{ 'evtpl-form__switch--on': row.count_in_total }"
                role="switch"
                :aria-labelledby="`evtpl-count-${row.evaluation_criteria_id}`"
                :aria-checked="row.count_in_total ? 'true' : 'false'"
                :disabled="disabled"
                @click="setCountInTotal(row.evaluation_criteria_id, !row.count_in_total)"
              >
                <span class="evtpl-form__switch-thumb" aria-hidden="true" />
              </button>
              <span class="evtpl-form__switch-text">{{ row.count_in_total ? 'Có' : 'Không' }}</span>
            </div>
          </div>
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
  flex: 1;
  min-height: 16rem;
  height: 100%;
}

.evtpl-criteria-picker__list,
.evtpl-criteria-picker__selected {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 0;
  max-height: none;
  height: 100%;
  overflow: hidden;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.evtpl-criteria-picker__pane-title {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}

.evtpl-criteria-picker__options {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.evtpl-criteria-picker__selected {
  overflow-y: auto;
}

.evtpl-criteria-picker__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-shrink: 0;
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-criteria-picker__summary-copy {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.evtpl-criteria-picker__total {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.evtpl-criteria-picker__total--warn {
  color: var(--color-danger);
}

.evtpl-criteria-picker__count-meta {
  color: var(--color-text-muted);
  font-size: 0.75rem;
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

/* Tên phòng ban nguồn — chỉ hiện khi mẫu dùng chung (pool nhiều phòng) */
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
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3) var(--space-2) calc(var(--space-2) + 3px + var(--space-3));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.evtpl-criteria-row::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-success);
}

.evtpl-criteria-row--skipped::before {
  background: var(--color-warning);
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

.evtpl-criteria-row__count {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.evtpl-criteria-row__count-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.evtpl-criteria-row__count-control {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.375rem;
}

.evtpl-criteria-row__skipped-weight {
  display: inline-flex;
  align-items: center;
  min-height: 2.375rem;
  color: var(--color-warning-tint-fg, var(--color-warning));
  font-size: 0.8125rem;
  font-weight: 600;
  font-style: italic;
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
  background: var(--color-success);
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

.evtpl-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem 1rem;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.evtpl-page__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.evtpl-page__btn--ghost:hover {
  background: var(--color-border);
}

.evtpl-page__btn--sm {
  padding: 0.3125rem 0.75rem;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.evtpl-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
    min-height: 20rem;
  }
}
</style>
