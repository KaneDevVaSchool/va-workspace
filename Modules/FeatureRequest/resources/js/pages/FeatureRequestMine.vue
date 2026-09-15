<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import AppIcon from '@/components/AppIcon.vue';
import { formatDate, formatDateTime, formatRelativeTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';

const STATUS_LABEL = {
  pending: 'Chờ ghi nhận',
  reviewing: 'Đang xem xét',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  done: 'Đã hoàn thành',
};

const STATUS_HINT = {
  pending: 'Chưa ai xem ghi nhận này.',
  reviewing: 'Superadmin đang xem xét yêu cầu của bạn.',
  approved: 'Yêu cầu đã được duyệt, đang chờ triển khai.',
  rejected: 'Yêu cầu không được thực hiện.',
  done: 'Tính năng đã hoàn thành.',
};

// Thẻ thống kê đầu trang — đồng thời là bộ lọc theo trạng thái, cùng kiểu
// với trang superadmin (FeatureRequestSuperAdmin.vue::STAT_CARDS).
const STAT_CARDS = [
  { key: '', tone: 'gold', icon: 'layoutList', label: 'Tổng ghi nhận', stat: 'total' },
  { key: 'pending', tone: 'neutral', icon: 'clock', label: 'Chờ ghi nhận', stat: 'pending' },
  { key: 'reviewing', tone: 'warning', icon: 'eye', label: 'Đang xem xét', stat: 'reviewing' },
  { key: 'approved', tone: 'info', icon: 'check', label: 'Đã duyệt', stat: 'approved' },
  { key: 'done', tone: 'success', icon: 'clipboardCheck', label: 'Hoàn thành', stat: 'done' },
  { key: 'rejected', tone: 'danger', icon: 'close', label: 'Từ chối', stat: 'rejected' },
];

const DESCRIPTION_MAX = 2000;

const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const acting = ref(false);
const statusFilter = ref('');
const searchQuery = ref('');
const selectedId = ref(null);
const editingId = ref(null);
const errors = ref({});

const form = reactive({
  description: '',
});

const overallCounts = computed(() => {
  const counts = { pending: 0, reviewing: 0, approved: 0, rejected: 0, done: 0, total: items.value.length };
  for (const item of items.value) {
    if (item.status in counts) counts[item.status] += 1;
  }
  return counts;
});

const visibleItems = computed(() => {
  const needle = searchQuery.value.trim().toLowerCase();

  return items.value.filter((item) => {
    if (statusFilter.value && item.status !== statusFilter.value) return false;
    if (!needle) return true;
    return (
      item.description.toLowerCase().includes(needle) ||
      (item.page_title || '').toLowerCase().includes(needle)
    );
  });
});

const selected = computed(() => items.value.find((item) => item.id === selectedId.value) ?? null);

const descriptionCount = computed(() => form.description.length);
const canSubmit = computed(() => form.description.trim().length > 0 && !saving.value);

function resetForm() {
  editingId.value = null;
  form.description = '';
  errors.value = {};
}

async function load() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/feature-requests/mine');
    items.value = data.items ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách ghi nhận của bạn.');
  } finally {
    loading.value = false;
  }
}

function openDetail(item) {
  selectedId.value = item.id;
}

function closeDetail() {
  selectedId.value = null;
}

function startEdit(item) {
  editingId.value = item.id;
  form.description = item.description;
  errors.value = {};
  selectedId.value = null;
}

async function submit() {
  if (saving.value) return;
  errors.value = {};

  const description = form.description.trim();
  if (!description) {
    errors.value = { description: 'Vui lòng mô tả yêu cầu cụ thể.' };
    return;
  }

  saving.value = true;
  try {
    if (editingId.value) {
      const existing = items.value.find((row) => row.id === editingId.value);
      const { data } = await window.axios.put(`/api/feature-requests/${editingId.value}`, {
        description,
        page_title: existing?.page_title ?? null,
        page_url: existing?.page_url ?? null,
      });
      items.value = items.value.map((row) => (row.id === editingId.value ? data.item : row));
      showClientToast('success', 'Đã cập nhật ghi nhận.');
    } else {
      const { data } = await window.axios.post('/api/feature-requests', { description });
      items.value = [data.item, ...items.value];
      showClientToast('success', 'Đã gửi ghi nhận yêu cầu tính năng.');
    }
    resetForm();
  } catch (error) {
    if (error?.response?.status === 422) {
      errors.value = Object.fromEntries(
        Object.entries(error.response.data.errors ?? {}).map(([key, value]) => [key, value[0]]),
      );
    } else {
      showClientToast('error', error?.response?.data?.message || 'Không gửi được ghi nhận.');
    }
  } finally {
    saving.value = false;
  }
}

async function removeItem(item) {
  if (acting.value) return;
  acting.value = true;
  try {
    await window.axios.delete(`/api/feature-requests/${item.id}`);
    items.value = items.value.filter((row) => row.id !== item.id);
    if (selectedId.value === item.id) selectedId.value = null;
    showClientToast('success', 'Đã rút lại ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được ghi nhận.');
  } finally {
    acting.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="frm-page">
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="frm-page__watermark"
      aria-hidden="true"
    />
    <PageHeader
      title="Ghi nhận yêu cầu tính năng của tôi"
      icon="alertTriangle"
      :subtitle="`${overallCounts.total} ghi nhận`"
    />

    <div class="frm-page__stats" role="group" aria-label="Lọc theo trạng thái">
      <button
        v-for="card in STAT_CARDS"
        :key="card.key || 'all'"
        type="button"
        class="frm-page__stat"
        :class="[`frm-page__stat--${card.tone}`, { 'frm-page__stat--active': statusFilter === card.key }]"
        :aria-pressed="statusFilter === card.key"
        @click="statusFilter = card.key"
      >
        <span class="frm-page__stat-icon">
          <AppIcon :name="card.icon" :size="18" :stroke-width="1.75" />
        </span>
        <span class="frm-page__stat-copy">
          <span class="frm-page__stat-value">{{ overallCounts[card.stat] || 0 }}</span>
          <span class="frm-page__stat-label">{{ card.label }}</span>
        </span>
      </button>
    </div>

    <div class="frm-page__toolbar">
      <div class="frm-page__search">
        <AppIcon name="search" :size="16" :stroke-width="2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tìm theo nội dung hoặc trang đính kèm…"
          class="frm-page__search-input"
        />
      </div>
    </div>

    <div class="frm-page__body">
      <div class="frm-page__list hide-scrollbar">
        <div v-if="editingId !== null || (items.length === 0 && !loading)" class="frm-form">
          <p class="frm-form__intro">
            Mô tả tính năng bạn muốn có — superadmin sẽ xem và phản hồi trực tiếp trong danh sách bên dưới.
          </p>

          <div class="frm-form__field">
            <label class="frm-form__label" for="frm-description">
              Yêu cầu cụ thể <span class="frm-form__required" aria-hidden="true">*</span>
            </label>
            <textarea
              id="frm-description"
              v-model="form.description"
              class="frm-form__textarea"
              :class="{ 'frm-form__textarea--error': errors.description }"
              rows="4"
              maxlength="2000"
              placeholder="Vd. Muốn có nút xuất Excel cho bảng này, lọc theo tháng."
              :disabled="saving"
            />
            <div class="frm-form__field-foot">
              <span v-if="errors.description" class="frm-form__error">{{ errors.description }}</span>
              <span class="frm-form__count">{{ descriptionCount }}/{{ DESCRIPTION_MAX }}</span>
            </div>
          </div>

          <div class="frm-form__actions">
            <button
              v-if="editingId"
              type="button"
              class="frm-form__btn frm-form__btn--ghost"
              :disabled="saving"
              @click="resetForm()"
            >
              Huỷ sửa
            </button>
            <button
              type="button"
              class="frm-form__btn frm-form__btn--primary"
              :disabled="!canSubmit"
              @click="submit"
            >
              <AppIcon v-if="!saving" name="check" :size="14" :stroke-width="2.5" />
              {{ saving ? 'Đang gửi…' : editingId ? 'Lưu thay đổi' : 'Gửi ghi nhận' }}
            </button>
          </div>
        </div>

        <p v-if="loading" class="frm-page__empty">Đang tải…</p>
        <div v-else-if="visibleItems.length === 0 && editingId === null && items.length > 0" class="frm-page__empty">
          <AppIcon name="alertTriangle" :size="32" :stroke-width="1.5" />
          <span>Không tìm thấy ghi nhận phù hợp.</span>
        </div>

        <button
          v-for="item in visibleItems"
          :key="item.id"
          type="button"
          class="frm-row"
          :class="{ 'frm-row--active': selectedId === item.id }"
          @click="openDetail(item)"
        >
          <span class="frm-row__dot" :class="`frm-row__dot--${item.status}`" />
          <span class="frm-row__desc">{{ item.description }}</span>
          <span class="frm-row__status">{{ STATUS_LABEL[item.status] ?? item.status }}</span>
          <span class="frm-row__time">{{ formatRelativeTime(item.created_at) }}</span>
        </button>
      </div>

      <Transition name="frm-detail-slide">
        <aside v-if="selected" class="frm-detail" aria-label="Chi tiết ghi nhận">
          <div class="frm-detail__head">
            <h2 class="frm-detail__title">Chi tiết ghi nhận</h2>
            <button type="button" class="frm-detail__close" aria-label="Đóng" @click="closeDetail">
              <AppIcon name="close" :size="16" />
            </button>
          </div>

          <div class="frm-detail__rows">
            <div class="frm-detail__row">
              <span class="frm-detail__label">Trạng thái</span>
              <span class="frm-detail__value frm-detail__value--status">
                <span class="frm-detail__status-dot" :class="`frm-detail__status-dot--${selected.status}`" />
                {{ STATUS_LABEL[selected.status] ?? selected.status }}
              </span>
            </div>
            <div v-if="selected.page_title" class="frm-detail__row">
              <span class="frm-detail__label">Trang đính kèm</span>
              <a class="frm-detail__value frm-detail__link" :href="selected.page_url" target="_blank" rel="noopener">
                {{ selected.page_title }}
              </a>
            </div>
            <div class="frm-detail__row">
              <span class="frm-detail__label">Thời gian gửi</span>
              <span class="frm-detail__value">{{ formatDateTime(selected.created_at) }}</span>
            </div>
            <div v-if="selected.reviewed_by_name" class="frm-detail__row">
              <span class="frm-detail__label">Người xử lý</span>
              <span class="frm-detail__value">{{ selected.reviewed_by_name }}</span>
            </div>
            <div v-if="selected.expected_done_at" class="frm-detail__row">
              <span class="frm-detail__label">Ngày hoàn thành dự kiến</span>
              <span class="frm-detail__value">{{ formatDate(selected.expected_done_at) }}</span>
            </div>
            <div v-if="selected.progress_note" class="frm-detail__row">
              <span class="frm-detail__label">Ghi chú tiến độ</span>
              <span class="frm-detail__value">{{ selected.progress_note }}</span>
            </div>
            <div v-if="selected.reject_reason" class="frm-detail__row">
              <span class="frm-detail__label">Lý do từ chối</span>
              <span class="frm-detail__value">{{ selected.reject_reason }}</span>
            </div>
            <div v-if="selected.done_at" class="frm-detail__row">
              <span class="frm-detail__label">Hoàn thành lúc</span>
              <span class="frm-detail__value">{{ formatDateTime(selected.done_at) }}</span>
            </div>
          </div>

          <div class="frm-detail__desc-block">
            <span class="frm-detail__label">Yêu cầu cụ thể</span>
            <p class="frm-detail__desc">{{ selected.description }}</p>
          </div>

          <p class="frm-detail__hint">{{ STATUS_HINT[selected.status] }}</p>

          <div v-if="selected.status === 'pending'" class="frm-detail__actions">
            <button type="button" class="frm-detail__btn frm-detail__btn--ghost" @click="startEdit(selected)">
              Sửa ghi nhận
            </button>
            <button
              type="button"
              class="frm-detail__btn frm-detail__btn--danger-ghost"
              :disabled="acting"
              @click="removeItem(selected)"
            >
              Rút lại ghi nhận
            </button>
          </div>
        </aside>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.frm-page {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  height: 100%;
  min-height: 0;
  padding: var(--space-4);
  overflow: hidden;
}

.frm-page__watermark {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  opacity: 0.05;
  pointer-events: none;
  user-select: none;
}

.frm-page > :not(.frm-page__watermark) {
  position: relative;
  z-index: 1;
}

.frm-page__stats {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: var(--space-3);
  flex-shrink: 0;
}

.frm-page__stat {
  --stat-color: var(--color-text);
  --stat-fill: var(--color-surface-muted);
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  width: 100%;
  min-width: 0;
  padding: var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  color: inherit;
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
  transition: box-shadow 0.15s ease;
}

.frm-page__stat:hover {
  box-shadow: var(--shadow-md);
}

.frm-page__stat:focus-visible {
  outline: none;
  box-shadow: var(--shadow-md), 0 0 0 2px var(--color-surface), 0 0 0 4px var(--stat-color);
}

.frm-page__stat--active {
  box-shadow: var(--shadow-md), inset 0 0 0 1px var(--stat-color);
}

.frm-page__stat--gold {
  --stat-color: var(--color-gold-600);
  --stat-fill: var(--color-gold-surface);
}

.frm-page__stat--neutral {
  --stat-color: var(--color-text-muted);
  --stat-fill: var(--color-surface-muted);
}

.frm-page__stat--warning {
  --stat-color: var(--color-warning);
  --stat-fill: var(--color-warning-tint-bg);
}

.frm-page__stat--info {
  --stat-color: var(--color-info);
  --stat-fill: var(--color-info-tint-bg);
}

.frm-page__stat--success {
  --stat-color: var(--color-success);
  --stat-fill: var(--color-success-tint-bg);
}

.frm-page__stat--danger {
  --stat-color: var(--color-danger);
  --stat-fill: var(--color-danger-tint-bg);
}

.frm-page__stat-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-sm);
  background: var(--stat-fill);
  color: var(--stat-color);
}

.frm-page__stat-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.frm-page__stat-value {
  color: var(--stat-color);
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.15;
}

.frm-page__stat-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 500;
  line-height: 1.3;
}

.frm-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}

.frm-page__search {
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

.frm-page__search-input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.frm-page__search-input:focus-visible {
  outline: none;
}

.frm-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.frm-page__list {
  flex: 1;
  min-width: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.frm-page__empty {
  margin: 0;
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  text-align: center;
  color: var(--color-text-muted);
}

.frm-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.frm-form__intro {
  margin: 0 0 var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.5;
}

.frm-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.frm-form__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.frm-form__required {
  color: var(--color-danger);
}

.frm-form__textarea {
  width: 100%;
  min-height: 5.5rem;
  padding: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  resize: vertical;
}

.frm-form__textarea:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.frm-form__textarea--error {
  border-color: var(--color-danger);
}

.frm-form__field-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.frm-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

.frm-form__count {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.frm-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding-top: var(--space-1);
}

.frm-form__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  border-radius: var(--radius-sm);
  padding: var(--space-2) var(--space-3);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.frm-form__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.frm-form__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.frm-form__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.frm-form__btn--ghost {
  background: transparent;
  color: var(--color-text-muted);
}

.frm-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  padding: var(--space-2) var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  font-family: var(--font-family-base);
  text-align: left;
  cursor: pointer;
}

.frm-row:hover,
.frm-row--active {
  background: var(--color-surface-muted);
}

.frm-row__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  background: var(--color-text-muted);
}

.frm-row__dot--pending {
  background: var(--color-text-muted);
}

.frm-row__dot--reviewing {
  background: var(--color-warning);
}

.frm-row__dot--approved {
  background: var(--color-primary);
}

.frm-row__dot--done {
  background: var(--color-success);
}

.frm-row__dot--rejected {
  background: var(--color-danger);
}

.frm-row__desc {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.8125rem;
}

.frm-row__status {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.frm-row__time {
  flex-shrink: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.frm-detail {
  flex-shrink: 0;
  width: 28rem;
  overflow-y: auto;
  padding: var(--space-4);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.frm-detail-slide-enter-active,
.frm-detail-slide-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.frm-detail-slide-enter-from,
.frm-detail-slide-leave-to {
  opacity: 0;
  transform: translateX(0.75rem);
}

@media (prefers-reduced-motion: reduce) {
  .frm-detail-slide-enter-active,
  .frm-detail-slide-leave-active {
    transition: none;
  }
}

.frm-detail__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.frm-detail__title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.frm-detail__close {
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

.frm-detail__close:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.frm-detail__rows {
  margin-top: var(--space-3);
}

.frm-detail__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.frm-detail__label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.frm-detail__value {
  color: var(--color-text);
  text-align: right;
  overflow-wrap: anywhere;
}

.frm-detail__value--status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.frm-detail__status-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
  background: var(--color-text-muted);
}

.frm-detail__status-dot--pending {
  background: var(--color-text-muted);
}

.frm-detail__status-dot--reviewing {
  background: var(--color-warning);
}

.frm-detail__status-dot--approved {
  background: var(--color-primary);
}

.frm-detail__status-dot--done {
  background: var(--color-success);
}

.frm-detail__status-dot--rejected {
  background: var(--color-danger);
}

.frm-detail__link {
  color: var(--color-primary);
}

.frm-detail__desc-block {
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.frm-detail__desc {
  margin: var(--space-1) 0 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  white-space: pre-wrap;
}

.frm-detail__hint {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.frm-detail__actions {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.frm-detail__btn {
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

.frm-detail__btn:hover:not(:disabled) {
  filter: brightness(0.96);
}

.frm-detail__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.frm-detail__btn--ghost {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.frm-detail__btn--danger-ghost {
  background: transparent;
  color: var(--color-danger);
}

@media (max-width: 768px) {
  .frm-page__stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .frm-page__body {
    flex-direction: column;
  }

  .frm-detail {
    width: 100%;
    max-height: 60%;
  }
}

@media (max-width: 480px) {
  .frm-page__stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .frm-page__stat-label {
    font-size: 0.6875rem;
  }
}
</style>
