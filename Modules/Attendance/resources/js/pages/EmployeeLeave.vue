<script setup>
//
// Nghỉ phép mobile — loại nghỉ từ portal HRM (GET /api/attendance/leave-types → HRM_LEAVE_API_*).
// Số dư / lịch sử / gửi đơn vẫn mock cục bộ cho tới khi nối API đơn nghỉ.
//
import { computed, onMounted, reactive, ref } from 'vue';
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

/** @type {import('vue').Ref<Array<Record<string, unknown>>>} */
const leaveTypes = ref([]);
const typesLoading = ref(true);
const typesError = ref('');

const formOpen = ref(false);
const submitting = ref(false);

const form = reactive({
  leaveTypeId: null,
  fromDate: '',
  toDate: '',
  reason: '',
  documentLink: '',
});

const selectedLeaveType = computed(() =>
  leaveTypes.value.find((t) => Number(t.id) === Number(form.leaveTypeId)) ?? null,
);

async function loadLeaveTypes() {
  typesLoading.value = true;
  typesError.value = '';
  try {
    const { data } = await window.axios.get('/api/attendance/leave-types');
    const rows = Array.isArray(data?.data) ? data.data : [];
    leaveTypes.value = rows;
    if (rows.length && form.leaveTypeId == null) {
      form.leaveTypeId = rows[0].id;
    }
  } catch {
    typesError.value = 'Không tải được danh sách loại nghỉ từ HRM.';
    leaveTypes.value = [];
  } finally {
    typesLoading.value = false;
  }
}

onMounted(() => {
  void loadLeaveTypes();
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
  form.leaveTypeId = leaveTypes.value[0]?.id ?? null;
  form.fromDate = '';
  form.toDate = '';
  form.reason = '';
  form.documentLink = '';
}

function submit() {
  if (!form.leaveTypeId) {
    showClientToast('warning', 'Chọn loại nghỉ trước khi gửi.');
    return;
  }
  if (!form.fromDate || !form.toDate) {
    showClientToast('warning', 'Chọn đủ ngày bắt đầu và kết thúc trước khi gửi.');
    return;
  }
  if (selectedLeaveType.value?.requires_document_link && !form.documentLink.trim()) {
    showClientToast('warning', 'Nhập link giấy tờ đính kèm.');
    return;
  }

  submitting.value = true;
  const typeLabel = selectedLeaveType.value?.name ?? 'Nghỉ phép';
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

const seniorityDays = computed(
  () => breakdown.find((b) => b.id === 'seniority')?.value ?? 0,
);
</script>

<template>
  <div class="leave">
    <section class="leave-hero">
      <div class="leave-hero__glow" aria-hidden="true"></div>
      <div class="leave-hero__head">
        <span class="leave-hero__badge" aria-hidden="true">
          <AppIcon name="calendar" :size="22" :stroke-width="1.8" />
        </span>
        <p class="leave-hero__label">Số ngày phép còn lại</p>
      </div>
      <p class="leave-hero__value">
        <span class="leave-hero__number">{{ formatDays(overview.remaining) }}</span>
        <span class="leave-hero__unit">ngày</span>
      </p>
      <div class="leave-hero__split">
        <div class="leave-hero__stat">
          <p class="leave-hero__split-label">Đã sử dụng</p>
          <p class="leave-hero__split-value">{{ formatDays(overview.used) }} ngày</p>
        </div>
        <div class="leave-hero__stat">
          <p class="leave-hero__split-label">Phép thâm niên</p>
          <p class="leave-hero__split-value">{{ formatDays(seniorityDays) }} ngày</p>
        </div>
      </div>
    </section>

    <section class="leave-panel">
      <div class="leave-panel__ribbon" aria-hidden="true"></div>
      <div class="leave-panel__head">
        <h2 class="leave-panel__title">Tổng quan phép năm</h2>
        <button
          type="button"
          class="leave-panel__refresh"
          :disabled="refreshing"
          aria-label="Cập nhật số dư phép"
          @click="refreshOverview"
        >
          <AppIcon name="rotateCw" :size="18" :class="{ 'leave-panel__refresh-spin': refreshing }" />
          <span>{{ refreshing ? 'Đang cập nhật…' : 'Cập nhật' }}</span>
        </button>
      </div>

      <div class="leave-metrics leave-metrics--primary">
        <article class="leave-metric leave-metric--fund">
          <p class="leave-metric__label">Tổng quỹ phát sinh</p>
          <p class="leave-metric__value">{{ formatDays(overview.totalFund) }} <span class="leave-metric__unit">ngày</span></p>
        </article>
        <article class="leave-metric leave-metric--used">
          <p class="leave-metric__label">Đã sử dụng</p>
          <p class="leave-metric__value">{{ formatDays(overview.used) }} <span class="leave-metric__unit">ngày</span></p>
        </article>
        <article class="leave-metric leave-metric--remain">
          <p class="leave-metric__label">Còn lại</p>
          <p class="leave-metric__value">{{ formatDays(overview.remaining) }} <span class="leave-metric__unit">ngày</span></p>
        </article>
      </div>

      <div class="leave-metrics leave-metrics--secondary">
        <article class="leave-chip">
          <p class="leave-chip__label">Đã đặt</p>
          <p class="leave-chip__value">{{ formatDays(overview.planned) }} ngày</p>
        </article>
        <article class="leave-chip">
          <p class="leave-chip__label">Đang chờ</p>
          <p class="leave-chip__value">{{ formatDays(overview.pending) }} ngày</p>
        </article>
        <article class="leave-chip">
          <p class="leave-chip__label">Đã hết hạn</p>
          <p class="leave-chip__value">{{ formatDays(overview.expired) }} ngày</p>
        </article>
        <article class="leave-chip leave-chip--warn">
          <p class="leave-chip__label">Dự kiến hết hạn · {{ overview.expiringBefore }}</p>
          <p class="leave-chip__value">{{ formatDays(overview.expiringSoon) }} ngày</p>
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

    <button
      type="button"
      class="leave-create"
      :disabled="typesLoading || !!typesError || leaveTypes.length === 0"
      @click="openForm"
    >
      <AppIcon name="plus" :size="20" :stroke-width="2" />
      <span>{{ typesLoading ? 'Đang tải loại nghỉ…' : 'Tạo đơn xin nghỉ phép' }}</span>
    </button>
    <p v-if="typesError" class="leave-types-error">{{ typesError }}</p>

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
                <select
                  v-model="form.leaveTypeId"
                  class="leave-form-field__control"
                  :disabled="typesLoading || leaveTypes.length === 0"
                >
                  <option v-for="opt in leaveTypes" :key="opt.id" :value="opt.id">
                    {{ opt.code }} — {{ opt.name }}
                  </option>
                </select>
              </label>
              <label
                v-if="selectedLeaveType?.requires_document_link"
                class="leave-form-field leave-form-field--span"
              >
                <span class="leave-form-field__label">
                  Link giấy tờ
                  <span class="leave-form-field__req">*</span>
                </span>
                <input
                  v-model="form.documentLink"
                  type="url"
                  class="leave-form-field__control"
                  placeholder="https://drive.google.com/…"
                />
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
  --leave-clip-card: polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 14px) 100%, 0 100%);
  --leave-clip-metric: polygon(0 0, calc(100% - 12px) 0, 100% 12px, 100% 100%, 0 100%);
  --leave-clip-chip: polygon(12px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 10px) 100%, 0 100%, 0 8px);
  --leave-clip-btn: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.leave-hero {
  position: relative;
  margin-top: calc(-1 * var(--employee-stat-overlap, 3rem));
  padding: var(--space-5) var(--space-4) var(--space-4);
  overflow: hidden;
  clip-path: polygon(0 0, 100% 0, 100% calc(100% - 14px), 50% 100%, 0 calc(100% - 14px));
  background: linear-gradient(145deg, var(--color-surface) 0%, var(--color-tertiary-surface) 100%);
  box-shadow: var(--shadow-md);
}

.leave-hero__glow {
  position: absolute;
  top: -2rem;
  right: -1.5rem;
  width: 8rem;
  height: 8rem;
  clip-path: circle(50% at 50% 50%);
  background: radial-gradient(circle, rgba(14, 51, 111, 0.12) 0%, transparent 70%);
  pointer-events: none;
}

.leave-hero__head {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

.leave-hero__badge {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  clip-path: polygon(25% 0, 100% 0, 100% 75%, 75% 100%, 0 100%, 0 25%);
  background: linear-gradient(135deg, var(--color-tertiary-surface), #fff);
  color: var(--color-tertiary);
  box-shadow: var(--shadow-sm);
}

.leave-hero__label {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-hero__value {
  position: relative;
  margin: 0 0 var(--space-4);
  display: flex;
  align-items: baseline;
  gap: var(--space-2);
}

.leave-hero__number {
  font-size: 2.75rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1;
  color: var(--color-tertiary);
}

.leave-hero__unit {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.leave-hero__split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.leave-hero__stat {
  padding: var(--space-2) var(--space-3);
  clip-path: var(--leave-clip-chip);
  background: rgba(255, 255, 255, 0.72);
}

.leave-hero__split-label {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-hero__split-value {
  margin: 4px 0 0;
  font-size: 1.0625rem;
  font-weight: 800;
  color: var(--color-text);
}

.leave-panel {
  position: relative;
  padding: var(--space-5) var(--space-4) var(--space-4);
  clip-path: var(--leave-clip-card);
  background: var(--color-surface);
  box-shadow: var(--shadow-md);
}

.leave-panel__ribbon {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 0.35rem;
  clip-path: polygon(0 0, 100% 0, 98% 100%, 2% 100%);
  background: linear-gradient(90deg, var(--color-tertiary), var(--color-primary));
}

.leave-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.leave-panel__title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.leave-panel__refresh {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border: none;
  clip-path: var(--leave-clip-btn);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  font-family: inherit;
  font-size: 0.875rem;
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
  position: relative;
  padding: var(--space-4) var(--space-3);
  clip-path: var(--leave-clip-metric);
  background: var(--color-tertiary-surface);
}

.leave-metric--fund {
  background: linear-gradient(135deg, var(--color-tertiary-surface), #eef3fb);
}

.leave-metric--used {
  background: linear-gradient(135deg, #fdeef0, #fff5f6);
}

.leave-metric--used .leave-metric__value {
  color: var(--color-primary);
}

.leave-metric--remain {
  background: linear-gradient(135deg, #e8f7ee, #f4fbf7);
}

.leave-metric--remain .leave-metric__value {
  color: var(--color-secondary);
}

.leave-metric__label {
  margin: 0 0 var(--space-2);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.leave-metric__value {
  margin: 0;
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-tertiary);
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.leave-metric__unit {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.leave-chip {
  padding: var(--space-3) var(--space-3);
  clip-path: var(--leave-clip-chip);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.leave-chip--warn {
  background: linear-gradient(135deg, #fff8f0, #fff3e6);
  box-shadow: inset 0 0 0 1px rgba(196, 122, 0, 0.22);
}

.leave-chip__label {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
  line-height: 1.3;
}

.leave-chip__value {
  margin: var(--space-1) 0 0;
  font-size: 1.125rem;
  font-weight: 800;
  color: var(--color-text);
  letter-spacing: -0.02em;
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
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-progress__pct {
  font-size: 1.0625rem;
  font-weight: 800;
  color: var(--color-tertiary);
}

.leave-progress__track {
  height: 0.625rem;
  clip-path: polygon(0 0, 100% 0, 100% 70%, calc(100% - 4px) 100%, 0 100%);
  background: var(--color-surface-muted);
  overflow: hidden;
}

.leave-progress__fill {
  height: 100%;
  clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
  background: linear-gradient(90deg, var(--color-tertiary-600), var(--color-tertiary));
}

.leave-breakdown__title {
  margin: 0 0 var(--space-3);
  font-size: 1rem;
  font-weight: 800;
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
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text);
}

.leave-breakdown__value {
  font-size: 1rem;
  font-weight: 800;
  color: var(--color-secondary);
}

.leave-types-error {
  margin: calc(-1 * var(--space-3)) 0 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-primary);
  text-align: center;
}

.leave-form-field__req {
  color: var(--color-primary);
}

.leave-create {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-4) var(--space-4);
  border: none;
  clip-path: var(--leave-clip-btn);
  background: linear-gradient(135deg, var(--color-tertiary-700, var(--color-tertiary)), var(--color-tertiary));
  color: var(--color-on-tertiary);
  font-family: inherit;
  font-size: 1.0625rem;
  font-weight: 800;
  cursor: pointer;
  box-shadow: var(--shadow-md);
}

.leave-create:disabled {
  opacity: 0.55;
  cursor: default;
}

.leave-history__title {
  margin: 0 0 var(--space-3);
  font-size: 1.0625rem;
  font-weight: 800;
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
  padding: var(--space-4) var(--space-3);
  clip-path: var(--leave-clip-chip);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.history-row__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  clip-path: polygon(20% 0, 100% 0, 100% 80%, 80% 100%, 0 100%, 0 20%);
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
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text);
}

.history-row__meta {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.history-row__status {
  flex-shrink: 0;
  padding: 0.35rem 0.6rem;
  clip-path: polygon(4px 0, 100% 0, 100% calc(100% - 4px), calc(100% - 4px) 100%, 0 100%, 0 4px);
  font-size: 0.75rem;
  font-weight: 800;
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
