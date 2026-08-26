<script setup>
//
// Panel cấu hình menu trái dùng chung cho tab manager (bật/tắt + đổi tên
// + kéo thả + đổi tên nhóm) và superadmin chi tiết phòng ban (chỉ xem).
// Nhóm theo section sidebar, thống kê hiện/ẩn, lọc, xem trước menu trái.
//
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import StatusBadge from './StatusBadge.vue';
import {
  groupMenus,
  LABEL_MAX_LENGTH,
  moveMenuItem,
} from '../constants/sidebarMenus.js';
import { menuVisibilityLabel } from '../constants/departmentDetail.js';

const props = defineProps({
  menus: { type: Array, default: () => [] },
  sections: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'Chưa có mục menu nào có thể cấu hình.' },
  editable: { type: Boolean, default: false },
  savingKey: { type: String, default: null },
  busy: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'show-all', 'hide-all', 'rename', 'rename-section', 'reorder']);

const filter = ref('all');
const drafts = reactive({});
const sectionDrafts = reactive({});
const draggingKey = ref(null);
const dropTarget = ref(null);
const dragSnapshot = ref(null);
const ghost = ref(null);

let grabX = 0;
let grabY = 0;
let pendingX = 0;
let pendingY = 0;
let rafId = 0;

const canDrag = computed(() => props.editable && !props.busy && filter.value === 'all');

const visibleCount = computed(() => props.menus.filter((item) => item.is_visible).length);
const hiddenCount = computed(() => props.menus.length - visibleCount.value);

const filteredMenus = computed(() => {
  if (filter.value === 'visible') return props.menus.filter((item) => item.is_visible);
  if (filter.value === 'hidden') return props.menus.filter((item) => !item.is_visible);
  return props.menus;
});

const displayMenus = computed(() => {
  if (!draggingKey.value || !dragSnapshot.value || !dropTarget.value) return props.menus;
  return moveMenuItem(dragSnapshot.value, draggingKey.value, dropTarget.value.sectionId, dropTarget.value.index);
});

const groupedSections = computed(() =>
  groupMenus(draggingKey.value ? displayMenus.value : filteredMenus.value, props.sections, {
    includeEmpty: props.editable && filter.value === 'all',
  }),
);
const previewSections = computed(() => groupMenus(draggingKey.value ? displayMenus.value : props.menus, props.sections));

const filterEmptyText = computed(() => {
  if (filter.value === 'visible') return 'Không có mục nào đang hiện.';
  if (filter.value === 'hidden') return 'Không có mục nào đang ẩn.';
  return props.emptyText;
});

const canShowAll = computed(() => props.editable && hiddenCount.value > 0 && !props.busy);
const canHideAll = computed(() => props.editable && visibleCount.value > 0 && !props.busy);

function defaultLabel(menu) {
  return menu.default_label || menu.label;
}

function titleValue(menu) {
  return Object.hasOwn(drafts, menu.menu_key) ? drafts[menu.menu_key] : menu.label;
}

function isCustomized(menu) {
  return titleValue(menu).trim() !== defaultLabel(menu);
}

function onTitleInput(menu, event) {
  drafts[menu.menu_key] = event.target.value;
}

function commitTitle(menu) {
  const next = titleValue(menu).trim();
  delete drafts[menu.menu_key];
  if (next === menu.label) return;
  emit('rename', menu, next);
}

function revertTitle(menu) {
  delete drafts[menu.menu_key];
}

function resetTitle(menu) {
  delete drafts[menu.menu_key];
  if (!menu.custom_label && menu.label === defaultLabel(menu)) return;
  emit('rename', menu, '');
}

function onTitleKeydown(event, menu) {
  if (event.key === 'Enter') {
    event.preventDefault();
    event.target.blur();
  }
  if (event.key === 'Escape') {
    revertTitle(menu);
    event.target.blur();
  }
}

function sectionTitleValue(section) {
  return Object.hasOwn(sectionDrafts, section.id) ? sectionDrafts[section.id] : section.label;
}

function isSectionCustomized(section) {
  return sectionTitleValue(section).trim() !== section.defaultLabel;
}

function onSectionTitleInput(section, event) {
  sectionDrafts[section.id] = event.target.value;
}

function commitSectionTitle(section) {
  const next = sectionTitleValue(section).trim();
  delete sectionDrafts[section.id];
  if (next === section.label) return;
  emit('rename-section', section.id, next);
}

function revertSectionTitle(section) {
  delete sectionDrafts[section.id];
}

function resetSectionTitle(section) {
  delete sectionDrafts[section.id];
  if (section.label === section.defaultLabel && !isSectionCustomized(section)) return;
  emit('rename-section', section.id, '');
}

function onSectionTitleKeydown(event, section) {
  if (event.key === 'Enter') {
    event.preventDefault();
    event.target.blur();
  }
  if (event.key === 'Escape') {
    revertSectionTitle(section);
    event.target.blur();
  }
}

function readDropTarget(clientX, clientY) {
  const lists = document.querySelectorAll('.wc-menu__list-wrap [data-section-id]');
  let nearest = dropTarget.value;
  let nearestDist = Infinity;

  for (const list of lists) {
    const rect = list.getBoundingClientRect();
    if (clientX < rect.left - 20 || clientX > rect.right + 20) continue;

    const items = [...list.querySelectorAll('[data-menu-key]')].filter(
      (el) => el.dataset.menuKey !== draggingKey.value,
    );
    let index = items.length;
    for (let i = 0; i < items.length; i += 1) {
      const itemRect = items[i].getBoundingClientRect();
      if (clientY < itemRect.top + itemRect.height / 2) {
        index = i;
        break;
      }
    }

    const next = { sectionId: list.dataset.sectionId, index };
    if (clientY >= rect.top - 12 && clientY <= rect.bottom + 12) {
      return next;
    }

    const dist = clientY < rect.top ? rect.top - clientY : clientY - rect.bottom;
    if (dist < nearestDist) {
      nearestDist = dist;
      nearest = next;
    }
  }

  return nearest;
}

function flushPointer() {
  rafId = 0;
  if (!draggingKey.value || !ghost.value) return;
  ghost.value.x = pendingX - grabX;
  ghost.value.y = pendingY - grabY;
  const next = readDropTarget(pendingX, pendingY);
  if (
    next &&
    (!dropTarget.value || next.sectionId !== dropTarget.value.sectionId || next.index !== dropTarget.value.index)
  ) {
    dropTarget.value = next;
  }
}

function stopPointerDrag() {
  if (rafId) {
    cancelAnimationFrame(rafId);
    rafId = 0;
  }
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerup', onPointerUp);
  window.removeEventListener('pointercancel', onPointerCancel);
  document.body.style.userSelect = '';
  document.body.style.cursor = '';
}

function clearDragState() {
  draggingKey.value = null;
  dropTarget.value = null;
  dragSnapshot.value = null;
  ghost.value = null;
  stopPointerDrag();
}

function commitReorder(key, sectionId, index) {
  const current = dragSnapshot.value || props.menus;
  const next = moveMenuItem(current, key, sectionId, index);
  if (
    next.length === current.length &&
    next.every((item, i) => item.menu_key === current[i].menu_key && item.section === current[i].section)
  ) {
    return;
  }
  emit('reorder', next);
}

function onPointerMove(event) {
  if (!draggingKey.value) return;
  pendingX = event.clientX;
  pendingY = event.clientY;
  if (!rafId) rafId = requestAnimationFrame(flushPointer);
}

function onPointerUp() {
  const key = draggingKey.value;
  const target = dropTarget.value;
  if (key && target) commitReorder(key, target.sectionId, target.index);
  clearDragState();
}

function onPointerCancel() {
  clearDragState();
}

function onHandlePointerDown(event, menu) {
  if (!canDrag.value || event.button !== 0) return;
  event.preventDefault();
  event.stopPropagation();

  const itemEl = event.currentTarget.closest('[data-menu-key]');
  const rect = itemEl?.getBoundingClientRect();
  grabX = rect ? event.clientX - rect.left : 0;
  grabY = rect ? event.clientY - rect.top : 0;
  pendingX = event.clientX;
  pendingY = event.clientY;

  dragSnapshot.value = props.menus.map((item) => ({ ...item }));
  draggingKey.value = menu.menu_key;
  ghost.value = {
    label: titleValue(menu),
    icon: menu.icon,
    on: menu.is_visible,
    width: rect?.width ?? 0,
    x: rect?.left ?? event.clientX,
    y: rect?.top ?? event.clientY,
  };

  const fromSection = groupedSections.value.find((section) =>
    section.items.some((item) => item.menu_key === menu.menu_key),
  );
  const fromIndex = fromSection?.items.findIndex((item) => item.menu_key === menu.menu_key) ?? 0;
  dropTarget.value = { sectionId: fromSection?.id || menu.section, index: fromIndex };

  document.body.style.userSelect = 'none';
  document.body.style.cursor = 'grabbing';
  window.addEventListener('pointermove', onPointerMove);
  window.addEventListener('pointerup', onPointerUp);
  window.addEventListener('pointercancel', onPointerCancel);
}

function onRowPointerDown(event, menu) {
  if (event.target.closest('input, button, [role="switch"]')) return;
  onHandlePointerDown(event, menu);
}

onBeforeUnmount(() => {
  clearDragState();
});
</script>

<template>
  <div class="wc-menu" :class="{ 'wc-menu--dragging': draggingKey }">
    <div class="wc-menu__toolbar">
      <div class="wc-menu__stats" aria-live="polite">
        <span class="wc-menu__stat">
          <strong>{{ menus.length }}</strong>
          tổng
        </span>
        <span class="wc-menu__stat wc-menu__stat--on">
          <AppIcon name="eye" :size="14" :stroke-width="1.75" />
          <strong>{{ visibleCount }}</strong>
          đang hiện
        </span>
        <span class="wc-menu__stat wc-menu__stat--off">
          <AppIcon name="eyeOff" :size="14" :stroke-width="1.75" />
          <strong>{{ hiddenCount }}</strong>
          đang ẩn
        </span>
      </div>

      <div class="wc-menu__toolbar-end">
        <div class="wc-menu__filters" role="group" aria-label="Lọc trạng thái menu">
          <button
            type="button"
            class="wc-menu__filter"
            :class="{ 'wc-menu__filter--active': filter === 'all' }"
            :aria-pressed="filter === 'all'"
            @click="filter = 'all'"
          >
            Tất cả
          </button>
          <button
            type="button"
            class="wc-menu__filter"
            :class="{ 'wc-menu__filter--active': filter === 'visible' }"
            :aria-pressed="filter === 'visible'"
            @click="filter = 'visible'"
          >
            Đang hiện
          </button>
          <button
            type="button"
            class="wc-menu__filter"
            :class="{ 'wc-menu__filter--active': filter === 'hidden' }"
            :aria-pressed="filter === 'hidden'"
            @click="filter = 'hidden'"
          >
            Đang ẩn
          </button>
        </div>

        <div v-if="editable" class="wc-menu__bulk">
          <button type="button" class="wc-menu__bulk-btn" :disabled="!canShowAll" @click="emit('show-all')">
            Hiện tất cả
          </button>
          <button type="button" class="wc-menu__bulk-btn" :disabled="!canHideAll" @click="emit('hide-all')">
            Ẩn tất cả
          </button>
        </div>
      </div>
    </div>

    <div class="wc-menu__main">
      <div class="wc-menu__list-wrap hide-scrollbar">
        <p v-if="loading" class="wc-menu__empty">Đang tải…</p>
        <p v-else-if="menus.length === 0" class="wc-menu__empty">{{ emptyText }}</p>
        <p v-else-if="groupedSections.length === 0" class="wc-menu__empty">{{ filterEmptyText }}</p>

        <div v-else class="wc-menu__sections">
          <section
            v-for="section in groupedSections"
            :key="section.id"
            class="wc-menu__section"
            :class="{ 'wc-menu__section--drop': dropTarget?.sectionId === section.id }"
          >
            <header class="wc-menu__section-head">
              <div class="wc-menu__section-title">
                <input
                  v-if="editable"
                  class="wc-menu__section-input"
                  type="text"
                  :value="sectionTitleValue(section)"
                  :maxlength="LABEL_MAX_LENGTH"
                  :disabled="busy"
                  :placeholder="section.defaultLabel"
                  :aria-label="`Tên nhóm ${section.defaultLabel}`"
                  @input="onSectionTitleInput(section, $event)"
                  @blur="commitSectionTitle(section)"
                  @keydown="onSectionTitleKeydown($event, section)"
                />
                <h2 v-else class="wc-menu__section-label">{{ section.label }}</h2>
                <button
                  v-if="editable && isSectionCustomized(section)"
                  type="button"
                  class="wc-menu__reset"
                  :disabled="busy"
                  @mousedown.prevent="resetSectionTitle(section)"
                >
                  Đặt lại
                </button>
              </div>
              <span class="wc-menu__section-count">{{ section.items.length }} mục</span>
            </header>

            <TransitionGroup
              name="wc-menu-sort"
              tag="ul"
              class="wc-menu__list"
              role="list"
              :data-section-id="section.id"
              :data-item-count="section.items.length"
            >
              <li
                v-if="section.items.length === 0"
                key="empty"
                class="wc-menu__drop-empty"
                :class="{ 'wc-menu__drop-empty--active': dropTarget?.sectionId === section.id }"
              />
              <li
                v-for="(menu, index) in section.items"
                :key="menu.menu_key"
                class="wc-menu__item"
                :class="{
                  'wc-menu__item--on': menu.is_visible,
                  'wc-menu__item--off': !menu.is_visible,
                  'wc-menu__item--saving': savingKey === menu.menu_key,
                  'wc-menu__item--sortable': canDrag,
                  'wc-menu__item--dragging': draggingKey === menu.menu_key,
                }"
                :data-menu-key="menu.menu_key"
                :data-index="index"
                @pointerdown="onRowPointerDown($event, menu)"
              >
                <span
                  v-if="canDrag"
                  class="wc-menu__drag"
                  data-drag-handle
                  role="button"
                  :aria-label="`Đổi vị trí ${menu.label}`"
                  @pointerdown="onHandlePointerDown($event, menu)"
                >
                  <AppIcon name="gripVertical" :size="16" :stroke-width="2.25" />
                </span>
                <span class="wc-menu__item-icon" aria-hidden="true">
                  <AppIcon :name="menu.icon" :size="18" :stroke-width="1.75" />
                </span>

                <div class="wc-menu__item-body">
                  <input
                    v-if="editable"
                    class="wc-menu__title-input"
                    type="text"
                    :value="titleValue(menu)"
                    :maxlength="LABEL_MAX_LENGTH"
                    :disabled="busy"
                    :placeholder="defaultLabel(menu)"
                    :aria-label="`Tên hiển thị của ${defaultLabel(menu)}`"
                    @input="onTitleInput(menu, $event)"
                    @blur="commitTitle(menu)"
                    @keydown="onTitleKeydown($event, menu)"
                  />
                  <p v-else class="wc-menu__item-label">{{ menu.label }}</p>

                  <div v-if="isCustomized(menu)" class="wc-menu__item-meta">
                    <span>Mặc định: {{ defaultLabel(menu) }}</span>
                    <button
                      v-if="editable"
                      type="button"
                      class="wc-menu__reset"
                      :disabled="busy"
                      @mousedown.prevent="resetTitle(menu)"
                    >
                      Đặt lại
                    </button>
                  </div>
                </div>

                <div class="wc-menu__item-controls">
                  <StatusBadge :on="menu.is_visible" :label="menuVisibilityLabel(menu.is_visible)" />
                  <button
                    v-if="editable"
                    type="button"
                    class="wc-menu__switch"
                    :class="{ 'wc-menu__switch--on': menu.is_visible }"
                    role="switch"
                    :aria-checked="menu.is_visible"
                    :disabled="busy"
                    :aria-label="menu.is_visible ? `Ẩn mục ${menu.label}` : `Hiện mục ${menu.label}`"
                    @click="emit('toggle', menu)"
                  >
                    <span class="wc-menu__switch-thumb" />
                  </button>
                </div>
              </li>
            </TransitionGroup>
          </section>
        </div>
      </div>

      <aside class="wc-menu__preview" aria-label="Xem trước menu trái">
        <p class="wc-menu__preview-kicker">Xem trước</p>
        <p class="wc-menu__preview-title">Menu trái thành viên sẽ thấy</p>

        <div class="wc-menu__preview-rail sidebar-surface">
          <div class="wc-menu__preview-brand">
            <img
              src="/images/congnghe/brand/vas-white.png"
              alt=""
              class="wc-menu__preview-logo"
            />
          </div>

          <div v-if="loading" class="wc-menu__preview-empty">Đang tải…</div>
          <div v-else-if="menus.length === 0" class="wc-menu__preview-empty">Chưa có mục cấu hình.</div>

          <nav v-else class="wc-menu__preview-nav" aria-hidden="true">
            <section v-for="section in previewSections" :key="section.id" class="wc-menu__preview-section">
              <p class="wc-menu__preview-section-label">{{ sectionTitleValue(section) || section.label }}</p>
              <div
                v-for="menu in section.items"
                :key="menu.menu_key"
                class="wc-menu__preview-link"
                :class="{ 'wc-menu__preview-link--hidden': !menu.is_visible }"
              >
                <span class="wc-menu__preview-link-icon">
                  <AppIcon :name="menu.icon" :size="14" :stroke-width="1.75" />
                </span>
                <span class="wc-menu__preview-link-text">{{ titleValue(menu) || menu.label }}</span>
                <span v-if="!menu.is_visible" class="wc-menu__preview-hidden">Ẩn</span>
              </div>
            </section>
          </nav>
        </div>
      </aside>
    </div>

    <Teleport to="body">
      <div
        v-if="ghost"
        class="wc-menu__ghost"
        :class="{ 'wc-menu__ghost--on': ghost.on }"
        :style="{
          width: `${ghost.width}px`,
          '--ghost-x': `${ghost.x}px`,
          '--ghost-y': `${ghost.y}px`,
        }"
      >
        <span class="wc-menu__item-icon" aria-hidden="true">
          <AppIcon :name="ghost.icon" :size="18" :stroke-width="1.75" />
        </span>
        <span class="wc-menu__ghost-label">{{ ghost.label }}</span>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.wc-menu {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow: hidden;
}

.wc-menu__toolbar {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.wc-menu__stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.wc-menu__stat {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: 0.25rem 0.625rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-menu__stat strong {
  color: var(--color-text);
  font-size: 0.8125rem;
}

.wc-menu__stat--on {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.wc-menu__stat--on strong {
  color: var(--color-success-tint-fg);
}

.wc-menu__stat--off {
  background: var(--color-surface-muted);
}

.wc-menu__toolbar-end {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.wc-menu__filters {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.wc-menu__filter {
  height: 1.75rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-menu__filter:hover {
  color: var(--color-text);
}

.wc-menu__filter--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.wc-menu__filter:focus-visible,
.wc-menu__bulk-btn:focus-visible,
.wc-menu__switch:focus-visible,
.wc-menu__title-input:focus-visible,
.wc-menu__section-input:focus-visible,
.wc-menu__reset:focus-visible {
  outline: 2px solid var(--color-info);
  outline-offset: 2px;
}

.wc-menu__bulk {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.wc-menu__bulk-btn {
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

.wc-menu__bulk-btn:hover:not(:disabled) {
  background: var(--color-surface-muted);
}

.wc-menu__bulk-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.wc-menu__main {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 16.5rem;
  gap: var(--space-4);
  overflow: hidden;
}

.wc-menu__list-wrap {
  min-width: 0;
  min-height: 0;
  overflow: auto;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.wc-menu__empty {
  margin: 0;
  padding: var(--space-8) var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.wc-menu__sections {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.wc-menu__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.wc-menu__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-menu__section-title {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: center;
  gap: var(--space-2);
}

.wc-menu__section-input {
  min-width: 0;
  flex: 1;
  height: 2rem;
  padding: 0 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.wc-menu__section-input:disabled {
  opacity: 0.7;
}

.wc-menu__section-label {
  margin: 0;
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.wc-menu__section-count {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

.wc-menu__list {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.wc-menu-sort-move {
  transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}

.wc-menu-sort-enter-active,
.wc-menu-sort-leave-active {
  transition:
    opacity 0.16s ease,
    transform 0.16s cubic-bezier(0.22, 1, 0.36, 1);
}

.wc-menu-sort-enter-from,
.wc-menu-sort-leave-to {
  opacity: 0;
  transform: scale(0.98);
}

.wc-menu-sort-leave-active {
  position: absolute;
  left: 0;
  right: 0;
}

.wc-menu__item {
  display: grid;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto;
  align-items: start;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
}

.wc-menu__item--sortable {
  grid-template-columns: 1.75rem 2.5rem minmax(0, 1fr) auto;
  cursor: grab;
}

.wc-menu__item--sortable .wc-menu__title-input {
  cursor: text;
}

.wc-menu__item--sortable .wc-menu__switch {
  cursor: pointer;
}

.wc-menu__item--saving {
  opacity: 0.7;
}

.wc-menu__item--dragging {
  opacity: 0.38;
  pointer-events: none;
  box-shadow: inset 0 0 0 1px var(--color-info);
}

.wc-menu__drag {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  align-self: stretch;
  width: 1.75rem;
  min-height: 2.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text-muted);
  cursor: grab;
  user-select: none;
  touch-action: none;
}

.wc-menu__drag .app-icon {
  pointer-events: none;
}

.wc-menu__title-input,
.wc-menu__section-input,
.wc-menu__switch {
  -webkit-user-drag: none;
}

.wc-menu--dragging,
.wc-menu--dragging * {
  cursor: grabbing;
}

.wc-menu__drop-empty {
  margin: 0;
  min-height: 2.75rem;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px dashed var(--color-border);
  transition:
    background 0.16s ease,
    box-shadow 0.16s ease;
}

.wc-menu__drop-empty--active {
  background: color-mix(in srgb, var(--color-info) 10%, var(--color-surface-muted));
  box-shadow: inset 0 0 0 1px var(--color-info);
}

.wc-menu__ghost {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  pointer-events: none;
  will-change: transform;
  transform: translate3d(var(--ghost-x), var(--ghost-y), 0) scale(1.03);
}

.wc-menu__ghost-label {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wc-menu__ghost--on .wc-menu__item-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.wc-menu__item-icon {
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

.wc-menu__item--on .wc-menu__item-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
  box-shadow: inset 0 0 0 1px var(--color-success-tint-border);
}

.wc-menu__item-body {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: var(--space-1);
}

.wc-menu__title-input {
  width: 100%;
  min-width: 0;
  height: 2.25rem;
  padding: 0 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.9375rem;
  font-weight: 600;
}

.wc-menu__title-input:disabled {
  opacity: 0.7;
}

.wc-menu__item-label {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.wc-menu__item-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text-muted);
  font-size: 0.75rem;
}

.wc-menu__reset {
  padding: 0;
  border: none;
  background: transparent;
  color: var(--color-info);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-menu__reset:hover:not(:disabled) {
  text-decoration: underline;
}

.wc-menu__reset:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.wc-menu__item-controls {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
  padding-top: 0.375rem;
}

.wc-menu__switch {
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

.wc-menu__switch--on {
  background: var(--color-success);
}

.wc-menu__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.wc-menu__switch:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-menu__switch-thumb {
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

.wc-menu__switch--on .wc-menu__switch-thumb {
  transform: translateX(1.25rem);
}

.wc-menu__preview {
  display: flex;
  min-height: 0;
  flex-direction: column;
  gap: var(--space-2);
  overflow: hidden;
}

.wc-menu__preview-kicker {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.wc-menu__preview-title {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 600;
}

.wc-menu__preview-rail {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  user-select: none;
}

.wc-menu__preview-brand {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 3.25rem;
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
}

.wc-menu__preview-logo {
  display: block;
  height: 1.5rem;
  width: auto;
  max-width: 100%;
  object-fit: contain;
}

.wc-menu__preview-nav {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
}

.wc-menu__preview-empty {
  padding: var(--space-5) var(--space-3);
  color: var(--color-sidebar-text-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.wc-menu__preview-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.wc-menu__preview-section-label {
  margin: 0 0 var(--space-1);
  padding: 0 var(--space-2);
  color: var(--color-sidebar-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.wc-menu__preview-link {
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

.wc-menu__preview-link--hidden {
  opacity: 0.42;
}

.wc-menu__preview-link-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: var(--radius-sm);
  background: var(--color-sidebar-well);
  color: var(--color-sidebar-text-muted);
}

.wc-menu__preview-link-text {
  min-width: 0;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wc-menu__preview-hidden {
  flex-shrink: 0;
  color: var(--color-sidebar-text-muted);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

@media (max-width: 960px) {
  .wc-menu__main {
    grid-template-columns: 1fr;
    grid-template-rows: minmax(0, 1fr) auto;
  }

  .wc-menu__preview {
    max-height: 16rem;
  }

  .wc-menu__preview-rail {
    min-height: 12rem;
  }
}

@media (max-width: 640px) {
  .wc-menu__toolbar-end {
    width: 100%;
  }

  .wc-menu__filters,
  .wc-menu__bulk {
    width: 100%;
  }

  .wc-menu__filter,
  .wc-menu__bulk-btn {
    flex: 1;
  }

  .wc-menu__item {
    grid-template-columns: 2.5rem minmax(0, 1fr);
  }

  .wc-menu__item--sortable {
    grid-template-columns: 1.75rem 2.5rem minmax(0, 1fr);
  }

  .wc-menu__item-controls {
    grid-column: 1 / -1;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding-top: 0;
  }

  .wc-menu__preview {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wc-menu__switch-thumb,
  .wc-menu-sort-move,
  .wc-menu-sort-enter-active,
  .wc-menu-sort-leave-active {
    transition: none;
  }

  .wc-menu__ghost {
    transform: translate3d(var(--ghost-x), var(--ghost-y), 0);
  }
}
</style>
