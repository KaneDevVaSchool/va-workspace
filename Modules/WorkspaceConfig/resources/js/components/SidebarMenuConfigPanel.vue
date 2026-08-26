<script setup>
//
// Panel cấu hình menu trái dùng chung cho tab manager (bật/tắt + đổi tên)
// và superadmin chi tiết phòng ban (chỉ xem). Nhóm theo section sidebar,
// thống kê hiện/ẩn, lọc, xem trước menu trái.
//
import { computed, reactive, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import StatusBadge from './StatusBadge.vue';
import { groupMenus, LABEL_MAX_LENGTH } from '../constants/sidebarMenus.js';
import { menuVisibilityLabel } from '../constants/departmentDetail.js';

const props = defineProps({
  menus: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'Chưa có mục menu nào có thể cấu hình.' },
  editable: { type: Boolean, default: false },
  savingKey: { type: String, default: null },
  busy: { type: Boolean, default: false },
  introEyebrow: { type: String, default: 'Menu trái' },
  introText: { type: String, required: true },
});

const emit = defineEmits(['toggle', 'show-all', 'hide-all', 'rename']);

const filter = ref('all');
const drafts = reactive({});

const visibleCount = computed(() => props.menus.filter((item) => item.is_visible).length);
const hiddenCount = computed(() => props.menus.length - visibleCount.value);

const filteredMenus = computed(() => {
  if (filter.value === 'visible') return props.menus.filter((item) => item.is_visible);
  if (filter.value === 'hidden') return props.menus.filter((item) => !item.is_visible);
  return props.menus;
});

const sections = computed(() => groupMenus(filteredMenus.value));
const previewSections = computed(() => groupMenus(props.menus));

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
</script>

<template>
  <div class="wc-menu">
    <div class="wc-menu__intro" :class="editable ? 'wc-menu__intro--info' : 'wc-menu__intro--warning'">
      <p class="wc-menu__intro-kicker">{{ introEyebrow }}</p>
      <p class="wc-menu__intro-text">{{ introText }}</p>
    </div>

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
        <p v-else-if="sections.length === 0" class="wc-menu__empty">{{ filterEmptyText }}</p>

        <div v-else class="wc-menu__sections">
          <section v-for="section in sections" :key="section.id" class="wc-menu__section">
            <header class="wc-menu__section-head">
              <h2 class="wc-menu__section-label">{{ section.label }}</h2>
              <span class="wc-menu__section-count">{{ section.items.length }} mục</span>
            </header>

            <ul class="wc-menu__list" role="list">
              <li
                v-for="menu in section.items"
                :key="menu.menu_key"
                class="wc-menu__item"
                :class="{
                  'wc-menu__item--on': menu.is_visible,
                  'wc-menu__item--off': !menu.is_visible,
                  'wc-menu__item--saving': savingKey === menu.menu_key,
                }"
              >
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

                  <p class="wc-menu__item-desc">{{ menu.description }}</p>

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
            </ul>
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
              <p class="wc-menu__preview-section-label">{{ section.label }}</p>
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

        <p v-if="!loading && menus.length > 0" class="wc-menu__preview-hint">
          Mục mờ không hiện trên menu trái. Đổi tên ở ô bên trái sẽ hiện ngay tại đây.
        </p>
      </aside>
    </div>
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

.wc-menu__intro {
  position: relative;
  flex-shrink: 0;
  padding: var(--space-3) var(--space-4) var(--space-3) calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.wc-menu__intro::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.wc-menu__intro--info::before {
  background: var(--color-info);
}

.wc-menu__intro--warning::before {
  background: var(--color-warning);
}

.wc-menu__intro-kicker {
  margin: 0 0 var(--space-1);
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.wc-menu__intro-text {
  margin: 0;
  color: var(--color-text);
  font-size: 0.875rem;
  line-height: 1.5;
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
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
  box-shadow: 0 1px 0 var(--color-border);
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
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
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

.wc-menu__item--saving {
  opacity: 0.7;
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

.wc-menu__item--off .wc-menu__item-desc {
  color: var(--color-text-muted);
}

.wc-menu__item-desc {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  line-height: 1.45;
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

.wc-menu__preview-hint {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
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
  .wc-menu__switch-thumb {
    transition: none;
  }
}
</style>
