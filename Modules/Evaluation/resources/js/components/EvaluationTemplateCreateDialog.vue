<script setup>
//
// Dialog TẠO MỚI mẫu đánh giá — component riêng biệt, KHÔNG dùng chung state
// với dialog Sửa (EvaluationTemplateEditDialog.vue). Tách từ
// EvaluationTemplateList.vue để tránh rò rỉ dữ liệu form giữa 2 thao tác.
//
import { computed, nextTick, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import EvaluationCriteriaPicker from './EvaluationCriteriaPicker.vue';
import EvaluationCustomFieldsEditor from './EvaluationCustomFieldsEditor.vue';
import EvaluationPositionPicker from './EvaluationPositionPicker.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  allCriteria: { type: Array, required: true },
  globalCriteriaPool: { type: Array, required: true },
  loadingGlobalPool: { type: Boolean, default: false },
  allPositions: { type: Array, required: true },
  canManageGlobal: { type: Boolean, default: false },
  forceGlobal: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'created', 'request-global-pool']);

const form = reactive({
  name: '',
  description: '',
  is_active: true,
  criteria: [],
  position_ids: [],
  is_global: false,
  custom_fields: [],
});
const formErrors = ref({});
const formSaving = ref(false);

const criteriaPool = computed(() => (form.is_global ? props.globalCriteriaPool : props.allCriteria));

const submitLabel = computed(() => (formSaving.value ? 'Đang lưu…' : 'Tạo mẫu'));

function resetForm() {
  form.name = '';
  form.description = '';
  form.is_active = true;
  form.criteria = [];
  form.position_ids = [];
  form.is_global = Boolean(props.forceGlobal);
  form.custom_fields = [];
  formErrors.value = {};
}

function closeDialog() {
  if (formSaving.value) return;
  emit('update:open', false);
}

async function submitForm() {
  if (form.criteria.length === 0) {
    showClientToast('error', 'Mẫu đánh giá phải có ít nhất 1 tiêu chí.');
    return;
  }
  const counted = form.criteria.filter((row) => row.count_in_total);
  if (counted.length === 0) {
    showClientToast('error', 'Phải có ít nhất 1 tiêu chí tính vào tổng điểm.');
    return;
  }
  const totalWeight = counted.reduce((sum, row) => sum + (Number(row.weight_percent) || 0), 0);
  if (totalWeight !== 100) {
    showClientToast('error', `Tổng trọng số các tiêu chí tính vào tổng điểm phải bằng 100% (hiện đang là ${totalWeight}%).`);
    return;
  }
  for (const field of form.custom_fields) {
    if (!field.label.trim()) {
      showClientToast('error', 'Trường tùy biến phải có nhãn hiển thị.');
      return;
    }
  }

  formErrors.value = {};
  formSaving.value = true;

  const payload = {
    name: form.name,
    description: form.description || null,
    is_active: form.is_active,
    criteria: form.criteria.map((row) => ({
      evaluation_criteria_id: row.evaluation_criteria_id,
      weight_percent: row.count_in_total ? row.weight_percent : 0,
      required_score: row.required_score === '' ? null : row.required_score,
      count_in_total: Boolean(row.count_in_total),
    })),
    position_ids: form.position_ids,
    is_global: form.is_global,
    custom_fields: form.custom_fields.map((field) => ({
      label: field.label.trim(),
      field_type: field.field_type,
      options: null,
      is_required: Boolean(field.is_required),
    })),
  };

  try {
    const { data } = await window.axios.post('/api/evaluation/templates', payload);
    emit('created', data.template);
    showClientToast('success', `Đã tạo mẫu đánh giá "${data.template.name}".`);
    emit('update:open', false);
  } catch (err) {
    if (err?.response?.status === 422) {
      formErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không lưu được mẫu đánh giá.');
    }
  } finally {
    formSaving.value = false;
  }
}

function handleKeydown(e) {
  if (e.key !== 'Escape') return;
  closeDialog();
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    resetForm();
    if (form.is_global) emit('request-global-pool');
    nextTick(() => document.getElementById('evtpl-create-name')?.focus());
  },
);

watch(
  () => form.is_global,
  (isGlobal) => {
    if (isGlobal) emit('request-global-pool');
  },
);
</script>

<template>
  <Teleport to="body">
    <Transition name="evtpl-dialog-fade">
      <div
        v-if="open"
        class="evtpl-dialog"
        role="presentation"
        tabindex="-1"
        @mousedown.self="closeDialog"
        @keydown="handleKeydown"
      >
        <div class="evtpl-dialog__panel evtpl-dialog__panel--fill" role="dialog" aria-modal="true" aria-labelledby="evtpl-create-title">
          <div class="evtpl-dialog__head">
            <span class="evtpl-dialog__icon" aria-hidden="true">
              <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
            </span>
            <h2 id="evtpl-create-title" class="evtpl-dialog__title">Tạo mới mẫu đánh giá</h2>
            <button type="button" class="evtpl-dialog__close" aria-label="Đóng" :disabled="formSaving" @click="closeDialog">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="evtpl-dialog__body">
            <div class="evtpl-form hide-scrollbar">
              <div class="evtpl-form__section evtpl-form__section--general">
                <span class="evtpl-form__section-title">Thông tin chung</span>
                <div class="evtpl-form__section-grid">
                  <div class="evtpl-form__field evtpl-form__field--name">
                    <label class="evtpl-form__label" for="evtpl-create-name">
                      Tên mẫu đánh giá <span class="evtpl-form__required">*</span>
                    </label>
                    <input
                      id="evtpl-create-name"
                      v-model="form.name"
                      type="text"
                      class="evtpl-page__input"
                      :class="{ 'evtpl-page__input--error': formErrors.name }"
                      placeholder="VD: Đánh giá năng lực trưởng phòng Marketing…"
                      maxlength="255"
                      :disabled="formSaving"
                    />
                    <span v-if="formErrors.name" class="evtpl-form__error">
                      {{ Array.isArray(formErrors.name) ? formErrors.name[0] : formErrors.name }}
                    </span>
                  </div>

                  <div class="evtpl-form__field evtpl-form__field--status">
                    <span id="evtpl-create-status-label" class="evtpl-form__label">Trạng thái</span>
                    <div class="evtpl-form__switch-row">
                      <button
                        type="button"
                        class="evtpl-form__switch"
                        :class="{ 'evtpl-form__switch--on': form.is_active }"
                        role="switch"
                        aria-labelledby="evtpl-create-status-label"
                        :aria-checked="form.is_active ? 'true' : 'false'"
                        :disabled="formSaving"
                        @click="form.is_active = !form.is_active"
                      >
                        <span class="evtpl-form__switch-thumb" aria-hidden="true" />
                      </button>
                      <span class="evtpl-form__switch-text">{{ form.is_active ? 'Hoạt động' : 'Không hoạt động' }}</span>
                    </div>
                  </div>

                  <div v-if="canManageGlobal" class="evtpl-form__field evtpl-form__field--global">
                    <span id="evtpl-create-global-label" class="evtpl-form__label">Dùng chung toàn hệ thống</span>
                    <div class="evtpl-form__switch-row">
                      <button
                        type="button"
                        class="evtpl-form__switch"
                        :class="{ 'evtpl-form__switch--on': form.is_global }"
                        role="switch"
                        aria-labelledby="evtpl-create-global-label"
                        :aria-checked="form.is_global ? 'true' : 'false'"
                        :disabled="formSaving || forceGlobal"
                        @click="form.is_global = !form.is_global"
                      >
                        <span class="evtpl-form__switch-thumb" aria-hidden="true" />
                      </button>
                      <span class="evtpl-form__switch-text">{{ form.is_global ? 'Có' : 'Không' }}</span>
                    </div>
                  </div>

                  <div class="evtpl-form__field evtpl-form__field--desc">
                    <label class="evtpl-form__label" for="evtpl-create-desc">Mô tả</label>
                    <textarea
                      id="evtpl-create-desc"
                      v-model="form.description"
                      class="evtpl-page__textarea"
                      rows="2"
                      placeholder="Ghi chú cách áp dụng mẫu đánh giá…"
                      maxlength="1000"
                      :disabled="formSaving"
                    />
                  </div>
                </div>
              </div>

              <div class="evtpl-form__field evtpl-form__field--criteria">
                <span class="evtpl-form__section-title">
                  Tiêu chí đánh giá &amp; trọng số <span class="evtpl-form__required">*</span>
                </span>
                <p v-if="form.is_global" class="evtpl-page__note">
                  Mẫu này dùng chung toàn hệ thống — được chọn tiêu chí của mọi phòng ban.
                </p>
                <EvaluationCriteriaPicker
                  v-model="form.criteria"
                  :pool="criteriaPool"
                  :is-global="form.is_global"
                  :disabled="formSaving"
                  :loading="form.is_global && loadingGlobalPool"
                />
              </div>

              <div class="evtpl-form__field evtpl-form__field--positions">
                <span class="evtpl-form__section-title">Vị trí đánh giá</span>
                <EvaluationPositionPicker
                  v-model="form.position_ids"
                  :positions="allPositions"
                  :disabled="formSaving"
                />
              </div>

              <div class="evtpl-form__field evtpl-form__field--custom-fields">
                <span class="evtpl-form__section-title">Trường tùy biến</span>
                <EvaluationCustomFieldsEditor
                  v-model="form.custom_fields"
                  :disabled="formSaving"
                />
              </div>
            </div>
          </div>

          <div class="evtpl-dialog__actions">
            <button type="button" class="evtpl-page__btn evtpl-page__btn--ghost" :disabled="formSaving" @click="closeDialog">
              Huỷ
            </button>
            <button type="button" class="evtpl-page__btn" :disabled="formSaving || !form.name.trim()" @click="submitForm">
              {{ submitLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* ── dialog form (form-modal skill) ────────────────────────────────── */

.evtpl-dialog {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay, rgba(0, 0, 0, 0.4));
}

.evtpl-dialog-fade-enter-active,
.evtpl-dialog-fade-leave-active {
  transition: opacity 0.15s ease;
}

.evtpl-dialog-fade-enter-from,
.evtpl-dialog-fade-leave-to {
  opacity: 0;
}

.evtpl-dialog__panel,
.evtpl-dialog__panel--fill {
  display: flex;
  flex-direction: column;
  width: min(90rem, calc(100vw - 2.5rem));
  max-width: calc(100vw - 2.5rem);
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.18);
}

.evtpl-dialog__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
  padding: var(--space-4) var(--space-5);
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-dialog__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  flex-shrink: 0;
}

.evtpl-dialog__title {
  flex: 1;
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
}

.evtpl-dialog__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  flex-shrink: 0;
}

.evtpl-dialog__close:hover {
  background: var(--color-surface-muted);
}

.evtpl-dialog__body {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: var(--space-5);
}

.evtpl-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-4) var(--space-5);
  box-shadow: 0 -1px 0 var(--color-border);
}

/* Form ngang 2-3 cột (form-modal skill) */
.evtpl-form {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto minmax(20rem, 1fr) auto;
  grid-template-areas:
    'general general'
    'criteria criteria'
    'positions custom-fields';
  gap: var(--space-5);
  align-content: stretch;
}

/* Khối "Thông tin chung" — gom name/status/global/desc, tách khỏi các khối
   dưới bằng tiêu đề nhỏ + đường kẻ mỏng (CLAUDE.md §2, không border 1 cạnh). */
.evtpl-form__section--general {
  grid-area: general;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-bottom: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.evtpl-form__section-title {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.evtpl-form__section-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  grid-template-areas:
    'name name status'
    'desc desc global';
  gap: var(--space-4);
}

.evtpl-form__section-grid:not(:has(.evtpl-form__field--global)) {
  grid-template-areas:
    'name name status'
    'desc desc desc';
}

.evtpl-form__field--name { grid-area: name; }
.evtpl-form__field--status { grid-area: status; }
.evtpl-form__field--global { grid-area: global; }
.evtpl-form__field--desc { grid-area: desc; }
.evtpl-form__field--criteria {
  grid-area: criteria;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  min-height: 0;
  overflow: hidden;
  height: 100%;
}

.evtpl-form__field--criteria :deep(.evtpl-criteria-picker) {
  flex: 1;
  min-height: 0;
  height: 100%;
}
.evtpl-form__field--positions {
  grid-area: positions;
  min-height: 0;
}
.evtpl-form__field--custom-fields {
  grid-area: custom-fields;
  min-height: 0;
}

.evtpl-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.evtpl-form__label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.evtpl-form__required {
  color: var(--color-primary);
}

.evtpl-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

/* Toggle trạng thái — cùng pattern eval-page__switch của WorkspaceConfigEvaluation.vue */
.evtpl-form__switch-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  height: 100%;
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
}

.evtpl-page__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
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

.evtpl-page__input--error {
  box-shadow: 0 0 0 1px var(--color-danger);
}

.evtpl-page__textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-family: var(--font-family-base);
  resize: vertical;
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

.evtpl-page__btn:hover {
  background: var(--color-primary-hover);
}

.evtpl-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.evtpl-page__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.evtpl-page__btn--ghost:hover {
  background: var(--color-border);
}

/* ── responsive ─────────────────────────────────────────────────────── */

@media (max-width: 1279px) {
  .evtpl-form {
    grid-template-columns: 1fr;
    grid-template-rows: auto minmax(16rem, 1fr) auto auto;
    grid-template-areas:
      'general'
      'criteria'
      'positions'
      'custom-fields';
  }

  .evtpl-form__section-grid {
    grid-template-columns: 1fr;
    grid-template-areas:
      'name'
      'status'
      'global'
      'desc';
  }
}

@media (max-width: 768px) {
  .evtpl-dialog {
    padding: var(--space-2);
  }

  .evtpl-dialog__panel,
  .evtpl-dialog__panel--fill {
    width: 100%;
    max-width: 100%;
    height: min(94vh, calc(100vh - 1.25rem));
    max-height: min(94vh, calc(100vh - 1.25rem));
  }
}
</style>
