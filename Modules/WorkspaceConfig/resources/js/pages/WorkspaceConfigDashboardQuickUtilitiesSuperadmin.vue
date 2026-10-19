<script setup>
//
// superadmin/workspace-config/quick-utilities — cấu hình lối tắt trên
// xem trước giao diện nhân viên (điện thoại) song song danh sách cấu hình.
//
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import QuickUtilitiesConfigPanel from '../components/QuickUtilitiesConfigPanel.vue';

const utilities = ref([]);
const meta = ref({ tones: [], link_types: [] });
const loading = ref(false);
const saving = ref(false);
const dialogOpen = ref(false);
const dialogMode = ref('create');
const deleteKey = ref(null);
const highlightKey = ref(null);

const form = reactive({
  utility_key: '',
  label: '',
  icon: 'layoutGrid',
  tone: 'primary',
  link_type: 'none',
  link_target: '',
  is_visible: true,
  is_under_maintenance: false,
});

const toneLabels = {
  primary: 'Xanh (mặc định)',
  secondary: 'Tím',
  tertiary: 'Xanh ngọc',
  gold: 'Vàng',
  muted: 'Xám',
};

const linkTypeLabels = {
  none: 'Không mở trang (chỉ hiển thị tên)',
  route: 'Mở trang trong Workspace',
  url: 'Mở website bên ngoài',
};

const editingKey = ref(null);

const dialogTitle = computed(() => (dialogMode.value === 'create' ? 'Thêm tiện ích nhanh' : 'Sửa tiện ích nhanh'));

const previewUtilities = computed(() => {
  const visible = utilities.value.filter((item) => item.is_visible);

  if (!dialogOpen.value) {
    return visible;
  }

  const draft = {
    utility_key: dialogMode.value === 'edit' ? editingKey.value : '__draft__',
    label: form.label.trim() || 'Tiện ích mới',
    icon: form.icon || 'layoutGrid',
    tone: form.tone || 'primary',
    link_type: form.link_type,
    link_target: form.link_target,
    is_visible: form.is_visible,
    is_under_maintenance: form.is_under_maintenance,
  };

  if (!draft.is_visible) {
    if (dialogMode.value === 'edit') {
      return visible.filter((item) => item.utility_key !== editingKey.value);
    }
    return visible;
  }

  if (dialogMode.value === 'edit') {
    return visible.map((item) => (item.utility_key === editingKey.value ? { ...item, ...draft } : item));
  }

  return [...visible, draft];
});

watch(dialogOpen, (open) => {
  if (open && dialogMode.value === 'create') {
    highlightKey.value = '__draft__';
  }
});

function resetForm() {
  form.utility_key = '';
  form.label = '';
  form.icon = 'layoutGrid';
  form.tone = 'primary';
  form.link_type = 'none';
  form.link_target = '';
  form.is_visible = true;
  form.is_under_maintenance = false;
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
  highlightKey.value = null;
  dialogOpen.value = true;
}

function openEdit(item) {
  dialogMode.value = 'edit';
  editingKey.value = item.utility_key;
  highlightKey.value = item.utility_key;
  form.label = item.label;
  form.icon = item.icon;
  form.tone = item.tone;
  form.link_type = item.link_type;
  form.link_target = item.link_target || '';
  form.is_visible = Boolean(item.is_visible);
  form.is_under_maintenance = Boolean(item.is_under_maintenance);
  dialogOpen.value = true;
}

function closeDialog() {
  if (saving.value) return;
  dialogOpen.value = false;
  if (highlightKey.value === '__draft__') {
    highlightKey.value = null;
  }
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
      is_under_maintenance: form.is_under_maintenance,
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
    highlightKey.value = null;
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
    showClientToast(
      'success',
      item.is_visible ? `Đã bật hiển thị «${item.label}».` : `Đã tắt hiển thị «${item.label}».`,
    );
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được trạng thái hiển thị.');
  }
}

async function toggleMaintenance(item) {
  try {
    const next = !item.is_under_maintenance;
    await window.axios.put(`/api/dashboard/quick-utilities/${item.utility_key}`, {
      is_under_maintenance: next,
    });
    item.is_under_maintenance = next;
    showClientToast(
      'success',
      next ? `«${item.label}» đang bảo trì — nhân viên không mở được.` : `Đã tắt bảo trì «${item.label}».`,
    );
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không đổi được chế độ bảo trì.');
  }
}

async function setAllVisible(visible) {
  const targets = utilities.value.filter((item) => item.is_visible !== visible);
  if (!targets.length) return;

  saving.value = true;
  try {
    await Promise.all(
      targets.map((item) =>
        window.axios.put(`/api/dashboard/quick-utilities/${item.utility_key}`, { is_visible: visible }),
      ),
    );
    targets.forEach((item) => {
      item.is_visible = visible;
    });
    showClientToast('success', visible ? 'Đã bật hiển thị tất cả lối tắt.' : 'Đã tắt hiển thị tất cả lối tắt.');
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không cập nhật hàng loạt được.');
    await loadUtilities();
  } finally {
    saving.value = false;
  }
}

async function moveItem(item, direction) {
  const index = utilities.value.findIndex((row) => row.utility_key === item.utility_key);
  const nextIndex = index + direction;
  if (index < 0 || nextIndex < 0 || nextIndex >= utilities.value.length) return;

  const reordered = [...utilities.value];
  const [row] = reordered.splice(index, 1);
  reordered.splice(nextIndex, 0, row);
  utilities.value = reordered;
  highlightKey.value = item.utility_key;

  try {
    await window.axios.put('/api/dashboard/quick-utilities/layout', {
      items: reordered.map((rowItem, sortIndex) => ({
        utility_key: rowItem.utility_key,
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
    if (highlightKey.value === deleteKey.value) highlightKey.value = null;
    deleteKey.value = null;
    await loadUtilities();
  } catch (error) {
    showClientToast('error', error?.response?.data?.message || 'Không xoá được tiện ích.');
  } finally {
    saving.value = false;
  }
}

async function scrollToHighlight(key) {
  highlightKey.value = key;
  await nextTick();
  const row = document.querySelector(`[data-utility-key="${key}"]`);
  row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

onMounted(loadUtilities);
</script>

<template>
  <section class="dqu-page">
    <PageHeader
      title="Lối tắt trên Dashboard"
      icon="sparkles"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Lối tắt Dashboard' },
      ]"
    >
      <template #actions>
        <RouterLink class="dqu-page__link" :to="{ name: 'dashboard.me' }">
          <AppIcon name="externalLink" :size="16" />
          Mở Dashboard của tôi
        </RouterLink>
        <button type="button" class="dqu-page__btn" :disabled="loading || saving" @click="loadUtilities">
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
      <QuickUtilitiesConfigPanel
        :utilities="utilities"
        :preview-items="previewUtilities"
        :loading="loading"
        :busy="saving"
        :active-key="highlightKey"
        @toggle="toggleVisible"
        @toggle-maintenance="toggleMaintenance"
        @edit="openEdit"
        @delete="askDelete"
        @move="moveItem"
        @show-all="setAllVisible(true)"
        @hide-all="setAllVisible(false)"
        @highlight="scrollToHighlight"
        @create="openCreate"
      />
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
                  <span class="dqu-field__label">Mã nội bộ (không bắt buộc)</span>
                  <input v-model="form.utility_key" class="dqu-field__input" type="text" placeholder="vd: phieu_luong" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Tên trên màn hình</span>
                  <input v-model="form.label" class="dqu-field__input" type="text" required maxlength="120" placeholder="vd: Chấm công" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Biểu tượng (tên icon)</span>
                  <input v-model="form.icon" class="dqu-field__input" type="text" required maxlength="64" placeholder="vd: clock" />
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Màu ô biểu tượng</span>
                  <select v-model="form.tone" class="dqu-field__input">
                    <option v-for="tone in meta.tones" :key="tone" :value="tone">{{ toneLabels[tone] || tone }}</option>
                  </select>
                </label>
                <label class="dqu-field">
                  <span class="dqu-field__label">Khi nhân viên bấm vào</span>
                  <select v-model="form.link_type" class="dqu-field__input">
                    <option v-for="type in meta.link_types" :key="type" :value="type">{{ linkTypeLabels[type] || type }}</option>
                  </select>
                </label>
                <label v-if="form.link_type !== 'none'" class="dqu-field dqu-field--wide">
                  <span class="dqu-field__label">{{
                    form.link_type === 'route' ? 'Tên trang trong app (kỹ thuật)' : 'Địa chỉ website (https://…)'
                  }}</span>
                  <input
                    v-model="form.link_target"
                    class="dqu-field__input"
                    type="text"
                    required
                    maxlength="512"
                    :placeholder="form.link_type === 'route' ? 'vd: employee.attendance' : 'https://…'"
                  />
                </label>
                <label class="dqu-field dqu-field--check">
                  <input v-model="form.is_visible" type="checkbox" />
                  <span>Cho nhân viên thấy lối tắt này</span>
                </label>
                <label class="dqu-field dqu-field--check">
                  <input v-model="form.is_under_maintenance" type="checkbox" />
                  <span>Đang bảo trì (hiện tên nhưng không mở được)</span>
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
            <p class="dqu-alert__text">Không thể hoàn tác. Nhân viên sẽ không còn thấy lối tắt này.</p>
            <div class="dqu-alert__actions">
              <button type="button" class="dqu-page__btn" :disabled="saving" @click="cancelDelete">Huỷ</button>
              <button type="button" class="dqu-page__btn dqu-page__btn--danger" :disabled="saving" @click="confirmDelete">
                Xoá
              </button>
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
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5) var(--space-5);
  overflow: hidden;
}

.dqu-page__link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 500;
  text-decoration: none;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
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
  width: min(52rem, calc(100vw - 2.5rem));
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
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
  .dqu-form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .dqu-form {
    grid-template-columns: 1fr;
  }
}
</style>
