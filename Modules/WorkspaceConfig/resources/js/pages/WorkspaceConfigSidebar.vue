<script setup>
//
// manager/workspace-config/sidebar — bật/tắt mục sidebar (menu trái) áp dụng
// cho cả phòng ban. Đổi 1 mục = ghi ngay; patch auth.hidden_menu_keys để
// sidebar đổi tức thì. Không đụng tới tab trong hub Cấu hình phòng ban.
//
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import StatusBadge from '../components/StatusBadge.vue';

const MENU_ICONS = {
  home: 'dashboard',
};

const hub = inject('workspaceConfigHub', null);
const auth = useAuthStore();
const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const menus = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);

const visibleCount = computed(() => menus.value.filter((item) => item.is_visible).length);

function menuIcon(menuKey) {
  return MENU_ICONS[menuKey] || 'layoutList';
}

function applyVisibility(menuKey, isVisible) {
  const idx = menus.value.findIndex((item) => item.menu_key === menuKey);
  if (idx !== -1) {
    menus.value[idx].is_visible = isVisible;
  }
  auth.setMenuKeyVisible(menuKey, isVisible);
}

async function loadMenus() {
  if (!hasDepartment.value) {
    menus.value = [];
    return;
  }

  isLoading.value = true;
  try {
    const { data } = await window.axios.get('/api/workspace-config/sidebar');
    menus.value = (data.menus ?? []).map((item) => ({ ...item }));
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được cấu hình menu.');
  } finally {
    isLoading.value = false;
  }
}

async function toggle(menu) {
  if (savingKey.value) return;

  const previous = menu.is_visible;
  const nextVisible = !previous;
  savingKey.value = menu.menu_key;
  applyVisibility(menu.menu_key, nextVisible);

  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar', {
      menu_key: menu.menu_key,
      is_visible: nextVisible,
    });
    applyVisibility(menu.menu_key, Boolean(data.menu.is_visible));
    showClientToast('success', `Đã ${data.menu.is_visible ? 'bật' : 'tắt'} "${menu.label}".`);
  } catch (error) {
    applyVisibility(menu.menu_key, previous);
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thay đổi.');
  } finally {
    savingKey.value = null;
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
  <div class="wc-sidebar">
    <div class="wc-sidebar__list-wrap hide-scrollbar">
      <p v-if="isLoading" class="wc-sidebar__empty">Đang tải…</p>
      <p v-else-if="!hasDepartment" class="wc-sidebar__empty">
        Tài khoản chưa gắn với phòng ban nào.
      </p>
      <p v-else-if="menus.length === 0" class="wc-sidebar__empty">
        Chưa có mục menu nào có thể cấu hình.
      </p>

      <ul v-else class="wc-sidebar__list" role="list">
        <li
          v-for="menu in menus"
          :key="menu.menu_key"
          class="wc-sidebar__item"
          :class="{ 'wc-sidebar__item--on': menu.is_visible }"
        >
          <span class="wc-sidebar__item-icon" aria-hidden="true">
            <AppIcon :name="menuIcon(menu.menu_key)" :size="18" :stroke-width="1.75" />
          </span>

          <div class="wc-sidebar__item-copy">
            <p class="wc-sidebar__item-label">{{ menu.label }}</p>
            <StatusBadge
              :on="menu.is_visible"
              :label="menu.is_visible ? 'Đang hiện' : 'Đang ẩn'"
            />
          </div>

          <button
            type="button"
            class="wc-sidebar__switch"
            :class="{ 'wc-sidebar__switch--on': menu.is_visible }"
            role="switch"
            :aria-checked="menu.is_visible"
            :disabled="savingKey === menu.menu_key"
            :aria-label="menu.is_visible ? `Ẩn mục ${menu.label}` : `Hiện mục ${menu.label}`"
            @click="toggle(menu)"
          >
            <span class="wc-sidebar__switch-thumb" />
          </button>
        </li>
      </ul>
    </div>

    <p v-if="!isLoading && menus.length > 0" class="wc-sidebar__meta">
      {{ visibleCount }}/{{ menus.length }} đang hiện
    </p>
  </div>
</template>

<style scoped>
.wc-sidebar {
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  overflow: hidden;
}

.wc-sidebar__list-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
}

.wc-sidebar__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.wc-sidebar__list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wc-sidebar__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-sidebar__item:last-child {
  box-shadow: none;
}

.wc-sidebar__item-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
}

.wc-sidebar__item--on .wc-sidebar__item-icon {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.wc-sidebar__item-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
}

.wc-sidebar__item-label {
  margin: 0;
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.wc-sidebar__switch {
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

.wc-sidebar__switch--on {
  background: var(--color-success);
}

.wc-sidebar__switch:hover:not(:disabled) {
  filter: brightness(0.96);
}

.wc-sidebar__switch:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-sidebar__switch-thumb {
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

.wc-sidebar__switch--on .wc-sidebar__switch-thumb {
  transform: translateX(1.25rem);
}

.wc-sidebar__meta {
  flex-shrink: 0;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
  .wc-sidebar__switch-thumb {
    transition: none;
  }
}
</style>
