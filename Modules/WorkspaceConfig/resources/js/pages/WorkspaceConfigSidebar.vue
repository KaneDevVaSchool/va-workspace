<script setup>
//
// manager/workspace-config/sidebar — bật/tắt, đổi tên, đổi tên nhóm và
// kéo thả thứ tự mục sidebar (menu trái) áp dụng cho cả phòng ban. Đổi
// 1 mục = ghi ngay; patch auth để sidebar đổi tức thì.
//
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import SidebarMenuConfigPanel from '../components/SidebarMenuConfigPanel.vue';
import { layoutPayload } from '../constants/sidebarMenus.js';

const hub = inject('workspaceConfigHub', null);
const auth = useAuthStore();
const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const menus = ref([]);
const sections = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);
const bulkBusy = ref(false);

const busy = computed(() => Boolean(savingKey.value) || bulkBusy.value);

const emptyText = computed(() =>
  hasDepartment.value
    ? 'Chưa có mục menu nào có thể cấu hình.'
    : 'Tài khoản chưa gắn với phòng ban nào.',
);

function applyAuthLayout(nextMenus) {
  const order = {};
  const itemSections = {};
  for (const item of nextMenus) {
    order[item.menu_key] = item.sort_order;
    itemSections[item.menu_key] = item.section;
  }
  auth.setMenuLayout({ order, itemSections });
}

function applyMenu(menuKey, patch) {
  const idx = menus.value.findIndex((item) => item.menu_key === menuKey);
  if (idx === -1) return;
  menus.value[idx] = { ...menus.value[idx], ...patch };
  if (Object.hasOwn(patch, 'is_visible')) {
    auth.setMenuKeyVisible(menuKey, patch.is_visible);
  }
  if (Object.hasOwn(patch, 'label') || Object.hasOwn(patch, 'custom_label')) {
    auth.setMenuLabel(menuKey, patch.custom_label || '');
  }
}

function applyFromResponse(payload) {
  if (!payload?.menu_key) return;
  applyMenu(payload.menu_key, {
    is_visible: Boolean(payload.is_visible),
    label: payload.label,
    default_label: payload.default_label,
    custom_label: payload.custom_label ?? null,
    section: payload.section ?? menus.value.find((item) => item.menu_key === payload.menu_key)?.section,
    sort_order: payload.sort_order ?? menus.value.find((item) => item.menu_key === payload.menu_key)?.sort_order,
  });
}

function applyVisibility(menuKey, isVisible) {
  applyMenu(menuKey, { is_visible: isVisible });
}

async function persist(menu, nextVisible) {
  const previous = menu.is_visible;
  savingKey.value = menu.menu_key;
  applyVisibility(menu.menu_key, nextVisible);

  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar', {
      menu_key: menu.menu_key,
      is_visible: nextVisible,
    });
    applyFromResponse(data.menu);
    return true;
  } catch (error) {
    applyVisibility(menu.menu_key, previous);
    if (!bulkBusy.value) {
      const message = error?.response?.data?.message;
      showClientToast('error', message || 'Không lưu được thay đổi.');
    }
    return false;
  } finally {
    savingKey.value = null;
  }
}

async function rename(menu, nextLabel) {
  if (busy.value) return;

  const previous = {
    label: menu.label,
    custom_label: menu.custom_label ?? null,
  };
  const fallback = menu.default_label || menu.label;
  const trimmed = String(nextLabel ?? '').trim();
  savingKey.value = menu.menu_key;
  applyMenu(menu.menu_key, {
    label: trimmed || fallback,
    custom_label: trimmed || null,
  });

  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar', {
      menu_key: menu.menu_key,
      custom_label: trimmed,
    });
    applyFromResponse(data.menu);
    showClientToast(
      'success',
      data.menu.custom_label ? `Đã đổi tên thành "${data.menu.label}".` : `Đã đặt lại tên "${data.menu.label}".`,
    );
  } catch (error) {
    applyMenu(menu.menu_key, previous);
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được tên menu.');
  } finally {
    savingKey.value = null;
  }
}

async function renameSection(sectionId, nextLabel) {
  if (busy.value) return;

  const idx = sections.value.findIndex((item) => item.id === sectionId);
  if (idx === -1) return;

  const previous = { ...sections.value[idx] };
  const fallback = previous.default_label || previous.label;
  const trimmed = String(nextLabel ?? '').trim();
  sections.value[idx] = {
    ...previous,
    label: trimmed || fallback,
    custom_label: trimmed || null,
  };
  auth.setSectionLabel(sectionId, trimmed);
  bulkBusy.value = true;

  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar/section', {
      section_key: sectionId,
      custom_label: trimmed,
    });
    if (Array.isArray(data.sections)) {
      sections.value = data.sections.map((item) => ({ ...item }));
    }
    const updated = data.section ?? sections.value.find((item) => item.id === sectionId);
    auth.setSectionLabel(sectionId, updated?.custom_label || '');
    showClientToast(
      'success',
      updated?.custom_label ? `Đã đổi tên nhóm thành "${updated.label}".` : `Đã đặt lại tên nhóm "${updated.label}".`,
    );
  } catch (error) {
    sections.value[idx] = previous;
    auth.setSectionLabel(sectionId, previous.custom_label || '');
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được tên nhóm.');
  } finally {
    bulkBusy.value = false;
  }
}

async function reorder(nextMenus) {
  if (busy.value) return;

  const previousMenus = menus.value.map((item) => ({ ...item }));
  menus.value = nextMenus.map((item) => ({ ...item }));
  applyAuthLayout(menus.value);
  bulkBusy.value = true;

  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar/layout', {
      items: layoutPayload(menus.value),
    });
    menus.value = (data.menus ?? menus.value).map((item) => ({ ...item }));
    if (Array.isArray(data.sections)) {
      sections.value = data.sections.map((item) => ({ ...item }));
    }
    applyAuthLayout(menus.value);
  } catch (error) {
    menus.value = previousMenus;
    applyAuthLayout(previousMenus);
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thứ tự menu.');
  } finally {
    bulkBusy.value = false;
  }
}

async function loadMenus() {
  if (!hasDepartment.value) {
    menus.value = [];
    sections.value = [];
    return;
  }

  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/sidebar');
    menus.value = (data.menus ?? []).map((item) => ({ ...item }));
    sections.value = (data.sections ?? []).map((item) => ({ ...item }));
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được cấu hình menu.');
  } finally {
    isLoading.value = false;
  }
}

async function toggle(menu) {
  if (busy.value) return;
  const nextVisible = !menu.is_visible;
  const ok = await persist(menu, nextVisible);
  if (ok) {
    showClientToast('success', `Đã ${nextVisible ? 'bật' : 'tắt'} "${menu.label}".`);
  }
}

async function setAllVisible(isVisible) {
  if (busy.value) return;
  const targets = menus.value.filter((item) => item.is_visible !== isVisible);
  if (targets.length === 0) return;

  bulkBusy.value = true;
  let done = 0;
  try {
    for (const menu of targets) {
      const ok = await persist(menu, isVisible);
      if (ok) done += 1;
    }
    if (done === targets.length) {
      showClientToast('success', isVisible ? `Đã hiện ${done} mục menu.` : `Đã ẩn ${done} mục menu.`);
    } else if (done > 0) {
      showClientToast('error', `Đã cập nhật ${done}/${targets.length} mục. Một số mục không lưu được.`);
    } else {
      showClientToast('error', 'Không lưu được thay đổi.');
    }
  } finally {
    bulkBusy.value = false;
  }
}

onMounted(() => {
  hub?.registerReload?.(loadMenus);
  loadMenus();
});

onBeforeUnmount(() => {
  hub?.unregisterReload?.();
});
</script>

<template>
  <SidebarMenuConfigPanel
    :menus="menus"
    :sections="sections"
    :loading="isLoading"
    :empty-text="emptyText"
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
</template>
