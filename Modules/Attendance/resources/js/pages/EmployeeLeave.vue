<script setup>
//
// Nghỉ phép — UI only, chưa nối API. Gửi đơn chỉ thêm vào danh sách mock
// cục bộ (reactive) + toast xác nhận, không gọi window.axios.
//
import { reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';

const leaveBalance = {
  remainingDays: 8.5,
  usedDays: 3.5,
  totalDays: 12,
};

const LEAVE_TYPES = [
  { value: 'annual', label: 'Nghỉ phép năm' },
  { value: 'sick', label: 'Nghỉ ốm' },
  { value: 'personal', label: 'Nghỉ việc riêng' },
  { value: 'unpaid', label: 'Nghỉ không lương' },
];

const form = reactive({
  type: 'annual',
  fromDate: '',
  toDate: '',
  reason: '',
});

const submitting = ref(false);

const sentRequests = reactive([
  { id: 1, type: 'Nghỉ phép năm', range: '12/10 - 13/10', reason: 'Việc gia đình', status: 'pending' },
]);

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
    sentRequests.unshift({
      id: Date.now(),
      type: typeLabel,
      range: `${fromLabel} - ${toLabel}`,
      reason: form.reason,
      status: 'pending',
    });
    showClientToast('success', 'Đã gửi đơn nghỉ phép, chờ trưởng phòng duyệt.');
    resetForm();
    submitting.value = false;
  }, 300);
}
</script>

<template>
  <div class="leave">
    <section class="leave__balance">
      <div class="leave__balance-main">
        <span class="leave__balance-value">{{ leaveBalance.remainingDays }}</span>
        <span class="leave__balance-unit">ngày còn lại</span>
      </div>
      <div class="leave__balance-row">
        <span>Đã dùng {{ leaveBalance.usedDays }} ngày</span>
        <span>Tổng {{ leaveBalance.totalDays }} ngày/năm</span>
      </div>
    </section>

    <section class="leave__form">
      <h2 class="leave__section-title">Gửi đơn nghỉ phép</h2>
      <form class="leave-form" @submit.prevent="submit">
        <div class="leave-form__grid">
          <label class="leave-form__field">
            <span class="leave-form__label">Loại nghỉ</span>
            <select v-model="form.type" class="leave-form__control">
              <option v-for="opt in LEAVE_TYPES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>

          <label class="leave-form__field">
            <span class="leave-form__label">Từ ngày</span>
            <input v-model="form.fromDate" type="date" class="leave-form__control" />
          </label>

          <label class="leave-form__field">
            <span class="leave-form__label">Đến ngày</span>
            <input v-model="form.toDate" type="date" class="leave-form__control" />
          </label>
        </div>

        <label class="leave-form__field leave-form__field--full">
          <span class="leave-form__label">Lý do</span>
          <textarea
            v-model="form.reason"
            class="leave-form__control leave-form__control--textarea"
            rows="3"
            placeholder="Vd. Về quê giải quyết việc gia đình"
          ></textarea>
        </label>

        <button type="submit" class="leave-form__submit" :disabled="submitting">
          <AppIcon name="send" :size="16" />
          <span>{{ submitting ? 'Đang gửi...' : 'Gửi đơn' }}</span>
        </button>
      </form>
    </section>

    <section v-if="sentRequests.length" class="leave__recent">
      <h2 class="leave__section-title">Đơn vừa gửi</h2>
      <ul class="recent-list">
        <li v-for="req in sentRequests" :key="req.id" class="recent-row">
          <span class="recent-row__dot" aria-hidden="true"></span>
          <span class="recent-row__body">
            <span class="recent-row__type">{{ req.type }} · {{ req.range }}</span>
            <span class="recent-row__status">Đang chờ duyệt</span>
          </span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.leave {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.leave__balance {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave__balance-main {
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
}

.leave__balance-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--color-secondary);
  letter-spacing: -0.02em;
}

.leave__balance-unit {
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.leave__balance-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.leave__section-title {
  margin: 0 0 var(--space-3);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave__form {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.leave-form__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

.leave-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.leave-form__field--full {
  grid-column: 1 / -1;
}

.leave-form__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-form__control {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
}

.leave-form__control:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.leave-form__control--textarea {
  resize: vertical;
  min-height: 4.5rem;
}

.leave-form__submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  cursor: pointer;
}

.leave-form__submit:disabled {
  opacity: 0.6;
  cursor: default;
}

.leave__recent {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.recent-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.recent-row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
}

.recent-row__dot {
  flex-shrink: 0;
  margin-top: 6px;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-warning);
}

.recent-row__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.recent-row__type {
  font-size: 0.8125rem;
  color: var(--color-text);
}

.recent-row__status {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

@media (max-width: 420px) {
  .leave-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
