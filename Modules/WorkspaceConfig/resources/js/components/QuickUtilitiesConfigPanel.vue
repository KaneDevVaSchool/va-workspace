<script setup>
//
// Cấu hình tiện ích nhanh Dashboard — danh sách chỉnh bên trái, xem trước
// song song giao diện nhân viên trên điện thoại.
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import DashboardQuickUtilitiesPreview from '@modules/Dashboard/resources/js/components/DashboardQuickUtilitiesPreview.vue';

const props = defineProps({
  utilities: { type: Array, default: () => [] },
  previewItems: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  activeKey: { type: String, default: null },
  allowCreate: { type: Boolean, default: false },
});

const emit = defineEmits([
  'toggle',
  'toggle-maintenance',
  'edit',
  'delete',
  'move',
  'show-all',
  'hide-all',
  'highlight',
  'create',
]);

const filter = ref('all');
const previewMode = ref('mobile-home');
const openMenuKey = ref(null);
const menuRootRef = ref(null);

const visibleCount = computed(() => props.utilities.filter((item) => item.is_visible).length);
const hiddenCount = computed(() => props.utilities.length - visibleCount.value);
const maintenanceCount = computed(() => props.utilities.filter((item) => item.is_under_maintenance).length);

const filteredUtilities = computed(() => {
  if (filter.value === 'visible') return props.utilities.filter((item) => item.is_visible);
  if (filter.value === 'hidden') return props.utilities.filter((item) => !item.is_visible);
  if (filter.value === 'maintenance') return props.utilities.filter((item) => item.is_under_maintenance);
  return props.utilities;
});

const filterEmptyText = computed(() => {
  if (filter.value === 'visible') return 'Chưa bật hiển thị mục nào.';
  if (filter.value === 'hidden') return 'Không có mục nào đang tắt hiển thị.';
  if (filter.value === 'maintenance') return 'Không có mục nào đang bảo trì.';
  return 'Chưa có lối tắt nào trong hệ thống.';
});

const canShowAll = computed(() => hiddenCount.value > 0 && !props.busy);
const canHideAll = computed(() => visibleCount.value > 0 && !props.busy);

const previewModeLabels = {
  'mobile-home': 'Trang chủ',
  'mobile-all': 'Xem tất cả',
};

function linkSummary(item) {
  if (item.is_under_maintenance) return 'Đang bảo trì — nhân viên không mở được';
  if (item.link_type === 'none' || !item.link_target) return 'Chưa chọn trang mở ra khi bấm';
  if (item.link_type === 'route') return `Mở trang trong app: ${item.link_target}`;
  return `Mở website: ${item.link_target}`;
}

function toggleMenu(key, event) {
  event.stopPropagation();
  openMenuKey.value = openMenuKey.value === key ? null : key;
}

function closeMenu() {
  openMenuKey.value = null;
}

function runMenuAction(action, item) {
  closeMenu();
  emit(action, item);
}

function onDocumentClick(event) {
  if (!openMenuKey.value || !menuRootRef.value) return;
  if (menuRootRef.value.contains(event.target)) return;
  closeMenu();
}

function onDocumentKeydown(event) {
  if (event.key === 'Escape' && openMenuKey.value) closeMenu();
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  document.removeEventListener('keydown', onDocumentKeydown);
});
</script>

<template>
  <div class="dqu-panel">
    <div class="dqu-panel__toolbar">
      <div class="dqu-panel__stats">
        <span class="dqu-panel__stat">
          <strong>{{ utilities.length }}</strong> lối tắt
        </span>
        <span class="dqu-panel__stat dqu-panel__stat--ok">
          <strong>{{ visibleCount }}</strong> đang hiện cho nhân viên
        </span>
        <span v-if="hiddenCount" class="dqu-panel__stat dqu-panel__stat--muted">
          <strong>{{ hiddenCount }}</strong> đang tắt
        </span>
        <span v-if="maintenanceCount" class="dqu-panel__stat dqu-panel__stat--warn">
          <strong>{{ maintenanceCount }}</strong> bảo trì
        </span>
      </div>

      <div class="dqu-panel__toolbar-actions">
        <div class="dqu-panel__filters" role="group" aria-label="Lọc danh sách">
          <button
            type="button"
            class="dqu-panel__filter"
            :class="{ 'dqu-panel__filter--active': filter === 'all' }"
            :aria-pressed="filter === 'all'"
            @click="filter = 'all'"
          >
            Tất cả
          </button>
          <button
            type="button"
            class="dqu-panel__filter"
            :class="{ 'dqu-panel__filter--active': filter === 'visible' }"
            :aria-pressed="filter === 'visible'"
            @click="filter = 'visible'"
          >
            Đang hiện
          </button>
          <button
            type="button"
            class="dqu-panel__filter"
            :class="{ 'dqu-panel__filter--active': filter === 'hidden' }"
            :aria-pressed="filter === 'hidden'"
            @click="filter = 'hidden'"
          >
            Đang tắt
          </button>
          <button
            type="button"
            class="dqu-panel__filter"
            :class="{ 'dqu-panel__filter--active': filter === 'maintenance' }"
            :aria-pressed="filter === 'maintenance'"
            @click="filter = 'maintenance'"
          >
            Bảo trì
          </button>
        </div>

        <div class="dqu-panel__bulk">
          <button type="button" class="dqu-panel__bulk-btn" :disabled="!canShowAll" @click="emit('show-all')">
            Bật hiển thị tất cả
          </button>
          <button type="button" class="dqu-panel__bulk-btn" :disabled="!canHideAll" @click="emit('hide-all')">
            Tắt hiển thị tất cả
          </button>
        </div>
      </div>
    </div>

    <div class="dqu-panel__main">
      <div class="dqu-panel__list-wrap hide-scrollbar">
        <p v-if="loading && !utilities.length" class="dqu-panel__empty">Đang tải…</p>
        <p v-else-if="!utilities.length" class="dqu-panel__empty">
          {{ filterEmptyText }}
          <button
            v-if="allowCreate"
            type="button"
            class="dqu-panel__empty-btn"
            @click="emit('create')"
          >
            Thêm tiện ích đầu tiên
          </button>
        </p>
        <p v-else-if="!filteredUtilities.length" class="dqu-panel__empty">{{ filterEmptyText }}</p>

        <ul v-else ref="menuRootRef" class="dqu-panel__list">
          <li
            v-for="(item, index) in filteredUtilities"
            :key="item.utility_key"
            class="dqu-panel__row"
            :class="{
              'dqu-panel__row--hidden': !item.is_visible,
              'dqu-panel__row--maintenance': item.is_under_maintenance,
              'dqu-panel__row--active': activeKey === item.utility_key,
            }"
            :data-utility-key="item.utility_key"
          >
            <div class="dqu-panel__order">
              <button
                type="button"
                class="dqu-panel__order-btn"
                aria-label="Lên"
                :disabled="busy || filter !== 'all' || index === 0"
                @click="emit('move', item, -1)"
              >
                <AppIcon name="chevronsUp" :size="16" />
              </button>
              <button
                type="button"
                class="dqu-panel__order-btn"
                aria-label="Xuống"
                :disabled="busy || filter !== 'all' || index === filteredUtilities.length - 1"
                @click="emit('move', item, 1)"
              >
                <AppIcon name="chevronsDown" :size="16" />
              </button>
            </div>

            <span class="dqu-panel__icon" :class="`dqu-panel__icon--${item.tone}`">
              <AppIcon :name="item.icon || 'layoutGrid'" :size="20" />
            </span>

            <div class="dqu-panel__body">
              <p class="dqu-panel__title">
                {{ item.label }}
                <span v-if="item.is_under_maintenance" class="dqu-panel__pill">Bảo trì</span>
              </p>
              <p class="dqu-panel__meta">{{ linkSummary(item) }}</p>
            </div>

            <div class="dqu-panel__controls">
              <button
                type="button"
                class="dqu-panel__switch"
                :class="{ 'dqu-panel__switch--on': item.is_visible }"
                role="switch"
                :aria-checked="item.is_visible"
                :disabled="busy"
                :aria-label="item.is_visible ? `Tắt hiển thị ${item.label}` : `Bật hiển thị ${item.label}`"
                @click="emit('toggle', item)"
              >
                <span class="dqu-panel__switch-thumb" />
              </button>

              <div class="dqu-panel__menu">
                <button
                  type="button"
                  class="dqu-panel__menu-trigger"
                  aria-label="Thao tác"
                  :aria-expanded="openMenuKey === item.utility_key"
                  :disabled="busy"
                  @click="toggleMenu(item.utility_key, $event)"
                >
                  <AppIcon name="moreVertical" :size="18" />
                </button>
                <div
                  v-if="openMenuKey === item.utility_key"
                  class="dqu-panel__menu-pop"
                  role="menu"
                >
                  <button type="button" role="menuitem" class="dqu-panel__menu-item" @click="runMenuAction('edit', item)">
                    Sửa thông tin
                  </button>
                  <button
                    type="button"
                    role="menuitem"
                    class="dqu-panel__menu-item"
                    @click="runMenuAction('toggle-maintenance', item)"
                  >
                    {{ item.is_under_maintenance ? 'Tắt bảo trì' : 'Bật bảo trì' }}
                  </button>
                  <button type="button" role="menuitem" class="dqu-panel__menu-item" @click="emit('highlight', item.utility_key)">
                    Xem trên hình minh hoạ
                  </button>
                  <button
                    v-if="!item.is_catalog_fixed"
                    type="button"
                    role="menuitem"
                    class="dqu-panel__menu-item dqu-panel__menu-item--danger"
                    @click="runMenuAction('delete', item)"
                  >
                    Xoá
                  </button>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <aside class="dqu-panel__preview" aria-label="Hình minh hoạ giao diện nhân viên">
        <div class="dqu-panel__preview-head">
          <div class="dqu-panel__preview-modes" role="group" aria-label="Chọn màn hình xem thử">
            <button
              v-for="(label, key) in previewModeLabels"
              :key="key"
              type="button"
              class="dqu-panel__mode"
              :class="{ 'dqu-panel__mode--active': previewMode === key }"
              :aria-pressed="previewMode === key"
              @click="previewMode = key"
            >
              {{ label }}
            </button>
          </div>
        </div>

        <div class="dqu-panel__preview-frame hide-scrollbar">
          <DashboardQuickUtilitiesPreview
            v-if="loading || previewItems.length"
            :mode="previewMode"
            :utilities="previewItems"
            :loading="loading"
            :active-utility-key="activeKey"
          />
          <p v-else class="dqu-panel__preview-empty">
            Chưa có lối tắt nào đang hiện. Bật công tắc hiển thị hoặc thêm mục mới.
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.dqu-panel {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  gap: var(--space-3);
}

.dqu-panel__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dqu-panel__stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
}

.dqu-panel__stat strong {
  color: var(--color-text);
  font-weight: 700;
}

.dqu-panel__stat--ok strong {
  color: var(--color-success, #15803d);
}

.dqu-panel__stat--warn strong {
  color: var(--color-warning, #b45309);
}

.dqu-panel__toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
}

.dqu-panel__filters {
  display: inline-flex;
  padding: 2px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dqu-panel__filter {
  height: 1.75rem;
  padding: 0 0.625rem;
  border: none;
  border-radius: calc(var(--radius-sm) - 2px);
  background: transparent;
  font-family: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
}

.dqu-panel__filter--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.dqu-panel__bulk {
  display: flex;
  gap: var(--space-2);
}

.dqu-panel__bulk-btn {
  height: 2rem;
  padding: 0 0.75rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
  cursor: pointer;
}

.dqu-panel__bulk-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dqu-panel__main {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(28rem, 1.15fr);
  gap: var(--space-4);
  overflow: hidden;
}

@media (min-width: 1280px) {
  .dqu-panel__main {
    grid-template-columns: minmax(0, 0.88fr) minmax(36rem, 1.12fr);
  }
}

.dqu-panel__list-wrap {
  min-width: 0;
  min-height: 0;
  overflow: auto;
  padding: var(--space-3);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.dqu-panel__empty {
  margin: 0;
  padding: var(--space-8) var(--space-4);
  text-align: center;
  font-size: 0.875rem;
  color: var(--color-text-muted);
}

.dqu-panel__empty-btn {
  display: block;
  margin: var(--space-3) auto 0;
  height: 2rem;
  padding: 0 0.875rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  color: #fff;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.dqu-panel__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.dqu-panel__row {
  display: grid;
  grid-template-columns: auto auto minmax(0, 1fr) auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
  transition: box-shadow 0.15s ease;
}

.dqu-panel__row--hidden {
  opacity: 0.72;
}

.dqu-panel__row--maintenance:not(.dqu-panel__row--hidden) {
  box-shadow:
    inset 3px 0 0 var(--color-warning, #d97706),
    var(--shadow-sm);
}

.dqu-panel__row--active {
  box-shadow:
    inset 0 0 0 2px var(--color-primary),
    var(--shadow-sm);
}

.dqu-panel__order {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dqu-panel__order-btn {
  display: grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.dqu-panel__order-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.dqu-panel__icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
}

.dqu-panel__icon--primary {
  background: var(--color-primary-surface);
  color: var(--color-primary);
}
.dqu-panel__icon--secondary {
  background: var(--color-secondary-surface);
  color: var(--color-secondary);
}
.dqu-panel__icon--tertiary {
  background: var(--color-tertiary-surface);
  color: var(--color-tertiary);
}
.dqu-panel__icon--gold {
  background: var(--color-gold-surface);
  color: var(--color-gold);
}
.dqu-panel__icon--muted {
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.dqu-panel__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
}

.dqu-panel__pill {
  padding: 0.125rem 0.375rem;
  border-radius: var(--radius-full);
  background: var(--color-warning-tint-bg, #fef3c7);
  color: var(--color-warning, #b45309);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.dqu-panel__meta {
  margin: 0.15rem 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dqu-panel__controls {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.dqu-panel__switch {
  position: relative;
  width: 2.25rem;
  height: 1.25rem;
  padding: 0;
  border: none;
  border-radius: 999px;
  background: var(--color-border);
  cursor: pointer;
  flex-shrink: 0;
}

.dqu-panel__switch--on {
  background: var(--color-primary);
}

.dqu-panel__switch:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dqu-panel__switch-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: calc(1.25rem - 4px);
  height: calc(1.25rem - 4px);
  border-radius: 50%;
  background: #fff;
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease;
}

.dqu-panel__switch--on .dqu-panel__switch-thumb {
  transform: translateX(1rem);
}

.dqu-panel__menu {
  position: relative;
}

.dqu-panel__menu-trigger {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  cursor: pointer;
}

.dqu-panel__menu-trigger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dqu-panel__menu-pop {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 20;
  min-width: 10.5rem;
  padding: var(--space-1);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
}

.dqu-panel__menu-item {
  display: block;
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  text-align: left;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
}

.dqu-panel__menu-item:hover {
  background: var(--color-surface-muted);
}

.dqu-panel__menu-item--danger {
  color: var(--color-danger);
}

.dqu-panel__preview {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
  gap: var(--space-3);
  overflow: hidden;
}

.dqu-panel__preview-head {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-shrink: 0;
}

.dqu-panel__preview-modes {
  display: inline-flex;
  padding: 2px;
  border-radius: var(--radius-sm);
  background: var(--color-surface-muted);
  box-shadow: inset 0 0 0 1px var(--color-border);
  flex-shrink: 0;
}

.dqu-panel__mode {
  height: 1.625rem;
  padding: 0 0.4375rem;
  border: none;
  border-radius: calc(var(--radius-sm) - 2px);
  background: transparent;
  font-family: inherit;
  font-size: 0.6875rem;
  font-weight: 700;
  color: var(--color-text-muted);
  cursor: pointer;
}

.dqu-panel__mode--active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: var(--shadow-sm);
}

.dqu-panel__preview-frame {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-2);
  border-radius: var(--radius-lg);
  background: var(--color-bg);
  box-shadow: inset 0 0 0 1px var(--color-border);
}

.dqu-panel__preview-empty {
  margin: 0;
  padding: var(--space-8) var(--space-4);
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  text-align: center;
}

@media (max-width: 960px) {
  .dqu-panel__main {
    grid-template-columns: 1fr;
    overflow: auto;
  }

  .dqu-panel__list-wrap {
    max-height: 28rem;
  }

  .dqu-panel__preview-frame {
    flex: none;
  }
}

@media (max-width: 640px) {
  .dqu-panel__row {
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-areas:
      'icon body'
      'order controls';
  }

  .dqu-panel__icon {
    grid-area: icon;
  }

  .dqu-panel__body {
    grid-area: body;
  }

  .dqu-panel__order {
    grid-area: order;
    flex-direction: row;
  }

  .dqu-panel__controls {
    grid-area: controls;
    justify-content: flex-end;
  }
}
</style>
