<script setup>
//
// Nghỉ phép mobile — UI mock (chưa nối API). Số liệu quy đổi từ phút theo
// lịch làm việc; nút Cập nhật / gửi đơn chỉ mô phỏng phản hồi cục bộ.
//
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

function formatDays(value) {
  return Number(value).toLocaleString('vi-VN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

const overview = reactive({
  totalFund: 12,
  used: 6.44,
  remaining: 5.56,
  planned: 0,
  pending: 0,
  expired: 0,
  expiringSoon: 7.56,
  expiringBefore: '31/12/2026',
  usagePercent: 53.7,
});

const breakdown = reactive([
  { id: 'carry2025', label: 'Phép 2025 chuyển sang', value: 2.0 },
  { id: 'annual2026', label: 'Phép năm 2026 phát sinh', value: 10.0 },
  { id: 'seniority', label: 'Phép thâm niên', value: 0 },
  { id: 'bonus', label: 'Phép cộng thêm', value: 0 },
  { id: 'hrGrant', label: 'Phép công tác do HR cấp', value: 0 },
]);

const refreshing = ref(false);

function refreshOverview() {
  if (refreshing.value) return;
  refreshing.value = true;
  setTimeout(() => {
    refreshing.value = false;
    showClientToast('success', 'Đã cập nhật số dư phép năm.');
  }, 600);
}

const LEAVE_TYPES = [
  { value: 'annual', label: 'Nghỉ phép năm' },
  { value: 'sick', label: 'Nghỉ bệnh' },
  { value: 'personal', label: 'Nghỉ việc riêng' },
  { value: 'unpaid', label: 'Nghỉ không lương' },
];

const formOpen = ref(false);
const submitting = ref(false);

const form = reactive({
  type: 'annual',
  fromDate: '',
  toDate: '',
  reason: '',
});

const history = reactive([
  {
    id: 1,
    type: 'Nghỉ phép việc riêng',
    days: 1,
    dateLabel: '15/09',
    status: 'approved',
    icon: 'user',
    tone: 'tertiary',
  },
  {
    id: 2,
    type: 'Nghỉ bệnh',
    days: 0.5,
    dateLabel: '02/08',
    status: 'approved',
    icon: 'clipboardCheck',
    tone: 'danger',
  },
]);

const statusLabel = {
  approved: 'Đã duyệt',
  pending: 'Đang chờ duyệt',
  rejected: 'Từ chối',
};

function openForm() {
  formOpen.value = true;
}

function closeForm() {
  formOpen.value = false;
}

function resetForm() {
  form.type = 'annual';
  form.fromDate = '';
  form.toDate = '';
  form.reason = '';
}

function submit() {
  if (!form.fromDate || !form.toDate) {
    showClientToast('warning', 'Chọn đủ ngày bắt đầu và kết thúc trước khi gửi.');
    return;
  }

  submitting.value = true;
  const typeLabel = LEAVE_TYPES.find((t) => t.value === form.type)?.label ?? 'Nghỉ phép';
  const fromLabel = new Date(form.fromDate).toLocaleDateString('vi-VN');
  const toLabel = new Date(form.toDate).toLocaleDateString('vi-VN');

  setTimeout(() => {
    history.unshift({
      id: Date.now(),
      type: typeLabel,
      days: 1,
      dateLabel: fromLabel === toLabel ? fromLabel : `${fromLabel} – ${toLabel}`,
      status: 'pending',
      icon: 'calendar',
      tone: 'gold',
    });
    showClientToast('success', 'Đã gửi đơn nghỉ phép, chờ trưởng phòng duyệt.');
    resetForm();
    submitting.value = false;
    closeForm();
  }, 400);
}

const progressWidth = computed(() => `${Math.min(100, Math.max(0, overview.usagePercent))}%`);
</script>

<template>
  <div class="leave">
    <section class="leave-hero">
      <div class="leave-hero__head">
        <span class="leave-hero__badge" aria-hidden="true">
          <AppIcon name="calendar" :size="18" :stroke-width="1.8" />
        </span>
        <p class="leave-hero__label">Số ngày phép còn lại</p>
      </div>
      <p class="leave-hero__value">
        <span class="leave-hero__number">{{ formatDays(overview.remaining) }}</span>
        <span class="leave-hero__unit">ngày</span>
      </p>
      <div class="leave-hero__split">
        <div>
          <p class="leave-hero__split-label">Đã sử dụng</p>
          <p class="leave-hero__split-value">{{ formatDays(overview.used) }} ngày</p>
        </div>
        <div>
          <p class="leave-hero__split-label">Phép thâm niên</p>
          <p class="leave-hero__split-value">{{ formatDays(breakdown.find((b) => b.id === 'seniority')?.value ?? 0) }} ngày</p>
        </div>
      </div>
    </section>

    <section class="leave-panel">
      <div class="leave-panel__head">
        <div>
          <h2 class="leave-panel__title">Tổng quan phép năm</h2>
          <p class="leave-panel__hint">
            Tổng quỹ, số đã dùng và số còn lại quy đổi từ phút làm việc theo lịch có hiệu lực.
          </p>
        </div>
        <button
          type="button"
          class="leave-panel__refresh"
          :disabled="refreshing"
          @click="refreshOverview"
        >
          <AppIcon name="rotateCw" :size="16" :class="{ 'leave-panel__refresh-spin': refreshing }" />
          <span>{{ refreshing ? 'Đang cập nhật…' : 'Cập nhật' }}</span>
        </button>
      </div>

      <div class="leave-metrics leave-metrics--primary">
        <article class="leave-metric">
          <p class="leave-metric__label">Tổng quỹ phát sinh</p>
          <p class="leave-metric__value">{{ formatDays(overview.totalFund) }}</p>
          <p class="leave-metric__hint">ngày · Tổng quyền lợi trước sử dụng và hết hạn</p>
        </article>
        <article class="leave-metric">
          <p class="leave-metric__label">Đã sử dụng</p>
          <p class="leave-metric__value leave-metric__value--used">{{ formatDays(overview.used) }}</p>
          <p class="leave-metric__hint">ngày · Số ngày nghỉ đã ghi nhận</p>
        </article>
        <article class="leave-metric">
          <p class="leave-metric__label">Còn lại</p>
          <p class="leave-metric__value leave-metric__value--remain">{{ formatDays(overview.remaining) }}</p>
          <p class="leave-metric__hint">ngày · Số ngày hiện có thể sử dụng</p>
        </article>
      </div>

      <div class="leave-metrics leave-metrics--secondary">
        <article class="leave-chip">
          <p class="leave-chip__label">Đã đặt</p>
          <p class="leave-chip__value">{{ formatDays(overview.planned) }} ngày</p>
          <p class="leave-chip__hint">Phép tương lai đã duyệt</p>
        </article>
        <article class="leave-chip">
          <p class="leave-chip__label">Đang chờ</p>
          <p class="leave-chip__value">{{ formatDays(overview.pending) }} ngày</p>
          <p class="leave-chip__hint">Chưa trừ số dư chính thức</p>
        </article>
        <article class="leave-chip">
          <p class="leave-chip__label">Đã hết hạn</p>
          <p class="leave-chip__value">{{ formatDays(overview.expired) }} ngày</p>
          <p class="leave-chip__hint">Phép chuyển năm không còn hiệu lực</p>
        </article>
        <article class="leave-chip leave-chip--warn">
          <p class="leave-chip__label">Dự kiến hết hạn</p>
          <p class="leave-chip__value">{{ formatDays(overview.expiringSoon) }} ngày</p>
          <p class="leave-chip__hint">Trước {{ overview.expiringBefore }}</p>
        </article>
      </div>

      <div class="leave-progress">
        <div class="leave-progress__head">
          <span class="leave-progress__label">Tiến độ sử dụng trong năm</span>
          <span class="leave-progress__pct">{{ formatDays(overview.usagePercent) }}%</span>
        </div>
        <div class="leave-progress__track" aria-hidden="true">
          <div class="leave-progress__fill" :style="{ width: progressWidth }"></div>
        </div>
      </div>

      <div class="leave-breakdown">
        <h3 class="leave-breakdown__title">Diễn giải số ngày phép</h3>
        <ul class="leave-breakdown__list">
          <li v-for="row in breakdown" :key="row.id" class="leave-breakdown__row">
            <span class="leave-breakdown__label">{{ row.label }}</span>
            <span class="leave-breakdown__value">+{{ formatDays(row.value) }} ngày</span>
          </li>
        </ul>
      </div>
    </section>

    <button type="button" class="leave-create" @click="openForm">
      <AppIcon name="plus" :size="20" :stroke-width="2" />
      <span>Tạo đơn xin nghỉ phép</span>
    </button>

    <section class="leave-history">
      <h2 class="leave-history__title">Lịch sử xin phép</h2>
      <ul class="leave-history__list">
        <li v-for="item in history" :key="item.id" class="history-row">
          <span class="history-row__icon" :class="`history-row__icon--${item.tone}`" aria-hidden="true">
            <AppIcon :name="item.icon" :size="20" :stroke-width="1.8" />
          </span>
          <span class="history-row__body">
            <span class="history-row__type">{{ item.type }}</span>
            <span class="history-row__meta">{{ formatDays(item.days) }} ngày · {{ item.dateLabel }}</span>
          </span>
          <span
            class="history-row__status"
            :class="{
              'history-row__status--approved': item.status === 'approved',
              'history-row__status--pending': item.status === 'pending',
            }"
          >
            {{ statusLabel[item.status] ?? item.status }}
          </span>
        </li>
      </ul>
    </section>

    <Teleport to="body">
      <div v-if="formOpen" class="leave-dialog" role="dialog" aria-modal="true" aria-labelledby="leave-form-title">
        <button type="button" class="leave-dialog__overlay" aria-label="Đóng" @click="closeForm"></button>
        <div class="leave-dialog__panel">
          <header class="leave-dialog__head">
            <h2 id="leave-form-title" class="leave-dialog__title">Tạo đơn xin nghỉ phép</h2>
            <button type="button" class="leave-dialog__close" aria-label="Đóng" @click="closeForm">
              <AppIcon name="close" :size="18" />
            </button>
          </header>
          <form class="leave-dialog__body" @submit.prevent="submit">
            <div class="leave-form-grid">
              <label class="leave-form-field leave-form-field--span">
                <span class="leave-form-field__label">Loại nghỉ</span>
                <select v-model="form.type" class="leave-form-field__control">
                  <option v-for="opt in LEAVE_TYPES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </label>
              <label class="leave-form-field">
                <span class="leave-form-field__label">Từ ngày</span>
                <input v-model="form.fromDate" type="date" class="leave-form-field__control" />
              </label>
              <label class="leave-form-field">
                <span class="leave-form-field__label">Đến ngày</span>
                <input v-model="form.toDate" type="date" class="leave-form-field__control" />
              </label>
              <label class="leave-form-field leave-form-field--span">
                <span class="leave-form-field__label">Lý do</span>
                <textarea
                  v-model="form.reason"
                  class="leave-form-field__control leave-form-field__control--area"
                  rows="3"
                  placeholder="Vd. Về quê giải quyết việc gia đình"
                ></textarea>
              </label>
            </div>
          </form>
          <footer class="leave-dialog__actions">
            <button type="button" class="leave-dialog__btn leave-dialog__btn--ghost" @click="closeForm">Huỷ</button>
            <button
              type="button"
              class="leave-dialog__btn leave-dialog__btn--primary"
              :disabled="submitting"
              @click="submit"
            >
              {{ submitting ? 'Đang gửi…' : 'Gửi đơn' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.leave {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.leave-hero {
  margin-top: calc(-1 * var(--employee-stat-overlap, 3rem));
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
}

.leave-hero__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.leave-hero__badge {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-full);
  background: var(--color-warning-surface, #fff8e6);
  color: var(--color-warning, #c47a00);
}

.leave-hero__label {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.leave-hero__value {
  margin: 0 0 var(--space-3);
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
}

.leave-hero__number {
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.leave-hero__unit {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-hero__split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.leave-hero__split-label {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.leave-hero__split-value {
  margin: 2px 0 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-panel {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave-panel__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.leave-panel__title {
  margin: 0 0 var(--space-1);
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-panel__hint {
  margin: 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.leave-panel__refresh {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-tertiary);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.leave-panel__refresh:disabled {
  opacity: 0.65;
  cursor: default;
}

.leave-panel__refresh-spin {
  animation: leave-spin 0.8s linear infinite;
}

@keyframes leave-spin {
  to {
    transform: rotate(360deg);
  }
}

.leave-metrics {
  display: grid;
  gap: var(--space-3);
}

.leave-metrics--primary {
  grid-template-columns: 1fr;
  margin-bottom: var(--space-4);
}

.leave-metrics--secondary {
  grid-template-columns: 1fr 1fr;
  margin-bottom: var(--space-4);
}

.leave-metric {
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-tertiary-surface);
}

.leave-metric__label {
  margin: 0 0 var(--space-1);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-metric__value {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-tertiary);
  letter-spacing: -0.02em;
}

.leave-metric__value--used {
  color: var(--color-primary);
}

.leave-metric__value--remain {
  color: var(--color-secondary);
}

.leave-metric__hint {
  margin: var(--space-1) 0 0;
  font-size: 0.6875rem;
  line-height: 1.4;
  color: var(--color-text-muted);
}

.leave-chip {
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.leave-chip--warn {
  background: #fff8f0;
  box-shadow: inset 0 0 0 1px rgba(196, 122, 0, 0.25);
}

.leave-chip__label {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-chip__value {
  margin: 2px 0 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-chip__hint {
  margin: 2px 0 0;
  font-size: 0.625rem;
  line-height: 1.35;
  color: var(--color-text-muted);
}

.leave-progress {
  margin-bottom: var(--space-4);
}

.leave-progress__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.leave-progress__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.leave-progress__pct {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-tertiary);
}

.leave-progress__track {
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.leave-progress__fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-tertiary-600), var(--color-tertiary));
}

.leave-breakdown__title {
  margin: 0 0 var(--space-2);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-breakdown__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.leave-breakdown__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.leave-breakdown__row:last-child {
  box-shadow: none;
}

.leave-breakdown__label {
  font-size: 0.8125rem;
  color: var(--color-text);
}

.leave-breakdown__value {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-secondary);
}

.leave-create {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-tertiary);
  color: var(--color-on-tertiary);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
}

.leave-history__title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-history__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.history-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.history-row__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
}

.history-row__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.history-row__icon--danger {
  background: #fdecec;
  color: var(--color-primary);
}

.history-row__icon--gold {
  background: var(--color-warning-surface, #fff8e6);
  color: var(--color-warning, #c47a00);
}

.history-row__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.history-row__type {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.history-row__meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.history-row__status {
  flex-shrink: 0;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  font-size: 0.6875rem;
  font-weight: 700;
}

.history-row__status--approved {
  background: #e8f7ee;
  color: var(--color-secondary);
}

.history-row__status--pending {
  background: #fff4e5;
  color: var(--color-warning, #c47a00);
}

.leave-dialog {
  position: fixed;
  inset: 0;
  z-index: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  padding-bottom: calc(var(--space-4) + env(safe-area-inset-bottom, 0px));
}

.leave-dialog__overlay {
  position: absolute;
  inset: 0;
  border: none;
  background: var(--color-sidebar-overlay, rgba(15, 23, 42, 0.45));
  cursor: pointer;
}

.leave-dialog__panel {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  width: min(28rem, calc(100vw - 2rem));
  max-height: calc(100dvh - 2rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.leave-dialog__head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: var(--space-4);
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.leave-dialog__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.leave-dialog__close {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.leave-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
}

.leave-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.leave-form-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.leave-form-field--span {
  grid-column: 1 / -1;
}

.leave-form-field__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-form-field__control {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.875rem;
  color: var(--color-text);
}

.leave-form-field__control:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.leave-form-field__control--area {
  resize: vertical;
  min-height: 4.5rem;
}

.leave-dialog__actions {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4) var(--space-4);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.leave-dialog__btn {
  padding: var(--space-2) var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}

.leave-dialog__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.leave-dialog__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.leave-dialog__btn--primary:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (min-width: 480px) {
  .leave-metrics--primary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
