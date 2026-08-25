<script setup>
//
// Content header teleport lên AppHeader — một hàng kiểu 1Office:
// trái = tạo mới · giữa = title · phải = search/export/actions.
//
import { computed, onBeforeUnmount, onMounted, ref, useId, useSlots, watch } from 'vue';
import { usePageHeaderTarget } from '../composables/usePageHeaderTarget';
import AppIcon from './AppIcon.vue';

const DEFAULT_EXPORT_ICONS = {
  excel: 'fileSpreadsheet',
  'excel-all': 'fileSpreadsheet',
  'excel-form': 'fileSpreadsheet',
  'excel-companies': 'building',
  'excel-levels': 'layers',
  'excel-flat': 'fileSpreadsheet',
  xls: 'fileSpreadsheet',
  xlsx: 'fileSpreadsheet',
  csv: 'fileText',
  import: 'fileUp',
  template: 'download',
  sample: 'download',
};

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  breadcrumbs: { type: Array, default: () => [] },
  description: { type: String, default: '' },
  icon: { type: String, default: '' },
  period: { type: String, default: '' },
  periodHint: { type: String, default: 'Số liệu theo tháng hiện tại' },
  exportLabel: { type: String, default: 'Export' },
  exportOptions: { type: Array, default: () => [] },
  exportBusyKey: { type: String, default: undefined },
  exportProgress: { type: Number, default: undefined },
  viewMode: { type: String, default: null },
  primaryAction: { type: Object, default: null },
  onExport: { type: Function, default: null },
});

const emit = defineEmits(['update:viewMode', 'export']);

const slots = useSlots();
const exportOpen = ref(false);
const exportRoot = ref(null);
const primaryOpen = ref(false);
const primaryRoot = ref(null);
const menuId = useId();
const primaryMenuId = useId();
const pageHeaderTarget = usePageHeaderTarget();
const teleportTo = computed(() => pageHeaderTarget?.value ?? '#app-content-header');
const canTeleport = computed(() => Boolean(pageHeaderTarget?.value));

const exportBusy = computed(() => props.exportBusyKey != null);
const hasExportOptions = computed(() => (props.exportOptions?.length ?? 0) > 0);
const hasPrimaryAction = computed(() => Boolean(props.primaryAction?.label));
const hasPrimaryMenu = computed(() => (props.primaryAction?.items?.length ?? 0) > 0);
const hasLeft = computed(() => hasPrimaryAction.value || Boolean(slots.primary));

const displayTitle = computed(() =>
  props.period ? `${props.title} (${props.period})` : props.title,
);

const titleHint = computed(() => {
  if (props.description && props.period) {
    return `${props.description} — ${props.periodHint}`;
  }
  return props.description || (props.period ? props.periodHint : '');
});

const showRight = computed(
  () =>
    props.viewMode != null ||
    hasExportOptions.value ||
    Boolean(props.onExport) ||
    Boolean(slots.actions),
);

function exportIcon(option) {
  return option.icon ?? DEFAULT_EXPORT_ICONS[option.key] ?? 'fileDown';
}

function exportButtonLabel() {
  if (!exportBusy.value) {
    return props.exportLabel;
  }
  if (props.exportProgress != null && props.exportProgress > 0) {
    return `Đang xuất… ${Math.round(props.exportProgress * 100)}%`;
  }
  return 'Đang xuất…';
}

function setViewMode(mode) {
  emit('update:viewMode', mode);
}

function onExportSelect(option) {
  exportOpen.value = false;
  option.onSelect?.();
}

function onSimpleExport() {
  props.onExport?.();
  emit('export');
}

function onPrimaryClick(event) {
  if (props.primaryAction?.disabled) {
    event.preventDefault();
    return;
  }
  if (hasPrimaryMenu.value) {
    event.preventDefault();
    primaryOpen.value = !primaryOpen.value;
    return;
  }
  const run = props.primaryAction?.onClick ?? props.primaryAction?.action;
  run?.(event);
}

function onPrimarySelect(item) {
  primaryOpen.value = false;
  item?.onSelect?.();
}

function handleDocumentClick(event) {
  if (exportOpen.value && exportRoot.value && !exportRoot.value.contains(event.target)) {
    exportOpen.value = false;
  }
  if (primaryOpen.value && primaryRoot.value && !primaryRoot.value.contains(event.target)) {
    primaryOpen.value = false;
  }
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape') {
    exportOpen.value = false;
    primaryOpen.value = false;
  }
}

watch(
  () => props.primaryAction,
  () => {
    primaryOpen.value = false;
  },
);

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
  <Teleport defer :to="teleportTo" :disabled="!canTeleport">
    <div id="header" class="page-header" data-tour="page-header">
      <div v-if="hasLeft" id="header-left-actions" class="page-header__left">
        <slot name="primary">
          <div
            v-if="hasPrimaryAction && primaryAction && hasPrimaryMenu"
            ref="primaryRoot"
            class="page-header__add-wrap"
          >
            <button
              type="button"
              class="page-header__add"
              :class="{
                'page-header__add--open': primaryOpen,
                'page-header__add--disabled': primaryAction.disabled,
              }"
              :disabled="primaryAction.disabled"
              :aria-expanded="primaryOpen"
              aria-haspopup="menu"
              :aria-controls="primaryMenuId"
              :title="primaryAction.label"
              :aria-label="primaryAction.label"
              data-tour="page-header-primary"
              @click="onPrimaryClick"
            >
              <AppIcon :name="primaryAction.icon || 'plus'" :size="18" :stroke-width="2" />
            </button>
            <div
              v-if="primaryOpen && !primaryAction.disabled"
              :id="primaryMenuId"
              role="menu"
              class="page-header__menu page-header__add-menu"
            >
              <button
                v-for="item in primaryAction.items"
                :key="item.key || item.label"
                type="button"
                role="menuitem"
                class="page-header__export-item"
                @click="onPrimarySelect(item)"
              >
                <span class="page-header__export-item-icon">
                  <AppIcon :name="item.icon || 'plus'" :size="16" :stroke-width="1.75" />
                </span>
                <span class="page-header__export-item-copy">
                  <span class="page-header__export-item-label">{{ item.label }}</span>
                  <span v-if="item.description" class="page-header__export-item-desc">{{ item.description }}</span>
                </span>
              </button>
            </div>
          </div>
          <component
            :is="primaryAction.to || primaryAction.href ? 'router-link' : 'button'"
            v-else-if="hasPrimaryAction && primaryAction"
            :to="primaryAction.to || primaryAction.href"
            :type="primaryAction.to || primaryAction.href ? undefined : 'button'"
            class="page-header__add"
            :class="{ 'page-header__add--disabled': primaryAction.disabled }"
            :disabled="primaryAction.to || primaryAction.href ? undefined : primaryAction.disabled"
            :aria-disabled="primaryAction.disabled || undefined"
            :title="primaryAction.label"
            :aria-label="primaryAction.label"
            data-tour="page-header-primary"
            @click="onPrimaryClick"
          >
            <AppIcon :name="primaryAction.icon || 'plus'" :size="18" :stroke-width="2" />
          </component>
        </slot>
      </div>

      <div id="header-title" class="page-header__title-wrap">
        <h1 v-if="!slots.title" class="page-header__title" :title="titleHint || displayTitle">
          {{ displayTitle }}
        </h1>
        <div v-else class="page-header__title">
          <h1 class="sr-only">{{ displayTitle }}</h1>
          <slot name="title" />
        </div>
        <p v-if="subtitle || slots.subtitle" class="page-header__subtitle">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>

      <div v-if="showRight" id="header-right-actions" class="page-header__right">
        <div
          v-if="viewMode != null"
          class="page-header__view"
          role="group"
          aria-label="Chế độ xem"
          data-tour="page-header-view-toggle"
        >
          <button
            type="button"
            class="page-header__view-btn"
            :class="{ 'page-header__view-btn--active': viewMode === 'list' }"
            :aria-pressed="viewMode === 'list'"
            title="Danh sách"
            @click="setViewMode('list')"
          >
            <AppIcon name="layoutList" :size="16" :stroke-width="1.75" />
            <span class="sr-only">Danh sách</span>
          </button>
          <button
            type="button"
            class="page-header__view-btn"
            :class="{ 'page-header__view-btn--active': viewMode === 'grid' }"
            :aria-pressed="viewMode === 'grid'"
            title="Lưới"
            @click="setViewMode('grid')"
          >
            <AppIcon name="layoutGrid" :size="16" :stroke-width="1.75" />
            <span class="sr-only">Lưới</span>
          </button>
        </div>

        <div v-if="hasExportOptions" ref="exportRoot" class="page-header__export" data-tour="page-header-export">
          <button
            type="button"
            class="page-header-control"
            :class="{ 'page-header-control--open': exportOpen }"
            :disabled="exportBusy"
            :aria-busy="exportBusy"
            :aria-expanded="exportOpen"
            aria-haspopup="menu"
            :aria-controls="menuId"
            @click="exportOpen = !exportOpen"
          >
            <AppIcon
              v-if="exportBusy"
              name="refresh"
              :size="16"
              :stroke-width="1.75"
              class="page-header__spin"
            />
            <AppIcon v-else name="fileDown" :size="16" :stroke-width="1.75" class="page-header-control__icon" />
            <span>{{ exportButtonLabel() }}</span>
            <AppIcon
              v-if="!exportBusy"
              name="chevronDown"
              :size="14"
              :stroke-width="1.75"
              class="page-header-control__caret"
              :class="{ 'page-header-control__caret--open': exportOpen }"
            />
          </button>

          <div v-if="exportOpen && !exportBusy" :id="menuId" role="menu" class="page-header__menu page-header__export-menu">
            <template v-for="opt in exportOptions" :key="opt.key">
              <div v-if="opt.separatorBefore" class="page-header__export-sep" role="separator" aria-hidden="true" />
              <button type="button" role="menuitem" class="page-header__export-item" @click="onExportSelect(opt)">
                <span class="page-header__export-item-icon">
                  <AppIcon :name="exportIcon(opt)" :size="16" :stroke-width="1.75" />
                </span>
                <span class="page-header__export-item-copy">
                  <span class="page-header__export-item-label">{{ opt.label }}</span>
                  <span v-if="opt.description" class="page-header__export-item-desc">{{ opt.description }}</span>
                </span>
              </button>
            </template>
          </div>
        </div>

        <button
          v-else-if="onExport"
          type="button"
          class="page-header-control"
          data-tour="page-header-export"
          @click="onSimpleExport"
        >
          <AppIcon name="fileDown" :size="16" :stroke-width="1.75" class="page-header-control__icon" />
          <span>{{ exportLabel }}</span>
        </button>

        <slot name="actions" />
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  height: 100%;
  min-width: 0;
  margin: 0;
  padding: 0;
  overflow: visible;
}

.page-header__left {
  position: relative;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 2px;
  overflow: visible;
}

.page-header__title-wrap {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  justify-content: center;
  gap: 0.0625rem;
}

.page-header__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  min-width: 0;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: 0;
}

.page-header__subtitle {
  display: flex;
  align-items: center;
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-muted);
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.3;
}

.page-header__right {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: var(--space-2);
}

.page-header__add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-primary);
  text-decoration: none;
  cursor: pointer;
}

.page-header__add:hover {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.page-header__add:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.page-header__add--open {
  background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.page-header__add--disabled {
  opacity: 0.4;
  pointer-events: none;
}

.page-header__add-wrap {
  position: relative;
}

.page-header__view {
  display: inline-flex;
  height: 2rem;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.page-header__view-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
}

.page-header__view-btn:hover:not(.page-header__view-btn--active) {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.page-header__view-btn--active {
  background: var(--color-primary);
  color: var(--color-on-primary);
}

.page-header__view-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.page-header__export {
  position: relative;
}

.page-header-control {
  display: inline-flex;
  height: 2rem;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border);
  cursor: pointer;
}

.page-header-control:hover {
  background: var(--color-surface-muted);
}

.page-header-control--open {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
  box-shadow:
    inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 35%, var(--color-border)),
    0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.page-header-control:disabled {
  opacity: 0.6;
  pointer-events: none;
  cursor: default;
}

.page-header-control:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.page-header-control__icon,
.page-header-control__caret {
  color: var(--color-text-muted);
}

.page-header-control--open .page-header-control__icon,
.page-header-control--open .page-header-control__caret {
  color: var(--color-primary);
}

.page-header-control__caret {
  opacity: 0.7;
  transition: transform 160ms ease;
}

.page-header-control__caret--open {
  transform: rotate(180deg);
}

.page-header__menu {
  position: absolute;
  z-index: 40;
  margin-top: 0.375rem;
  width: 18.5rem;
  overflow: hidden;
  padding: 0.375rem 0;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.page-header__export-menu {
  right: 0;
}

.page-header__add-menu {
  top: 100%;
  left: 0;
}

.page-header__export-sep {
  margin: 0.25rem 0;
  height: 1px;
  background: var(--color-border);
}

.page-header__export-item {
  display: flex;
  width: 100%;
  align-items: flex-start;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.page-header__export-item:hover {
  background: color-mix(in srgb, var(--color-primary) 4%, transparent);
}

.page-header__export-item-icon {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  margin-top: 2px;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-primary) 15%, transparent);
}

.page-header__export-item-copy {
  min-width: 0;
}

.page-header__export-item-label {
  display: block;
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 500;
}

.page-header__export-item-desc {
  display: block;
  margin-top: 2px;
  color: var(--color-text-muted);
  font-size: 11px;
  line-height: 1.35;
}

.page-header__spin {
  color: var(--color-primary);
  animation: page-header-spin 0.8s linear infinite;
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

@keyframes page-header-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-header__spin,
  .page-header-control__caret {
    animation: none;
    transition: none;
  }
}
</style>
