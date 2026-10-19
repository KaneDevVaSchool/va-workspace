<script setup>
//
// superadmin/workspace-config/quick-utilities — cấu hình app PWA nhân viên
// (lối tắt trang chủ + xem thử các màn hình mobile) song song danh sách.
//
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { APP_ICON_NAMES } from '@/lib/appIconNames.js';
import { showClientToast } from '@/lib/clientToast';
import QuickUtilitiesConfigPanel from '../components/QuickUtilitiesConfigPanel.vue';

const utilities = ref([]);
const meta = ref({ tones: [], link_types: [], allow_create: false });
const allowCreate = computed(() => Boolean(meta.value.allow_create));
const editingCatalogFixed = computed(
  () => dialogMode.value === 'edit' && utilities.value.find((u) => u.utility_key === editingKey.value)?.is_catalog_fixed,
);
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
const iconSearch = ref('');

const dialogTitle = computed(() => (dialogMode.value === 'create' ? 'Thêm tiện ích nhanh' : 'Sửa tiện ích nhanh'));

const filteredIconNames = computed(() => {
  const q = iconSearch.value.trim().toLowerCase();
  if (!q) return APP_ICON_NAMES;
  return APP_ICON_NAMES.filter((name) => name.toLowerCase().includes(q));
});

const previewLabel = computed(() => form.label.trim() || 'Tiện ích mới');

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
  iconSearch.value = '';
  highlightKey.value = null;
  dialogOpen.value = true;
}

const headerPrimaryAction = computed(() => {
  if (!allowCreate.value) return null;
  return {
    label: 'Thêm tiện ích nhanh',
    icon: 'plus',
    disabled: loading.value || saving.value,
    onClick: openCreate,
  };
});

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
  iconSearch.value = '';
  dialogOpen.value = true;
}

function selectIcon(name) {
  form.icon = name;
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
      is_visible: form.is_visible,
      is_under_maintenance: form.is_under_maintenance,
    };
    if (!editingCatalogFixed.value) {
      payload.link_type = form.link_type;
      payload.link_target = form.link_type === 'none' ? null : form.link_target;
    }

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
      title="App PWA nhân viên"
      subtitle="Lối tắt trang chủ app PWA: hiển thị, thứ tự, biểu tượng và bảo trì."
      icon="layoutGrid"
      :primary-action="headerPrimaryAction"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'App PWA nhân viên' },
      ]"
    >
      <template #actions>
        <RouterLink class="dqu-page__link" :to="{ name: 'dashboard.me' }">
          <AppIcon name="externalLink" :size="16" />
          Mở app nhân viên
        </RouterLink>
        <button type="button" class="dqu-page__btn" :disabled="loading || saving" @click="loadUtilities">
          <AppIcon name="refresh" :size="16" />
          Làm mới
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
        :allow-create="allowCreate"
        @toggle="toggleVisible"
        @toggle-maintenance="toggleMaintenance"
        @edit="openEdit"
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
                <div class="dqu-form__top">
                  <div class="dqu-form__fields">
                    <label v-if="dialogMode === 'create'" class="dqu-field">
                      <span class="dqu-field__label">Mã nội bộ (không bắt buộc)</span>
                      <input v-model="form.utility_key" class="dqu-field__input" type="text" placeholder="vd: phieu_luong" />
                    </label>
                    <label class="dqu-field" :class="{ 'dqu-field--span2': dialogMode !== 'create' }">
                      <span class="dqu-field__label">Tên trên màn hình</span>
                      <input
                        v-model="form.label"
                        class="dqu-field__input"
                        type="text"
                        required
                        maxlength="120"
                        placeholder="vd: Chấm công"
                      />
                    </label>
                    <template v-if="!editingCatalogFixed">
                      <label class="dqu-field">
                        <span class="dqu-field__label">Khi nhân viên bấm vào</span>
                        <select v-model="form.link_type" class="dqu-field__input">
                          <option v-for="type in meta.link_types" :key="type" :value="type">
                            {{ linkTypeLabels[type] || type }}
                          </option>
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
                    </template>
                    <div class="dqu-field dqu-field--wide dqu-form__checks">
                      <label class="dqu-field dqu-field--check">
                        <input v-model="form.is_visible" type="checkbox" />
                        <span>Cho nhân viên thấy lối tắt này</span>
                      </label>
                      <label class="dqu-field dqu-field--check">
                        <input v-model="form.is_under_maintenance" type="checkbox" />
                        <span>Đang bảo trì (hiện tên nhưng không mở được)</span>
                      </label>
                    </div>
                  </div>

                  <aside class="dqu-form__preview" aria-label="Xem trước trên điện thoại">
                    <p class="dqu-form__preview-label">Xem trước</p>
                    <div
                      class="dqu-form__preview-tile"
                      :class="{
                        'dqu-form__preview-tile--dim': !form.is_visible,
                        'dqu-form__preview-tile--maint': form.is_under_maintenance,
                      }"
                    >
                      <span class="dqu-form__preview-icon" :class="`dqu-form__preview-icon--${form.tone}`">
                        <AppIcon :name="form.icon || 'layoutGrid'" :size="26" />
                      </span>
                      <span class="dqu-form__preview-name">{{ previewLabel }}</span>
                    </div>
                  </aside>
                </div>

                <div class="dqu-form__section dqu-form__section--tone">
                  <div class="dqu-form__section-head">
                    <span class="dqu-form__section-title">Màu ô biểu tượng</span>
                  </div>
                  <div class="dqu-tone-grid" role="radiogroup" aria-label="Màu ô biểu tượng">
                    <button
                      v-for="tone in meta.tones"
                      :key="tone"
                      type="button"
                      class="dqu-tone-opt"
                      :class="{ 'dqu-tone-opt--active': form.tone === tone }"
                      role="radio"
                      :aria-checked="form.tone === tone"
                      @click="form.tone = tone"
                    >
                      <span class="dqu-tone-opt__chip" :class="`dqu-tone-opt__chip--${tone}`">
                        <AppIcon :name="form.icon || 'layoutGrid'" :size="22" />
                      </span>
                      <span class="dqu-tone-opt__label">{{ toneLabels[tone] || tone }}</span>
                    </button>
                  </div>
                </div>

                <div class="dqu-form__section dqu-form__section--icons">
                  <div class="dqu-form__section-head">
                    <span class="dqu-form__section-title">Biểu tượng</span>
                    <span class="dqu-form__section-meta">{{ filteredIconNames.length }} / {{ APP_ICON_NAMES.length }}</span>
                    <label class="dqu-icon-search">
                      <AppIcon name="search" :size="16" />
                      <input
                        v-model="iconSearch"
                        class="dqu-icon-search__input"
                        type="search"
                        placeholder="Tìm icon…"
                        autocomplete="off"
                      />
                    </label>
                  </div>
                  <div class="dqu-icon-grid hide-scrollbar" role="listbox" aria-label="Chọn biểu tượng">
                    <button
                      v-for="iconName in filteredIconNames"
                      :key="iconName"
                      type="button"
                      class="dqu-icon-cell"
                      :class="{ 'dqu-icon-cell--active': form.icon === iconName }"
                      role="option"
                      :aria-selected="form.icon === iconName"
                      :aria-label="iconName"
                      @click="selectIcon(iconName)"
                    >
                      <AppIcon :name="iconName" :size="22" />
                    </button>
                    <p v-if="!filteredIconNames.length" class="dqu-icon-grid__empty">Không có icon khớp tìm kiếm.</p>
                  </div>
                </div>
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
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
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
  display: flex;
  flex-direction: column;
  padding: var(--space-4);
  overflow: auto;
}

.dqu-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-height: 0;
}

.dqu-form__top {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(11rem, 13.5rem);
  gap: var(--space-4);
  align-items: start;
}

.dqu-form__fields {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.dqu-form__checks {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3) var(--space-5);
  margin: 0;
}

.dqu-form__preview {
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-bg);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dqu-form__preview-label {
  margin: 0 0 var(--space-3);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.dqu-form__preview-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-2);
  text-align: center;
}

.dqu-form__preview-tile--dim {
  opacity: 0.55;
}

.dqu-form__preview-tile--maint .dqu-form__preview-name::after {
  content: ' · Bảo trì';
  color: var(--color-warning, #b45309);
  font-weight: 600;
}

.dqu-form__preview-icon {
  display: grid;
  place-items: center;
  width: 3.25rem;
  height: 3.25rem;
  border-radius: var(--radius-md);
}

.dqu-form__preview-icon--primary,
.dqu-tone-opt__chip--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.dqu-form__preview-icon--secondary,
.dqu-tone-opt__chip--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}

.dqu-form__preview-icon--tertiary,
.dqu-tone-opt__chip--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}

.dqu-form__preview-icon--gold,
.dqu-tone-opt__chip--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}

.dqu-form__preview-icon--muted,
.dqu-tone-opt__chip--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.dqu-form__preview-name {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
  color: var(--color-text);
}

.dqu-form__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-height: 0;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dqu-form__section--icons {
  flex: 1;
  min-height: 12rem;
}

.dqu-form__section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  flex-shrink: 0;
}

.dqu-form__section-title {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text);
}

.dqu-form__section-meta {
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dqu-icon-search {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  margin-left: auto;
  height: 2rem;
  padding: 0 var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  color: var(--color-text-muted);
}

.dqu-icon-search__input {
  width: min(14rem, 40vw);
  border: none;
  background: transparent;
  font-family: inherit;
  font-size: 0.8125rem;
  color: var(--color-text);
  outline: none;
}

.dqu-tone-grid {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.dqu-tone-opt {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  min-width: 5.5rem;
  padding: var(--space-2) var(--space-3);
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  transition: box-shadow 0.12s ease;
}

.dqu-tone-opt--active {
  box-shadow:
    inset 0 0 0 2px var(--color-primary),
    var(--shadow-sm);
}

.dqu-tone-opt__chip {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
}

.dqu-tone-opt__label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-align: center;
  line-height: 1.25;
}

.dqu-icon-grid {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(2.75rem, 1fr));
  gap: var(--space-2);
  padding: var(--space-1);
  overflow: auto;
  align-content: start;
}

.dqu-icon-cell {
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  padding: 0;
  border: none;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
  transition:
    background 0.12s ease,
    box-shadow 0.12s ease,
    color 0.12s ease;
}

.dqu-icon-cell:hover {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}

.dqu-icon-cell--active {
  background: var(--color-primary-surface);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.dqu-icon-grid__empty {
  grid-column: 1 / -1;
  margin: var(--space-4) 0;
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.dqu-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.dqu-field--wide {
  grid-column: 1 / -1;
}

.dqu-field--span2 {
  grid-column: span 2;
}

.dqu-field--check {
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

.dqu-field__hint {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.45;
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

@media (max-width: 900px) {
  .dqu-form__top {
    grid-template-columns: 1fr;
  }

  .dqu-form__fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dqu-field--span2 {
    grid-column: 1 / -1;
  }
}

@media (max-width: 520px) {
  .dqu-form__fields {
    grid-template-columns: 1fr;
  }

  .dqu-icon-search {
    width: 100%;
    margin-left: 0;
  }

  .dqu-icon-search__input {
    width: 100%;
    min-width: 0;
  }
}
</style>
