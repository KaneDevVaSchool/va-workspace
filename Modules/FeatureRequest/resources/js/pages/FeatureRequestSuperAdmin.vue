<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate, formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';

const STATUS_LABEL = {
  pending: 'Chờ ghi nhận',
  reviewing: 'Đang xem xét',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  done: 'Đã hoàn thành',
};

const STATUS_FILTERS = [
  { value: '', label: 'Tất cả', icon: 'layoutList' },
  { value: 'pending', label: 'Chờ ghi nhận', icon: 'clock' },
  { value: 'reviewing', label: 'Đang xem xét', icon: 'eye' },
  { value: 'approved', label: 'Đã duyệt', icon: 'check' },
  { value: 'done', label: 'Đã hoàn thành', icon: 'clipboardCheck' },
  { value: 'rejected', label: 'Từ chối', icon: 'close' },
];

const REJECT_REASON_MAX = 500;
const PROGRESS_NOTE_MAX = 500;

const groups = ref([]);
const loading = ref(false);
const statusFilter = ref('');
const searchQuery = ref('');
const selected = ref(null);
const openDepartments = reactive({});

const approveForm = reactive({ expected_done_at: '', progress_note: '' });
const rejectReason = ref('');
const showRejectForm = ref(false);
const acting = ref(false);

const totalCount = computed(() =>
  groups.value.reduce((sum, group) => sum + (group.counts?.total || 0), 0),
);

const overallCounts = computed(() => {
  const counts = { pending: 0, reviewing: 0, approved: 0, rejected: 0, done: 0 };
  for (const group of groups.value) {
    for (const key of Object.keys(counts)) {
      counts[key] += group.counts?.[key] || 0;
    }
  }
  return counts;
});

const visibleGroups = computed(() => {
  const needle = searchQuery.value.trim().toLowerCase();
  if (!needle) return groups.value;

  return groups.value
    .map((group) => ({
      ...group,
      items: group.items.filter(
        (item) =>
          item.description.toLowerCase().includes(needle) ||
          (item.created_by_name || '').toLowerCase().includes(needle) ||
          (item.page_title || '').toLowerCase().includes(needle),
      ),
    }))
    .filter((group) => group.items.length > 0);
});

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/superadmin/feature-requests', {
      params: statusFilter.value ? { status: statusFilter.value } : {},
    });
    groups.value = data.groups ?? [];
    for (const group of groups.value) {
      if (!(group.department_id in openDepartments)) {
        openDepartments[group.department_id ?? 'none'] = true;
      }
    }
  } catch {
    showClientToast('error', 'Không tải được danh sách ghi nhận.');
  } finally {
    loading.value = false;
  }
}

function toggleDepartment(id) {
  const key = id ?? 'none';
  openDepartments[key] = !openDepartments[key];
}

function patchItemInGroups(item) {
  for (const group of groups.value) {
    const index = group.items.findIndex((row) => row.id === item.id);
    if (index !== -1) {
      group.items.splice(index, 1, item);
      group.counts = recomputeCounts(group.items);
      break;
    }
  }
  if (selected.value?.id === item.id) {
    selected.value = item;
  }
}

function recomputeCounts(items) {
  const counts = { pending: 0, reviewing: 0, approved: 0, rejected: 0, done: 0, total: items.length };
  for (const item of items) {
    if (item.status in counts) counts[item.status] += 1;
  }
  return counts;
}

async function openDetail(item) {
  showRejectForm.value = false;
  rejectReason.value = '';
  approveForm.expected_done_at = item.expected_done_at || '';
  approveForm.progress_note = item.progress_note || '';

  try {
    const { data } = await window.axios.get(`/api/superadmin/feature-requests/${item.id}`);
    selected.value = data.item;
    patchItemInGroups(data.item);
  } catch {
    showClientToast('error', 'Không mở được chi tiết ghi nhận.');
  }
}

async function approve() {
  if (!selected.value || acting.value) return;
  acting.value = true;
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${selected.value.id}/approve`, {
      expected_done_at: approveForm.expected_done_at || null,
      progress_note: approveForm.progress_note || null,
    });
    selected.value = data.item;
    patchItemInGroups(data.item);
    showClientToast('success', 'Đã duyệt ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không duyệt được ghi nhận.');
  } finally {
    acting.value = false;
  }
}

async function reject() {
  if (!selected.value || acting.value || !rejectReason.value.trim()) return;
  acting.value = true;
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${selected.value.id}/reject`, {
      reject_reason: rejectReason.value.trim(),
    });
    selected.value = data.item;
    patchItemInGroups(data.item);
    showRejectForm.value = false;
    showClientToast('success', 'Đã từ chối ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không từ chối được ghi nhận.');
  } finally {
    acting.value = false;
  }
}

async function markDone() {
  if (!selected.value || acting.value) return;
  acting.value = true;
  try {
    const { data } = await window.axios.patch(`/api/superadmin/feature-requests/${selected.value.id}/done`);
    selected.value = data.item;
    patchItemInGroups(data.item);
    showClientToast('success', 'Đã đánh dấu hoàn thành.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật được.');
  } finally {
    acting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="fr-page">
    <PageHeader
      title="Ghi nhận yêu cầu tính năng"
      icon="alertTriangle"
      :subtitle="`${totalCount} ghi nhận`"
    />

    <div class="fr-page__summary">
      <div class="fr-page__summary-item">
        <span class="fr-page__summary-dot fr-page__summary-dot--pending" />
        <span class="fr-page__summary-value">{{ overallCounts.pending }}</span>
        <span class="fr-page__summary-label">chờ ghi nhận</span>
      </div>
      <div class="fr-page__summary-item">
        <span class="fr-page__summary-dot fr-page__summary-dot--reviewing" />
        <span class="fr-page__summary-value">{{ overallCounts.reviewing }}</span>
        <span class="fr-page__summary-label">đang xem xét</span>
      </div>
      <div class="fr-page__summary-item">
        <span class="fr-page__summary-dot fr-page__summary-dot--approved" />
        <span class="fr-page__summary-value">{{ overallCounts.approved }}</span>
        <span class="fr-page__summary-label">đã duyệt</span>
      </div>
      <div class="fr-page__summary-item">
        <span class="fr-page__summary-dot fr-page__summary-dot--done" />
        <span class="fr-page__summary-value">{{ overallCounts.done }}</span>
        <span class="fr-page__summary-label">hoàn thành</span>
      </div>
      <div class="fr-page__summary-item">
        <span class="fr-page__summary-dot fr-page__summary-dot--rejected" />
        <span class="fr-page__summary-value">{{ overallCounts.rejected }}</span>
        <span class="fr-page__summary-label">từ chối</span>
      </div>
    </div>

    <div class="fr-page__toolbar">
      <div class="fr-page__search">
        <AppIcon name="search" :size="16" :stroke-width="2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tìm theo nội dung, người gửi hoặc trang đính kèm…"
          class="fr-page__search-input"
        />
      </div>

      <div class="fr-page__filters">
        <button
          v-for="option in STATUS_FILTERS"
          :key="option.value"
          type="button"
          class="fr-page__filter-chip"
          :class="{ 'fr-page__filter-chip--active': statusFilter === option.value }"
          @click="statusFilter = option.value; load();"
        >
          <AppIcon :name="option.icon" :size="13" :stroke-width="2" />
          {{ option.label }}
        </button>
      </div>
    </div>

    <div class="fr-page__body">
      <div class="fr-page__list hide-scrollbar">
        <p v-if="loading" class="fr-page__empty">Đang tải…</p>
        <div v-else-if="visibleGroups.length === 0" class="fr-page__empty">
          <AppIcon name="alertTriangle" :size="32" :stroke-width="1.5" />
          <span>{{ groups.length === 0 ? 'Chưa có ghi nhận nào.' : 'Không tìm thấy ghi nhận phù hợp.' }}</span>
        </div>

        <div v-for="group in visibleGroups" :key="group.department_id ?? 'none'" class="fr-dept">
          <button
            type="button"
            class="fr-dept__head"
            @click="toggleDepartment(group.department_id)"
          >
            <AppIcon
              :name="openDepartments[group.department_id ?? 'none'] ? 'chevronDown' : 'chevronRight'"
              :size="16"
            />
            <span class="fr-dept__name">{{ group.department_name }}</span>
            <span class="fr-dept__count">{{ group.counts.total }} ghi nhận</span>
            <span v-if="group.counts.pending > 0" class="fr-dept__pending">
              {{ group.counts.pending }} chờ xem
            </span>
          </button>

          <div v-if="openDepartments[group.department_id ?? 'none']" class="fr-dept__items">
            <button
              v-for="item in group.items"
              :key="item.id"
              type="button"
              class="fr-row"
              :class="{ 'fr-row--active': selected?.id === item.id }"
              @click="openDetail(item)"
            >
              <span class="fr-row__dot" :class="`fr-row__dot--${item.status}`" />
              <span class="fr-row__desc">{{ item.description }}</span>
              <span class="fr-row__meta">{{ item.created_by_name }}</span>
              <span class="fr-row__status">{{ STATUS_LABEL[item.status] ?? item.status }}</span>
            </button>
          </div>
        </div>
      </div>

      <Transition name="fr-detail-slide">
      <aside v-if="selected" class="fr-detail" aria-label="Chi tiết ghi nhận">
        <div class="fr-detail__head">
          <h2 class="fr-detail__title">Chi tiết ghi nhận</h2>
          <button type="button" class="fr-detail__close" aria-label="Đóng" @click="selected = null">
            <AppIcon name="close" :size="16" />
          </button>
        </div>

        <div class="fr-detail__rows">
          <div class="fr-detail__row">
            <span class="fr-detail__label">Người gửi</span>
            <span class="fr-detail__value">{{ selected.created_by_name }}</span>
          </div>
          <div class="fr-detail__row">
            <span class="fr-detail__label">Phòng ban</span>
            <span class="fr-detail__value">{{ selected.department_name || 'Chưa xác định' }}</span>
          </div>
          <div v-if="selected.page_title" class="fr-detail__row">
            <span class="fr-detail__label">Trang đính kèm</span>
            <a class="fr-detail__value fr-detail__link" :href="selected.page_url" target="_blank" rel="noopener">
              {{ selected.page_title }}
            </a>
          </div>
          <div class="fr-detail__row">
            <span class="fr-detail__label">Trạng thái</span>
            <span class="fr-detail__value fr-detail__value--status">
              <span class="fr-detail__status-dot" :class="`fr-detail__status-dot--${selected.status}`" />
              {{ STATUS_LABEL[selected.status] ?? selected.status }}
            </span>
          </div>
          <div class="fr-detail__row">
            <span class="fr-detail__label">Thời gian gửi</span>
            <span class="fr-detail__value">{{ formatDateTime(selected.created_at) }}</span>
          </div>
          <div v-if="selected.reviewed_by_name" class="fr-detail__row">
            <span class="fr-detail__label">Người xử lý</span>
            <span class="fr-detail__value">{{ selected.reviewed_by_name }}</span>
          </div>
          <div v-if="selected.expected_done_at" class="fr-detail__row">
            <span class="fr-detail__label">Ngày hoàn thành dự kiến</span>
            <span class="fr-detail__value">{{ formatDate(selected.expected_done_at) }}</span>
          </div>
          <div v-if="selected.progress_note" class="fr-detail__row">
            <span class="fr-detail__label">Ghi chú tiến độ</span>
            <span class="fr-detail__value">{{ selected.progress_note }}</span>
          </div>
          <div v-if="selected.reject_reason" class="fr-detail__row">
            <span class="fr-detail__label">Lý do từ chối</span>
            <span class="fr-detail__value">{{ selected.reject_reason }}</span>
          </div>
          <div v-if="selected.done_at" class="fr-detail__row">
            <span class="fr-detail__label">Hoàn thành lúc</span>
            <span class="fr-detail__value">{{ formatDateTime(selected.done_at) }}</span>
          </div>
        </div>

        <div class="fr-detail__desc-block">
          <span class="fr-detail__label">Yêu cầu cụ thể</span>
          <p class="fr-detail__desc">{{ selected.description }}</p>
        </div>

        <div v-if="['pending', 'reviewing', 'approved'].includes(selected.status)" class="fr-detail__actions">
          <div v-if="selected.status !== 'approved'" class="fr-detail__form">
            <label class="fr-detail__form-label" for="fr-expected-done">Ngày hoàn thành dự kiến</label>
            <input id="fr-expected-done" v-model="approveForm.expected_done_at" type="date" class="fr-detail__input" />

            <label class="fr-detail__form-label" for="fr-progress-note">Ghi chú tiến độ</label>
            <textarea
              id="fr-progress-note"
              v-model="approveForm.progress_note"
              class="fr-detail__textarea"
              rows="3"
              :maxlength="PROGRESS_NOTE_MAX"
              placeholder="Vd. Sẽ triển khai trong đợt cập nhật tháng sau."
            />
            <span class="fr-detail__count">{{ approveForm.progress_note.length }}/{{ PROGRESS_NOTE_MAX }}</span>

            <button type="button" class="fr-detail__btn fr-detail__btn--primary" :disabled="acting" @click="approve">
              <AppIcon name="check" :size="14" :stroke-width="2.5" />
              Duyệt ghi nhận
            </button>
          </div>

          <div v-else class="fr-detail__form">
            <label class="fr-detail__form-label" for="fr-progress-note-2">Cập nhật ghi chú tiến độ</label>
            <textarea
              id="fr-progress-note-2"
              v-model="approveForm.progress_note"
              class="fr-detail__textarea"
              rows="3"
              :maxlength="PROGRESS_NOTE_MAX"
              placeholder="Vd. Đang triển khai, dự kiến xong tuần sau."
            />
            <span class="fr-detail__count">{{ approveForm.progress_note.length }}/{{ PROGRESS_NOTE_MAX }}</span>

            <div class="fr-detail__btn-row">
              <button type="button" class="fr-detail__btn fr-detail__btn--primary" :disabled="acting" @click="approve">
                Lưu tiến độ
              </button>
              <button type="button" class="fr-detail__btn fr-detail__btn--success" :disabled="acting" @click="markDone">
                <AppIcon name="clipboardCheck" :size="14" :stroke-width="2" />
                Đánh dấu hoàn thành
              </button>
            </div>
          </div>

          <div class="fr-detail__reject">
            <button
              v-if="!showRejectForm"
              type="button"
              class="fr-detail__btn fr-detail__btn--danger-ghost"
              @click="showRejectForm = true"
            >
              Từ chối ghi nhận
            </button>
            <template v-else>
              <label class="fr-detail__form-label" for="fr-reject-reason">
                Lý do từ chối <span class="fr-detail__required" aria-hidden="true">*</span>
              </label>
              <textarea
                id="fr-reject-reason"
                v-model="rejectReason"
                class="fr-detail__textarea"
                rows="3"
                :maxlength="REJECT_REASON_MAX"
                placeholder="Vd. Tính năng đã có sẵn ở mục Báo cáo."
              />
              <span class="fr-detail__count">{{ rejectReason.length }}/{{ REJECT_REASON_MAX }}</span>

              <div class="fr-detail__btn-row">
                <button type="button" class="fr-detail__btn fr-detail__btn--ghost" @click="showRejectForm = false">
                  Huỷ
                </button>
                <button
                  type="button"
                  class="fr-detail__btn fr-detail__btn--danger"
                  :disabled="acting || !rejectReason.trim()"
                  @click="reject"
                >
                  Xác nhận từ chối
                </button>
              </div>
            </template>
          </div>
        </div>
      </aside>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.fr-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  height: 100%;
  min-height: 0;
  padding: var(--space-4);
  overflow: hidden;
}

.fr-page__summary {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  flex-shrink: 0;
  padding-bottom: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-page__summary-item {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
}

.fr-page__summary-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  align-self: center;
  flex-shrink: 0;
}

.fr-page__summary-dot--pending {
  background: var(--color-text-muted);
}

.fr-page__summary-dot--reviewing {
  background: var(--color-warning);
}

.fr-page__summary-dot--approved {
  background: var(--color-primary);
}

.fr-page__summary-dot--done {
  background: var(--color-success);
}

.fr-page__summary-dot--rejected {
  background: var(--color-danger);
}

.fr-page__summary-value {
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.fr-page__summary-label {
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.fr-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}

.fr-page__search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: 1;
  min-width: 14rem;
  max-width: 24rem;
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
}

.fr-page__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.fr-page__search-input:focus-visible {
  outline: none;
}

.fr-page__filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  flex-shrink: 0;
}

.fr-page__filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  padding: var(--space-1) var(--space-3);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  cursor: pointer;
  transition: background 0.12s ease, border-color 0.12s ease;
}

.fr-page__filter-chip:hover {
  background: var(--color-surface-muted);
}

.fr-page__filter-chip--active {
  border-color: var(--color-primary);
  background: var(--color-primary-surface);
  color: var(--color-primary);
  font-weight: 600;
}

.fr-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.fr-page__list {
  flex: 1;
  min-width: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.fr-page__empty {
  margin: 0;
  padding: var(--space-6);
  text-align: center;
  color: var(--color-text-muted);
}

.fr-dept {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.fr-dept__head {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border: none;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: var(--font-family-base);
  cursor: pointer;
  text-align: left;
}

.fr-dept__name {
  font-weight: 600;
}

.fr-dept__count {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.fr-dept__pending {
  color: var(--color-danger);
  font-size: 0.8125rem;
  font-weight: 600;
}

.fr-dept__items {
  display: flex;
  flex-direction: column;
}

.fr-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  background: var(--color-surface);
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.fr-row:hover,
.fr-row--active {
  background: var(--color-surface-muted);
}

.fr-row__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  background: var(--color-text-muted);
}

.fr-row__dot--pending {
  background: var(--color-text-muted);
}

.fr-row__dot--reviewing {
  background: var(--color-warning);
}

.fr-row__dot--approved {
  background: var(--color-primary);
}

.fr-row__dot--done {
  background: var(--color-success);
}

.fr-row__dot--rejected {
  background: var(--color-danger);
}

.fr-row__desc {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.8125rem;
}

.fr-row__meta {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-row__status {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-detail {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.fr-detail-slide-enter-active,
.fr-detail-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.fr-detail-slide-enter-from,
.fr-detail-slide-leave-to {
  opacity: 0;
  transform: translateX(0.75rem);
}

@media (prefers-reduced-motion: reduce) {
  .fr-detail-slide-enter-active,
  .fr-detail-slide-leave-active {
    transition: none;
  }
}

.fr-detail__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.fr-detail__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.fr-detail__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.fr-detail__close:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.fr-detail__rows {
  margin-top: var(--space-3);
}

.fr-detail__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.fr-detail__label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.fr-detail__value {
  color: var(--color-text);
  text-align: right;
  overflow-wrap: anywhere;
}

.fr-detail__value--status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.fr-detail__status-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  background: var(--color-text-muted);
}

.fr-detail__status-dot--pending {
  background: var(--color-text-muted);
}

.fr-detail__status-dot--reviewing {
  background: var(--color-warning);
}

.fr-detail__status-dot--approved {
  background: var(--color-primary);
}

.fr-detail__status-dot--done {
  background: var(--color-success);
}

.fr-detail__status-dot--rejected {
  background: var(--color-danger);
}

.fr-detail__link {
  color: var(--color-primary);
}

.fr-detail__required {
  color: var(--color-danger);
}

.fr-detail__count {
  display: block;
  margin-top: -0.25rem;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  text-align: right;
}

.fr-detail__desc-block {
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-detail__desc {
  margin: var(--space-1) 0 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  white-space: pre-wrap;
}

.fr-detail__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.fr-detail__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.fr-detail__form-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-detail__input,
.fr-detail__textarea {
  width: 100%;
  padding: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.fr-detail__textarea {
  resize: vertical;
}

.fr-detail__btn-row {
  display: flex;
  gap: var(--space-2);
}

.fr-detail__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  border: none;
  border-radius: var(--radius-sm);
  padding: var(--space-2) var(--space-3);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition: filter 0.12s ease;
}

.fr-detail__btn:hover:not(:disabled) {
  filter: brightness(0.96);
}

.fr-detail__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-detail__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.fr-detail__btn--success {
  background: var(--color-success);
  color: var(--color-on-primary);
}

.fr-detail__btn--ghost {
  background: transparent;
  color: var(--color-text-muted);
}

.fr-detail__btn--danger {
  background: var(--color-danger);
  color: var(--color-on-primary);
}

.fr-detail__btn--danger-ghost {
  align-self: flex-start;
  background: transparent;
  color: var(--color-danger);
}

.fr-detail__reject {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

@media (max-width: 768px) {
  .fr-page__body {
    flex-direction: column;
  }

  .fr-detail {
    width: 100%;
    max-height: 60%;
  }
}
</style>
