<script setup>
//
// superadmin/workspace-config/global-menu — ẩn/hiện, đổi tên và sắp xếp
// menu sidebar Ở MỨC TOÀN HỆ THỐNG. Áp dụng cho MỌI tài khoản kể cả
// super_admin, thắng tuyệt đối per-department override.
// Mọi thao tác ghi ngay; sidebar trái phản ánh tức thì qua auth store.
//
import { onBeforeUnmount, onMounted, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SidebarMenuConfigPanel from '../components/SidebarMenuConfigPanel.vue';
import { layoutPayload } from '../constants/sidebarMenus.js';

const GLOBAL_SECTION_ORDER = ['general', 'admin', 'manager', 'superadmin-workspace-config'];

const auth = useAuthStore();

const menus = ref([]);
const sections = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);
const bulkBusy = ref(false);

const busy = ref(false);

function applyAuthLayout(nextMenus) {
  const order = {};
  const itemSections = {};
  for (const item of nextMenus) {
    order[item.menu_key] = item.sort_order;
    itemSections[item.menu_key] = item.section;
    auth.setGlobalMenuKeyHidden(item.menu_key, Boolean(item.is_hidden));
    auth.setGlobalMenuLabel(item.menu_key, item.custom_label || '');
  }
  auth.setGlobalMenuLayout({ order, itemSections });
}

function applyAuthSections(nextSections) {
  for (const section of nextSections) {
    auth.setGlobalMenuSectionLabel(section.id, section.custom_label || '');
  }
}

function applyMenu(menuKey, patch) {
  const idx = menus.value.findIndex((item) => item.menu_key === menuKey);
  if (idx === -1) return;
  menus.value[idx] = { ...menus.value[idx], ...patch };
}

function applyFromResponse(payload) {
  if (!payload?.menu_key) return;
  const current = menus.value.find((item) => item.menu_key === payload.menu_key);
  applyMenu(payload.menu_key, {
    is_hidden: Boolean(payload.is_hidden),
    is_visible: !Boolean(payload.is_hidden),
    label: payload.label,
    default_label: payload.default_label,
    custom_label: payload.custom_label ?? null,
    section: payload.section ?? current?.section,
    sort_order: payload.sort_order ?? current?.sort_order,
  });
}

function applyFullResponse(data) {
  if (Array.isArray(data.menus)) {
    menus.value = data.menus.map((item) => ({ ...item, is_visible: !item.is_hidden }));
    applyAuthLayout(menus.value);
  }
  if (Array.isArray(data.sections)) {
    sections.value = data.sections.map((item) => ({ ...item }));
    applyAuthSections(sections.value);
  }
}

async function loadMenus() {
  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/global-menu');
    applyFullResponse(data);
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách menu.');
  } finally {
    isLoading.value = false;
  }
}

async function toggle(menu) {
  if (busy.value || menu.is_protected) return;
  // is_hidden ngược với is_visible — không dùng !is_visible (mẫu department sidebar).
  const nextHidden = Boolean(menu.is_visible);
  const previous = { is_hidden: menu.is_hidden, is_visible: menu.is_visible };
  savingKey.value = menu.menu_key;
  busy.value = true;

  applyMenu(menu.menu_key, { is_hidden: nextHidden, is_visible: !nextHidden });
  auth.setGlobalMenuKeyHidden(menu.menu_key, nextHidden);

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu', {
      menu_key: menu.menu_key,
      is_hidden: nextHidden,
    });
    const updated = (data.menus ?? []).find((item) => item.menu_key === menu.menu_key);
    if (updated) applyFromResponse(updated);
    auth.setGlobalMenuKeyHidden(menu.menu_key, updated?.is_hidden ?? nextHidden);
    showClientToast('success', `Đã ${nextHidden ? 'ẩn' : 'hiện'} "${menu.label}".`);
  } catch (error) {
    applyMenu(menu.menu_key, previous);
    auth.setGlobalMenuKeyHidden(menu.menu_key, previous.is_hidden);
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thay đổi.');
  } finally {
    savingKey.value = null;
    busy.value = false;
  }
}

async function rename(menu, nextLabel) {
  if (busy.value) return;

  const previous = { label: menu.label, custom_label: menu.custom_label ?? null };
  const fallback = menu.default_label || menu.label;
  const trimmed = String(nextLabel ?? '').trim();
  savingKey.value = menu.menu_key;
  busy.value = true;

  applyMenu(menu.menu_key, { label: trimmed || fallback, custom_label: trimmed || null });
  auth.setGlobalMenuLabel(menu.menu_key, trimmed);

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu', {
      menu_key: menu.menu_key,
      custom_label: trimmed,
    });
    const updated = (data.menus ?? []).find((item) => item.menu_key === menu.menu_key);
    if (updated) applyFromResponse(updated);
    auth.setGlobalMenuLabel(menu.menu_key, updated?.custom_label || '');
    showClientToast(
      'success',
      updated?.custom_label ? `Đã đổi tên thành "${updated.label}".` : `Đã đặt lại tên "${updated.label}".`,
    );
  } catch (error) {
    applyMenu(menu.menu_key, previous);
    auth.setGlobalMenuLabel(menu.menu_key, previous.custom_label || '');
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được tên menu.');
  } finally {
    savingKey.value = null;
    busy.value = false;
  }
}

async function renameSection(sectionId, nextLabel) {
  if (busy.value) return;

  const idx = sections.value.findIndex((item) => item.id === sectionId);
  if (idx === -1) return;

  const previous = { ...sections.value[idx] };
  const fallback = previous.defaultLabel || previous.label;
  const trimmed = String(nextLabel ?? '').trim();
  sections.value[idx] = { ...previous, label: trimmed || fallback, custom_label: trimmed || null };
  auth.setGlobalMenuSectionLabel(sectionId, trimmed);
  busy.value = true;

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu/section', {
      section_key: sectionId,
      custom_label: trimmed,
    });
    if (Array.isArray(data.sections)) {
      sections.value = data.sections.map((item) => ({ ...item }));
    }
    const updated = data.sections?.find((item) => item.id === sectionId);
    auth.setGlobalMenuSectionLabel(sectionId, updated?.custom_label || '');
    showClientToast(
      'success',
      updated?.custom_label
        ? `Đã đổi tên nhóm thành "${updated.label}".`
        : `Đã đặt lại tên nhóm "${updated?.label ?? fallback}".`,
    );
  } catch (error) {
    sections.value[idx] = previous;
    auth.setGlobalMenuSectionLabel(sectionId, previous.custom_label || '');
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được tên nhóm.');
  } finally {
    busy.value = false;
  }
}

async function reorder(nextMenus) {
  if (busy.value) return;

  const previousMenus = menus.value.map((item) => ({ ...item }));
  menus.value = nextMenus.map((item) => ({ ...item }));
  applyAuthLayout(menus.value);
  busy.value = true;

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu/layout', {
      items: layoutPayload(menus.value),
    });
    applyFullResponse(data);
  } catch (error) {
    menus.value = previousMenus;
    applyAuthLayout(previousMenus);
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thứ tự menu.');
  } finally {
    busy.value = false;
  }
}

async function setAllVisible(isVisible) {
  if (busy.value) return;
  const targets = menus.value.filter(
    (item) => !item.is_protected && item.is_visible !== isVisible,
  );
  if (!targets.length) return;

  busy.value = true;
  let done = 0;
  try {
    for (const menu of targets) {
      const current = menus.value.find((item) => item.menu_key === menu.menu_key);
      if (!current || current.is_protected || current.is_visible === isVisible) continue;

      const nextHidden = !isVisible;
      applyMenu(current.menu_key, { is_hidden: nextHidden, is_visible: isVisible });
      auth.setGlobalMenuKeyHidden(current.menu_key, nextHidden);

      try {
        const { data } = await window.axios.put('/api/workspace-config/global-menu', {
          menu_key: current.menu_key,
          is_hidden: nextHidden,
        });
        const updated = (data.menus ?? []).find((item) => item.menu_key === current.menu_key);
        if (updated) applyFromResponse(updated);
        auth.setGlobalMenuKeyHidden(current.menu_key, updated?.is_hidden ?? nextHidden);
        done += 1;
      } catch {
        applyMenu(current.menu_key, { is_hidden: !nextHidden, is_visible: !isVisible });
        auth.setGlobalMenuKeyHidden(current.menu_key, !nextHidden);
        showClientToast('error', 'Không lưu hết thay đổi. Đã dừng lại.');
        return;
      }
    }
    if (done) {
      showClientToast('success', isVisible ? `Đã hiện ${done} mục menu.` : `Đã ẩn ${done} mục menu.`);
    }
  } finally {
    busy.value = false;
  }
}

onMounted(() => {
  loadMenus();
});

onBeforeUnmount(() => {
  // noop — không có document listener ở trang này
});
</script>

<template>
  <section class="gmv2-page">
    <PageHeader
      title="Cấu hình menu toàn hệ thống"
      icon="eyeOff"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Cấu hình menu toàn hệ thống' },
      ]"
    >
      <template #actions>
        <button
          type="button"
          class="gmv2-page__refresh-btn"
          :disabled="isLoading || busy"
          @click="loadMenus"
        >
          <svg
            class="gmv2-page__refresh-icon"
            :class="{ 'gmv2-page__refresh-icon--spin': isLoading }"
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
            <path d="M21 3v5h-5" />
            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
            <path d="M8 16H3v5" />
          </svg>
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="gmv2-page__body">
      <SidebarMenuConfigPanel
        :menus="menus"
        :sections="sections"
        :loading="isLoading && !menus.length"
        :section-order="GLOBAL_SECTION_ORDER"
        editable
        :saving-key="savingKey"
        :busy="busy"
        @toggle="toggle"
        @show-all="setAllVisible(true)"
        @hide-all="setAllVisible(false)"
        @rename="rename"
        @rename-section="renameSection"
        @reorder="reorder"
      />
    </div>
  </section>
</template>

<style scoped>
.gmv2-page {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.gmv2-page__body {
  flex: 1;
  min-height: 0;
  padding: var(--space-5);
  overflow: hidden;
}

.gmv2-page__refresh-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.gmv2-page__refresh-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.gmv2-page__refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.gmv2-page__refresh-btn:focus-visible {
  outline: 2px solid var(--color-info);
  outline-offset: 2px;
}

.gmv2-page__refresh-icon {
  flex-shrink: 0;
}

.gmv2-page__refresh-icon--spin {
  animation: gmv2-spin 0.8s linear infinite;
}

@keyframes gmv2-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
