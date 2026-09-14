<script setup>
//
// "Thêm công việc nhanh" từ trang Tất cả công việc (/manager/project/tasks)
// — không gắn cố định 1 dự án như modal variant='bulk' trong
// ProjectQuickActionModals.vue (trang Chi tiết dự án), nên mỗi dòng tự chọn
// dự án riêng (để trống = việc thường xuyên). Cùng khuôn form-modal (lưới
// ngang, trong viewport) — xem .cursor/rules/form-modal.mdc.
//
import { nextTick, reactive, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import ProjectUserPicker from './ProjectUserPicker.vue';
import TaskProjectPicker from './TaskProjectPicker.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  users: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'created']);

let rowSeq = 0;
function nextRowKey() {
  rowSeq += 1;
  return `row-${rowSeq}`;
}

function emptyRow() {
  return {
    key: nextRowKey(),
    title: '',
    project_id: '',
    assignee_id: '',
    start_date: '',
    end_date: '',
    start_time: '',
    due_time: '',
    estimated_hours: '',
  };
}

const saving = ref(false);
const showHourFields = ref(false);
const rows = ref([emptyRow()]);
const firstField = ref(null);

function addRow() {
  rows.value.push(emptyRow());
}

function removeRow(row) {
  rows.value = rows.value.filter((r) => r.key !== row.key);
  if (!rows.value.length) addRow();
}

function close() {
  if (saving.value) return;
  emit('close');
}

function dayCount(start, end) {
  if (!start || !end) return null;
  const a = new Date(`${start}T00:00:00`);
  const b = new Date(`${end}T00:00:00`);
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return null;
  return Math.round((b.getTime() - a.getTime()) / 86400000) + 1;
}

function dateRangeError(start, end) {
  if (start && end && dayCount(start, end) == null) {
    return 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.';
  }
  return '';
}

const WEEKDAY_LABELS = ['CN', 'Th 2', 'Th 3', 'Th 4', 'Th 5', 'Th 6', 'Th 7'];

function formatDateDisplay(value) {
  if (!value) return '';
  const d = new Date(`${value}T00:00:00`);
  if (Number.isNaN(d.getTime())) return '';
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  return `${WEEKDAY_LABELS[d.getDay()]}, ${day}/${month}/${d.getFullYear()}`;
}

function formatTimeDisplay(value) {
  if (!value) return '';
  return value.slice(0, 5);
}

// Click bất kỳ đâu trong field ngày/giờ đều mở lịch/đồng hồ chọn của
// trình duyệt (showPicker), không bắt buộc bấm đúng icon lịch nhỏ.
function openPicker(event) {
  const input = event.currentTarget.querySelector('input');
  if (!input || input.disabled) return;
  if (document.activeElement !== input) input.focus();
  try {
    input.showPicker?.();
  } catch {
    // trình duyệt không hỗ trợ showPicker() — input vẫn nhận focus/click bình thường.
  }
}

async function submit() {
  if (saving.value) return;
  const valid = rows.value.filter((row) => row.title.trim());
  if (!valid.length) {
    showClientToast('error', 'Nhập ít nhất một công việc.');
    return;
  }
  for (const row of valid) {
    const err = dateRangeError(row.start_date, row.end_date);
    if (err) {
      showClientToast('error', err);
      return;
    }
  }

  saving.value = true;
  try {
    const { data } = await window.axios.post('/api/project/tasks/bulk', {
      items: valid.map((row) => ({
        title: row.title.trim(),
        project_id: row.project_id || null,
        assignee_id: row.assignee_id || null,
        start_date: row.start_date || null,
        end_date: row.end_date || null,
        start_time: showHourFields.value ? row.start_time || null : null,
        due_time: showHourFields.value ? row.due_time || null : null,
        estimated_hours: showHourFields.value && row.estimated_hours !== '' ? row.estimated_hours : null,
      })),
    });
    showClientToast('success', `Đã thêm ${valid.length} công việc.`);
    emit('created', data.tasks ?? []);
  } catch (err) {
    showClientToast('error', err?.response?.data?.message || 'Không tạo được công việc.');
  } finally {
    saving.value = false;
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    rows.value = [emptyRow()];
    showHourFields.value = false;
    nextTick(() => firstField.value?.focus());
  },
);

function onKeydown(event) {
  if (event.key === 'Escape') close();
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="tqa" role="presentation" @mousedown.self="close" @keydown="onKeydown">
      <div class="tqa__panel" role="dialog" aria-modal="true" aria-labelledby="tqa-title">
        <div class="tqa__head">
          <span class="tqa__icon" aria-hidden="true">
            <AppIcon name="listChecks" :size="22" :stroke-width="1.75" />
          </span>
          <div class="tqa__head-copy">
            <h2 id="tqa-title" class="tqa__title">Thêm công việc nhanh</h2>
            <p class="tqa__sub">Mỗi dòng là 1 công việc — chọn dự án riêng hoặc để trống cho việc thường xuyên.</p>
          </div>
          <button type="button" class="tqa__close" aria-label="Đóng" :disabled="saving" @click="close">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <form class="tqa__body hide-scrollbar" @submit.prevent="submit">
          <label class="tqa__check">
            <input v-model="showHourFields" type="checkbox">
            <span>Đặt giờ cụ thể (giờ bắt đầu/hạn, thời gian dự kiến)</span>
          </label>

          <div class="tqa__rows">
            <div v-for="(row, index) in rows" :key="row.key" class="tqa__row">
              <span class="tqa__row-index">{{ index + 1 }}</span>
              <div class="tqa__row-fields">
                <label class="tqa__field tqa__field--title">
                  <span class="tqa__label">Tên công việc</span>
                  <input
                    :ref="index === 0 ? (el) => (firstField = el) : undefined"
                    v-model="row.title"
                    class="tqa__input"
                    maxlength="255"
                    placeholder="Ví dụ: Lập hồ sơ mời thầu"
                  >
                </label>

                <div class="tqa__row-grid">
                  <div class="tqa__field">
                    <span class="tqa__label">Dự án</span>
                    <TaskProjectPicker v-model="row.project_id" />
                  </div>
                  <div class="tqa__field">
                    <span class="tqa__label">Người thực hiện</span>
                    <ProjectUserPicker
                      v-model="row.assignee_id"
                      :users="users"
                      search-label="Tìm người thực hiện"
                      placeholder="Chọn người thực hiện"
                    />
                  </div>
                  <div class="tqa__field">
                    <span class="tqa__label">Ngày bắt đầu</span>
                    <div
                      class="tqa__date"
                      :class="{ 'tqa__date--filled': !!row.start_date }"
                      @click="openPicker"
                    >
                      <AppIcon name="calendar" :size="15" :stroke-width="1.75" class="tqa__date-icon" />
                      <span class="tqa__date-text" :class="{ 'tqa__date-text--muted': !row.start_date }">
                        {{ formatDateDisplay(row.start_date) || 'Chọn ngày bắt đầu' }}
                      </span>
                      <input v-model="row.start_date" type="date" class="tqa__date-input" aria-label="Ngày bắt đầu">
                    </div>
                  </div>
                  <div class="tqa__field">
                    <span class="tqa__label">Ngày kết thúc</span>
                    <div
                      class="tqa__date"
                      :class="{ 'tqa__date--filled': !!row.end_date }"
                      @click="openPicker"
                    >
                      <AppIcon name="calendar" :size="15" :stroke-width="1.75" class="tqa__date-icon" />
                      <span class="tqa__date-text" :class="{ 'tqa__date-text--muted': !row.end_date }">
                        {{ formatDateDisplay(row.end_date) || 'Chọn ngày kết thúc' }}
                      </span>
                      <input v-model="row.end_date" type="date" class="tqa__date-input" aria-label="Ngày kết thúc">
                    </div>
                  </div>
                </div>

                <div v-if="showHourFields" class="tqa__row-hours">
                  <div class="tqa__field">
                    <span class="tqa__label">Giờ bắt đầu</span>
                    <div
                      class="tqa__date"
                      :class="{ 'tqa__date--filled': !!row.start_time }"
                      @click="openPicker"
                    >
                      <AppIcon name="clock" :size="15" :stroke-width="1.75" class="tqa__date-icon" />
                      <span class="tqa__date-text" :class="{ 'tqa__date-text--muted': !row.start_time }">
                        {{ formatTimeDisplay(row.start_time) || 'Chọn giờ bắt đầu' }}
                      </span>
                      <input v-model="row.start_time" type="time" class="tqa__date-input" aria-label="Giờ bắt đầu">
                    </div>
                  </div>
                  <div class="tqa__field">
                    <span class="tqa__label">Giờ hạn</span>
                    <div
                      class="tqa__date"
                      :class="{ 'tqa__date--filled': !!row.due_time }"
                      @click="openPicker"
                    >
                      <AppIcon name="clock" :size="15" :stroke-width="1.75" class="tqa__date-icon" />
                      <span class="tqa__date-text" :class="{ 'tqa__date-text--muted': !row.due_time }">
                        {{ formatTimeDisplay(row.due_time) || 'Chọn giờ hạn' }}
                      </span>
                      <input v-model="row.due_time" type="time" class="tqa__date-input" aria-label="Giờ hạn">
                    </div>
                  </div>
                  <label class="tqa__field">
                    <span class="tqa__label">Thời gian dự kiến (giờ)</span>
                    <input
                      v-model="row.estimated_hours"
                      type="number"
                      min="0"
                      step="0.5"
                      class="tqa__input"
                      placeholder="Vd. 8"
                    >
                  </label>
                </div>
              </div>
              <button type="button" class="tqa__row-remove" aria-label="Xoá dòng" @click="removeRow(row)">
                <AppIcon name="close" :size="14" />
              </button>
            </div>
            <button type="button" class="tqa__add-row" @click="addRow">
              <AppIcon name="plus" :size="14" />
              Thêm dòng
            </button>
          </div>
        </form>

        <div class="tqa__actions">
          <button type="button" class="tqa__btn tqa__btn--ghost" :disabled="saving" @click="close">
            Huỷ bỏ
          </button>
          <button type="button" class="tqa__btn tqa__btn--primary" :disabled="saving" @click="submit">
            {{ saving ? 'Đang lưu…' : 'Thêm công việc' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.tqa {
  position: fixed;
  inset: 0;
  z-index: 1300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.tqa__panel {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  width: min(92rem, calc(100vw - 2.5rem));
  max-width: calc(100vw - 2.5rem);
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.tqa__head,
.tqa__actions {
  flex-shrink: 0;
}

.tqa__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 0 var(--color-border);
}

.tqa__icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.tqa__head-copy {
  flex: 1;
  min-width: 0;
}

.tqa__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.35;
}

.tqa__sub {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.tqa__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-primary);
  cursor: pointer;
}

.tqa__close:hover {
  background: var(--color-primary-surface);
}

.tqa__body {
  flex: 1;
  min-height: 0;
  padding: 1.25rem;
  overflow: auto;
}

.tqa__check {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: fit-content;
  margin-bottom: var(--space-3);
  color: var(--color-text);
  font-size: 0.875rem;
  cursor: pointer;
}

.tqa__check input {
  width: 1rem;
  height: 1rem;
  margin: 0;
  accent-color: var(--color-primary);
  cursor: pointer;
}

.tqa__rows {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.tqa__row {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.tqa__row-index {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-top: 1.375rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.tqa__row-fields {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-3);
}

.tqa__row-grid {
  display: grid;
  min-width: 0;
  grid-template-columns: repeat(4, minmax(10rem, 1fr));
  gap: var(--space-3);
}

.tqa__row-hours {
  display: grid;
  min-width: 0;
  grid-template-columns: repeat(3, minmax(9rem, 1fr));
  gap: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: 0 -1px 0 var(--color-border);
}

.tqa__field {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.375rem;
}

.tqa__field--title {
  width: 100%;
}

.tqa__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.tqa__input {
  width: 100%;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
}

.tqa__input:focus {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 1px;
}

/*
 * Field ngày/giờ: cả khối là vùng bấm được (click đâu cũng mở lịch/đồng hồ
 * của trình duyệt qua showPicker()), không bắt buộc trúng icon lịch nhỏ.
 * Input thật vẫn nằm trong DOM để giữ hành vi/keyboard/mobile gốc, nhưng ẩn
 * đi và thay bằng icon + chữ hiển thị định dạng rõ ràng (Th 2, 12/09/2026).
 */
.tqa__date {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.375rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
}

.tqa__date:hover {
  border-color: var(--color-border-strong);
}

.tqa__date:focus-within {
  border-color: var(--color-primary-300);
  outline: 2px solid var(--color-primary-200);
  outline-offset: 1px;
}

.tqa__date--filled {
  border-color: var(--color-primary-200);
  background: var(--color-primary-surface);
}

.tqa__date-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.tqa__date--filled .tqa__date-icon {
  color: var(--color-primary);
}

.tqa__date-text {
  overflow: hidden;
  flex: 1;
  min-width: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tqa__date-text--muted {
  color: var(--color-text-muted);
  font-weight: 400;
}

.tqa__date-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  opacity: 0;
  cursor: pointer;
}

.tqa__row-remove {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-top: 1.375rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.tqa__row-remove:hover {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.tqa__add-row {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  width: fit-content;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-secondary-700);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.tqa__add-row:hover {
  background: var(--color-secondary-surface);
}

.tqa__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: 0.875rem 1.25rem 1.125rem;
  box-shadow: 0 -1px 0 var(--color-border);
}

.tqa__btn {
  padding: 0.5rem 1rem;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.tqa__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.tqa__btn--primary:hover {
  filter: brightness(0.95);
}

.tqa__btn--ghost {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
}

.tqa__btn--ghost:hover {
  background: var(--color-surface-muted);
}

.tqa__btn:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 1024px) {
  .tqa__row-grid {
    grid-template-columns: repeat(2, minmax(10rem, 1fr));
  }
}

@media (max-width: 768px) {
  .tqa__row {
    flex-direction: column;
  }

  .tqa__row-index {
    margin-top: 0;
  }

  .tqa__row-grid,
  .tqa__row-hours {
    grid-template-columns: minmax(0, 1fr);
    width: 100%;
  }

  .tqa__row-remove {
    position: absolute;
    top: var(--space-3);
    right: var(--space-3);
    margin-top: 0;
  }

  .tqa__row {
    padding-right: 2.75rem;
  }
}
</style>
