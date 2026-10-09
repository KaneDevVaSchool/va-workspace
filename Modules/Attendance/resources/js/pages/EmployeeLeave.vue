<script setup>
//
// Nghỉ phép mobile — catalog + gửi đơn qua HRM (HRM_LEAVE_API_* → POST /api/v1/leave/requests).
// Số dư phép năm vẫn mock cục bộ cho tới khi HRM có API balance.
//
import { computed, onMounted, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AppDatePicker from '@/components/AppDatePicker.vue';
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
  reason: '',
  documentLink: '',
});

/** @typedef {'drive' | 'files'} DocumentAttachMode */

/** @type {import('vue').Ref<DocumentAttachMode>} */
const documentAttachMode = ref('drive');

/** @type {import('vue').Ref<Array<{ id: number, file: File, name: string, sizeLabel: string }>>} */
const attachedFiles = ref([]);

const documentFileInput = ref(null);

let attachmentSeq = 1;

const MAX_ATTACHMENT_FILES = 8;
const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;
const ATTACHMENT_ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf';

const todayMinDate = computed(() => {
  const n = new Date();
  const y = n.getFullYear();
  const m = String(n.getMonth() + 1).padStart(2, '0');
  const d = String(n.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
});

function parseLocalDateParts(dateStr) {
  const [y, m, d] = String(dateStr || '').split('-').map(Number);
  if (!y || !m || !d) return null;
  return new Date(y, m - 1, d);
}

function startOfToday() {
  const t = new Date();
  t.setHours(0, 0, 0, 0);
  return t;
}

function minTimeForPeriod(period) {
  if (period.mode !== 'hour' || period.date !== todayMinDate.value) return undefined;
  const n = new Date();
  return `${String(n.getHours()).padStart(2, '0')}:${String(n.getMinutes()).padStart(2, '0')}`;
}

function minToDateForPeriod(period) {
  if (period.mode !== 'day') return todayMinDate.value;
  return period.fromDate && period.fromDate > todayMinDate.value ? period.fromDate : todayMinDate.value;
}

function formatFileSize(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function setDocumentAttachMode(mode) {
  documentAttachMode.value = mode;
}

function openDocumentFilePicker() {
  documentFileInput.value?.click();
}

function onDocumentFilesChange(event) {
  const input = event.target;
  const picked = input.files ? Array.from(input.files) : [];
  input.value = '';

  if (!picked.length) return;

  const room = MAX_ATTACHMENT_FILES - attachedFiles.value.length;
  if (room <= 0) {
    showClientToast('warning', `Chỉ đính kèm tối đa ${MAX_ATTACHMENT_FILES} tệp.`);
    return;
  }

  for (const file of picked.slice(0, room)) {
    if (file.size > MAX_ATTACHMENT_BYTES) {
      showClientToast('warning', `Tệp «${file.name}» vượt 10 MB.`);
      continue;
    }
    attachedFiles.value.push({
      id: attachmentSeq++,
      file,
      name: file.name,
      sizeLabel: formatFileSize(file.size),
    });
  }
}

function removeAttachedFile(id) {
  attachedFiles.value = attachedFiles.value.filter((row) => row.id !== id);
}

function validateDocumentAttachment() {
  if (!selectedLeaveType.value?.requires_document_link) return true;

  if (documentAttachMode.value === 'drive') {
    if (!form.documentLink.trim()) {
      showClientToast('warning', 'Nhập link Google Drive hoặc chuyển sang đính kèm tệp.');
      return false;
    }
    return true;
  }

  if (attachedFiles.value.length === 0) {
    showClientToast('warning', 'Chọn ít nhất một tệp đính kèm.');
    return false;
  }

  return true;
}

const leaveWorkflow = reactive({
  approverGroupLabel: 'Người duyệt',
  approver: null,
  notifyTo: null,
  watcherLabel: 'Nhân sự phụ trách',
  directManager: null,
  directManagerLabel: 'Cấp trên trực tiếp',
  hrResponsible: null,
  hrResponsibleCaption: '',
  message: null,
});
const workflowLoading = ref(false);

function resetLeaveWorkflow() {
  leaveWorkflow.approverGroupLabel = 'Người duyệt';
  leaveWorkflow.approver = null;
  leaveWorkflow.notifyTo = null;
  leaveWorkflow.watcherLabel = 'Nhân sự phụ trách';
  leaveWorkflow.directManager = null;
  leaveWorkflow.directManagerLabel = 'Cấp trên trực tiếp';
  leaveWorkflow.hrResponsible = null;
  leaveWorkflow.hrResponsibleCaption = '';
  leaveWorkflow.message = null;
}

async function loadLeaveWorkflow() {
  workflowLoading.value = true;
  resetLeaveWorkflow();
  try {
    const { data } = await window.axios.get('/api/attendance/leave-workflow');
    const row = data?.data ?? {};
    leaveWorkflow.approverGroupLabel = row.approver_group_label || 'Người duyệt';
    leaveWorkflow.approver = row.approver ?? null;
    leaveWorkflow.notifyTo = row.notify_to ?? null;
    leaveWorkflow.watcherLabel = row.watcher_label || 'Nhân sự phụ trách';
    leaveWorkflow.directManager = row.direct_manager ?? null;
    leaveWorkflow.directManagerLabel = row.direct_manager_label || 'Cấp trên trực tiếp';
    leaveWorkflow.hrResponsible = row.hr_responsible ?? null;
    leaveWorkflow.hrResponsibleCaption = row.hr_responsible_caption ?? '';
    leaveWorkflow.message = row.message ?? null;
  } catch {
    leaveWorkflow.message = 'Không tải được người duyệt từ HRM.';
  } finally {
    workflowLoading.value = false;
  }
}

function formatContactPerson(person) {
  if (!person?.full_name) return '—';
  const code = person.code?.trim();
  return code ? `${person.full_name} (${code})` : person.full_name;
}

function formatApproverName(approver) {
  if (!approver?.name) return '';
  const code = approver.code?.trim();
  return code ? `${approver.name} (${code})` : approver.name;
}

let periodSeq = 1;

function createPeriod() {
  return {
    id: periodSeq++,
    mode: 'day',
    fromDate: '',
    toDate: '',
    date: '',
    fromTime: '',
    toTime: '',
  };
}

const periods = ref([createPeriod()]);

const selectedLeaveType = computed(() =>
  leaveTypes.value.find((t) => Number(t.id) === Number(form.leaveTypeId)) ?? null,
);

const documentFieldLabel = computed(() => {
  const custom = selectedLeaveType.value?.document_link_label;
  if (typeof custom === 'string' && custom.trim()) return custom.trim();
  return 'Link ảnh Giấy chứng sinh / Giấy chứng nhận phẫu thuật';
});

function addPeriod() {
  periods.value.push(createPeriod());
}

function removePeriod(id) {
  if (periods.value.length <= 1) return;
  periods.value = periods.value.filter((p) => p.id !== id);
}

function setPeriodMode(period, mode) {
  period.mode = mode;
}

function approverInitials(name) {
  const parts = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();
  return (parts[0].slice(0, 1) + parts[parts.length - 1].slice(0, 1)).toUpperCase();
}

function formatPeriodSummary(period) {
  if (period.mode === 'hour') {
    if (!period.date) return '';
    const d = new Date(period.date).toLocaleDateString('vi-VN');
    const t =
      period.fromTime && period.toTime ? `${period.fromTime}–${period.toTime}` : period.fromTime || '';
    return t ? `${d} · ${t}` : d;
  }
  if (!period.fromDate) return '';
  const from = new Date(period.fromDate).toLocaleDateString('vi-VN');
  if (!period.toDate || period.toDate === period.fromDate) return from;
  const to = new Date(period.toDate).toLocaleDateString('vi-VN');
  return `${from} – ${to}`;
}

function validatePeriods() {
  const today = startOfToday();
  const now = new Date();

  for (const period of periods.value) {
    if (period.mode === 'hour') {
      if (!period.date || !period.fromTime || !period.toTime) {
        showClientToast('warning', 'Nhập đủ ngày và khung giờ cho từng khoảng nghỉ.');
        return false;
      }

      const day = parseLocalDateParts(period.date);
      if (!day || day < today) {
        showClientToast('warning', 'Không chọn ngày nghỉ trong quá khứ.');
        return false;
      }

      const [fh, fm] = period.fromTime.split(':').map(Number);
      const [th, tm] = period.toTime.split(':').map(Number);
      const start = new Date(day);
      start.setHours(fh, fm, 0, 0);
      const end = new Date(day);
      end.setHours(th, tm, 0, 0);

      if (start < now) {
        showClientToast('warning', 'Không chọn khung giờ đã qua.');
        return false;
      }
      if (end <= start) {
        showClientToast('warning', 'Giờ kết thúc phải sau giờ bắt đầu.');
        return false;
      }
      continue;
    }

    if (!period.fromDate || !period.toDate) {
      showClientToast('warning', 'Chọn đủ từ ngày và đến ngày cho từng khoảng nghỉ.');
      return false;
    }

    const from = parseLocalDateParts(period.fromDate);
    const to = parseLocalDateParts(period.toDate);
    if (!from || !to || from < today) {
      showClientToast('warning', 'Không chọn ngày nghỉ trong quá khứ.');
      return false;
    }
    if (to < from) {
      showClientToast('warning', 'Đến ngày không được trước từ ngày.');
      return false;
    }
  }
  return true;
}

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
  void loadLeaveHistory();
});

/** @type {import('vue').Ref<Array<Record<string, unknown>>>} */
const history = ref([]);
const historyLoading = ref(false);

function historyToneForStatus(status) {
  if (status === 'approved') return 'tertiary';
  if (status === 'rejected') return 'danger';
  if (status === 'cancelled') return 'muted';
  return 'gold';
}

function formatHistoryDateLabel(row) {
  const from = row.date_from ? new Date(row.date_from).toLocaleDateString('vi-VN') : '';
  const to = row.date_to && row.date_to !== row.date_from
    ? new Date(row.date_to).toLocaleDateString('vi-VN')
    : '';
  if (from && to) return `${from} – ${to}`;
  return from || '—';
}

async function loadLeaveHistory() {
  historyLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/attendance/leave-requests', { params: { limit: 20 } });
    const rows = Array.isArray(data?.data) ? data.data : [];
    history.value = rows.map((row, index) => ({
      id: row.uuid ?? index,
      type: row.leave_type_name ?? 'Nghỉ phép',
      days: Number(row.total_days ?? 0),
      dateLabel: formatHistoryDateLabel(row),
      status: row.status ?? 'pending',
      icon: 'calendar',
      tone: historyToneForStatus(row.status),
    }));
  } catch {
    history.value = [];
  } finally {
    historyLoading.value = false;
  }
}

function buildPeriodsForApi() {
  return periods.value.map((period) => {
    if (period.mode === 'hour') {
      return {
        mode: 'hour',
        date_from: period.date,
        date_to: period.date,
        time_from: period.fromTime,
        time_to: period.toTime,
      };
    }
    return {
      mode: 'day',
      date_from: period.fromDate,
      date_to: period.toDate || period.fromDate,
    };
  });
}

const statusLabel = {
  approved: 'Đã duyệt',
  pending: 'Đang chờ duyệt',
  rejected: 'Từ chối',
};

function openForm() {
  formOpen.value = true;
  void loadLeaveWorkflow();
}

function closeForm() {
  formOpen.value = false;
}

function resetForm() {
  form.leaveTypeId = leaveTypes.value[0]?.id ?? null;
  form.reason = '';
  form.documentLink = '';
  documentAttachMode.value = 'drive';
  attachedFiles.value = [];
  attachmentSeq = 1;
  periodSeq = 1;
  periods.value = [createPeriod()];
}

async function submit() {
  if (!form.leaveTypeId) {
    showClientToast('warning', 'Chọn loại nghỉ trước khi gửi.');
    return;
  }
  if (!validatePeriods()) return;
  if (!form.reason.trim()) {
    showClientToast('warning', 'Nhập lý do nghỉ phép.');
    return;
  }
  if (!validateDocumentAttachment()) return;

  submitting.value = true;

  const payload = {
    leave_type_id: Number(form.leaveTypeId),
    reason: form.reason.trim(),
    document_link: documentAttachMode.value === 'drive' ? form.documentLink.trim() : '',
    approver_employee_uuid: leaveWorkflow.approver?.uuid ?? null,
    follower_employee_uuid: leaveWorkflow.hrResponsible?.uuid ?? null,
    periods: buildPeriodsForApi(),
  };

  const hasFiles = documentAttachMode.value === 'files' && attachedFiles.value.length > 0;

  try {
    if (hasFiles) {
      const formData = new FormData();
      formData.append('leave_type_id', String(payload.leave_type_id));
      formData.append('reason', payload.reason);
      if (payload.document_link) formData.append('document_link', payload.document_link);
      if (payload.approver_employee_uuid) {
        formData.append('approver_employee_uuid', payload.approver_employee_uuid);
      }
      if (payload.follower_employee_uuid) {
        formData.append('follower_employee_uuid', payload.follower_employee_uuid);
      }
      payload.periods.forEach((period, index) => {
        Object.entries(period).forEach(([key, value]) => {
          if (value != null && value !== '') {
            formData.append(`periods[${index}][${key}]`, String(value));
          }
        });
      });
      for (const row of attachedFiles.value) {
        formData.append('attachments[]', row.file, row.name);
      }
      await window.axios.post('/api/attendance/leave-requests', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    } else {
      await window.axios.post('/api/attendance/leave-requests', payload);
    }

    showClientToast('success', 'Đã gửi đơn nghỉ phép qua HRM, chờ người duyệt.');
    resetForm();
    closeForm();
    await loadLeaveHistory();
  } catch (err) {
    const message =
      err?.response?.data?.message ??
      err?.response?.data?.errors?.periods?.[0] ??
      'Không gửi được đơn nghỉ. Thử lại sau.';
    showClientToast('error', message);
  } finally {
    submitting.value = false;
  }
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
      <p v-if="historyLoading" class="leave-workflow__status">Đang tải từ HRM…</p>
      <p v-else-if="history.length === 0" class="leave-workflow__status">Chưa có đơn nghỉ nào.</p>
      <ul v-else class="leave-history__list">
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
            <span class="leave-dialog__head-icon" aria-hidden="true">
              <AppIcon name="calendar" :size="20" :stroke-width="1.8" />
            </span>
            <div class="leave-dialog__head-text">
              <h2 id="leave-form-title" class="leave-dialog__title">Tạo đơn xin nghỉ phép</h2>
              <p v-if="selectedLeaveType" class="leave-dialog__subtitle">
                {{ selectedLeaveType.code }} — {{ selectedLeaveType.name }}
              </p>
            </div>
            <button type="button" class="leave-dialog__close" aria-label="Đóng" @click="closeForm">
              <AppIcon name="close" :size="18" />
            </button>
          </header>

          <form class="leave-dialog__body hide-scrollbar" @submit.prevent="submit">
            <div class="leave-form-stack">
              <section class="leave-form-section">
                <label class="leave-form-field">
                  <span class="leave-form-field__label">Loại nghỉ</span>
                  <select
                    v-model="form.leaveTypeId"
                    class="leave-form-field__control leave-form-field__control--select"
                    :disabled="typesLoading || leaveTypes.length === 0"
                  >
                    <option v-for="opt in leaveTypes" :key="opt.id" :value="opt.id">
                      {{ opt.code }} — {{ opt.name }}
                    </option>
                  </select>
                </label>
              </section>

              <section class="leave-form-section" aria-labelledby="leave-periods-heading">
                <div class="leave-form-section__head">
                  <h3 id="leave-periods-heading" class="leave-form-section__title">Khoảng nghỉ</h3>
                </div>

                <article
                  v-for="(period, index) in periods"
                  :key="period.id"
                  class="leave-period-card leave-card-accent leave-card-accent--info"
                >
                  <header class="leave-period-card__head">
                    <span class="leave-period-card__badge">{{ index + 1 }}</span>
                    <div class="leave-period-mode" role="group" aria-label="Cách tính khoảng nghỉ">
                      <button
                        type="button"
                        class="leave-period-mode__btn"
                        :class="{ 'leave-period-mode__btn--active': period.mode === 'day' }"
                        :aria-pressed="period.mode === 'day'"
                        @click="setPeriodMode(period, 'day')"
                      >
                        Theo ngày
                      </button>
                      <button
                        type="button"
                        class="leave-period-mode__btn"
                        :class="{ 'leave-period-mode__btn--active': period.mode === 'hour' }"
                        :aria-pressed="period.mode === 'hour'"
                        @click="setPeriodMode(period, 'hour')"
                      >
                        Theo giờ
                      </button>
                    </div>
                    <button
                      v-if="periods.length > 1"
                      type="button"
                      class="leave-period-card__remove"
                      aria-label="Xoá khoảng nghỉ"
                      @click="removePeriod(period.id)"
                    >
                      <AppIcon name="close" :size="16" />
                    </button>
                  </header>

                  <div v-if="period.mode === 'day'" class="leave-period-card__dates leave-period-card__dates--day">
                    <div class="leave-form-field">
                      <label class="leave-form-field__label" :for="`leave-period-${period.id}-from`">Từ ngày</label>
                      <AppDatePicker
                        :id="`leave-period-${period.id}-from`"
                        v-model="period.fromDate"
                        :min="todayMinDate"
                      />
                    </div>
                    <div class="leave-form-field">
                      <label class="leave-form-field__label" :for="`leave-period-${period.id}-to`">Đến ngày</label>
                      <AppDatePicker
                        :id="`leave-period-${period.id}-to`"
                        v-model="period.toDate"
                        :min="minToDateForPeriod(period)"
                      />
                    </div>
                  </div>
                  <div v-else class="leave-period-card__dates leave-period-card__dates--hour">
                    <div class="leave-form-field">
                      <label class="leave-form-field__label" :for="`leave-period-${period.id}-date`">Ngày</label>
                      <AppDatePicker
                        :id="`leave-period-${period.id}-date`"
                        v-model="period.date"
                        :min="todayMinDate"
                      />
                    </div>
                    <div class="leave-period-card__times">
                      <label class="leave-form-field">
                        <span class="leave-form-field__label">Từ giờ</span>
                        <input
                          v-model="period.fromTime"
                          type="time"
                          class="leave-form-field__control leave-form-field__control--time"
                          :min="minTimeForPeriod(period)"
                        />
                      </label>
                      <label class="leave-form-field">
                        <span class="leave-form-field__label">Đến giờ</span>
                        <input
                          v-model="period.toTime"
                          type="time"
                          class="leave-form-field__control leave-form-field__control--time"
                        />
                      </label>
                    </div>
                  </div>
                </article>

                <button type="button" class="leave-period-add" @click="addPeriod">
                  <AppIcon name="plus" :size="18" :stroke-width="2" />
                  <span>Thêm khoảng nghỉ</span>
                </button>
              </section>

              <section
                v-if="selectedLeaveType?.requires_document_link"
                class="leave-form-section leave-form-section--doc leave-card-accent leave-card-accent--gold"
              >
                <div class="leave-form-field">
                  <span class="leave-form-field__label">
                    {{ documentFieldLabel }}
                    <span class="leave-form-field__req">*</span>
                  </span>

                  <div class="leave-doc-mode" role="group" aria-label="Cách đính kèm giấy tờ">
                    <button
                      type="button"
                      class="leave-doc-mode__btn"
                      :class="{ 'leave-doc-mode__btn--active': documentAttachMode === 'drive' }"
                      :aria-pressed="documentAttachMode === 'drive'"
                      @click="setDocumentAttachMode('drive')"
                    >
                      Link Google Drive
                    </button>
                    <button
                      type="button"
                      class="leave-doc-mode__btn"
                      :class="{ 'leave-doc-mode__btn--active': documentAttachMode === 'files' }"
                      :aria-pressed="documentAttachMode === 'files'"
                      @click="setDocumentAttachMode('files')"
                    >
                      Đính kèm tệp
                    </button>
                  </div>

                  <div v-if="documentAttachMode === 'drive'" class="leave-doc-panel">
                    <span class="leave-form-field__with-icon">
                      <AppIcon name="link" :size="18" class="leave-form-field__icon" aria-hidden="true" />
                      <input
                        v-model="form.documentLink"
                        type="url"
                        class="leave-form-field__control leave-form-field__control--icon"
                        placeholder="https://drive.google.com/…"
                      />
                    </span>
                  </div>

                  <div v-else class="leave-doc-panel">
                    <input
                      ref="documentFileInput"
                      type="file"
                      class="leave-doc-file-input"
                      :accept="ATTACHMENT_ACCEPT"
                      multiple
                      @change="onDocumentFilesChange"
                    />
                    <button type="button" class="leave-doc-file-add" @click="openDocumentFilePicker">
                      <AppIcon name="paperclip" :size="18" />
                      <span>Chọn tệp (PDF, JPG, PNG)</span>
                    </button>
                    <ul v-if="attachedFiles.length" class="leave-doc-file-list">
                      <li v-for="row in attachedFiles" :key="row.id" class="leave-doc-file-row">
                        <span class="leave-doc-file-row__name">{{ row.name }}</span>
                        <span class="leave-doc-file-row__size">{{ row.sizeLabel }}</span>
                        <button
                          type="button"
                          class="leave-doc-file-row__remove"
                          aria-label="Gỡ tệp đính kèm"
                          @click="removeAttachedFile(row.id)"
                        >
                          <AppIcon name="close" :size="16" />
                        </button>
                      </li>
                    </ul>
                  </div>
                </div>
              </section>

              <section class="leave-form-section">
                <label class="leave-form-field">
                  <span class="leave-form-field__label">
                    Lý do
                    <span class="leave-form-field__req">*</span>
                  </span>
                  <textarea
                    v-model="form.reason"
                    class="leave-form-field__control leave-form-field__control--area"
                    rows="4"
                    placeholder="Nhập lý do nghỉ phép…"
                  ></textarea>
                </label>
              </section>

              <section class="leave-form-section leave-form-section--workflow" aria-labelledby="leave-workflow-heading">
                <h3 id="leave-workflow-heading" class="leave-form-section__title">Người duyệt</h3>
                <p v-if="workflowLoading" class="leave-workflow__status">Đang tải luồng duyệt…</p>
                <template v-else>
                  <p class="leave-workflow__group">{{ leaveWorkflow.approverGroupLabel }}</p>
                  <p v-if="leaveWorkflow.message && !leaveWorkflow.approver" class="leave-workflow__status">
                    {{ leaveWorkflow.message }}
                  </p>

                  <article
                    v-if="leaveWorkflow.approver"
                    class="leave-approver-card leave-card-accent leave-card-accent--tertiary"
                  >
                    <span class="leave-approver-card__avatar" aria-hidden="true">
                      {{ approverInitials(leaveWorkflow.approver.name) }}
                    </span>
                    <span class="leave-approver-card__body">
                      <span class="leave-approver-card__name">{{ formatApproverName(leaveWorkflow.approver) }}</span>
                      <span v-if="leaveWorkflow.approver.title" class="leave-approver-card__title">
                        {{ leaveWorkflow.approver.title }}
                      </span>
                    </span>
                  </article>

                  <div class="leave-workflow-meta">
                    <div v-if="leaveWorkflow.directManager" class="leave-workflow-row">
                      <span class="leave-workflow-row__label">{{ leaveWorkflow.directManagerLabel }}</span>
                      <span class="leave-workflow-row__value">
                        {{ formatContactPerson(leaveWorkflow.directManager) }}
                      </span>
                    </div>
                    <div v-if="leaveWorkflow.notifyTo" class="leave-workflow-row">
                      <span class="leave-workflow-row__label">Thông báo tới</span>
                      <span class="leave-workflow-row__value">{{ leaveWorkflow.notifyTo }}</span>
                    </div>
                    <div class="leave-workflow-hr">
                      <div class="leave-workflow-row">
                        <span class="leave-workflow-row__label">{{ leaveWorkflow.watcherLabel }}</span>
                        <span class="leave-workflow-row__value">
                          {{ formatContactPerson(leaveWorkflow.hrResponsible) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </template>
              </section>
            </div>
          </form>

          <footer class="leave-dialog__actions">
            <button type="button" class="leave-dialog__btn leave-dialog__btn--ghost" @click="closeForm">Huỷ</button>
            <button
              type="submit"
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
  flex-direction: column;
  justify-content: flex-end;
  align-items: stretch;
  padding: 0;
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
  width: 100%;
  max-width: 32rem;
  margin: 0 auto;
  height: min(calc(100dvh - env(safe-area-inset-top, 0px)), calc(100dvh - 0.5rem));
  max-height: calc(100dvh - env(safe-area-inset-top, 0px));
  overflow: hidden;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  background: var(--color-surface-muted);
  box-shadow: var(--shadow-lg);
}

.leave-dialog__head {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4) var(--space-3);
  background: var(--color-surface);
  box-shadow: inset 0 -1px 0 var(--color-border);
}

.leave-dialog__head-icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--color-tertiary-surface), #fff);
  color: var(--color-tertiary);
  box-shadow: var(--shadow-sm);
}

.leave-dialog__head-text {
  flex: 1;
  min-width: 0;
}

.leave-dialog__title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--color-text);
}

.leave-dialog__subtitle {
  margin: 2px 0 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  line-height: 1.35;
}

.leave-dialog__close {
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

.leave-dialog__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-3) var(--space-4) var(--space-5);
}

.leave-form-stack {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.leave-form-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.leave-form-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.leave-form-section__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 800;
  color: var(--color-text);
  letter-spacing: -0.01em;
}

.leave-form-section--doc {
  padding: var(--space-3) var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave-card-accent {
  position: relative;
  padding-left: calc(var(--space-2) + 3px + var(--space-3));
}

.leave-card-accent::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.leave-card-accent--info::before {
  background: var(--color-tertiary);
}

.leave-card-accent--gold::before {
  background: var(--color-warning, #c47a00);
}

.leave-card-accent--tertiary::before {
  background: var(--color-tertiary);
}

.leave-period-card {
  padding: var(--space-3) var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave-period-card__head {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.leave-period-card__badge {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-full);
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
  font-size: 0.8125rem;
  font-weight: 800;
}

.leave-period-mode {
  flex: 1;
  min-width: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px;
  padding: 3px;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.leave-period-mode__btn {
  padding: var(--space-2) var(--space-2);
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: transparent;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}

.leave-period-mode__btn--active {
  background: var(--color-surface);
  color: var(--color-tertiary);
  box-shadow: var(--shadow-sm);
}

.leave-period-mode__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.leave-period-card__remove {
  flex-shrink: 0;
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

.leave-period-card__dates {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
}

.leave-period-card__dates--day {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-3);
}

.leave-period-card__times {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  min-width: 0;
}

.leave-period-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-tertiary);
  cursor: pointer;
}

.leave-form-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.leave-form-field__label {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.leave-form-field__req {
  color: var(--color-primary);
}

.leave-form-field__with-icon {
  position: relative;
  display: block;
}

.leave-form-field__icon {
  position: absolute;
  top: 50%;
  left: var(--space-3);
  transform: translateY(-50%);
  color: var(--color-text-muted);
  pointer-events: none;
}

.leave-form-field__control {
  box-sizing: border-box;
  width: 100%;
  max-width: 100%;
  padding: var(--space-3) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.9375rem;
  color: var(--color-text);
}

.leave-form-field__control--time {
  min-height: 2.75rem;
  padding: var(--space-2) var(--space-3);
  font-size: 1rem;
  line-height: 1.35;
  color-scheme: light;
}

.leave-form-field__control--time::-webkit-date-and-time-value {
  text-align: left;
}

.leave-form-field__control--time::-webkit-calendar-picker-indicator {
  margin-left: var(--space-1);
  opacity: 0.7;
  cursor: pointer;
}

.leave-form-field__control--select {
  background: var(--color-surface);
}

.leave-form-field__control--icon {
  padding-left: calc(var(--space-3) + 1.5rem);
  background: var(--color-surface);
}

.leave-doc-mode {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px;
  margin: var(--space-2) 0 var(--space-3);
  padding: 3px;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.leave-doc-mode__btn {
  padding: var(--space-2) var(--space-2);
  border: none;
  border-radius: calc(var(--radius-md) - 2px);
  background: transparent;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
  cursor: pointer;
}

.leave-doc-mode__btn--active {
  background: var(--color-surface);
  color: var(--color-tertiary);
  box-shadow: var(--shadow-sm);
}

.leave-doc-mode__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.leave-doc-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.leave-doc-file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.leave-doc-file-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-tertiary);
  cursor: pointer;
}

.leave-doc-file-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.leave-doc-file-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.leave-doc-file-row__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
}

.leave-doc-file-row__size {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-doc-file-row__remove {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.leave-form-field__control:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.leave-form-field__control--area {
  resize: vertical;
  min-height: 5.5rem;
  background: var(--color-surface);
}

.leave-form-section--workflow {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.leave-workflow__group {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.leave-workflow__status {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-approver-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-top: var(--space-2);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.leave-approver-card__avatar {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-full);
  background: linear-gradient(145deg, var(--color-tertiary), var(--color-tertiary-600, var(--color-tertiary)));
  color: var(--color-on-tertiary, #fff);
  font-size: 0.9375rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.leave-approver-card__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.leave-approver-card__name {
  font-size: 0.9375rem;
  font-weight: 800;
  color: var(--color-text);
}

.leave-approver-card__title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  line-height: 1.35;
}

.leave-workflow-meta {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.leave-workflow-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
}

.leave-workflow-row__label {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text-muted);
}

.leave-workflow-row__value {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text);
  text-align: right;
}

.leave-workflow-row__value--muted {
  font-weight: 600;
  color: var(--color-text-muted);
}

.leave-workflow-hr {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.leave-dialog__actions {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  padding-bottom: var(--space-3);
  background: var(--color-surface);
  box-shadow: inset 0 1px 0 var(--color-border);
}

.leave-dialog__btn {
  padding: var(--space-3) var(--space-4);
  border: none;
  border-radius: var(--radius-md);
  font-family: inherit;
  font-size: 0.9375rem;
  font-weight: 800;
  cursor: pointer;
}

.leave-dialog__btn--ghost {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.leave-dialog__btn--primary {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-600, var(--color-primary)));
  color: var(--color-on-primary);
  box-shadow: var(--shadow-sm);
}

.leave-dialog__btn--primary:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (min-width: 480px) {
  .leave-dialog {
    align-items: center;
    justify-content: center;
    padding: var(--space-4);
    padding-bottom: var(--space-4);
  }

  .leave-dialog__panel {
    height: min(36rem, calc(100dvh - 2rem));
    max-height: calc(100dvh - 2rem);
    border-radius: var(--radius-lg);
  }

  .leave-period-card__dates--day {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 480px) {
  .leave-metrics--primary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
