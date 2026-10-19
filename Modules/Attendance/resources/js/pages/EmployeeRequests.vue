<script setup>
//
// Danh sách đơn nghỉ phép của tôi — lấy từ HRM qua GET /api/attendance/leave-requests.
// Giải trình chấm công sẽ bổ sung khi có API workspace/HRM tương ứng.
//
import { inject, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const STATUS_META = {
  pending: { label: 'Đang chờ duyệt', color: 'var(--color-warning)' },
  approved: { label: 'Đã duyệt', color: 'var(--color-success)' },
  rejected: { label: 'Từ chối', color: 'var(--color-primary)' },
  cancelled: { label: 'Đã huỷ', color: 'var(--color-text-muted)' },
};

/** @type {import('vue').Ref<Array<Record<string, unknown>>>} */
const requests = ref([]);
const loading = ref(true);
const loadError = ref('');

const selected = ref(null);

function statusMeta(status) {
  return STATUS_META[status] ?? STATUS_META.pending;
}

function formatSubmittedAt(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString('vi-VN');
}

function formatRange(row) {
  const from = row.date_from ? new Date(row.date_from).toLocaleDateString('vi-VN') : '';
  const to =
    row.date_to && row.date_to !== row.date_from
      ? new Date(row.date_to).toLocaleDateString('vi-VN')
      : '';

  if (row.mode === 'hour' && (row.time_from || row.time_to)) {
    const timePart = [row.time_from, row.time_to].filter(Boolean).join(' – ');
    return from ? `${from} · ${timePart}` : timePart || '—';
  }

  if (from && to) return `${from} – ${to}`;
  return from || '—';
}

function formatApprover(row, status) {
  const name =
    row.approver_name ??
    row.approver?.name ??
    row.approved_by_name ??
    row.approved_by?.name ??
    null;
  if (name) return String(name);
  if (status === 'pending') return 'Chưa có người duyệt';
  return '—';
}

function mapLeaveRow(row, index) {
  const status = String(row.status ?? 'pending');
  return {
    id: row.uuid ?? `leave-${index}`,
    kind: row.leave_type_name ?? 'Nghỉ phép',
    submittedAt: formatSubmittedAt(row.submitted_at ?? row.created_at),
    range: formatRange(row),
    reason: String(row.reason ?? '').trim() || '—',
    status,
    approver: formatApprover(row, status),
    totalDays: Number(row.total_days ?? 0),
  };
}

async function loadRequests() {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await window.axios.get('/api/attendance/leave-requests', {
      params: { limit: 50 },
    });
    const rows = Array.isArray(data?.data) ? data.data : [];
    requests.value = rows.map(mapLeaveRow);
  } catch (err) {
    requests.value = [];
    loadError.value =
      err?.response?.data?.message ?? 'Không tải được đơn nghỉ phép từ HRM.';
  } finally {
    loading.value = false;
  }
}

function openDetail(request) {
  selected.value = request;
}

function closeDetail() {
  selected.value = null;
}

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

onMounted(() => {
  void loadRequests();
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(loadRequests);
  }
});

onBeforeUnmount(() => {
  unregisterPageRefresh?.();
});
</script>

<template>
  <div class="requests">
    <p v-if="loading" class="requests-state">Đang tải đơn nghỉ phép…</p>
    <p v-else-if="loadError" class="requests-state requests-state--error">{{ loadError }}</p>
    <p v-else-if="requests.length === 0" class="requests-state">
      Chưa có đơn nghỉ phép nào trên HRM.
    </p>

    <template v-else>
      <p class="requests-count">{{ requests.length }} đơn</p>
      <ul class="requests-list">
        <li v-for="req in requests" :key="req.id">
          <button
            type="button"
            class="request-row"
            :class="`request-row--${req.status}`"
            @click="openDetail(req)"
          >
            <span class="request-row__body">
              <span class="request-row__line">
                <span class="request-row__kind">{{ req.kind }}</span>
                <span class="request-row__badge" :data-status="req.status">
                  {{ statusMeta(req.status).label }}
                </span>
              </span>
              <span class="request-row__range">{{ req.range }}</span>
              <span class="request-row__sub">
                Gửi {{ req.submittedAt }}
                <template v-if="req.totalDays > 0"> · {{ req.totalDays }} ngày</template>
              </span>
            </span>
            <AppIcon name="chevronRight" :size="18" class="request-row__chev" aria-hidden="true" />
          </button>
        </li>
      </ul>
    </template>

    <Teleport to="body">
      <div v-if="selected" class="sheet-overlay" @click.self="closeDetail">
        <div class="sheet" role="dialog" aria-modal="true" :aria-label="selected.kind">
          <div class="sheet__handle" aria-hidden="true"></div>

          <div class="sheet__header">
            <div class="sheet__header-main">
              <p class="sheet__kicker">Chi tiết đơn</p>
              <p class="sheet__title">{{ selected.kind }}</p>
            </div>
            <button type="button" class="sheet__close" aria-label="Đóng" @click="closeDetail">
              <AppIcon name="close" :size="18" />
            </button>
          </div>

          <p class="sheet__status-banner" :data-status="selected.status">
            {{ statusMeta(selected.status).label }}
          </p>

          <dl class="sheet__details">
            <div class="sheet__row">
              <dt>Ngày gửi</dt>
              <dd>{{ selected.submittedAt }}</dd>
            </div>
            <div class="sheet__row">
              <dt>Thời gian nghỉ</dt>
              <dd>{{ selected.range }}</dd>
            </div>
            <div v-if="selected.totalDays > 0" class="sheet__row">
              <dt>Số ngày</dt>
              <dd>{{ selected.totalDays }}</dd>
            </div>
            <div class="sheet__row sheet__row--block">
              <dt>Lý do</dt>
              <dd class="sheet__reason">{{ selected.reason }}</dd>
            </div>
            <div class="sheet__row">
              <dt>Người duyệt</dt>
              <dd>{{ selected.approver }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.requests {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.requests-state {
  margin: 0;
  padding: var(--space-6) var(--space-3);
  font-size: 0.875rem;
  font-weight: 400;
  line-height: 1.5;
  color: var(--color-text-muted);
  text-align: center;
}

.requests-state--error {
  color: var(--color-primary);
}

.requests-count {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 400;
  letter-spacing: 0.02em;
  color: var(--color-text-muted);
}

.requests-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.request-row {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3) var(--space-3) var(--space-3)
    calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.15s ease;
}

.request-row::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.request-row--pending::before {
  background: var(--color-warning);
}

.request-row--approved::before {
  background: var(--color-success);
}

.request-row--rejected::before {
  background: var(--color-primary);
}

.request-row--cancelled::before {
  background: var(--color-text-muted);
}

.request-row:active {
  background: var(--color-surface-muted);
}

.request-row__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.request-row__line {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.request-row__kind {
  font-size: 0.875rem;
  font-weight: 400;
  line-height: 1.35;
  color: var(--color-text);
}

.request-row__badge {
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 400;
  line-height: 1.35;
  white-space: nowrap;
}

.request-row__badge[data-status='pending'] {
  color: color-mix(in srgb, var(--color-warning) 85%, var(--color-text));
  background: color-mix(in srgb, var(--color-warning) 14%, var(--color-surface));
}

.request-row__badge[data-status='approved'] {
  color: color-mix(in srgb, var(--color-success) 85%, var(--color-text));
  background: color-mix(in srgb, var(--color-success) 14%, var(--color-surface));
}

.request-row__badge[data-status='rejected'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.request-row__badge[data-status='cancelled'] {
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
}

.request-row__range {
  font-size: 0.8125rem;
  font-weight: 400;
  line-height: 1.4;
  color: var(--color-text);
}

.request-row__sub {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

.request-row__chev {
  flex-shrink: 0;
  color: var(--color-border-strong);
}

.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 600;
  display: flex;
  align-items: flex-end;
  background: var(--color-sidebar-overlay, rgba(15, 23, 42, 0.45));
}

.sheet {
  width: 100%;
  max-height: min(85dvh, 32rem);
  overflow-y: auto;
  padding: var(--space-2) var(--space-4)
    calc(var(--space-5) + var(--employee-safe-bottom, env(safe-area-inset-bottom, 0px)));
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.sheet__handle {
  width: 2.25rem;
  height: 3px;
  margin: var(--space-2) auto var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-border);
}

.sheet__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

.sheet__header-main {
  min-width: 0;
}

.sheet__kicker {
  margin: 0 0 2px;
  font-size: 0.6875rem;
  font-weight: 400;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.sheet__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.35;
  color: var(--color-text);
}

.sheet__close {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.sheet__status-banner {
  margin: 0 0 var(--space-4);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.8125rem;
  font-weight: 400;
  text-align: center;
}

.sheet__status-banner[data-status='pending'] {
  color: color-mix(in srgb, var(--color-warning) 90%, var(--color-text));
  background: color-mix(in srgb, var(--color-warning) 12%, var(--color-surface-muted));
}

.sheet__status-banner[data-status='approved'] {
  color: color-mix(in srgb, var(--color-success) 90%, var(--color-text));
  background: color-mix(in srgb, var(--color-success) 12%, var(--color-surface-muted));
}

.sheet__status-banner[data-status='rejected'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.sheet__status-banner[data-status='cancelled'] {
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
}

.sheet__details {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.sheet__row {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  gap: var(--space-3);
  padding: var(--space-3) 0;
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.sheet__row:last-child {
  box-shadow: none;
}

.sheet__row--block {
  grid-template-columns: 1fr;
  gap: var(--space-1);
}

.sheet__row dt {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

.sheet__row dd {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 400;
  line-height: 1.45;
  color: var(--color-text);
  text-align: right;
}

.sheet__row--block dd {
  text-align: left;
}

.sheet__reason {
  font-style: italic;
  color: var(--color-text);
}

@media (min-width: 480px) {
  .sheet-overlay {
    align-items: center;
    justify-content: center;
    padding: var(--space-4);
  }

  .sheet {
    max-width: 24rem;
    border-radius: var(--radius-xl);
    max-height: min(80dvh, 28rem);
  }

  .sheet__handle {
    display: none;
  }
}
</style>
