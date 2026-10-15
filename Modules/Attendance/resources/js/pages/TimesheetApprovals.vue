<script setup>
//
// Trưởng đơn vị duyệt bảng chấm công HRM ngay trên Workspace.
// Dữ liệu + quyền do VA-HRM quyết định (API v1 attendance/timesheet-approvals qua
// backend Workspace) — trang chỉ hiển thị và gửi quyết định duyệt / từ chối.
// Mở từ thông báo: ?confirmation=<uuid> tự mở chi tiết bảng đó.
//
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';

const STATUS_TONE = {
  pending_unit_head: 'warning',
  pending_hcns: 'info',
  pending_pp: 'info',
  archived: 'success',
  unlock_requested: 'success',
  rejected: 'danger',
};

const route = useRoute();
const router = useRouter();

const tab = ref('pending');
const items = ref([]);
const loading = ref(false);
const loadError = ref('');

const selectedUuid = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');

const acting = ref(false);
const approveOpen = ref(false);
const rejectOpen = ref(false);
const rejectReason = ref('');
const rejectError = ref('');

const pendingCount = computed(() => (tab.value === 'pending' ? items.value.length : null));
const canDecide = computed(() => Boolean(detail.value?.can_decide));

function errorMessage(error, fallback) {
  return error?.response?.data?.message || fallback;
}

function formatDays(value) {
  const number = Number(value ?? 0);
  if (!Number.isFinite(number) || Math.abs(number) < 0.00001) return '–';
  return number.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
}

function toneOf(status) {
  return STATUS_TONE[status] ?? 'info';
}

async function loadList() {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await window.axios.get('/api/attendance/timesheet-approvals', {
      params: { scope: tab.value },
    });
    items.value = Array.isArray(data.data) ? data.data : [];
  } catch (error) {
    items.value = [];
    loadError.value = errorMessage(error, 'Không tải được danh sách bảng chấm công.');
  } finally {
    loading.value = false;
  }
}

async function openDetail(uuid) {
  if (!uuid) return;
  selectedUuid.value = uuid;
  detail.value = null;
  detailError.value = '';
  rejectOpen.value = false;
  rejectReason.value = '';
  rejectError.value = '';

  if (route.query.confirmation !== uuid) {
    router.replace({ query: { ...route.query, confirmation: uuid } });
  }

  detailLoading.value = true;
  try {
    const { data } = await window.axios.get(`/api/attendance/timesheet-approvals/${uuid}`);
    detail.value = data.data ?? null;
  } catch (error) {
    detailError.value = errorMessage(error, 'Không tải được bảng chấm công.');
  } finally {
    detailLoading.value = false;
  }
}

function closeDetail() {
  selectedUuid.value = null;
  detail.value = null;
  const { confirmation, ...rest } = route.query;
  if (confirmation) router.replace({ query: rest });
}

async function approve() {
  if (!selectedUuid.value || acting.value) return;
  acting.value = true;
  try {
    const { data } = await window.axios.post(`/api/attendance/timesheet-approvals/${selectedUuid.value}/approve`);
    approveOpen.value = false;
    detail.value = { ...(detail.value ?? {}), ...(data.data ?? {}) };
    showClientToast('success', data.message || 'Đã duyệt bảng chấm công.');
    await loadList();
  } catch (error) {
    approveOpen.value = false;
    showClientToast('error', errorMessage(error, 'Không duyệt được bảng chấm công.'));
    await openDetail(selectedUuid.value);
  } finally {
    acting.value = false;
  }
}

async function reject() {
  if (!selectedUuid.value || acting.value) return;
  const reason = rejectReason.value.trim();
  if (reason.length < 5) {
    rejectError.value = 'Lý do từ chối cần tối thiểu 5 ký tự.';
    return;
  }

  acting.value = true;
  rejectError.value = '';
  try {
    const { data } = await window.axios.post(`/api/attendance/timesheet-approvals/${selectedUuid.value}/reject`, {
      reason,
    });
    rejectOpen.value = false;
    rejectReason.value = '';
    detail.value = { ...(detail.value ?? {}), ...(data.data ?? {}) };
    showClientToast('success', data.message || 'Đã từ chối bảng chấm công.');
    await loadList();
  } catch (error) {
    const firstError = error?.response?.data?.errors?.reason?.[0];
    rejectError.value = firstError || errorMessage(error, 'Không từ chối được bảng chấm công.');
  } finally {
    acting.value = false;
  }
}

watch(tab, () => {
  closeDetail();
  loadList();
});

onMounted(async () => {
  await loadList();
  const fromQuery = typeof route.query.confirmation === 'string' ? route.query.confirmation : null;
  if (fromQuery) {
    await openDetail(fromQuery);
  }
});
</script>

<template>
  <section class="ts-approvals">
    <PageHeader
      title="Duyệt bảng công"
      icon="clipboardCheck"
      :subtitle="pendingCount !== null ? `${pendingCount} bảng chờ duyệt` : ''"
    >
      <template #actions>
        <button type="button" class="ts-approvals__header-btn" :disabled="loading" @click="loadList">
          <AppIcon name="refresh" :size="16" :class="{ 'ts-approvals__spin': loading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="ts-approvals__tabs" role="tablist" aria-label="Danh sách bảng chấm công">
      <button
        type="button"
        role="tab"
        class="ts-approvals__tab"
        :class="{ 'ts-approvals__tab--active': tab === 'pending' }"
        :aria-selected="tab === 'pending'"
        @click="tab = 'pending'"
      >
        Chờ duyệt
      </button>
      <button
        type="button"
        role="tab"
        class="ts-approvals__tab"
        :class="{ 'ts-approvals__tab--active': tab === 'history' }"
        :aria-selected="tab === 'history'"
        @click="tab = 'history'"
      >
        Đã xử lý
      </button>
    </div>

    <div class="ts-approvals__body" :class="{ 'ts-approvals__body--detail': selectedUuid }">
      <div class="ts-approvals__list" :aria-busy="loading">
        <p v-if="loadError" class="ts-approvals__notice ts-approvals__notice--danger" role="alert">{{ loadError }}</p>
        <p v-else-if="loading && items.length === 0" class="ts-approvals__muted">Đang tải…</p>
        <p v-else-if="items.length === 0" class="ts-approvals__empty">
          {{ tab === 'pending' ? 'Không có bảng chấm công nào chờ anh/chị duyệt.' : 'Chưa có bảng chấm công nào đã xử lý.' }}
        </p>

        <button
          v-for="item in items"
          :key="`${item.uuid}-${item.decision?.id ?? 'p'}`"
          type="button"
          class="ts-approvals__card"
          :class="{ 'ts-approvals__card--active': item.uuid === selectedUuid }"
          @click="openDetail(item.uuid)"
        >
          <span class="ts-approvals__card-top">
            <span class="ts-approvals__card-title">{{ item.org_unit?.name }}</span>
            <span class="ts-approvals__badge" :class="`ts-approvals__badge--${toneOf(item.status)}`">
              {{ item.status_label }}
            </span>
          </span>
          <span class="ts-approvals__card-meta">
            {{ item.period?.label }}<template v-if="item.company_name"> · {{ item.company_name }}</template>
          </span>
          <span class="ts-approvals__card-meta">
            <template v-if="item.decision">
              {{ item.decision.action_label }} · {{ formatDateTime(item.decision.created_at) }}
            </template>
            <template v-else>
              {{ item.employee_count }} nhân sự · gửi {{ formatDateTime(item.submitted_at) }}
              <span v-if="item.is_overdue" class="ts-approvals__overdue">Quá hạn</span>
            </template>
          </span>
        </button>
      </div>

      <div v-if="selectedUuid" class="ts-approvals__detail">
        <div class="ts-approvals__detail-head">
          <button type="button" class="ts-approvals__back" @click="closeDetail">
            <AppIcon name="chevronLeft" :size="16" />
            Danh sách
          </button>
          <a
            v-if="detail?.hrm_url"
            class="ts-approvals__link"
            :href="detail.hrm_url"
            target="_blank"
            rel="noopener"
          >
            Mở trên HRM
            <AppIcon name="externalLink" :size="14" />
          </a>
        </div>

        <p v-if="detailLoading" class="ts-approvals__muted">Đang tải…</p>
        <p v-else-if="detailError" class="ts-approvals__notice ts-approvals__notice--danger" role="alert">{{ detailError }}</p>

        <template v-else-if="detail">
          <h2 class="ts-approvals__title">{{ detail.org_unit?.name }} · {{ detail.period?.label }}</h2>
          <dl class="ts-approvals__facts">
            <div>
              <dt>Trạng thái</dt>
              <dd>
                <span class="ts-approvals__badge" :class="`ts-approvals__badge--${toneOf(detail.status)}`">
                  {{ detail.status_label }}
                </span>
              </dd>
            </div>
            <div v-if="detail.company_name">
              <dt>Pháp nhân</dt>
              <dd>{{ detail.company_name }}</dd>
            </div>
            <div>
              <dt>Người gửi</dt>
              <dd>{{ detail.submitted_by_name || '—' }} · {{ formatDateTime(detail.submitted_at) }}</dd>
            </div>
            <div v-if="detail.sla_due_at && detail.status === 'pending_unit_head'">
              <dt>Hạn xử lý</dt>
              <dd>
                {{ formatDateTime(detail.sla_due_at) }}
                <span v-if="detail.is_overdue" class="ts-approvals__overdue">Quá hạn</span>
              </dd>
            </div>
            <div v-if="detail.status === 'rejected' && detail.reject_reason">
              <dt>Lý do trả về</dt>
              <dd>{{ detail.reject_reason }}</dd>
            </div>
          </dl>

          <div v-if="canDecide" class="ts-approvals__actions">
            <button type="button" class="ts-approvals__btn ts-approvals__btn--primary" :disabled="acting" @click="approveOpen = true">
              <AppIcon name="check" :size="16" />
              Duyệt
            </button>
            <button
              type="button"
              class="ts-approvals__btn ts-approvals__btn--danger"
              :disabled="acting"
              @click="rejectOpen = !rejectOpen"
            >
              Từ chối
            </button>
          </div>

          <form v-if="canDecide && rejectOpen" class="ts-approvals__reject" @submit.prevent="reject">
            <label class="ts-approvals__label" for="ts-reject-reason">Lý do từ chối</label>
            <textarea
              id="ts-reject-reason"
              v-model="rejectReason"
              class="ts-approvals__textarea"
              rows="3"
              maxlength="1000"
              required
            />
            <p v-if="rejectError" class="ts-approvals__field-error" role="alert">{{ rejectError }}</p>
            <button type="submit" class="ts-approvals__btn ts-approvals__btn--danger" :disabled="acting">
              Từ chối — trả về Admin đơn vị
            </button>
          </form>

          <template v-if="detail.digest">
            <h3 class="ts-approvals__section">
              Tổng hợp công · {{ detail.digest.employees?.length ?? 0 }} nhân sự · công chuẩn
              {{ formatDays(detail.digest.max_workdays) }}
            </h3>
            <div class="ts-approvals__table-wrap">
              <table class="ts-approvals__table">
                <thead>
                  <tr>
                    <th scope="col">Nhân sự</th>
                    <th v-for="column in detail.digest.columns" :key="column.key" scope="col">{{ column.label }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="employee in detail.digest.employees" :key="employee.uuid">
                    <td>
                      <strong>{{ employee.full_name }}</strong>
                      <span class="ts-approvals__muted-inline">
                        <template v-if="employee.code"> · {{ employee.code }}</template>
                        <template v-if="employee.job_title"> · {{ employee.job_title }}</template>
                      </span>
                    </td>
                    <td v-for="column in detail.digest.columns" :key="column.key">
                      {{ formatDays(employee.totals?.[column.key]) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td>Tổng</td>
                    <td v-for="column in detail.digest.columns" :key="column.key">
                      {{ formatDays(detail.digest.totals?.[column.key]) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </template>

          <template v-if="detail.history?.length">
            <h3 class="ts-approvals__section">Lịch sử duyệt</h3>
            <ol class="ts-approvals__history">
              <li v-for="entry in [...detail.history].reverse()" :key="entry.id">
                <span :class="{ 'ts-approvals__history-reject': entry.is_rejection }">{{ entry.action_label }}</span>
                <span class="ts-approvals__muted-inline">
                  · {{ entry.actor_name || '—' }} · {{ entry.channel_label }} · {{ formatDateTime(entry.created_at) }}
                </span>
                <p v-if="entry.reason" class="ts-approvals__history-reason">«{{ entry.reason }}»</p>
              </li>
            </ol>
          </template>
        </template>
      </div>
    </div>

    <ConfirmDialog
      v-model:open="approveOpen"
      title="Duyệt bảng chấm công"
      description="Bảng chấm công chuyển sang HCNS kiểm tra."
      confirm-label="Duyệt"
      :loading="acting"
      @confirm="approve"
    />
  </section>
</template>

<style scoped>
.ts-approvals {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-5);
  overflow: hidden;
  container-type: inline-size;
}

.ts-approvals__header-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.ts-approvals__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ts-approvals__spin {
  animation: ts-approvals-spin 0.8s linear infinite;
}

@keyframes ts-approvals-spin {
  to {
    transform: rotate(360deg);
  }
}

.ts-approvals__tabs {
  display: inline-flex;
  gap: var(--space-1);
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  align-self: flex-start;
}

.ts-approvals__tab {
  border: none;
  border-radius: var(--radius-sm);
  padding: 0.375rem 0.875rem;
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.ts-approvals__tab--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.ts-approvals__body {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--space-4);
  overflow: hidden;
}

.ts-approvals__list,
.ts-approvals__detail {
  min-height: 0;
  overflow-y: auto;
}

.ts-approvals__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.ts-approvals__detail {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

@container (min-width: 860px) {
  .ts-approvals__body--detail {
    grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
  }
}

@container (max-width: 859px) {
  .ts-approvals__body--detail .ts-approvals__list {
    display: none;
  }
}

.ts-approvals__card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  width: 100%;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.ts-approvals__card:hover {
  background: var(--color-surface-muted);
}

.ts-approvals__card--active {
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.ts-approvals__card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
}

.ts-approvals__card-title {
  font-weight: 600;
}

.ts-approvals__card-meta {
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.ts-approvals__badge {
  flex-shrink: 0;
  border-radius: var(--radius-full);
  padding: 0.125rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
}

.ts-approvals__badge--warning {
  background: var(--color-warning-tint-bg);
  color: var(--color-warning-tint-fg);
}

.ts-approvals__badge--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.ts-approvals__badge--success {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.ts-approvals__badge--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
}

.ts-approvals__overdue {
  margin-left: var(--space-1);
  font-weight: 600;
  color: var(--color-danger);
}

.ts-approvals__detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.ts-approvals__back,
.ts-approvals__link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border: none;
  background: none;
  padding: 0;
  color: var(--color-primary);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.ts-approvals__title {
  margin: 0;
  font-size: 1.0625rem;
  line-height: 1.35;
}

.ts-approvals__facts {
  display: grid;
  gap: var(--space-2);
  margin: 0;
}

.ts-approvals__facts > div {
  display: grid;
  grid-template-columns: minmax(96px, 30%) 1fr;
  gap: var(--space-2);
  font-size: 0.875rem;
}

.ts-approvals__facts dt {
  color: var(--color-text-muted);
}

.ts-approvals__facts dd {
  margin: 0;
  overflow-wrap: anywhere;
}

.ts-approvals__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.ts-approvals__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: 2.25rem;
  padding: 0 1rem;
  border: none;
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.ts-approvals__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ts-approvals__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.ts-approvals__btn--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
}

.ts-approvals__reject {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
}

.ts-approvals__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.ts-approvals__textarea {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border-strong);
  color: var(--color-text);
  font: inherit;
  resize: vertical;
}

.ts-approvals__field-error {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-danger);
}

.ts-approvals__section {
  margin: var(--space-2) 0 0;
  font-size: 0.9375rem;
}

.ts-approvals__table-wrap {
  overflow-x: auto;
  border-radius: var(--radius-md);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.ts-approvals__table {
  width: 100%;
  min-width: 560px;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.ts-approvals__table th,
.ts-approvals__table td {
  padding: 0.5rem 0.625rem;
  text-align: right;
  white-space: nowrap;
}

.ts-approvals__table th:first-child,
.ts-approvals__table td:first-child {
  text-align: left;
  white-space: normal;
}

.ts-approvals__table thead th {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-weight: 600;
}

.ts-approvals__table tbody td,
.ts-approvals__table tfoot td {
  border-top: 1px solid var(--color-border);
}

.ts-approvals__table tfoot td {
  font-weight: 600;
  background: var(--color-surface-muted);
}

.ts-approvals__history {
  margin: 0;
  padding-left: 1.125rem;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  font-size: 0.8125rem;
}

.ts-approvals__history-reject {
  font-weight: 600;
  color: var(--color-danger);
}

.ts-approvals__history-reason {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  overflow-wrap: anywhere;
}

.ts-approvals__muted,
.ts-approvals__empty {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.ts-approvals__empty {
  padding: var(--space-5);
  text-align: center;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.ts-approvals__muted-inline {
  color: var(--color-text-muted);
}

.ts-approvals__notice {
  margin: 0;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
}

.ts-approvals__notice--danger {
  background: var(--color-danger-tint-bg);
  color: var(--color-danger-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-danger-tint-border);
}

@media (max-width: 640px) {
  .ts-approvals {
    padding: var(--space-3);
  }

  .ts-approvals__btn {
    flex: 1 1 auto;
  }
}
</style>
