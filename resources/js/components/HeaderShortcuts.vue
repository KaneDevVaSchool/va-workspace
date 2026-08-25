<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showClientToast } from '../lib/clientToast';
import { useHeaderPopover } from '../composables/useHeaderPopover';
import AppIcon from './AppIcon.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const route = useRoute();
const router = useRouter();
const rootRef = ref(null);
const { isOpen, toggle, close } = useHeaderPopover('shortcuts');

const items = ref([]);
const loading = ref(false);
const query = ref('');
const formOpen = ref(false);
const formMode = ref('create');
const formSaving = ref(false);
const form = reactive({ id: null, title: '', description: '', path: '' });
const deleteTarget = ref(null);
const deleting = ref(false);

const filtered = computed(() => {
  const needle = query.value.trim().toLowerCase();
  if (!needle) {
    return items.value;
  }

  return items.value.filter((item) => {
    const hay = `${item.title} ${item.description ?? ''}`.toLowerCase();
    return hay.includes(needle);
  });
});

const currentPath = computed(() => route.fullPath);
const currentShortcut = computed(() => items.value.find((item) => item.path === currentPath.value));

function currentPageMeta() {
  return {
    title: typeof route.meta?.title === 'string' && route.meta.title ? route.meta.title : 'Trang hiện tại',
    description: '',
    path: route.fullPath,
  };
}

function patchItem(shortcut) {
  const index = items.value.findIndex((item) => item.id === shortcut.id);
  if (index === -1) {
    items.value = [...items.value, shortcut];
    return;
  }
  items.value = items.value.map((item) => (item.id === shortcut.id ? shortcut : item));
}

async function loadShortcuts() {
  loading.value = true;
  try {
    const { data } = await window.axios.get('/api/shortcuts');
    items.value = data.shortcuts ?? [];
  } catch {
    showClientToast('error', 'Không tải được danh sách lối tắt.');
  } finally {
    loading.value = false;
  }
}

function openCreateForm() {
  const existing = currentShortcut.value;
  if (existing) {
    openEditForm(existing);
    showClientToast('info', 'Trang này đã có trong lối tắt.');
    return;
  }

  const meta = currentPageMeta();
  formMode.value = 'create';
  form.id = null;
  form.title = meta.title;
  form.description = meta.description;
  form.path = meta.path;
  formOpen.value = true;
}

function openEditForm(item) {
  formMode.value = 'edit';
  form.id = item.id;
  form.title = item.title;
  form.description = item.description ?? '';
  form.path = item.path;
  formOpen.value = true;
}

function closeForm() {
  if (formSaving.value) {
    return;
  }
  formOpen.value = false;
}

async function submitForm() {
  if (!form.title.trim()) {
    showClientToast('error', 'Vui lòng nhập tên lối tắt.');
    return;
  }

  formSaving.value = true;
  try {
    if (formMode.value === 'create') {
      const { data } = await window.axios.post('/api/shortcuts', {
        title: form.title.trim(),
        description: form.description.trim() || null,
        path: form.path,
      });
      patchItem(data.shortcut);
      showClientToast('success', data.message || 'Đã thêm vào lối tắt.');
    } else {
      const { data } = await window.axios.put(`/api/shortcuts/${form.id}`, {
        title: form.title.trim(),
        description: form.description.trim() || null,
      });
      patchItem(data.shortcut);
      showClientToast('success', data.message || 'Đã cập nhật lối tắt.');
    }
    formOpen.value = false;
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được lối tắt. Vui lòng thử lại.');
  } finally {
    formSaving.value = false;
  }
}

async function toggleFavorite(item) {
  try {
    const { data } = await window.axios.patch(`/api/shortcuts/${item.id}/favorite`);
    patchItem(data.shortcut);
  } catch {
    showClientToast('error', 'Không đổi được mục hay dùng.');
  }
}

function requestDelete(item) {
  deleteTarget.value = item;
}

async function confirmDelete() {
  if (!deleteTarget.value) {
    return;
  }

  deleting.value = true;
  try {
    await window.axios.delete(`/api/shortcuts/${deleteTarget.value.id}`);
    items.value = items.value.filter((item) => item.id !== deleteTarget.value.id);
    showClientToast('success', 'Đã xoá lối tắt.');
    deleteTarget.value = null;
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không xoá được lối tắt.');
  } finally {
    deleting.value = false;
  }
}

function goTo(item) {
  close();
  if (item.path === route.fullPath) {
    return;
  }
  router.push(item.path);
}

function handleDocumentClick(event) {
  if (!isOpen.value || !rootRef.value || deleteTarget.value) {
    return;
  }
  if (rootRef.value.contains(event.target)) {
    return;
  }
  close();
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    if (formOpen.value) {
      closeForm();
      return;
    }
    close();
  }
}

watch(isOpen, (open) => {
  if (open) {
    formOpen.value = false;
    query.value = '';
    loadShortcuts();
  }
});

onMounted(() => {
  document.addEventListener('mousedown', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
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
      class="header-pop__btn"
      :class="{ 'header-pop__btn--open': isOpen, 'header-pop__btn--active': Boolean(currentShortcut) }"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      aria-label="Lối tắt"
      @click="toggle"
    >
      <AppIcon name="bookmark" :size="20" :stroke-width="1.75" />
    </button>

    <div v-if="isOpen" class="header-pop__panel" role="dialog" aria-label="Lối tắt">
      <div class="header-pop__head">
        <span class="header-pop__tab">Lối tắt</span>
        <div class="header-pop__tools">
          <div class="header-pop__search">
            <AppIcon name="search" :size="16" :stroke-width="1.75" />
            <label class="sr-only" for="shortcut-search">Tìm lối tắt</label>
            <input id="shortcut-search" v-model="query" type="text" placeholder="Từ khoá..." />
            <button
              v-if="query"
              type="button"
              class="header-pop__search-clear"
              aria-label="Xoá từ khoá"
              @click="query = ''"
            >
              <AppIcon name="close" :size="14" :stroke-width="1.75" />
            </button>
          </div>
          <button
            type="button"
            class="header-pop__plus"
            aria-label="Thêm trang hiện tại vào lối tắt"
            @click="openCreateForm"
          >
            <AppIcon name="plusCircle" :size="20" />
          </button>
        </div>
      </div>

      <div v-if="formOpen" class="shortcut-form">
        <p class="shortcut-form__title">
          {{ formMode === 'create' ? 'Thêm lối tắt' : 'Sửa lối tắt' }}
        </p>
        <div class="shortcut-form__field">
          <label class="shortcut-form__label" for="shortcut-title">Tên lối tắt</label>
          <input id="shortcut-title" v-model="form.title" type="text" class="shortcut-form__input" />
        </div>
        <div class="shortcut-form__field">
          <label class="shortcut-form__label" for="shortcut-desc">Mô tả</label>
          <input id="shortcut-desc" v-model="form.description" type="text" class="shortcut-form__input" />
        </div>
        <div class="shortcut-form__field">
          <span class="shortcut-form__label">Đường dẫn</span>
          <p class="shortcut-form__path">{{ form.path }}</p>
        </div>
        <div class="shortcut-form__actions">
          <button type="button" class="shortcut-form__btn" :disabled="formSaving" @click="closeForm">Huỷ</button>
          <button
            type="button"
            class="shortcut-form__btn shortcut-form__btn--primary"
            :disabled="formSaving"
            @click="submitForm"
          >
            {{ formSaving ? 'Đang lưu…' : 'Lưu' }}
          </button>
        </div>
      </div>

      <div v-else class="shortcut-list">
        <p v-if="loading" class="shortcut-list__empty">Đang tải…</p>
        <p v-else-if="filtered.length === 0" class="shortcut-list__empty">Không tìm thấy lối tắt nào</p>
        <div
          v-for="item in filtered"
          v-else
          :key="item.id"
          class="shortcut-item"
        >
          <span class="shortcut-item__icon">
            <AppIcon :name="item.description ? 'search' : 'link'" :size="16" :stroke-width="1.75" />
          </span>
          <button type="button" class="shortcut-item__body" @click="goTo(item)">
            <span class="shortcut-item__title">{{ item.title }}</span>
            <span v-if="item.description" class="shortcut-item__desc">{{ item.description }}</span>
          </button>
          <div class="shortcut-item__actions">
            <button
              type="button"
              class="shortcut-item__action"
              :class="{ 'shortcut-item__action--fav': item.is_favorite }"
              :aria-label="item.is_favorite ? 'Bỏ khỏi danh sách hay dùng' : 'Thêm vào danh sách hay dùng'"
              @click="toggleFavorite(item)"
            >
              <AppIcon :name="item.is_favorite ? 'starFilled' : 'star'" :size="15" :stroke-width="1.75" />
            </button>
            <button
              type="button"
              class="shortcut-item__action"
              aria-label="Sửa lối tắt"
              @click="openEditForm(item)"
            >
              <AppIcon name="pencil" :size="15" :stroke-width="1.75" />
            </button>
            <button
              type="button"
              class="shortcut-item__action"
              aria-label="Xoá khỏi danh sách lối tắt"
              @click="requestDelete(item)"
            >
              <AppIcon name="trash" :size="15" :stroke-width="1.75" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="deleteTarget !== null"
      title="Xác nhận xoá lối tắt"
      :description="`Bạn có chắc muốn xoá lối tắt «${deleteTarget?.title ?? ''}»?`"
      confirm-label="Xoá lối tắt"
      danger
      :loading="deleting"
      @update:open="(open) => { if (!open) deleteTarget = null; }"
      @confirm="confirmDelete"
    />
  </div>
</template>

<style scoped>
.header-pop {
  position: relative;
}

.header-pop__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  cursor: pointer;
}

.header-pop__btn:hover,
.header-pop__btn--open {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.header-pop__btn--active {
  color: var(--color-primary);
}

.header-pop__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-pop__panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: min(26rem, calc(100vw - 1.5rem));
  max-height: min(32rem, calc(100vh - 5rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.header-pop__head {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.header-pop__tab {
  color: var(--color-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.header-pop__tools {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.header-pop__search {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 0.375rem;
  height: 2rem;
  padding: 0 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.header-pop__search input {
  flex: 1;
  min-width: 0;
  height: 100%;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  outline: none;
}

.header-pop__search-clear {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.header-pop__plus {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  color: var(--color-primary);
  cursor: pointer;
}

.header-pop__plus:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.shortcut-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.shortcut-list__empty {
  margin: 0;
  padding: var(--space-6) var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.shortcut-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.5rem var(--space-3);
}

.shortcut-item:hover {
  background: var(--color-surface-muted);
}

.shortcut-item__icon {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  color: var(--color-text-muted);
}

.shortcut-item__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  padding: 0;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.shortcut-item__title {
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 600;
}

.shortcut-item__desc {
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.shortcut-item__actions {
  display: flex;
  flex-shrink: 0;
  gap: 2px;
}

.shortcut-item__action {
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

.shortcut-item__action:hover {
  background: var(--color-surface);
  color: var(--color-text);
}

.shortcut-item__action--fav {
  color: var(--color-warning);
}

.shortcut-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
}

.shortcut-form__title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
}

.shortcut-form__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.shortcut-form__label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.shortcut-form__input {
  height: 2.25rem;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
}

.shortcut-form__path {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.shortcut-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
}

.shortcut-form__btn {
  height: 2rem;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.shortcut-form__btn--primary {
  border-color: transparent;
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.sr-only {
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
