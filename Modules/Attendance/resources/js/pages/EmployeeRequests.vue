<script setup>
//
// Lịch sử của tôi — hub các tiện ích lịch sử.
// Nghỉ phép lấy từ HRM (GET /api/attendance/leave-requests).
// Chấm công đọc nhật ký trên máy, cùng nguồn với màn Chấm công.
//
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import EmployeeAttendanceHistory from '../components/EmployeeAttendanceHistory.vue';

const HISTORY_UTILITIES = [
  {
    id: 'attendance',
    label: 'Lịch sử chấm công',
    hint: 'Ngày công, giờ vào và giờ ra',
    icon: 'clock',
    tone: 'tertiary',
  },
  {
    id: 'leave',
    label: 'Lịch sử nghỉ phép',
    hint: 'Đơn đã gửi và trạng thái duyệt',
    icon: 'calendar',
    tone: 'primary',
  },
];

const route = useRoute();
const router = useRouter();
const attendanceHistoryRef = ref(null);

const activeView = computed(() => {
  const view = String(route.query.view || '');
  return HISTORY_UTILITIES.some((item) => item.id === view) ? view : '';
});

const activeUtility = computed(
  () => HISTORY_UTILITIES.find((item) => item.id === activeView.value) || null,
);

function openUtility(id) {
  router.push({ name: 'employee.requests', query: { view: id } });
}

function backToHub() {
  const previous = window.history.state?.back;
  if (typeof previous === 'string' && previous.split('?')[0].endsWith('/dashboard/me/requests') && !previous.includes('view=')) {
    router.back();
    return;
  }
  router.replace({ name: 'employee.requests' });
}

const STATUS_META = {
  pending: { label: 'Đang chờ duyệt', color: 'var(--color-warning)' },
  approved: { label: 'Đã duyệt', color: 'var(--color-success)' },
  rejected: { label: 'Từ chối', color: 'var(--color-primary)' },
  cancel_requested: { label: 'Chờ duyệt huỷ', color: 'var(--color-warning)' },
  cancelled: { label: 'Đã huỷ', color: 'var(--color-text-muted)' },
};

const MODE_LABELS = {
  day: 'Theo ngày',
  hour: 'Theo giờ',
};

const SESSION_LABELS = {
  morning: 'Ca sáng',
  afternoon: 'Ca chiều',
};

/** @type {import('vue').Ref<Array<Record<string, unknown>>>} */
const requests = ref([]);
const loading = ref(false);
const loadError = ref('');
const leaveLoaded = ref(false);

const selected = ref(null);

function statusMeta(status) {
  return STATUS_META[status] ?? STATUS_META.pending;
}

function formatDateOnly(value) {
  if (!value) return '';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString('vi-VN');
}

function formatDateTime(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatMode(mode) {
  return MODE_LABELS[String(mode ?? '')] ?? (mode ? String(mode) : '—');
}

function formatSession(session) {
  if (!session) return '';
  return SESSION_LABELS[String(session)] ?? String(session);
}

function formatTimeRange(row) {
  const part = [row.time_from, row.time_to].filter(Boolean).join(' – ');
  return part || '—';
}

function formatRange(row) {
  const from = formatDateOnly(row.date_from);
  const to =
    row.date_to && row.date_to !== row.date_from ? formatDateOnly(row.date_to) : '';

  if (row.mode === 'hour') {
    const timePart = formatTimeRange(row);
    if (timePart !== '—') {
      return from ? `${from} · ${timePart}` : timePart;
    }
  }

  const session = formatSession(row.session);
  if (session && from && !to) {
    return `${from} · ${session}`;
  }

  if (from && to) return `${from} – ${to}`;
  return from || '—';
}

function normalizePerson(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const name = String(raw.name ?? raw.full_name ?? '').trim();
  if (!name) return null;
  const code = raw.code != null ? String(raw.code).trim() : '';
  const jobTitle = raw.job_title != null ? String(raw.job_title).trim() : '';
  return {
    name,
    code: code || null,
    jobTitle: jobTitle || null,
  };
}

function resolvePerson(row, objectKeys, nameKey) {
  for (const key of objectKeys) {
    const person = normalizePerson(row[key]);
    if (person) return person;
  }
  if (nameKey && row[nameKey]) {
    return normalizePerson({ name: row[nameKey], code: row[`${nameKey.replace(/_name$/, '')}_code`] });
  }
  return null;
}

function formatPersonLine(person) {
  if (!person) return '—';
  const withCode = person.code ? `${person.name} (${person.code})` : person.name;
  return person.jobTitle ? `${withCode} · ${person.jobTitle}` : withCode;
}

function mapLeaveRow(row, index) {
  const status = String(row.status ?? 'pending');
  const approver = resolvePerson(row, ['approver'], 'approver_name');
  const follower = resolvePerson(row, ['follower'], null);
  const handover = resolvePerson(row, ['handover'], null);
  const rejectReason = String(row.reject_reason ?? '').trim();
  const cancelReason = String(row.cancel_reason ?? '').trim();
  const documentLink = String(row.document_link ?? '').trim();
  const dateTo =
    row.date_to && row.date_to !== row.date_from ? formatDateOnly(row.date_to) : '';

  return {
    id: row.uuid ?? `leave-${index}`,
    kind: row.leave_type_name ?? 'Nghỉ phép',
    leaveTypeCode: String(row.leave_type_code ?? '').trim(),
    submittedAt: formatDateOnly(row.submitted_at ?? row.created_at),
    submittedAtDetail: formatDateTime(row.submitted_at ?? row.created_at),
    range: formatRange(row),
    mode: String(row.mode ?? ''),
    modeLabel: formatMode(row.mode),
    sessionLabel: formatSession(row.session),
    dateFrom: formatDateOnly(row.date_from) || '—',
    dateTo: dateTo || '—',
    timeRange: formatTimeRange(row),
    reason: String(row.reason ?? '').trim() || '—',
    status,
    statusLabel: String(row.status_label ?? '').trim() || statusMeta(status).label,
    approver,
    approverLine: formatPersonLine(approver),
    followerLine: formatPersonLine(follower),
    handoverLine: formatPersonLine(handover),
    hasFollower: Boolean(follower),
    hasHandover: Boolean(handover),
    totalDays: Number(row.total_days ?? 0),
    decidedAt: row.decided_at ? formatDateTime(row.decided_at) : '',
    rejectReason,
    cancelReason,
    documentLink,
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
    leaveLoaded.value = true;
  } catch (err) {
    requests.value = [];
    loadError.value = err?.response?.data?.message ?? 'Không tải được danh sách đơn.';
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

async function refreshPage() {
  if (activeView.value === 'leave') {
    await loadRequests();
    return;
  }
  if (activeView.value === 'attendance') {
    attendanceHistoryRef.value?.reload?.();
  }
}

watch(activeView, (view) => {
  if (view !== 'leave') selected.value = null;
  if (view === 'leave' && !leaveLoaded.value) {
    void loadRequests();
  }
}, { immediate: true });

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

onMounted(() => {
  if (typeof registerPageRefresh === 'function') {
    unregisterPageRefresh = registerPageRefresh(refreshPage);
  }
});

onBeforeUnmount(() => {
  unregisterPageRefresh?.();
});
</script>

<template>
  <div class="requests">
    <section v-if="!activeUtility" class="history-hub" aria-label="Tiện ích lịch sử">
      <p class="history-hub__lead">Chọn mục để xem lịch sử của bạn.</p>
      <ul class="history-hub__grid">
        <li v-for="item in HISTORY_UTILITIES" :key="item.id">
          <button
            type="button"
            class="history-tile"
            :class="`history-tile--${item.tone}`"
            @click="openUtility(item.id)"
          >
            <span class="history-tile__icon" :class="`history-tile__icon--${item.tone}`">
              <AppIcon :name="item.icon" :size="22" :stroke-width="1.75" />
            </span>
            <span class="history-tile__label">{{ item.label }}</span>
            <span class="history-tile__hint">{{ item.hint }}</span>
          </button>
        </li>
      </ul>
    </section>

    <template v-else>
      <button type="button" class="history-back" @click="backToHub">
        <AppIcon name="chevronLeft" :size="18" />
        <span>{{ activeUtility.label }}</span>
      </button>

      <EmployeeAttendanceHistory
        v-if="activeView === 'attendance'"
        ref="attendanceHistoryRef"
      />

      <template v-else-if="activeView === 'leave'">
        <p v-if="loading" class="requests-state">Đang tải đơn nghỉ phép…</p>
        <p v-else-if="loadError" class="requests-state requests-state--error">{{ loadError }}</p>
        <p v-else-if="requests.length === 0" class="requests-state">Chưa có đơn nghỉ phép nào.</p>

        <ul v-else class="requests-list">
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
                  {{ req.statusLabel }}
                </span>
              </span>
              <span class="request-row__range">{{ req.range }}</span>
              <span class="request-row__sub">
                {{ req.submittedAt }}
                <template v-if="req.totalDays > 0"> · {{ req.totalDays }} ngày</template>
                <template v-if="req.approver"> · {{ req.approver.name }}</template>
              </span>
            </span>
            <AppIcon name="chevronRight" :size="18" class="request-row__chev" aria-hidden="true" />
          </button>
        </li>
        </ul>
      </template>
    </template>

    <Teleport to="body">
      <div v-if="selected" class="sheet-overlay" @click.self="closeDetail">
        <div class="sheet" role="dialog" aria-modal="true" :aria-label="selected.kind">
          <div class="sheet__handle" aria-hidden="true"></div>

          <div class="sheet__header">
            <div class="sheet__header-main">
              <div class="sheet__title-row">
                <p class="sheet__title">{{ selected.kind }}</p>
                <span class="sheet__badge" :data-status="selected.status">{{ selected.statusLabel }}</span>
              </div>
              <p v-if="selected.leaveTypeCode" class="sheet__meta">{{ selected.leaveTypeCode }}</p>
            </div>
            <button type="button" class="sheet__close" aria-label="Đóng" @click="closeDetail">
              <AppIcon name="close" :size="18" />
            </button>
          </div>

          <dl class="sheet__details">
            <div class="sheet__row">
              <dt>Thời điểm gửi</dt>
              <dd>{{ selected.submittedAtDetail }}</dd>
            </div>
            <div class="sheet__row">
              <dt>Hình thức</dt>
              <dd>{{ selected.modeLabel }}</dd>
            </div>
            <div class="sheet__row">
              <dt>Từ ngày</dt>
              <dd>{{ selected.dateFrom }}</dd>
            </div>
            <div v-if="selected.dateTo !== '—'" class="sheet__row">
              <dt>Đến ngày</dt>
              <dd>{{ selected.dateTo }}</dd>
            </div>
            <div v-if="selected.sessionLabel" class="sheet__row">
              <dt>Buổi</dt>
              <dd>{{ selected.sessionLabel }}</dd>
            </div>
            <div v-if="selected.mode === 'hour'" class="sheet__row">
              <dt>Khung giờ</dt>
              <dd>{{ selected.timeRange }}</dd>
            </div>
            <div v-if="selected.totalDays > 0" class="sheet__row">
              <dt>Số ngày công</dt>
              <dd>{{ selected.totalDays }}</dd>
            </div>
            <div class="sheet__row sheet__row--block">
              <dt>Lý do nghỉ</dt>
              <dd class="sheet__reason">{{ selected.reason }}</dd>
            </div>
            <div v-if="selected.documentLink" class="sheet__row sheet__row--block">
              <dt>Tài liệu</dt>
              <dd class="sheet__link-wrap">
                <a
                  class="sheet__link"
                  :href="selected.documentLink"
                  target="_blank"
                  rel="noopener noreferrer"
                >{{ selected.documentLink }}</a>
              </dd>
            </div>
            <div class="sheet__row sheet__row--person">
              <dt>Người duyệt</dt>
              <dd>{{ selected.approverLine }}</dd>
            </div>
            <div v-if="selected.hasFollower" class="sheet__row">
              <dt>Người theo dõi</dt>
              <dd>{{ selected.followerLine }}</dd>
            </div>
            <div v-if="selected.hasHandover" class="sheet__row">
              <dt>Bàn giao</dt>
              <dd>{{ selected.handoverLine }}</dd>
            </div>
            <div v-if="selected.decidedAt" class="sheet__row">
              <dt>Thời điểm xử lý</dt>
              <dd>{{ selected.decidedAt }}</dd>
            </div>
            <div v-if="selected.rejectReason" class="sheet__row sheet__row--block">
              <dt>Lý do từ chối</dt>
              <dd class="sheet__reason sheet__reason--warn">{{ selected.rejectReason }}</dd>
            </div>
            <div v-if="selected.cancelReason" class="sheet__row sheet__row--block">
              <dt>Lý do huỷ</dt>
              <dd class="sheet__reason">{{ selected.cancelReason }}</dd>
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

.history-hub {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.history-hub__lead {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: var(--color-text-muted);
}

.history-hub__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.history-tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  min-height: 7.5rem;
  padding: var(--space-3) var(--space-3) var(--space-3)
    calc(var(--space-2) + 3px + var(--space-3));
  border: none;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  text-align: left;
  cursor: pointer;
  font-family: inherit;
}

.history-tile::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.history-tile--primary::before {
  background: var(--color-primary);
}

.history-tile--tertiary::before {
  background: var(--color-tertiary);
}

.history-tile:active {
  background: var(--color-surface-muted);
}

.history-tile__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
}

.history-tile__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.history-tile__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.history-tile__label {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--color-text);
}

.history-tile__hint {
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.35;
  color: var(--color-text-muted);
}

.history-back {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  align-self: flex-start;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
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

.request-row--cancel_requested::before {
  background: var(--color-warning);
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

.request-row__badge[data-status='cancel_requested'] {
  color: color-mix(in srgb, var(--color-warning) 85%, var(--color-text));
  background: color-mix(in srgb, var(--color-warning) 14%, var(--color-surface));
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

.sheet__title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.sheet__title {
  margin: 0;
  flex: 1;
  min-width: 0;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.35;
  color: var(--color-text);
}

.sheet__meta {
  margin: 4px 0 0;
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--color-text-muted);
}

.sheet__badge {
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: var(--radius-full);
  font-size: 0.6875rem;
  font-weight: 400;
  line-height: 1.35;
  white-space: nowrap;
}

.sheet__badge[data-status='pending'],
.sheet__badge[data-status='cancel_requested'] {
  color: color-mix(in srgb, var(--color-warning) 85%, var(--color-text));
  background: color-mix(in srgb, var(--color-warning) 14%, var(--color-surface));
}

.sheet__badge[data-status='approved'] {
  color: color-mix(in srgb, var(--color-success) 85%, var(--color-text));
  background: color-mix(in srgb, var(--color-success) 14%, var(--color-surface));
}

.sheet__badge[data-status='rejected'] {
  color: var(--color-primary);
  background: var(--color-primary-surface);
}

.sheet__badge[data-status='cancelled'] {
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
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

.sheet__reason--warn {
  color: var(--color-primary);
}

.sheet__link-wrap {
  text-align: left;
  word-break: break-word;
}

.sheet__link {
  font-size: 0.8125rem;
  color: var(--color-primary);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.sheet__row--person dd {
  line-height: 1.4;
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
