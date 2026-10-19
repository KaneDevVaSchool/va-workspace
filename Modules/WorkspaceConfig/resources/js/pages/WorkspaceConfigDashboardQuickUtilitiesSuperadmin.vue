<script setup>
//
// superadmin/workspace-config/quick-utilities — cấu hình lối tắt trên
// /dashboard/me (desktop + mobile EmployeeHome).
//
import { computed, onMounted, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';

const utilities = ref([]);
const meta = ref({ tones: [], link_types: [] });
const loading = ref(false);
const saving = ref(false);
const dialogOpen = ref(false);
const dialogMode = ref('create');
const deleteKey = ref(null);

const form = reactive({
  utility_key: '',
  label: '',
  icon: 'layoutGrid',
  tone: 'primary',
  link_type: 'none',
  link_target: '',
  is_visible: true,
});

const editingKey = ref(null);

const dialogTitle = computed(() => (dialogMode.value === 'create' ? 'Thêm tiện ích nhanh' : 'Sửa tiện ích nhanh'));

function resetForm() {
  form.utility_key = '';
  form.label = '';
  form.icon = 'layoutGrid';
  form.tone = 'primary';
  form.link_type = 'none';
  form.link_target = '';
  form.is_visible = true;
  editingKey.value = null;
}

async function loadUtilities() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/dashboard/quick-utilities');
    utilities.value = Array.isArray(data?.utilities) ? data.utilities : [];
    meta.value = data?.meta ?? meta.value;
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không tải được danh sách tiện ích.');
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  dialogMode.value = 'create';
  resetForm();
  dialogOpen.value = true;
}

function openEdit(item) {
  dialogMode.value = 'edit';
  editingKey.value = item.utility_key;
  form.label = item.label;
  form.icon = item.icon;
  form.tone = item.tone;
  form.link_type = item.link_type;
  form.link_target = item.link_target || '';
  form.is_visible = Boolean(item.is_visible);
  dialogOpen.value = true;
}

function closeDialog() {
  if (saving.value) return;
  dialogOpen.value = false;
}

async function saveForm() {
  saving.value = true;
  try {
    const payload = {
      label: form.label,
      icon: form.icon,
      tone: form.tone,
      link_type: form.link_type,
      link_target: form.link_type === 'none' ? null : form.link_target,
      is_visible: form.is_visible,
    };

    if (dialogMode.value === 'create') {
      await window.axios.post('/api/dashboard/quick-utilities', {
        ...payload,
        utility_key: form.utility_key || undefined,
      });
      showClientToast('success', 'Đã thêm tiện ích.');
    } else {
      await window.axios.put(`/api/dashboard/quick-utilities/${editingKey.value}`, payload);
      showClientToast('success', 'Đã lưu thay đổi.');
    }
    dialogOpen.value = false;
    await loadUtilities();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không lưu được tiện ích.');
  } finally {
    saving.value = false;
  }
}

async function toggleVisible(item) {
  try {
    await window.axios.put(`/api/dashboard/quick-utilities/${item.utility_key}`, {
      is_visible: !item.is_visible,
    });
    item.is_visible = !item.is_visible;
    showClientToast('success', item.is_visible ? `Đã hiện "${item.label}".` : `Đã ẩn "${item.label}".`);
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái hiển thị.');
  }
}

async function moveItem(index, direction) {
  const nextIndex = index + direction;
  if (nextIndex < 0 || nextIndex >= utilities.value.length) return;
  const reordered = [...utilities.value];
  const [row] = reordered.splice(index, 1);
  reordered.splice(nextIndex, 0, row);
  utilities.value = reordered;

  try {
    await window.axios.put('/api/dashboard/quick-utilities/layout', {
      items: reordered.map((item, sortIndex) => ({
        utility_key: item.utility_key,
        sort_order: (sortIndex + 1) * 10,
      })),
    });
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không sắp xếp được.');
    await loadUtilities();
  }
}

function askDelete(item) {
  deleteKey.value = item.utility_key;
}

function cancelDelete() {
  deleteKey.value = null;
}

async function confirmDelete() {
  if (!deleteKey.value) return;
  saving.value = true;
  try {
    await window.axios.delete(`/api/dashboard/quick-utilities/${deleteKey.value}`);
    showClientToast('success', 'Đã xoá tiện ích.');
    deleteKey.value = null;
    await loadUtilities();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được tiện ích.');
  } finally {
    saving.value = false;
  }
}

onMounted(loadUtilities);
</script>

<template>
  <section class="dqu-page">
    <PageHeader
      title="Tiện ích nhanh Dashboard"
      icon="sparkles"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Tiện ích nhanh' },
      ]"
    >
      <template #actions>
        <button type="button" class="dqu-page__btn" :disabled="loading" @click="loadUtilities">
          <AppIcon name="refresh" :size="16" />
          Làm mới
        </button>
        <button type="button" class="dqu-page__btn dqu-page__btn--primary" @click="openCreate">
          <AppIcon name="plus" :size="16" />
          Thêm tiện ích
        </button>
      </template>
    </PageHeader>

    <div class="dqu-page__body">
      <p class="dqu-page__lead">
        Các lối tắt này hiển thị trên trang <strong>Dashboard của tôi</strong> (desktop) và trang chủ mobile nhân viên.
        Route Vue dùng đúng tên route (ví dụ <code>employee.attendance</code>); URL mở tab mới.
      </p>

      <div v-if="loading && !utilities.length" class="dqu-page__empty">Đang tải…</div>

      <ul v-else class="dqu-list">
        <li v-for="(item, index) in utilities" :key="item.utility_key" class="dqu-row" :class="{ 'dqu-row--hidden': !item.is_visible }">
          <div class="dqu-row__order">
            <button type="button" class="dqu-row__order-btn" aria-label="Lên" :disabled="index === 0" @click="moveItem(index, -1)">
              <AppIcon name="chevronsUp" :size="16" />
            </button>
            <button
              type="button"
              class="dqu-row__order-btn"
              aria-label="Xuống"
              :disabled="index === utilities.length - 1"
              @click="moveItem(index, 1)"
            >
              <AppIcon name="chevronsDown" :size="16" />
            </button>
          </div>

          <span class="dqu-row__icon" :class="`dqu-row__icon--${item.tone}`">
            <AppIcon :name="item.icon || 'layoutGrid'" :size="20" />
          </span>

          <div class="dqu-row__main">
            <p class="dqu-row__title">{{ item.label }}</p>
            <p class="dqu-row__meta">
              <span>{{ item.utility_key }}</span>
              <span v-if="item.link_type !== 'none'"> · {{ item.link_type === 'route' ? 'Route' : 'URL' }}: {{ item.link_target }}</span>
              <span v-else> · Chưa gắn liên kết</span>
            </p>
          </div>

          <div class="dqu-row__actions">
            <button type="button" class="dqu-row__action" @click="toggleVisible(item)">
              {{ item.is_visible ? 'Ẩn' : 'Hiện' }}
            </button>
            <button type="button" class="dqu-row__action" @click="openEdit(item)">Sửa</button>
            <button type="button" class="dqu-row__action dqu-row__action--danger" @click="askDelete(item)">Xoá</button>
          </div>
        </li>
      </ul>
    </div>

    <Teleport to="body">
      <Transition name="dqu-fade">
        <div v-if="dialogOpen" class="dqu-dialog" role="presentation" @mousedown.self="closeDialog">
          <div class="dqu-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="dqu-dialog-title">
            <div class="dqu-dialog__head">
              <span class="dqu-dialog__head-icon" aria-hidden="true">
                <AppIcon name="sparkles" :size="22" />
              </span>
              <h2 id="dqu-dialog-title" class="dqu-dialog__title">{{ dialogTitle }}</h2>
              <button type="button" class="dqu-dialog__close" aria-label="Đóng" :disabled="saving" @click="closeDialog">
                <AppIcon name="close" :size="16" />
              </button>
            </div>

            <div class="dqu-dialog__body hide-scrollbar">
              <form class="dqu-form" @submit.prevent="saveForm">
                <label v-if="dialogMode === 'create'" class="dqu-field">
                  <span class="dqu-field__label">Mã (tuỳ chọn)</span>
                  <input v-model="form.utility_key" class="dqu-field__input" type="text" placeholder="vd: payslip" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Tên hiển thị</span>
                  <input v-model="form.label" class="dqu-field__input" type="text" required maxlength="120" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Icon</span>
                  <input v-model="form.icon" class="dqu-field__input" type="text" required maxlength="64" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Màu nhấn</span>
                  <select v-model="form.tone" class="dqu-field__input">
                    <option v-for="tone in meta.tones" :key="tone" :value="tone">{{ tone }}</option>
                  </select>
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Loại liên kết</span>
                  <select v-model="form.link_type" class="dqu-field__input">
                    <option v-for="type in meta.link_types" :key="type" :value="type">{{ type }}</option>
                  </select>
                </label>
                <label v-if="form.link_type !== 'none'" class="dqu-field dqu-field--wide">
                  <span class="dqu-field__label">{{ form.link_type === 'route' ? 'Tên route Vue' : 'URL đầy đủ' }}</span>
                  <input v-model="form.link_target" class="dqu-field__input" type="text" required maxlength="512" />
                </label>
                <label class="dqu-field dqu-field--check">
                  <input v-model="form.is_visible" type="checkbox" />
                  <span>Hiển thị trên Dashboard</span>
                </label>
              </form>
            </div>

            <div class="dqu-dialog__actions">
              <button type="button" class="dqu-page__btn" :disabled="saving" @click="closeDialog">Huỷ</button>
              <button type="button" class="dqu-page__btn dqu-page__btn--primary" :disabled="saving" @click="saveForm">
                {{ saving ? 'Đang lưu…' : 'Lưu' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <Transition name="dqu-fade">
        <div v-if="deleteKey" class="dqu-alert" role="alertdialog" aria-modal="true" aria-labelledby="dqu-delete-title">
          <div class="dqu-alert__panel">
            <h2 id="dqu-delete-title" class="dqu-alert__title">Xoá tiện ích này?</h2>
            <p class="dqu-alert__text">Thao tác không hoàn tác. Người dùng sẽ không còn thấy lối tắt trên Dashboard.</p>
            <div class="dqu-alert__actions">
              <button type="button" class="dqu-page__btn" :disabled="saving" @click="cancelDelete">Huỷ</button>
              <button type="button" class="dqu-page__btn dqu-page__btn--danger" :disabled="saving" @click="confirmDelete">Xoá</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </section>
</template>

<style scoped>
.dqu-page {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.dqu-page__body {
  flex: 1;
  min-height: 0;
  padding: var(--space-5);
  overflow: auto;
}

.dqu-page__lead {
  margin: 0 0 var(--space-4);
  max-width: 46rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--color-text-muted);
}

.dqu-page__lead code {
  font-size: 0.8125rem;
  color: var(--color-text);
}

.dqu-page__btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.dqu-page__btn--primary {
  background: var(--color-primary);
  color: #fff;
  box-shadow: var(--shadow-sm);
}

.dqu-page__btn--danger {
  background: var(--color-danger);
  color: #fff;
}

.dqu-page__btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.dqu-page__empty {
  padding: var(--space-6);
  text-align: center;
  color: var(--color-text-muted);
}

.dqu-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.dqu-row {
  display: grid;
  grid-template-columns: auto auto minmax(0, 1fr) auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dqu-row--hidden {
  opacity: 0.72;
}

.dqu-row__order {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dqu-row__order-btn {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.dqu-row__order-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.dqu-row__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
}

.dqu-row__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}
.dqu-row__icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}
.dqu-row__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}
.dqu-row__icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}
.dqu-row__icon--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.dqu-row__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
}

.dqu-row__meta {
  margin: 0.15rem 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dqu-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  justify-content: flex-end;
}

.dqu-row__action {
  height: 1.875rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.dqu-row__action--danger {
  color: var(--color-danger);
}

.dqu-dialog {
  position: fixed;
  inset: 0;
  z-index: var(--z-modal, 1200);
  display: grid;
  place-items: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay, rgba(9, 22, 48, 0.45));
}

.dqu-dialog__panel {
  display: flex;
  flex-direction: column;
  width: min(44rem, calc(100vw - 2.5rem));
  max-height: calc(100vh - 2.5rem);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.dqu-dialog__head {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.dqu-dialog__head-icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-gold-surface);
  color: var(--color-gold);
}

.dqu-dialog__title {
  flex: 1;
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.dqu-dialog__close {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

.dqu-dialog__body {
  flex: 1;
  min-height: 0;
  padding: var(--space-4);
  overflow: auto;
}

.dqu-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.dqu-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.dqu-field--wide {
  grid-column: 1 / -1;
}

.dqu-field--check {
  grid-column: 1 / -1;
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.875rem;
}

.dqu-field__label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
}

.dqu-field__input {
  height: 2.25rem;
  padding: 0 var(--space-3);
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  font-family: inherit;
  font-size: 0.875rem;
}

.dqu-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-4);
  box-shadow: 0 -1px 0 var(--color-border);
}

.dqu-alert {
  position: fixed;
  inset: 0;
  z-index: calc(var(--z-modal, 1200) + 1);
  display: grid;
  place-items: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay, rgba(9, 22, 48, 0.45));
}

.dqu-alert__panel {
  width: min(24rem, calc(100vw - 2rem));
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.dqu-alert__title {
  margin: 0 0 var(--space-2);
  font-size: 1rem;
}

.dqu-alert__text {
  margin: 0 0 var(--space-4);
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.dqu-alert__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.dqu-fade-enter-active,
.dqu-fade-leave-active {
  transition: opacity 0.15s ease;
}

.dqu-fade-enter-from,
.dqu-fade-leave-to {
  opacity: 0;
}

@media (max-width: 768px) {
  .dqu-row {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      'icon main'
      'order actions';
  }

  .dqu-row__icon {
    grid-area: icon;
  }

  .dqu-row__main {
    grid-area: main;
  }

  .dqu-row__order {
    grid-area: order;
    flex-direction: row;
  }

  .dqu-row__actions {
    grid-area: actions;
  }

  .dqu-form {
    grid-template-columns: 1fr;
  }
}
</style>
