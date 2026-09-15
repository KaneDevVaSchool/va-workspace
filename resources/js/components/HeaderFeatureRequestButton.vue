<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { formatRelativeTime } from '../lib/formatTime';
import { showClientToast } from '../lib/clientToast';
import { useHeaderPopover } from '../composables/useHeaderPopover';
import AppIcon from './AppIcon.vue';

const route = useRoute();
const rootRef = ref(null);
const { isOpen, toggle, close } = useHeaderPopover('feature-request');

const tab = ref('create');
const items = ref([]);
const loading = ref(false);
const saving = ref(false);
const errors = ref({});
const editingId = ref(null);

const form = reactive({
  attach_page: true,
  description: '',
});

const STATUS_META = {
  pending: { label: 'Chờ ghi nhận', hint: 'Chưa ai xem ghi nhận này.' },
  reviewing: { label: 'Đang xem xét', hint: 'Superadmin đang xem xét yêu cầu của bạn.' },
  approved: { label: 'Đã duyệt', hint: 'Yêu cầu đã được duyệt, đang chờ triển khai.' },
  rejected: { label: 'Từ chối', hint: 'Yêu cầu không được thực hiện.' },
  done: { label: 'Đã hoàn thành', hint: 'Tính năng đã hoàn thành.' },
};

const STATUS_LABEL = Object.fromEntries(Object.entries(STATUS_META).map(([key, value]) => [key, value.label]));

const DESCRIPTION_MAX = 2000;

const currentPageTitle = computed(() => {
  return (typeof route.meta?.title === 'string' && route.meta.title) || document.title || route.fullPath;
});

const currentPageUrl = computed(() => route.fullPath);

const descriptionCount = computed(() => form.description.length);

const canSubmit = computed(() => form.description.trim().length > 0 && !saving.value);

// Số ghi nhận còn đang chờ xử lý (pending/reviewing) — hiện thành số nhỏ
// trên icon để người dùng biết còn bao nhiêu yêu cầu chưa có kết quả.
const openCount = computed(
  () => items.value.filter((item) => item.status === 'pending' || item.status === 'reviewing').length,
);

function resetForm() {
  editingId.value = null;
  form.attach_page = true;
  form.description = '';
  errors.value = {};
}

async function loadMine() {
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

async function submit() {
  if (saving.value) return;
  errors.value = {};

  const payload = {
    description: form.description.trim(),
    page_title: form.attach_page ? currentPageTitle.value : null,
    page_url: form.attach_page ? currentPageUrl.value : null,
  };

  if (!payload.description) {
    errors.value = { description: 'Vui lòng mô tả yêu cầu cụ thể.' };
    return;
  }

  saving.value = true;
  try {
    if (editingId.value) {
      const { data } = await window.axios.put(`/api/feature-requests/${editingId.value}`, payload);
      items.value = items.value.map((row) => (row.id === editingId.value ? data.item : row));
      showClientToast('success', 'Đã cập nhật ghi nhận.');
    } else {
      const { data } = await window.axios.post('/api/feature-requests', payload);
      items.value = [data.item, ...items.value];
      showClientToast('success', 'Đã gửi ghi nhận yêu cầu tính năng.');
    }
    resetForm();
    tab.value = 'mine';
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

function startEdit(item) {
  editingId.value = item.id;
  form.description = item.description;
  form.attach_page = Boolean(item.page_url);
  errors.value = {};
  tab.value = 'create';
}

async function removeItem(item) {
  try {
    await window.axios.delete(`/api/feature-requests/${item.id}`);
    items.value = items.value.filter((row) => row.id !== item.id);
    showClientToast('success', 'Đã rút lại ghi nhận.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được ghi nhận.');
  }
}

function handleDocumentClick(event) {
  if (!isOpen.value || !rootRef.value) return;
  if (rootRef.value.contains(event.target)) return;
  close();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) close();
}

function togglePanel() {
  toggle();
  if (!isOpen.value) return;
  resetForm();
  tab.value = 'create';
  loadMine().catch(() => {});
}

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
  loadMine().catch(() => {});
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <div ref="rootRef" class="header-pop">
    <button
      type="button"
      class="header-pop__btn header-pop__btn--alert"
      :class="{ 'header-pop__btn--open': isOpen }"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      :aria-label="openCount > 0 ? `Ghi nhận yêu cầu tính năng, ${openCount} đang chờ xử lý` : 'Ghi nhận yêu cầu tính năng'"
      @click="togglePanel"
    >
      <AppIcon name="alertTriangle" :size="19" :stroke-width="2" />
      <span v-if="openCount > 0" class="header-pop__badge">{{ openCount > 9 ? '9+' : openCount }}</span>
    </button>

    <Transition name="fr-panel-fade">
      <div v-if="isOpen" class="header-pop__panel" role="dialog" aria-label="Ghi nhận yêu cầu tính năng">
        <div class="header-pop__head">
          <div class="header-pop__title-row">
            <AppIcon name="alertTriangle" :size="16" :stroke-width="2" />
            <span class="header-pop__title">Ghi nhận yêu cầu tính năng</span>
          </div>
          <div class="header-pop__tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="header-pop__tab-btn"
              :class="{ 'header-pop__tab-btn--active': tab === 'create' }"
              :aria-selected="tab === 'create'"
              @click="tab = 'create'; if (!editingId) resetForm();"
            >
              {{ editingId ? 'Sửa ghi nhận' : 'Ghi nhận mới' }}
            </button>
            <button
              type="button"
              role="tab"
              class="header-pop__tab-btn"
              :class="{ 'header-pop__tab-btn--active': tab === 'mine' }"
              :aria-selected="tab === 'mine'"
              @click="tab = 'mine'"
            >
              Ghi nhận của tôi
              <span v-if="items.length > 0" class="header-pop__tab-count">{{ items.length }}</span>
            </button>
          </div>
        </div>

        <div v-if="tab === 'create'" class="fr-form">
          <p class="fr-form__intro">
            Mô tả tính năng bạn muốn có — superadmin sẽ xem và phản hồi trực tiếp trong mục
            "Ghi nhận của tôi".
          </p>

          <label class="fr-form__toggle">
            <input v-model="form.attach_page" type="checkbox" />
            <span>Đính kèm trang đang đứng</span>
          </label>
          <p v-if="form.attach_page" class="fr-form__page-preview">
            <AppIcon name="link" :size="13" :stroke-width="2" />
            {{ currentPageTitle }}
          </p>

          <div class="fr-form__field">
            <label class="fr-form__label" for="fr-description">
              Yêu cầu cụ thể <span class="fr-form__required" aria-hidden="true">*</span>
            </label>
            <textarea
              id="fr-description"
              v-model="form.description"
              class="fr-form__textarea"
              :class="{ 'fr-form__textarea--error': errors.description }"
              rows="5"
              maxlength="2000"
              placeholder="Vd. Muốn có nút xuất Excel cho bảng này, lọc theo tháng."
              :disabled="saving"
            />
            <div class="fr-form__field-foot">
              <span v-if="errors.description" class="fr-form__error">{{ errors.description }}</span>
              <span class="fr-form__count">{{ descriptionCount }}/{{ DESCRIPTION_MAX }}</span>
            </div>
          </div>

          <div class="fr-form__actions">
            <button
              v-if="editingId"
              type="button"
              class="fr-form__btn fr-form__btn--ghost"
              :disabled="saving"
              @click="resetForm()"
            >
              Huỷ sửa
            </button>
            <button
              type="button"
              class="fr-form__btn fr-form__btn--primary"
              :disabled="!canSubmit"
              @click="submit"
            >
              <AppIcon v-if="!saving" name="check" :size="14" :stroke-width="2.5" />
              {{ saving ? 'Đang gửi…' : editingId ? 'Lưu thay đổi' : 'Gửi ghi nhận' }}
            </button>
          </div>
        </div>

        <div v-else class="fr-list">
          <p v-if="loading" class="fr-list__empty">Đang tải…</p>
          <div v-else-if="items.length === 0" class="fr-list__empty">
            <AppIcon name="alertTriangle" :size="28" :stroke-width="1.5" />
            <span>Bạn chưa ghi nhận yêu cầu nào.</span>
          </div>
          <div v-for="item in items" v-else :key="item.id" class="fr-item">
            <div class="fr-item__row">
              <span class="fr-item__dot" :class="`fr-item__dot--${item.status}`" />
              <span class="fr-item__status">{{ STATUS_LABEL[item.status] ?? item.status }}</span>
              <span class="fr-item__time">{{ formatRelativeTime(item.created_at) }}</span>
            </div>
            <p class="fr-item__desc">{{ item.description }}</p>
            <p v-if="item.page_title" class="fr-item__page">
              <AppIcon name="link" :size="12" :stroke-width="2" />
              {{ item.page_title }}
            </p>
            <p class="fr-item__hint">{{ STATUS_META[item.status]?.hint }}</p>
            <p v-if="item.status === 'approved' && item.expected_done_at" class="fr-item__note">
              Dự kiến hoàn thành: {{ item.expected_done_at }}
            </p>
            <p v-if="item.progress_note" class="fr-item__note">{{ item.progress_note }}</p>
            <p v-if="item.status === 'rejected' && item.reject_reason" class="fr-item__note fr-item__note--reject">
              Lý do từ chối: {{ item.reject_reason }}
            </p>
            <div v-if="item.status === 'pending'" class="fr-item__actions">
              <button type="button" class="fr-item__action" @click="startEdit(item)">Sửa</button>
              <button type="button" class="fr-item__action fr-item__action--danger" @click="removeItem(item)">Rút lại</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.header-pop {
  position: relative;
}

.header-pop__btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-danger);
  cursor: pointer;
}

.header-pop__btn--alert {
  animation: fr-pulse 1.8s ease-in-out infinite;
}

.header-pop__btn:hover,
.header-pop__btn--open {
  background: var(--color-danger-tint-bg);
}

.header-pop__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

@keyframes fr-pulse {
  0%,
  100% {
    opacity: 1;
    filter: drop-shadow(0 0 0 transparent);
  }
  50% {
    opacity: 0.55;
    filter: drop-shadow(0 0 4px var(--color-danger));
  }
}

@media (prefers-reduced-motion: reduce) {
  .header-pop__btn--alert {
    animation: none;
  }
}

.header-pop__badge {
  position: absolute;
  top: 0.2rem;
  right: 0.15rem;
  min-width: 1.05rem;
  height: 1.05rem;
  padding: 0 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-danger);
  color: var(--color-on-primary);
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1.05rem;
  text-align: center;
}

.header-pop__panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: min(25rem, calc(100vw - 1.5rem));
  max-height: min(34rem, calc(100vh - 5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.fr-panel-fade-enter-active,
.fr-panel-fade-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}

.fr-panel-fade-enter-from,
.fr-panel-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
  .fr-panel-fade-enter-active,
  .fr-panel-fade-leave-active {
    transition: none;
  }
}

.header-pop__head {
  flex-shrink: 0;
  padding: var(--space-3) var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.header-pop__title-row {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin-bottom: var(--space-2);
  color: var(--color-danger);
}

.header-pop__title {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.header-pop__tabs {
  display: flex;
  gap: var(--space-1);
}

.header-pop__tab-btn {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  border: none;
  background: transparent;
  padding: var(--space-2) var(--space-1);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 2px 0 transparent;
}

.header-pop__tab-btn--active {
  color: var(--color-primary);
  box-shadow: 0 2px 0 var(--color-primary);
}

.header-pop__tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.05rem;
  height: 1.05rem;
  padding: 0 0.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
}

.header-pop__tab-btn--active .header-pop__tab-count {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.fr-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  overflow-y: auto;
}

.fr-form__intro {
  margin: 0 0 var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.5;
}

.fr-form__toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--color-text);
  cursor: pointer;
}

.fr-form__page-preview {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin: 0;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow: hidden;
}

.fr-form__page-preview span,
.fr-form__page-preview {
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fr-form__field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.fr-form__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.fr-form__required {
  color: var(--color-danger);
}

.fr-form__textarea {
  width: 100%;
  min-height: 6.5rem;
  padding: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  resize: vertical;
}

.fr-form__textarea:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.fr-form__textarea--error {
  border-color: var(--color-danger);
}

.fr-form__field-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.fr-form__error {
  color: var(--color-danger);
  font-size: 0.75rem;
}

.fr-form__count {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
}

.fr-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding-top: var(--space-1);
}

.fr-form__btn {
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

.fr-form__btn--primary {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.fr-form__btn--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.fr-form__btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.fr-form__btn--ghost {
  background: transparent;
  color: var(--color-text-muted);
}

.fr-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.fr-list__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.fr-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
  transition: background 0.12s ease;
}

.fr-item:hover {
  background: var(--color-surface-muted);
}

.fr-item__row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.fr-item__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
  flex-shrink: 0;
}

.fr-item__dot--pending {
  background: var(--color-text-muted);
}

.fr-item__dot--reviewing {
  background: var(--color-warning);
}

.fr-item__dot--approved {
  background: var(--color-primary);
}

.fr-item__dot--done {
  background: var(--color-success);
}

.fr-item__dot--rejected {
  background: var(--color-danger);
}

.fr-item__status {
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.fr-item__time {
  margin-left: auto;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-item__desc {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  white-space: pre-wrap;
}

.fr-item__page {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.fr-item__hint,
.fr-item__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-style: italic;
}

.fr-item__note--reject {
  color: var(--color-danger);
  font-style: normal;
}

.fr-item__actions {
  display: flex;
  gap: var(--space-3);
  padding-top: var(--space-1);
}

.fr-item__action {
  border: none;
  background: transparent;
  padding: 0;
  color: var(--color-primary);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.fr-item__action--danger {
  color: var(--color-danger);
}

@media (max-width: 480px) {
  .header-pop__panel {
    position: fixed;
    top: 3.5rem;
    right: var(--space-3);
    left: var(--space-3);
    width: auto;
  }
}
</style>
