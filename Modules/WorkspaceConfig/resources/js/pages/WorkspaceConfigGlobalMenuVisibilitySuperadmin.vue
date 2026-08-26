<script setup>
//
// superadmin/workspace-config/global-menu — ẩn/hiện menu sidebar Ở MỨC
// TOÀN HỆ THỐNG. Superadmin bật/tắt trực tiếp, áp dụng cho MỌI tài khoản
// kể cả super_admin, thắng tuyệt đối per-department override.
// Bật/tắt = ghi ngay, không có nút Lưu riêng.
//
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { formatDateTime } from '@/lib/formatTime';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import StatusBadge from '../components/StatusBadge.vue';
import { menuVisibilityLabel } from '../constants/departmentDetail.js';

const SECTION_ORDER = ['general', 'admin', 'manager', 'superadmin-workspace-config'];

const MENU_ICONS = {
  home: 'dashboard',
  'social.feed': 'megaphone',
  'manager.evaluation.view': 'clipboardCheck',
  'superadmin.permissions': 'shield',
  'superadmin.activity': 'clock',
  'manager.workspace-config.hub': 'settings',
  'manager.evaluation-templates.index': 'clipboardCheck',
  'manager.social.moderation': 'listChecks',
  'superadmin.workspace-config.overview': 'settings',
  'superadmin.workspace-config.global-menu': 'eyeOff',
};

const auth = useAuthStore();

const menus = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);
const bulkBusy = ref(false);
const query = ref('');
const filter = ref('all');
const selectedKey = ref(null);
const confirm = ref(null);
const searchInput = ref(null);

const busy = computed(() => Boolean(savingKey.value) || bulkBusy.value);

const visibleCount = computed(() => menus.value.filter((item) => !item.is_hidden).length);
const hiddenCount = computed(() => menus.value.length - visibleCount.value);

const selected = computed(() => menus.value.find((item) => item.menu_key === selectedKey.value) ?? null);

const filteredMenus = computed(() => {
  const term = query.value.trim().toLowerCase();
  return menus.value.filter((item) => {
    if (filter.value === 'visible' && item.is_hidden) return false;
    if (filter.value === 'hidden' && !item.is_hidden) return false;
    if (!term) return true;
    return (
      item.label.toLowerCase().includes(term) ||
      (item.section_label || '').toLowerCase().includes(term) ||
      item.menu_key.toLowerCase().includes(term)
    );
  });
});

const groupedSections = computed(() => groupBySection(filteredMenus.value));
const previewSections = computed(() => groupBySection(menus.value));

const emptyText = computed(() => {
  if (!menus.value.length) return 'Chưa có mục menu nào.';
  if (query.value.trim()) return 'Không có mục khớp tìm kiếm.';
  if (filter.value === 'visible') return 'Không có mục nào đang hiện.';
  if (filter.value === 'hidden') return 'Không có mục nào đang ẩn.';
  return 'Chưa có mục menu nào.';
});

const canShowAll = computed(
  () => toggleable(menus.value.filter((item) => item.is_hidden)).length > 0 && !busy.value,
);
const canHideAll = computed(
  () => toggleable(menus.value.filter((item) => !item.is_hidden)).length > 0 && !busy.value,
);

function menuIcon(menuKey) {
  return MENU_ICONS[menuKey] || 'layoutList';
}

function groupBySection(items) {
  const groups = new Map();
  for (const menu of items) {
    const sectionId = menu.section || 'other';
    if (!groups.has(sectionId)) {
      groups.set(sectionId, { id: sectionId, label: menu.section_label || sectionId, items: [] });
    }
    groups.get(sectionId).items.push(menu);
  }

  const ordered = SECTION_ORDER.filter((id) => groups.has(id)).map((id) => groups.get(id));
  const rest = [...groups.values()].filter((group) => !SECTION_ORDER.includes(group.id));
  return [...ordered, ...rest];
}

function sectionTone(sectionId) {
  const items = menus.value.filter((item) => (item.section || 'other') === sectionId);
  if (!items.length) return 'neutral';
  const hidden = items.filter((item) => item.is_hidden).length;
  if (hidden === 0) return 'success';
  if (hidden === items.length) return 'danger';
  return 'info';
}

function toggleable(items) {
  return items.filter((item) => !item.is_protected);
}

function applyMenu(menuKey, patch) {
  const idx = menus.value.findIndex((item) => item.menu_key === menuKey);
  if (idx === -1) return;
  menus.value[idx] = { ...menus.value[idx], ...patch };
}

async function loadMenus() {
  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/global-menu');
    menus.value = (data.menus ?? []).map((item) => ({ ...item }));
    if (selectedKey.value && !menus.value.some((item) => item.menu_key === selectedKey.value)) {
      selectedKey.value = null;
    }
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách menu.');
  } finally {
    isLoading.value = false;
  }
}

async function persist(menu, nextHidden) {
  const previous = {
    is_hidden: menu.is_hidden,
    updated_by_name: menu.updated_by_name,
    updated_at: menu.updated_at,
  };
  savingKey.value = menu.menu_key;
  applyMenu(menu.menu_key, { is_hidden: nextHidden });
  auth.setGlobalMenuKeyHidden(menu.menu_key, nextHidden);

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu', {
      menu_key: menu.menu_key,
      is_hidden: nextHidden,
    });
    const updated = (data.menus ?? []).find((item) => item.menu_key === menu.menu_key);
    if (updated) applyMenu(menu.menu_key, updated);
    auth.setGlobalMenuKeyHidden(menu.menu_key, updated?.is_hidden ?? nextHidden);
    return true;
  } catch (error) {
    applyMenu(menu.menu_key, previous);
    auth.setGlobalMenuKeyHidden(menu.menu_key, previous.is_hidden);
    if (!bulkBusy.value) {
      const message = error?.response?.data?.message;
      showClientToast('error', message || 'Không lưu được thay đổi.');
    }
    return false;
  } finally {
    savingKey.value = null;
  }
}

async function toggle(menu) {
  if (busy.value || menu.is_protected) return;
  const nextHidden = !menu.is_hidden;
  const ok = await persist(menu, nextHidden);
  if (ok) {
    showClientToast('success', `Đã ${nextHidden ? 'ẩn' : 'hiện'} "${menu.label}".`);
  }
}

async function applyMany(items, nextHidden) {
  if (!items.length || busy.value) return;

  bulkBusy.value = true;
  let done = 0;
  try {
    for (const menu of items) {
      const current = menus.value.find((item) => item.menu_key === menu.menu_key);
      if (!current || current.is_protected || current.is_hidden === nextHidden) continue;
      const ok = await persist(current, nextHidden);
      if (!ok) {
        showClientToast('error', 'Không lưu hết thay đổi. Đã dừng lại.');
        return;
      }
      done += 1;
    }
    if (done) {
      showClientToast('success', `Đã ${nextHidden ? 'ẩn' : 'hiện'} ${done} mục.`);
    }
  } finally {
    bulkBusy.value = false;
  }
}

function askHide(items, title) {
  const targets = toggleable(items.filter((item) => !item.is_hidden));
  if (!targets.length) return;
  confirm.value = {
    title,
    description: `Ẩn ${targets.length} mục khỏi menu trái của mọi tài khoản.`,
    run: () => applyMany(targets, true),
  };
}

function showAll() {
  applyMany(toggleable(menus.value.filter((item) => item.is_hidden)), false);
}

function hideSection(section) {
  askHide(section.items, `Ẩn nhóm ${section.label}?`);
}

function showSection(section) {
  applyMany(toggleable(section.items.filter((item) => item.is_hidden)), false);
}

function sectionCanShow(section) {
  return toggleable(section.items.filter((item) => item.is_hidden)).length > 0 && !busy.value;
}

function sectionCanHide(section) {
  return toggleable(section.items.filter((item) => !item.is_hidden)).length > 0 && !busy.value;
}

function selectMenu(menu) {
  selectedKey.value = selectedKey.value === menu.menu_key ? null : menu.menu_key;
}

function onRowClick(event, menu) {
  if (event.target.closest('button, [role="switch"]')) return;
  selectMenu(menu);
}

function closeSelected() {
  selectedKey.value = null;
}

async function onConfirm() {
  const action = confirm.value;
  confirm.value = null;
  await action?.run?.();
}

function closeConfirm() {
  confirm.value = null;
}

function onDocumentKey(event) {
  if (event.key === 'Escape' && !confirm.value) {
    closeSelected();
  }
}

watch(filteredMenus, (items) => {
  if (selectedKey.value && !items.some((item) => item.menu_key === selectedKey.value)) {
    selectedKey.value = null;
  }
});

onMounted(() => {
  document.addEventListener('keydown', onDocumentKey);
  loadMenus();
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onDocumentKey);
});
</script>

<template>
  <section class="gmv-page">
    <PageHeader
      title="Ẩn/hiện menu toàn hệ thống"
      icon="eyeOff"
      :breadcrumbs="[
        { label: 'Trang chủ', to: { name: 'home' } },
        { label: 'Cấu hình Workspace', to: { name: 'superadmin.workspace-config.overview' } },
        { label: 'Ẩn/hiện menu toàn hệ thống' },
      ]"
    >
      <template #actions>
        <button type="button" class="gmv-page__header-btn" :disabled="isLoading || busy" @click="loadMenus">
          <AppIcon name="refresh" :size="16" :class="{ 'gmv-page__spin': isLoading }" />
          Làm mới
        </button>
      </template>
    </PageHeader>

    <div class="gmv-page__body">
      <div class="gmv-page__toolbar">
        <div class="gmv-page__stats" aria-live="polite">
          <button
            type="button"
            class="gmv-page__stat"
            :class="{ 'gmv-page__stat--active': filter === 'all' }"
            :aria-pressed="filter === 'all'"
            @click="filter = 'all'"
          >
            <strong>{{ menus.length }}</strong>
            tổng
          </button>
          <button
            type="button"
            class="gmv-page__stat gmv-page__stat--on"
            :class="{ 'gmv-page__stat--active': filter === 'visible' }"
            :aria-pressed="filter === 'visible'"
            @click="filter = 'visible'"
          >
            <AppIcon name="eye" :size="14" :stroke-width="1.75" />
            <strong>{{ visibleCount }}</strong>
            đang hiện
          </button>
          <button
            type="button"
            class="gmv-page__stat gmv-page__stat--off"
            :class="{ 'gmv-page__stat--active': filter === 'hidden' }"
            :aria-pressed="filter === 'hidden'"
            @click="filter = 'hidden'"
          >
            <AppIcon name="eyeOff" :size="14" :stroke-width="1.75" />
            <strong>{{ hiddenCount }}</strong>
            đang ẩn
          </button>
        </div>

        <div class="gmv-page__toolbar-end">
          <label class="gmv-page__search">
            <AppIcon name="search" :size="15" :stroke-width="1.75" />
            <input
              ref="searchInput"
              v-model="query"
              type="search"
              class="gmv-page__search-input"
              placeholder="Tìm mục, nhóm…"
              aria-label="Tìm mục menu"
              @keydown.escape.prevent="query = ''"
            />
          </label>

          <div class="gmv-page__bulk">
            <button type="button" class="gmv-page__bulk-btn" :disabled="!canShowAll" @click="showAll">
              Hiện tất cả
            </button>
            <button
              type="button"
              class="gmv-page__bulk-btn"
              :disabled="!canHideAll"
              @click="askHide(menus, 'Ẩn tất cả mục?')"
            >
              Ẩn tất cả
            </button>
          </div>
        </div>
      </div>

      <div class="gmv-page__main">
        <div class="gmv-page__list-wrap hide-scrollbar">
          <p v-if="isLoading && !menus.length" class="gmv-page__empty">Đang tải…</p>
          <p v-else-if="groupedSections.length === 0" class="gmv-page__empty">{{ emptyText }}</p>

          <div v-else class="gmv-page__sections">
            <section
              v-for="section in groupedSections"
              :key="section.id"
              class="gmv-page__section"
              :class="`gmv-page__section--${sectionTone(section.id)}`"
            >
              <header class="gmv-page__section-head">
                <h2 class="gmv-page__section-label">{{ section.label }}</h2>
                <div class="gmv-page__section-actions">
                  <span class="gmv-page__section-count">{{ section.items.length }} mục</span>
                  <button
                    type="button"
                    class="gmv-page__section-btn"
                    :disabled="!sectionCanShow(section)"
                    @click="showSection(section)"
                  >
                    Hiện
                  </button>
                  <button
                    type="button"
                    class="gmv-page__section-btn"
                    :disabled="!sectionCanHide(section)"
                    @click="hideSection(section)"
                  >
                    Ẩn
                  </button>
                </div>
              </header>

              <ul class="gmv-page__list" role="list">
                <li
                  v-for="menu in section.items"
                  :key="menu.menu_key"
                  class="gmv-page__item"
                  :class="{
                    'gmv-page__item--on': !menu.is_hidden,
                    'gmv-page__item--off': menu.is_hidden,
                    'gmv-page__item--saving': savingKey === menu.menu_key,
                    'gmv-page__item--selected': selectedKey === menu.menu_key,
                  }"
                  @click="onRowClick($event, menu)"
                >
                  <span class="gmv-page__item-icon" aria-hidden="true">
                    <AppIcon :name="menuIcon(menu.menu_key)" :size="18" :stroke-width="1.75" />
                  </span>

                  <div class="gmv-page__item-body">
                    <p class="gmv-page__item-label">
                      {{ menu.label }}
                      <AppIcon
                        v-if="menu.is_protected"
                        name="lock"
                        :size="13"
                        :stroke-width="2"
                        class="gmv-page__lock"
                        aria-hidden="true"
                      />
                    </p>
                    <p v-if="menu.audience" class="gmv-page__item-audience">{{ menu.audience }}</p>
                  </div>

                  <div class="gmv-page__item-controls">
                    <StatusBadge :on="!menu.is_hidden" :label="menuVisibilityLabel(!menu.is_hidden)" />
                    <button
                      type="button"
                      class="gmv-page__switch"
                      :class="{ 'gmv-page__switch--on': !menu.is_hidden }"
                      role="switch"
                      :aria-checked="!menu.is_hidden"
                      :disabled="menu.is_protected || busy"
                      :aria-label="
                        menu.is_protected
                          ? `${menu.label} được bảo vệ`
                          : menu.is_hidden
                            ? `Hiện mục ${menu.label}`
                            : `Ẩn mục ${menu.label}`
                      "
                      @click="toggle(menu)"
                    >
                      <span class="gmv-page__switch-thumb" />
                    </button>
                  </div>
                </li>
              </ul>
            </section>
          </div>
        </div>

        <aside class="gmv-page__side hide-scrollbar" aria-label="Xem trước">
          <div class="gmv-page__side-head">
            <h2 class="gmv-page__side-title">Xem trước</h2>
          </div>

          <div class="gmv-page__preview-rail sidebar-surface">
            <div class="gmv-page__preview-brand">
              <img src="/images/congnghe/brand/vas-white.png" alt="" class="gmv-page__preview-logo" />
            </div>

            <div v-if="isLoading && !menus.length" class="gmv-page__preview-empty">Đang tải…</div>
            <div v-else-if="!menus.length" class="gmv-page__preview-empty">Chưa có mục.</div>

            <nav v-else class="gmv-page__preview-nav hide-scrollbar" aria-hidden="true">
              <section v-for="section in previewSections" :key="section.id" class="gmv-page__preview-section">
                <p class="gmv-page__preview-section-label">{{ section.label }}</p>
                <div
                  v-for="menu in section.items"
                  :key="menu.menu_key"
                  class="gmv-page__preview-link"
                  :class="{
                    'gmv-page__preview-link--hidden': menu.is_hidden,
                    'gmv-page__preview-link--active': selectedKey === menu.menu_key,
                  }"
                >
                  <span class="gmv-page__preview-link-icon">
                    <AppIcon :name="menuIcon(menu.menu_key)" :size="14" :stroke-width="1.75" />
                  </span>
                  <span class="gmv-page__preview-link-text">{{ menu.label }}</span>
                  <span v-if="menu.is_hidden" class="gmv-page__preview-hidden">Ẩn</span>
                </div>
              </section>
            </nav>
          </div>

          <div v-if="selected" class="gmv-page__detail">
            <div class="gmv-page__detail-head">
              <h3 class="gmv-page__detail-title">Chi tiết</h3>
              <button type="button" class="gmv-page__icon-btn" aria-label="Đóng" @click="closeSelected">
                <AppIcon name="close" :size="16" :stroke-width="1.75" />
              </button>
            </div>

            <div
              class="gmv-page__detail-lead"
              :class="selected.is_hidden ? 'gmv-page__detail-lead--danger' : 'gmv-page__detail-lead--success'"
            >
              <span
                class="gmv-page__dot"
                :class="selected.is_hidden ? 'gmv-page__dot--danger' : 'gmv-page__dot--success'"
              />
              <div>
                <span class="gmv-page__detail-lead-action">{{ selected.section_label }}</span>
                <p class="gmv-page__detail-lead-desc">{{ selected.label }}</p>
              </div>
            </div>

            <div class="gmv-page__rows">
              <div class="gmv-page__row">
                <span class="gmv-page__row-label">Trạng thái</span>
                <span class="gmv-page__row-value">{{ menuVisibilityLabel(!selected.is_hidden).toLowerCase() }}</span>
              </div>
              <div class="gmv-page__row">
                <span class="gmv-page__row-label">Người đổi</span>
                <span class="gmv-page__row-value">{{ (selected.updated_by_name || '—').toLowerCase() }}</span>
              </div>
              <div class="gmv-page__row">
                <span class="gmv-page__row-label">Lúc đổi</span>
                <span class="gmv-page__row-value">{{
                  selected.updated_at ? formatDateTime(selected.updated_at).toLowerCase() : '—'
                }}</span>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </div>

    <ConfirmDialog
      :open="Boolean(confirm)"
      :title="confirm?.title || 'Xác nhận'"
      :description="confirm?.description || ''"
      confirm-label="Ẩn"
      danger
      @update:open="(open) => { if (!open) closeConfirm() }"
      @confirm="onConfirm"
    />
  </section>
</template>

<style scoped>
.gmv-page {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.gmv-page__header-btn {
  flex-shrink: 0;
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

.gmv-page__header-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.gmv-page__header-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.gmv-page__spin {
  animation: gmv-spin 0.8s linear infinite;
}

@keyframes gmv-spin {
  to {
    transform: rotate(360deg);
  }
}

.gmv-page__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-5);
  overflow: hidden;
}

.gmv-page__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.gmv-page__stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.gmv-page__stat {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  height: 2rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.gmv-page__stat strong {
  color: var(--color-text);
  font-size: 0.8125rem;
}

.gmv-page__stat--on {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.gmv-page__stat--on strong {
  color: var(--color-success-tint-fg);
}

.gmv-page__stat--off {
  background: var(--color-surface-muted);
}

.gmv-page__stat--active {
  box-shadow: inset 0 0 0 2px var(--color-info);
}

.gmv-page__stat:hover {
  filter: brightness(0.98);
}

.gmv-page__toolbar-end {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.gmv-page__search {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  height: 2rem;
  padding: 0 0.625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.gmv-page__search-input {
  width: 11rem;
  min-width: 0;
  height: 100%;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  outline: none;
}

.gmv-page__bulk {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.gmv-page__bulk-btn,
.gmv-page__section-btn {
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.gmv-page__section-btn {
  height: 1.75rem;
  padding: 0 0.5rem;
  font-size: 0.75rem;
}

.gmv-page__bulk-btn:hover:not(:disabled),
.gmv-page__section-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.gmv-page__bulk-btn:disabled,
.gmv-page__section-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.gmv-page__bulk-btn:focus-visible,
.gmv-page__section-btn:focus-visible,
.gmv-page__switch:focus-visible,
.gmv-page__stat:focus-visible,
.gmv-page__search:focus-within,
.gmv-page__icon-btn:focus-visible,
.gmv-page__header-btn:focus-visible {
  outline: 2px solid var(--color-info);
  outline-offset: 2px;
}

.gmv-page__main {
  flex: 1;
  min-height: 0;
  display: flex;
  gap: var(--space-4);
  overflow: hidden;
}

.gmv-page__list-wrap {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: auto;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.gmv-page__empty {
  margin: 0;
  padding: var(--space-8) var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.gmv-page__sections {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.gmv-page__section {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.gmv-page__section::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.gmv-page__section--success::before {
  background: var(--color-success);
}

.gmv-page__section--danger::before {
  background: var(--color-danger);
}

.gmv-page__section--info::before {
  background: var(--color-info);
}

.gmv-page__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.gmv-page__section-label {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.gmv-page__section-actions {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
}

.gmv-page__section-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.gmv-page__list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.gmv-page__item {
  display: grid;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  cursor: pointer;
}

.gmv-page__item:hover {
  background: color-mix(in srgb, var(--color-info) 6%, var(--color-surface-muted));
}

.gmv-page__item--selected {
  box-shadow: inset 0 0 0 1px var(--color-info);
  background: color-mix(in srgb, var(--color-info) 8%, var(--color-surface-muted));
}

.gmv-page__item--saving {
  opacity: 0.7;
}

.gmv-page__item-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface);
  color: var(--color-text-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.gmv-page__item--on .gmv-page__item-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.gmv-page__item-body {
  min-width: 0;
}

.gmv-page__item-label {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.gmv-page__lock {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.gmv-page__item-audience {
  margin: 0.125rem 0 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.gmv-page__item-controls {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-3);
}

.gmv-page__switch {
  position: relative;
  flex-shrink: 0;
  width: 2.75rem;
  height: 1.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-text-muted) 35%, var(--color-surface-muted));
  cursor: pointer;
}

.gmv-page__switch--on {
  background: var(--color-success);
}

.gmv-page__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.gmv-page__switch:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.gmv-page__switch-thumb {
  position: absolute;
  top: 0.125rem;
  left: 0.125rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.gmv-page__switch--on .gmv-page__switch-thumb {
  transform: translateX(1.25rem);
}

.gmv-page__side {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  width: 28rem;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.gmv-page__side-head {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.gmv-page__side-title,
.gmv-page__detail-title {
  margin: 0;
  color: var(--color-text);
  font-size: 1.0625rem;
  font-weight: 700;
}

.gmv-page__preview-rail {
  flex: 1;
  min-height: 12rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  user-select: none;
}

.gmv-page__preview-brand {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 3.25rem;
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
}

.gmv-page__preview-logo {
  display: block;
  height: 1.5rem;
  width: auto;
  max-width: 100%;
  object-fit: contain;
}

.gmv-page__preview-nav {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
}

.gmv-page__preview-empty {
  padding: var(--space-5) var(--space-3);
  color: var(--color-sidebar-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.gmv-page__preview-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.gmv-page__preview-section-label {
  margin: 0 0 var(--space-1);
  padding: 0 var(--space-2);
  color: var(--color-sidebar-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.gmv-page__preview-link {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.125rem;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--color-sidebar-text);
  font-size: 0.8125rem;
  font-weight: 500;
}

.gmv-page__preview-link--hidden {
  opacity: 0.42;
}

.gmv-page__preview-link--active {
  background: var(--color-sidebar-active);
}

.gmv-page__preview-link-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-sm);
  background: var(--color-sidebar-well);
  color: var(--color-sidebar-text-muted);
}

.gmv-page__preview-link-text {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gmv-page__preview-hidden {
  flex-shrink: 0;
  color: var(--color-sidebar-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.gmv-page__detail {
  flex-shrink: 0;
}

.gmv-page__detail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.gmv-page__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
}

.gmv-page__icon-btn:hover {
  background: var(--color-surface);
}

.gmv-page__detail-lead {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  margin: var(--space-3) 0 var(--space-4);
  padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-2) + 3px + var(--space-2));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.gmv-page__detail-lead::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.gmv-page__detail-lead--success::before {
  background: var(--color-success);
}

.gmv-page__detail-lead--danger::before {
  background: var(--color-danger);
}

.gmv-page__detail-lead-action {
  display: block;
  margin-bottom: var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.gmv-page__detail-lead-desc {
  margin: 0;
  color: var(--color-text);
  font-weight: 600;
  font-size: 0.9375rem;
  line-height: 1.45;
}

.gmv-page__dot {
  flex-shrink: 0;
  margin-top: 0.375rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.gmv-page__dot--success {
  background: var(--color-success);
}

.gmv-page__dot--danger {
  background: var(--color-danger);
}

.gmv-page__rows {
  display: flex;
  flex-direction: column;
}

.gmv-page__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) 0;
  box-shadow: 0 1px 0 var(--color-border);
  font-size: 0.8125rem;
}

.gmv-page__row:last-child {
  box-shadow: none;
}

.gmv-page__row-label {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.gmv-page__row-label::after {
  content: ':';
}

.gmv-page__row-value {
  color: var(--color-text);
  font-style: italic;
  font-weight: 400;
  text-align: right;
  overflow-wrap: anywhere;
}

@media (max-width: 1100px) {
  .gmv-page__main {
    flex-direction: column;
  }

  .gmv-page__side {
    width: 100%;
    max-height: 42%;
  }

  .gmv-page__preview-rail {
    min-height: 10rem;
  }
}

@media (max-width: 768px) {
  .gmv-page__body {
    padding: var(--space-4) var(--space-3);
  }

  .gmv-page__toolbar-end,
  .gmv-page__bulk {
    width: 100%;
  }

  .gmv-page__search {
    width: 100%;
  }

  .gmv-page__search-input {
    width: 100%;
  }

  .gmv-page__bulk-btn {
    flex: 1;
  }

  .gmv-page__item {
    grid-template-columns: 2.5rem minmax(0, 1fr);
  }

  .gmv-page__item-controls {
    grid-column: 1 / -1;
    justify-content: space-between;
  }
}

@media (prefers-reduced-motion: reduce) {
  .gmv-page__switch-thumb,
  .gmv-page__spin {
    animation: none;
    transition: none;
  }
}
</style>
