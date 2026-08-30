<script setup>
//
// Thanh công cụ bảng kiểu 1Office `table-pages`:
// tìm · bộ lọc · cỡ chữ · cột · Hiển thị a - b / n · Trang 01 / 05 · trước/sau
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';

const ZOOM_STEPS = [
  { value: 0.9, label: 'Nhỏ' },
  { value: 1, label: 'Vừa' },
  { value: 1.15, label: 'Lớn' },
];

const props = defineProps({
  from: { type: Number, default: 0 },
  to: { type: Number, default: 0 },
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  lastPage: { type: Number, default: 1 },
  perPage: { type: Number, default: 20 },
  perPageOptions: { type: Array, default: () => [20, 50, 100] },
  zoom: { type: Number, default: 1 },
  placement: { type: String, default: 'bottom' },
  showSearch: { type: Boolean, default: false },
  showClearFilters: { type: Boolean, default: false },
  filtersActive: { type: Boolean, default: false },
  extraMenuLabel: { type: String, default: '' },
  extraMenuTitle: { type: String, default: '' },
  extraMenuIcon: { type: String, default: '' },
  extraMenuActive: { type: Boolean, default: false },
  pagingOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:page', 'update:perPage', 'update:zoom', 'search', 'clear-filters']);

const open = ref(null); // 'filters' | 'zoom' | 'settings' | 'extra' | 'page'
const root = ref(null);

const last = computed(() => Math.max(props.lastPage || 1, 1));
const current = computed(() => Math.min(Math.max(props.page || 1, 1), last.value));
const pages = computed(() => Array.from({ length: last.value }, (_, i) => i + 1));
const dropDown = computed(() => props.placement === 'top');

const rangeLabel = computed(() => {
  if (!props.total) {
    return 'Hiển thị 0 - 0 / 0 bản ghi';
  }
  return `Hiển thị ${props.from || 0} - ${props.to || 0} / ${props.total} bản ghi`;
});

function pad2(n) {
  return String(n).padStart(2, '0');
}

function toggle(id) {
  open.value = open.value === id ? null : id;
}

function goPage(page) {
  if (page < 1 || page > last.value || page === current.value) return;
  open.value = null;
  emit('update:page', page);
}

function setPerPage(value) {
  open.value = null;
  emit('update:perPage', value);
}

function setZoom(value) {
  open.value = null;
  emit('update:zoom', value);
}

function onSearch() {
  open.value = null;
  emit('search');
}

function onClearFilters() {
  open.value = null;
  emit('clear-filters');
}

function closeMenus() {
  open.value = null;
}

function onDocumentClick(event) {
  if (!open.value || !root.value) return;
  if (!root.value.contains(event.target)) {
    open.value = null;
  }
}

function onDocumentKey(event) {
  if (event.key === 'Escape') open.value = null;
}

onMounted(() => {
  document.addEventListener('mousedown', onDocumentClick);
  document.addEventListener('keydown', onDocumentKey);
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKey);
});
</script>

<template>
  <div
    ref="root"
    class="table-pages"
    :class="{ 'table-pages--top': dropDown, 'table-pages--bottom': !dropDown }"
  >
    <div class="table-pages__group">
      <template v-if="!pagingOnly">
      <button
        v-if="showSearch"
        type="button"
        class="table-pages__icon"
        @click="onSearch"
      >
        <AppIcon name="search" :size="15" :stroke-width="1.75" />
        <span>Tìm</span>
      </button>

      <button
        v-if="showClearFilters"
        type="button"
        class="table-pages__icon"
        @click="onClearFilters"
      >
        <span>Xoá lọc</span>
      </button>

      <div v-if="$slots.filters" class="table-pages__pop">
        <button
          type="button"
          class="table-pages__icon"
          :class="{ 'table-pages__icon--on': open === 'filters' || filtersActive }"
          aria-haspopup="menu"
          :aria-expanded="open === 'filters'"
          @click="toggle('filters')"
        >
          <AppIcon name="sliders" :size="15" :stroke-width="1.75" />
          <span>Bộ lọc</span>
        </button>
        <div v-if="open === 'filters'" class="table-pages__menu" role="menu">
          <p class="table-pages__menu-title">Chọn bộ lọc hiện trên trang</p>
          <slot name="filters" />
        </div>
      </div>

      <div class="table-pages__pop">
        <button
          type="button"
          class="table-pages__icon"
          :class="{ 'table-pages__icon--on': open === 'zoom' }"
          aria-haspopup="menu"
          :aria-expanded="open === 'zoom'"
          aria-label="Phóng to, thu nhỏ"
          @click="toggle('zoom')"
        >
          <AppIcon name="minimize" :size="15" :stroke-width="1.75" />
          <span>Cỡ chữ</span>
        </button>
        <div v-if="open === 'zoom'" class="table-pages__menu" role="menu">
          <p class="table-pages__menu-title">Phóng to, thu nhỏ</p>
          <button
            v-for="step in ZOOM_STEPS"
            :key="step.value"
            type="button"
            class="table-pages__item"
            :class="{ 'table-pages__item--on': zoom === step.value }"
            role="menuitem"
            @click="setZoom(step.value)"
          >
            {{ step.label }}
          </button>
        </div>
      </div>

      <div class="table-pages__pop">
        <button
          type="button"
          class="table-pages__icon"
          :class="{ 'table-pages__icon--on': open === 'settings' }"
          aria-haspopup="menu"
          :aria-expanded="open === 'settings'"
          @click="toggle('settings')"
        >
          <AppIcon name="columns" :size="15" :stroke-width="1.75" />
          <span>Cột</span>
        </button>
        <div v-if="open === 'settings'" class="table-pages__menu" role="menu">
          <p class="table-pages__menu-title">Cài đặt danh sách</p>
          <slot name="settings" />
        </div>
      </div>

      <div v-if="$slots.extra" class="table-pages__pop">
        <button
          type="button"
          class="table-pages__icon"
          :class="{ 'table-pages__icon--on': open === 'extra' || extraMenuActive }"
          aria-haspopup="menu"
          :aria-expanded="open === 'extra'"
          @click="toggle('extra')"
        >
          <AppIcon v-if="extraMenuIcon" :name="extraMenuIcon" :size="15" :stroke-width="1.75" />
          <span>{{ extraMenuLabel }}</span>
        </button>
        <div
          v-if="open === 'extra'"
          class="table-pages__menu"
          :class="{ 'table-pages__menu--options': !extraMenuTitle }"
          role="menu"
        >
          <p v-if="extraMenuTitle" class="table-pages__menu-title">{{ extraMenuTitle }}</p>
          <slot name="extra" :close="closeMenus" />
        </div>
      </div>
      </template>

      <p class="table-pages__title">{{ rangeLabel }}</p>

      <div class="table-pages__pop">
        <button
          type="button"
          class="table-pages__page"
          :class="{ 'table-pages__icon--on': open === 'page' }"
          aria-haspopup="menu"
          :aria-expanded="open === 'page'"
          @click="toggle('page')"
        >
          Trang: {{ pad2(current) }}
          <span>/</span>
          {{ pad2(last) }}
          <AppIcon name="chevronDown" :size="14" :stroke-width="1.75" />
        </button>
        <div v-if="open === 'page'" class="table-pages__menu table-pages__menu--page" role="menu">
          <p class="table-pages__menu-title">Chọn trang</p>
          <div class="table-pages__pages hide-scrollbar">
            <button
              v-for="n in pages"
              :key="n"
              type="button"
              class="table-pages__item"
              :class="{ 'table-pages__item--on': n === current }"
              role="menuitem"
              @click="goPage(n)"
            >
              Trang {{ pad2(n) }}
            </button>
          </div>
          <p class="table-pages__menu-title">Số dòng mỗi trang</p>
          <button
            v-for="n in perPageOptions"
            :key="n"
            type="button"
            class="table-pages__item"
            :class="{ 'table-pages__item--on': n === perPage }"
            role="menuitem"
            @click="setPerPage(n)"
          >
            {{ n }} dòng
          </button>
        </div>
      </div>

      <button
        type="button"
        class="table-pages__nav"
        :disabled="current <= 1"
        aria-label="Trang trước"
        @click="goPage(current - 1)"
      >
        <AppIcon name="chevronLeft" :size="16" :stroke-width="1.75" />
        <span>Trước</span>
      </button>
      <button
        type="button"
        class="table-pages__nav"
        :disabled="current >= last"
        aria-label="Trang sau"
        @click="goPage(current + 1)"
      >
        <span>Sau</span>
        <AppIcon name="chevronRight" :size="16" :stroke-width="1.75" />
      </button>
    </div>

    <div class="table-pages__spacer" />

    <div v-if="$slots.actions" class="table-pages__group">
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.table-pages {
  position: relative;
  z-index: 8;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 2.25rem;
  padding: 0.25rem 0;
}

.table-pages--top {
  box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.table-pages--bottom {
  box-shadow: 0 -1px 0 var(--color-border);
}

.table-pages__group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px;
  min-width: 0;
}

.table-pages__spacer {
  flex: 1;
  min-width: 0.5rem;
}

.table-pages__pop {
  position: relative;
}

.table-pages__icon,
.table-pages__nav,
.table-pages__page,
:slotted(.table-pages__icon) {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  height: 1.75rem;
  padding: 0 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
}

.table-pages__icon:hover,
.table-pages__nav:hover:not(:disabled),
.table-pages__page:hover,
:slotted(.table-pages__icon):hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

.table-pages__icon--on {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.table-pages__nav:disabled {
  opacity: 0.35;
  cursor: default;
}

.table-pages__title {
  margin: 0 0.35rem;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.2;
  white-space: nowrap;
}

.table-pages__page span {
  color: var(--color-text-muted);
}

.table-pages__menu {
  position: absolute;
  left: 0;
  z-index: 30;
  width: 16rem;
  max-height: 18rem;
  overflow: auto;
  padding: var(--space-3);
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
  scrollbar-width: none;
}

.table-pages--bottom .table-pages__menu {
  bottom: calc(100% + 0.375rem);
}

.table-pages--top .table-pages__menu {
  top: calc(100% + 0.375rem);
}

.table-pages__menu::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.table-pages__menu--page {
  width: 12.5rem;
}

.table-pages__menu--options {
  width: 13.5rem;
  padding: var(--space-2);
}

.table-pages__pages {
  max-height: 10rem;
  overflow: auto;
  margin-bottom: var(--space-2);
}

.table-pages__menu-title {
  margin: 0 0 var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
}

.table-pages__item,
:slotted(.table-pages__item) {
  display: flex;
  width: 100%;
  align-items: center;
  padding: 0.375rem 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  text-align: left;
  cursor: pointer;
}

.table-pages__item:hover,
:slotted(.table-pages__item:hover) {
  background: var(--color-surface-muted);
}

.table-pages__item--on,
:slotted(.table-pages__item--on) {
  color: var(--color-primary);
  font-weight: 600;
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

@media (max-width: 768px) {
  .table-pages {
    flex-wrap: wrap;
  }

  .table-pages__title {
    width: 100%;
    margin: 0.125rem 0;
  }
}
</style>
