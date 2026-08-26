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

const WEIGHT_OPTIONS = [
  { value: 'khong_quan_trong', label: 'Không quan trọng' },
  { value: 'quan_trong', label: 'Quan trọng' },
  { value: 'kha_quan_trong', label: 'Khá quan trọng' },
  { value: 'rat_quan_trong', label: 'Rất quan trọng' },
];

const CUSTOM_FIELD_TYPES = [
  { value: 'text', label: 'Chữ' },
  { value: 'number', label: 'Số' },
  { value: 'select', label: 'Lựa chọn' },
  { value: 'date', label: 'Ngày' },
];

const props = defineProps({
  open: { type: Boolean, default: false },
  allCriteria: { type: Array, required: true },
  globalCriteriaPool: { type: Array, required: true },
  loadingGlobalPool: { type: Boolean, default: false },
  allPositions: { type: Array, required: true },
  canManageGlobal: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'created', 'position-created', 'request-global-pool']);

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

// ─── dialog quick-add vị trí đánh giá (state cục bộ) ───────────────────────

const positionDialogOpen = ref(false);
const positionFormSaving = ref(false);
const positionFormErrors = ref({});
const positionForm = reactive({ name: '', kind: 'position', description: '' });

const positionNamePlaceholder = computed(() =>
  positionForm.kind === 'department' ? 'VD: Phòng Kế toán' : 'VD: Trưởng phòng Marketing',
);

function resetForm() {
  form.name = '';
  form.description = '';
  form.is_active = true;
  form.criteria = [];
  form.position_ids = [];
  form.is_global = false;
  form.custom_fields = [];
  formErrors.value = {};
}

function closeDialog() {
  if (formSaving.value) return;
  emit('update:open', false);
}

function openPositionDialog() {
  positionForm.name = '';
  positionForm.kind = 'position';
  positionForm.description = '';
  positionFormErrors.value = {};
  positionDialogOpen.value = true;
  nextTick(() => document.getElementById('evtpl-create-position-name')?.focus());
}

function closePositionDialog() {
  if (positionFormSaving.value) return;
  positionDialogOpen.value = false;
}

async function submitPosition() {
  if (!positionForm.name.trim()) {
    positionFormErrors.value = { name: 'Tên vị trí đánh giá là bắt buộc.' };
    return;
  }
  positionFormErrors.value = {};
  positionFormSaving.value = true;
  try {
    const { data } = await window.axios.post('/api/evaluation/positions', {
      name: positionForm.name.trim(),
      kind: positionForm.kind,
      description: positionForm.description.trim() || null,
    });
    emit('position-created', data.position);
    form.position_ids = [...form.position_ids, data.position.id];
    showClientToast('success', `Đã tạo vị trí «${data.position.name}».`);
    positionDialogOpen.value = false;
  } catch (err) {
    if (err?.response?.status === 422) {
      positionFormErrors.value = err.response.data?.errors ?? {};
      const msg = err.response.data?.message;
      if (msg) showClientToast('error', msg);
    } else {
      showClientToast('error', err?.response?.data?.message || 'Không tạo được vị trí đánh giá.');
    }
  } finally {
    positionFormSaving.value = false;
  }
}

async function submitForm() {
  if (form.criteria.length === 0) {
    showClientToast('error', 'Mẫu đánh giá phải có ít nhất 1 tiêu chí.');
    return;
  }
  for (const field of form.custom_fields) {
    if (!field.label.trim()) {
      showClientToast('error', 'Trường tùy biến phải có nhãn hiển thị.');
      return;
    }
    if (field.field_type === 'select' && field.options.filter((o) => o.trim()).length === 0) {
      showClientToast('error', `Trường "${field.label}" kiểu lựa chọn phải có ít nhất 1 tùy chọn.`);
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
      weight_label: row.weight_label,
      required_score: row.required_score === '' ? null : row.required_score,
      count_in_total: Boolean(row.count_in_total),
    })),
    position_ids: form.position_ids,
    custom_fields: form.custom_fields.map((field) => ({
      label: field.label.trim(),
      field_type: field.field_type,
      options: field.field_type === 'select' ? field.options.map((o) => o.trim()).filter(Boolean) : null,
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
  if (positionDialogOpen.value) {
    closePositionDialog();
    return;
  }
  closeDialog();
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    resetForm();
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
        <div class="evtpl-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="evtpl-create-title">
          <div class="evtpl-dialog__head">
            <span class="evtpl-dialog__icon" aria-hidden="true">
              <AppIcon name="clipboardCheck" :size="22" :stroke-width="1.75" />
            </span>
            <h2 id="evtpl-create-title" class="evtpl-dialog__title">Tạo mới mẫu đánh giá</h2>
            <button type="button" class="evtpl-dialog__close" aria-label="Đóng" :disabled="formSaving" @click="closeDialog">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="evtpl-dialog__body hide-scrollbar">
            <div class="evtpl-form">
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
                <div class="evtpl-form__switch-row">
                  <span id="evtpl-create-status-label" class="evtpl-form__label">Trạng thái</span>
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

              <div class="evtpl-form__field evtpl-form__field--criteria">
                <span class="evtpl-form__label">
                  Danh sách tiêu chí đánh giá <span class="evtpl-form__required">*</span>
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
                  :weight-options="WEIGHT_OPTIONS"
                />
              </div>

              <div class="evtpl-form__field evtpl-form__field--positions">
                <span class="evtpl-form__label">Vị trí đánh giá</span>
                <EvaluationPositionPicker
                  v-model="form.position_ids"
                  :positions="allPositions"
                  :disabled="formSaving"
                  @request-create="openPositionDialog"
                />
              </div>

              <div class="evtpl-form__field evtpl-form__field--custom-fields">
                <span class="evtpl-form__label">Trường tùy biến</span>
                <p class="evtpl-page__note">
                  Thêm field ngoài bộ tiêu chí chuẩn (ví dụ "Nhận xét thêm của quản lý"). Sẽ dùng khi có phiếu đánh giá.
                </p>
                <EvaluationCustomFieldsEditor
                  v-model="form.custom_fields"
                  :disabled="formSaving"
                  :field-types="CUSTOM_FIELD_TYPES"
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

  <!-- ── dialog nhỏ: thêm vị trí đánh giá nhanh ──────────────────────────── -->
  <Teleport to="body">
    <div
      v-if="positionDialogOpen"
      class="evtpl-confirm-overlay"
      role="presentation"
      @mousedown.self="closePositionDialog"
    >
      <div class="evtpl-confirm" role="dialog" aria-modal="true" aria-labelledby="evtpl-create-position-title">
        <h3 id="evtpl-create-position-title" class="evtpl-confirm__title">Thêm vị trí đánh giá</h3>

        <div class="evtpl-form__field" style="margin-bottom: var(--space-3)">
          <span class="evtpl-form__label" id="evtpl-create-position-kind-label">Loại vị trí</span>
          <div class="evtpl-form__seg" role="radiogroup" aria-labelledby="evtpl-create-position-kind-label">
            <label class="evtpl-form__seg-opt" :class="{ 'evtpl-form__seg-opt--on': positionForm.kind === 'position' }">
              <input
                class="evtpl-form__seg-input"
                type="radio"
                :checked="positionForm.kind === 'position'"
                :disabled="positionFormSaving"
                @change="positionForm.kind = 'position'"
              />
              Chức danh
            </label>
            <label class="evtpl-form__seg-opt" :class="{ 'evtpl-form__seg-opt--on': positionForm.kind === 'department' }">
              <input
                class="evtpl-form__seg-input"
                type="radio"
                :checked="positionForm.kind === 'department'"
                :disabled="positionFormSaving"
                @change="positionForm.kind = 'department'"
              />
              Phòng ban
            </label>
          </div>
        </div>

        <div class="evtpl-form__field" style="margin-bottom: var(--space-3)">
          <label class="evtpl-form__label" for="evtpl-create-position-name">
            Tên vị trí <span class="evtpl-form__required">*</span>
          </label>
          <!--
            Ô nhập tên hiện là tự do (free text). Tương lai sẽ nối API VA-HRM
            để autocomplete theo đúng loại (chức danh hoặc phòng ban) — khi
            có, định danh HRM sẽ lưu vào cột hrm_position_uuid đã có sẵn ở
            bảng evaluation_positions, không cần thêm cột mới.
          -->
          <input
            id="evtpl-create-position-name"
            v-model="positionForm.name"
            type="text"
            class="evtpl-page__input"
            :class="{ 'evtpl-page__input--error': positionFormErrors.name }"
            :placeholder="positionNamePlaceholder"
            maxlength="255"
            :disabled="positionFormSaving"
            @keydown.enter="submitPosition"
          />
          <span v-if="positionFormErrors.name" class="evtpl-form__error">
            {{ Array.isArray(positionFormErrors.name) ? positionFormErrors.name[0] : positionFormErrors.name }}
          </span>
        </div>

        <div class="evtpl-form__field">
          <label class="evtpl-form__label" for="evtpl-create-position-desc">Mô tả</label>
          <textarea
            id="evtpl-create-position-desc"
            v-model="positionForm.description"
            class="evtpl-page__textarea"
            rows="2"
            maxlength="1000"
            :disabled="positionFormSaving"
          />
        </div>

        <div class="evtpl-confirm__actions">
          <button type="button" class="evtpl-page__btn" :disabled="positionFormSaving" @click="submitPosition">
            {{ positionFormSaving ? 'Đang tạo…' : 'Tạo vị trí' }}
          </button>
          <button type="button" class="evtpl-page__btn evtpl-page__btn--ghost" :disabled="positionFormSaving" @click="closePositionDialog">
            Huỷ
          </button>
        </div>
      </div>
    </div>
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

.evtpl-dialog__panel {
  display: flex;
  flex-direction: column;
  width: min(90rem, calc(100vw - 2.5rem));
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
  min-height: 0;
  overflow-y: auto;
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
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  grid-template-areas:
    'name name status'
    'desc desc desc'
    'criteria criteria criteria'
    'positions positions positions'
    'custom-fields custom-fields custom-fields';
  gap: var(--space-4);
}

.evtpl-form__field--name { grid-area: name; }
.evtpl-form__field--status { grid-area: status; }
.evtpl-form__field--desc { grid-area: desc; }
.evtpl-form__field--criteria { grid-area: criteria; }
.evtpl-form__field--positions { grid-area: positions; }
.evtpl-form__field--custom-fields { grid-area: custom-fields; }

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
  padding-top: 1.375rem;
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

.evtpl-form__seg {
  display: inline-flex;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
  width: fit-content;
}

.evtpl-form__seg-opt {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.5rem 0.875rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  cursor: pointer;
}

.evtpl-form__seg-opt--on {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.evtpl-form__seg-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
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

/* ── confirm nhỏ (dialog quick-add vị trí) ───────────────────────────── */

.evtpl-confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  background: var(--color-sidebar-overlay, rgba(0, 0, 0, 0.4));
}

.evtpl-confirm {
  width: min(24rem, 100%);
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: 0 20px 48px rgba(0, 0, 0, 0.18);
}

.evtpl-confirm__title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.evtpl-confirm__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-3);
}

/* ── responsive ─────────────────────────────────────────────────────── */

@media (max-width: 1279px) {
  .evtpl-form {
    grid-template-columns: 1fr;
    grid-template-areas:
      'name'
      'status'
      'desc'
      'criteria'
      'positions'
      'custom-fields';
  }
}

@media (max-width: 768px) {
  .evtpl-dialog {
    padding: var(--space-2);
  }

  .evtpl-dialog__panel {
    width: 100%;
    height: calc(100vh - 1rem);
    max-height: calc(100vh - 1rem);
  }
}
</style>
