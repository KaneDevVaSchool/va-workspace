<script setup>
//
// Lịch sử của tôi — hub các tiện ích lịch sử.
// Nghỉ phép lấy từ HRM (GET /api/attendance/leave-requests).
// Chấm công đọc nhật ký trên máy, cùng nguồn với màn Chấm công.
//
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import EmployeeAttendanceHistory from '../components/EmployeeAttendanceHistory.vue';
import EmployeePwaLogoClip from '../components/EmployeePwaLogoClip.vue';
import { EMPLOYEE_HISTORY_UTILITIES, isHistoryUtilityId } from '../constants/employeeHistoryUtilities.js';
import { preloadEmployeePwaLogos } from '../constants/employeePwaBrandVisuals.js';
import { buildAttendanceMonth } from '../lib/employeeAttendanceHistory';
import { groupItemsByWeek, parseIsoDate } from '../lib/employeeHistoryWeek';
import { readEmployeeAttendanceLog } from '../lib/employeeAttendanceSession';

const LEAVE_FILTERS = [
  { id: 'all', label: 'Tất cả' },
  { id: 'pending', label: 'Chờ duyệt' },
  { id: 'approved', label: 'Đã duyệt' },
  { id: 'rejected', label: 'Từ chối' },
];

const route = useRoute();
const router = useRouter();
const attendanceHistoryRef = ref(null);

const activeView = computed(() => {
  const view = String(route.query.view || '');
  return isHistoryUtilityId(view) ? view : '';
});

const leaveFilter = ref('all');
const hubLeaveCount = ref(null);
const hubAttendanceDays = ref(null);

function openUtility(id) {
  router.push({ name: 'employee.requests', query: { view: id } });
}

const filteredLeaveRequests = computed(() => {
  if (leaveFilter.value === 'all') return requests.value;
  return requests.value.filter((row) => row.status === leaveFilter.value);
});

const leaveWeekGroups = computed(() =>
  groupItemsByWeek(filteredLeaveRequests.value, (row) => row.sortDate ?? null),
);

const leaveFilterCounts = computed(() => {
  const counts = { all: requests.value.length };
  for (const row of requests.value) {
    counts[row.status] = (counts[row.status] ?? 0) + 1;
  }
  return counts;
});

function hubTileMeta(id) {
  if (id === 'leave' && hubLeaveCount.value != null) {
    const n = hubLeaveCount.value;
    return n > 0 ? `${n} đơn` : 'Chưa có đơn';
  }
  if (id === 'attendance' && hubAttendanceDays.value != null) {
    const n = hubAttendanceDays.value;
    return n > 0 ? `${n} ngày tháng này` : 'Chưa có trên máy';
  }
  return '';
}

function refreshHubAttendanceCount() {
  const now = new Date();
  const model = buildAttendanceMonth(
    new Date(now.getFullYear(), now.getMonth(), 1),
    readEmployeeAttendanceLog(),
  );
  hubAttendanceDays.value = model.stats.recorded;
}

async function loadHubLeaveCount() {
  try {
    const { data } = await window.axios.get('/api/attendance/leave-requests', {
      params: { limit: 50 },
    });
    const rows = Array.isArray(data?.data) ? data.data : [];
    hubLeaveCount.value = rows.length;
  } catch {
    hubLeaveCount.value = null;
  }
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
const exportingId = ref('');

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

  const sortSource = row.date_from ?? row.submitted_at ?? row.created_at;
  const sortDate = parseIsoDate(sortSource);

  return {
    id: row.uuid ?? `leave-${index}`,
    sortDate,
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
    hubLeaveCount.value = requests.value.length;
    leaveLoaded.value = true;
  } catch (err) {
    requests.value = [];
    loadError.value = err?.response?.data?.message ?? 'Không tải được danh sách đơn.';
  } finally {
    loading.value = false;
  }
}

// Mở chi tiết thì thêm một mục lịch sử (`?sheet=1`) để nút Back của hệ thống đóng bảng chi tiết
// thay vì rời cả trang — PWA không có nút Back của trình duyệt.
let sheetEntryPushed = false;

async function exportLeaveForm(request) {
  const id = String(request?.id ?? '');
  if (!id || exportingId.value) return;
  exportingId.value = id;
  try {
    const response = await window.axios.get(`/api/attendance/leave-requests/${id}/form`, {
      responseType: 'blob',
    });
    const blob = response.data;
    if (blob?.type && blob.type.includes('json')) {
      const json = JSON.parse(await blob.text());
      throw new Error(json.message || 'Không xuất được đơn nghỉ phép.');
    }

    const disposition = response.headers['content-disposition'] || '';
    const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
    const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
    const filename = decodeURIComponent(utfMatch?.[1] || plainMatch?.[1] || 'Don-nghi-phep.pdf');
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
    showClientToast('success', 'Đã tải đơn nghỉ phép.');
  } catch (err) {
    let message = err?.message;
    if (err?.response?.data instanceof Blob) {
      try {
        const json = JSON.parse(await err.response.data.text());
        message = json.message || message;
      } catch {
        message = 'Không xuất được đơn nghỉ phép.';
      }
    } else {
      message = err?.response?.data?.message || message;
    }
    showClientToast('error', message || 'Không xuất được đơn nghỉ phép.');
  } finally {
    exportingId.value = '';
  }
}

function openDetail(request) {
  selected.value = request;
  if (sheetEntryPushed) return;
  sheetEntryPushed = true;
  router
    .push({ name: 'employee.requests', query: { ...route.query, sheet: '1' } })
    .catch(() => {});
}

function closeDetail() {
  selected.value = null;
  if (!sheetEntryPushed) return;
  sheetEntryPushed = false;
  // Đóng bằng nút trong app: gỡ mục lịch sử đã thêm.
  if (route.query.sheet) router.back();
}

watch(() => route.query.sheet, (sheet) => {
  // Người dùng bấm Back của hệ thống: mục lịch sử bị gỡ → đóng bảng chi tiết.
  if (sheet || !sheetEntryPushed) return;
  sheetEntryPushed = false;
  selected.value = null;
});

onBeforeUnmount(() => {
  // Rời trang: không được gọi router.back() từ closeDetail nữa.
  sheetEntryPushed = false;
});

async function refreshPage() {
  refreshHubAttendanceCount();
  if (!activeView.value) {
    await loadHubLeaveCount();
  }
  if (activeView.value === 'leave') {
    await loadRequests();
    return;
  }
  if (activeView.value === 'attendance') {
    attendanceHistoryRef.value?.reload?.();
  }
}

watch(activeView, (view) => {
  if (view !== 'leave') {
    selected.value = null;
    leaveFilter.value = 'all';
  }
  if (view === 'leave' && !leaveLoaded.value) {
    void loadRequests();
  }
}, { immediate: true });

const registerPageRefresh = inject('employeePageRefreshRegister', null);
let unregisterPageRefresh;

onMounted(() => {
  preloadEmployeePwaLogos();
  refreshHubAttendanceCount();
  void loadHubLeaveCount();
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
    <section v-if="!activeView" class="history-hub" aria-label="Tiện ích lịch sử">
      <ul class="history-hub__grid">
        <li v-for="item in EMPLOYEE_HISTORY_UTILITIES" :key="item.id">
          <button
            type="button"
            class="history-tile"
            :class="`history-tile--${item.tone}`"
            @click="openUtility(item.id)"
          >
            <EmployeePwaLogoClip :visual-index="item.visualIndex" />
            <span class="history-tile__icon" :class="`history-tile__icon--${item.tone}`">
              <AppIcon :name="item.icon" :size="22" :stroke-width="1.75" />
            </span>
            <span class="history-tile__text">
              <span class="history-tile__label">{{ item.label }}</span>
              <span v-if="hubTileMeta(item.id)" class="history-tile__meta">{{ hubTileMeta(item.id) }}</span>
            </span>
            <AppIcon name="chevronRight" :size="18" class="history-tile__chev" aria-hidden="true" />
          </button>
        </li>
      </ul>
    </section>

    <template v-else>
      <EmployeeAttendanceHistory
        v-if="activeView === 'attendance'"
        ref="attendanceHistoryRef"
      />

      <template v-else-if="activeView === 'leave'">
        <p v-if="loading" class="requests-state">Đang tải đơn nghỉ phép…</p>
        <div v-else-if="loadError" class="requests-state requests-state--error">
          <p>{{ loadError }}</p>
          <button type="button" class="requests-retry" @click="loadRequests">Thử lại</button>
        </div>
        <template v-else-if="requests.length === 0">
          <p class="requests-state">Chưa có đơn nghỉ phép nào.</p>
        </template>
        <template v-else>
          <div class="leave-filters" role="tablist" aria-label="Lọc trạng thái đơn">
            <button
              v-for="chip in LEAVE_FILTERS"
              :key="chip.id"
              type="button"
              role="tab"
              class="leave-filters__chip"
              :class="{ 'leave-filters__chip--active': leaveFilter === chip.id }"
              :aria-selected="leaveFilter === chip.id"
              @click="leaveFilter = chip.id"
            >
              {{ chip.label }}
              <span v-if="leaveFilterCounts[chip.id]" class="leave-filters__count">
                {{ leaveFilterCounts[chip.id] }}
              </span>
            </button>
          </div>
          <p v-if="filteredLeaveRequests.length === 0" class="requests-state">
            Không có đơn ở trạng thái này.
          </p>
          <div v-else class="requests-weeks">
            <section
              v-for="group in leaveWeekGroups"
              :key="group.key"
              class="requests-week"
              :aria-label="`Tuần ${group.weekNum}`"
            >
              <header class="requests-week__head">
                <span class="requests-week__title">Tuần {{ group.weekNum }}</span>
                <span class="requests-week__range">{{ group.label }}</span>
              </header>
              <ul class="requests-list">
                <li v-for="req in group.items" :key="req.id">
                  <div class="request-card" :class="`request-card--${req.status}`">
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
                  <button
                    v-if="req.status === 'approved'"
                    type="button"
                    class="request-export"
                    :disabled="exportingId === req.id"
                    @click="exportLeaveForm(req)"
                  >
                    <AppIcon name="download" :size="16" />
                    {{ exportingId === req.id ? 'Đang xuất…' : 'Xuất đơn nghỉ phép' }}
                  </button>
                  </div>
                </li>
              </ul>
            </section>
          </div>
        </template>
      </template>
    </template>

    <Teleport to="body">
      <div v-if="selected" class="sheet-overlay" @click.self="closeDetail">
        <div class="sheet hide-scrollbar" role="dialog" aria-modal="true" :aria-label="selected.kind">
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

          <button
            v-if="selected.status === 'approved'"
            type="button"
            class="sheet__export"
            :disabled="exportingId === selected.id"
            @click="exportLeaveForm(selected)"
          >
            <AppIcon name="download" :size="16" />
            {{ exportingId === selected.id ? 'Đang xuất…' : 'Xuất đơn nghỉ phép' }}
          </button>

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

.history-hub__grid {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.history-tile {
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-height: 4.75rem;
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

.history-tile__text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.history-tile__label {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--color-text);
}

.history-tile__meta {
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.35;
  color: var(--color-text-muted);
}

.history-tile__chev {
  flex-shrink: 0;
  color: var(--color-border-strong);
}

.leave-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.leave-filters__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.leave-filters__chip--active {
  background: var(--color-primary-surface);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border));
  color: var(--color-primary);
}

.leave-filters__count {
  min-width: 1.125rem;
  padding: 0 0.25rem;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, currentColor 12%, var(--color-surface));
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.35;
  text-align: center;
}

.requests-retry {
  margin-top: var(--space-2);
  padding: 0.4rem 0.85rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.requests-state--error p {
  margin: 0;
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

.requests-weeks {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.requests-week {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.requests-week__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0 var(--space-1);
}

.requests-week__title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.requests-week__range {
  font-size: 0.75rem;
  font-weight: 500;
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

.request-card {
  position: relative;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.request-card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
  z-index: 1;
}

.request-card--pending::before,
.request-card--cancel_requested::before {
  background: var(--color-warning);
}

.request-card--approved::before {
  background: var(--color-success);
}

.request-card--rejected::before {
  background: var(--color-primary);
}

.request-card--cancelled::before {
  background: var(--color-text-muted);
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
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.15s ease;
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

.request-export,
.sheet__export {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  border: none;
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.request-export {
  padding: 0.55rem var(--space-3);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.sheet__export {
  margin-bottom: var(--space-3);
  padding: 0.7rem 1rem;
  border-radius: var(--radius-md);
}

.request-export:disabled,
.sheet__export:disabled {
  opacity: 0.65;
  cursor: default;
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
  padding: var(--space-2) var(--space-4) var(--space-5);
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
