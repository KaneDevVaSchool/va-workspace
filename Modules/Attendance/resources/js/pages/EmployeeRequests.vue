<script setup>
//
// Danh sách đơn nghỉ phép của tôi — lấy từ HRM qua GET /api/attendance/leave-requests.
// Giải trình chấm công sẽ bổ sung khi có API workspace/HRM tương ứng.
//
import { onMounted, ref } from 'vue';
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

onMounted(() => {
  void loadRequests();
});
</script>

<template>
  <div class="requests">
    <p v-if="loading" class="requests-state">Đang tải đơn nghỉ phép…</p>
    <p v-else-if="loadError" class="requests-state requests-state--error">{{ loadError }}</p>
    <p v-else-if="requests.length === 0" class="requests-state">
      Chưa có đơn nghỉ phép nào trên HRM.
    </p>

    <ul v-else class="requests-list">
      <li v-for="req in requests" :key="req.id">
        <button type="button" class="request-row" @click="openDetail(req)">
          <span class="request-row__main">
            <span class="request-row__kind">{{ req.kind }}</span>
            <span class="request-row__range">{{ req.range }}</span>
          </span>
          <span class="request-row__status">
            <span
              class="request-row__dot"
              :style="{ background: statusMeta(req.status).color }"
              aria-hidden="true"
            ></span>
            {{ statusMeta(req.status).label }}
          </span>
        </button>
      </li>
    </ul>

    <Teleport to="body">
      <div v-if="selected" class="sheet-overlay" @click.self="closeDetail">
        <div class="sheet" role="dialog" aria-modal="true" :aria-label="selected.kind">
          <div class="sheet__handle" aria-hidden="true"></div>

          <div class="sheet__header">
            <h2>{{ selected.kind }}</h2>
            <button type="button" class="sheet__close" aria-label="Đóng" @click="closeDetail">
              <AppIcon name="close" :size="18" />
            </button>
          </div>

          <div class="sheet__field">
            <span class="sheet__label">Trạng thái</span>
            <span class="sheet__value sheet__value--status">
              <span
                class="request-row__dot"
                :style="{ background: statusMeta(selected.status).color }"
                aria-hidden="true"
              ></span>
              {{ statusMeta(selected.status).label }}
            </span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Ngày gửi</span>
            <span class="sheet__value">{{ selected.submittedAt }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Thời gian</span>
            <span class="sheet__value">{{ selected.range }}</span>
          </div>
          <div v-if="selected.totalDays > 0" class="sheet__field">
            <span class="sheet__label">Số ngày</span>
            <span class="sheet__value">{{ selected.totalDays }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Lý do</span>
            <span class="sheet__value">{{ selected.reason }}</span>
          </div>
          <div class="sheet__field">
            <span class="sheet__label">Người duyệt</span>
            <span class="sheet__value">{{ selected.approver }}</span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.requests-state {
  margin: 0;
  padding: var(--space-4);
  font-size: 0.875rem;
  color: var(--color-text-muted);
  text-align: center;
}

.requests-state--error {
  color: var(--color-primary);
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.request-row__main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.request-row__kind {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.request-row__range {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.request-row__status {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.8125rem;
  color: var(--color-text);
}

.request-row__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.sheet-overlay {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: flex-end;
  background: var(--color-sidebar-overlay);
}

.sheet {
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  padding: var(--space-2) var(--space-4) var(--space-4);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.sheet__handle {
  width: 2.5rem;
  height: 4px;
  margin: var(--space-2) auto var(--space-3);
  border-radius: var(--radius-full);
  background: var(--color-border-strong);
}

.sheet__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

.sheet__header h2 {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--color-text);
}

.sheet__close {
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

.sheet__field {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.sheet__field:last-child {
  box-shadow: none;
}

.sheet__label {
  flex-shrink: 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.sheet__value {
  text-align: right;
  font-size: 0.875rem;
  color: var(--color-text);
}

.sheet__value--status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}
</style>
