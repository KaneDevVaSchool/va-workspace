<script setup>
//
// Cấu hình tiện ích nhanh Dashboard — danh sách chỉnh bên trái, xem trước
// song song giống /dashboard/me (desktop + mobile compact).
//
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import QuickUtilitiesGrid from '@modules/Dashboard/resources/js/components/QuickUtilitiesGrid.vue';

const props = defineProps({
  utilities: { type: Array, default: () => [] },
  previewItems: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  activeKey: { type: String, default: null },
});

const emit = defineEmits([
  'toggle',
  'edit',
  'delete',
  'move',
  'show-all',
  'hide-all',
  'highlight',
  'create',
]);

const filter = ref('all');
const previewMode = ref('desktop');
const openMenuKey = ref(null);
const menuRootRef = ref(null);

const visibleCount = computed(() => props.utilities.filter((item) => item.is_visible).length);
const hiddenCount = computed(() => props.utilities.length - visibleCount.value);

const filteredUtilities = computed(() => {
  if (filter.value === 'visible') return props.utilities.filter((item) => item.is_visible);
  if (filter.value === 'hidden') return props.utilities.filter((item) => !item.is_visible);
  return props.utilities;
});

const filterEmptyText = computed(() => {
  if (filter.value === 'visible') return 'Không có tiện ích nào đang hiện.';
  if (filter.value === 'hidden') return 'Không có tiện ích nào đang ẩn.';
  return 'Chưa có tiện ích nào. Thêm lối tắt để hiển thị trên Dashboard của tôi.';
});

const canShowAll = computed(() => hiddenCount.value > 0 && !props.busy);
const canHideAll = computed(() => visibleCount.value > 0 && !props.busy);

const previewCompact = computed(() => previewMode.value === 'mobile');

function linkSummary(item) {
  if (item.link_type === 'none' || !item.link_target) return 'Chưa gắn liên kết';
  if (item.link_type === 'route') return `Route: ${item.link_target}`;
  return `URL: ${item.link_target}`;
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
          <strong>{{ utilities.length }}</strong> tiện ích
        </span>
        <span class="dqu-panel__stat dqu-panel__stat--ok">
          <strong>{{ visibleCount }}</strong> đang hiện
        </span>
        <span v-if="hiddenCount" class="dqu-panel__stat dqu-panel__stat--muted">
          <strong>{{ hiddenCount }}</strong> đang ẩn
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
            Đang ẩn
          </button>
        </div>

        <div class="dqu-panel__bulk">
          <button type="button" class="dqu-panel__bulk-btn" :disabled="!canShowAll" @click="emit('show-all')">
            Hiện tất cả
          </button>
          <button type="button" class="dqu-panel__bulk-btn" :disabled="!canHideAll" @click="emit('hide-all')">
            Ẩn tất cả
          </button>
        </div>
      </div>
    </div>

    <div class="dqu-panel__main">
      <div class="dqu-panel__list-wrap hide-scrollbar">
        <p v-if="loading && !utilities.length" class="dqu-panel__empty">Đang tải…</p>
        <p v-else-if="!utilities.length" class="dqu-panel__empty">
          {{ filterEmptyText }}
          <button type="button" class="dqu-panel__empty-btn" @click="emit('create')">Thêm tiện ích đầu tiên</button>
        </p>
        <p v-else-if="!filteredUtilities.length" class="dqu-panel__empty">{{ filterEmptyText }}</p>

        <ul v-else ref="menuRootRef" class="dqu-panel__list">
          <li
            v-for="(item, index) in filteredUtilities"
            :key="item.utility_key"
            class="dqu-panel__row"
            :class="{
              'dqu-panel__row--hidden': !item.is_visible,
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
              <p class="dqu-panel__title">{{ item.label }}</p>
              <p class="dqu-panel__meta">
                <span>{{ item.utility_key }}</span>
                <span> · {{ linkSummary(item) }}</span>
              </p>
            </div>

            <div class="dqu-panel__controls">
              <button
                type="button"
                class="dqu-panel__switch"
                :class="{ 'dqu-panel__switch--on': item.is_visible }"
                role="switch"
                :aria-checked="item.is_visible"
                :disabled="busy"
                :aria-label="item.is_visible ? `Ẩn ${item.label}` : `Hiện ${item.label}`"
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
                    Sửa
                  </button>
                  <button type="button" role="menuitem" class="dqu-panel__menu-item" @click="emit('highlight', item.utility_key)">
                    Xem trên bản xem trước
                  </button>
                  <button
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

      <aside class="dqu-panel__preview" aria-label="Xem trước Dashboard của tôi">
        <div class="dqu-panel__preview-head">
          <div>
            <p class="dqu-panel__preview-kicker">Xem trước</p>
            <p class="dqu-panel__preview-title">Giống trên Dashboard của tôi</p>
          </div>
          <div class="dqu-panel__preview-modes" role="group" aria-label="Chế độ xem trước">
            <button
              type="button"
              class="dqu-panel__mode"
              :class="{ 'dqu-panel__mode--active': previewMode === 'desktop' }"
              :aria-pressed="previewMode === 'desktop'"
              @click="previewMode = 'desktop'"
            >
              Desktop
            </button>
            <button
              type="button"
              class="dqu-panel__mode"
              :class="{ 'dqu-panel__mode--active': previewMode === 'mobile' }"
              :aria-pressed="previewMode === 'mobile'"
              @click="previewMode = 'mobile'"
            >
              Mobile
            </button>
          </div>
        </div>

        <div
          class="dqu-panel__preview-frame"
          :class="{ 'dqu-panel__preview-frame--mobile': previewCompact }"
        >
          <section class="dqu-panel__preview-utilities">
            <div class="dqu-panel__preview-util-head">
              <div>
                <h3>Tiện ích nhanh</h3>
                <p>Lối tắt do quản trị hệ thống cấu hình</p>
              </div>
              <AppIcon name="sparkles" :size="16" class="dqu-panel__preview-spark" aria-hidden="true" />
            </div>

            <QuickUtilitiesGrid
              :items="previewItems"
              :loading="loading"
              :compact="previewCompact"
              preview
              :active-key="activeKey"
            />

            <p v-if="!loading && !previewItems.length" class="dqu-panel__preview-empty">
              Không có tiện ích nào đang hiện. Bật hiển thị hoặc thêm mục mới.
            </p>
          </section>
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
  grid-template-columns: minmax(0, 1fr) min(22rem, 38vw);
  gap: var(--space-4);
  overflow: hidden;
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
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
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
  min-height: 0;
  gap: var(--space-3);
}

.dqu-panel__preview-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  flex-shrink: 0;
}

.dqu-panel__preview-kicker {
  margin: 0;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.dqu-panel__preview-title {
  margin: 0.15rem 0 0;
  font-size: 0.875rem;
  font-weight: 700;
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
  padding: 0 0.5rem;
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
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface-muted);
}

.dqu-panel__preview-frame--mobile {
  max-width: 22rem;
  margin-inline: auto;
  padding: var(--space-3);
  border-radius: 1.25rem;
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-md);
}

.dqu-panel__preview-utilities {
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: linear-gradient(180deg, var(--color-surface) 0%, var(--color-surface-muted) 140%);
  box-shadow: inset 0 0 0 1px var(--color-border), var(--shadow-sm);
}

.dqu-panel__preview-util-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.dqu-panel__preview-util-head h3 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
}

.dqu-panel__preview-util-head p {
  margin: 0.25rem 0 0;
  font-size: 0.75rem;
  color: var(--color-text-muted);
}

.dqu-panel__preview-spark {
  color: var(--color-gold);
  flex-shrink: 0;
}

.dqu-panel__preview-empty {
  margin: var(--space-3) 0 0;
  font-size: 0.75rem;
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
