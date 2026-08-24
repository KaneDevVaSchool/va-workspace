<script setup>
//
// manager/workspace-config/sidebar — bật/tắt menu sidebar áp dụng riêng
// cho phòng ban mình. Đổi 1 mục = ghi ngay (không cần nút Lưu tổng),
// backend trả về đúng bản ghi vừa đổi để patch trực tiếp vào state.
//
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const hub = inject('workspaceConfigHub', null);
const auth = useAuthStore();
const hasDepartment = computed(() => Boolean(auth.user?.department?.id));

const menus = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);

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
  const nextVisible = !menu.is_visible;
  savingKey.value = menu.menu_key;
  try {
    const { data } = await window.axios.put('/api/workspace-config/sidebar', {
      menu_key: menu.menu_key,
      is_visible: nextVisible,
    });

    const idx = menus.value.findIndex((m) => m.menu_key === menu.menu_key);
    if (idx !== -1) {
      menus.value[idx].is_visible = data.menu.is_visible;
    }

    showClientToast('success', `Đã ${data.menu.is_visible ? 'bật' : 'tắt'} "${menu.label}".`);
  } catch (error) {
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
    <p class="wc-sidebar__hint">
      Tắt 1 mục sẽ ẩn khỏi menu bên trái của TOÀN BỘ thành viên trong phòng
      ban mình. Người dùng cần tải lại trang để thấy thay đổi.
    </p>

    <p v-if="isLoading" class="wc-sidebar__empty">Đang tải…</p>
    <p v-else-if="!hasDepartment" class="wc-sidebar__empty">
      Tài khoản chưa gắn với phòng ban nào.
    </p>
    <p v-else-if="menus.length === 0" class="wc-sidebar__empty">
      Chưa có mục menu nào có thể cấu hình.
    </p>

    <div v-for="menu in menus" v-else :key="menu.menu_key" class="wc-sidebar__row">
      <span class="wc-sidebar__row-label">{{ menu.label }}</span>
      <button
        type="button"
        class="wc-sidebar__toggle"
        :class="{ 'wc-sidebar__toggle--on': menu.is_visible }"
        :disabled="savingKey === menu.menu_key"
        :aria-pressed="menu.is_visible"
        :aria-label="menu.is_visible ? `Ẩn mục ${menu.label}` : `Hiện mục ${menu.label}`"
        @click="toggle(menu)"
      >
        <span class="wc-sidebar__dot" :class="menu.is_visible ? 'wc-sidebar__dot--on' : 'wc-sidebar__dot--off'" />
        {{ menu.is_visible ? 'Đang hiện' : 'Đang ẩn' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.wc-sidebar {
  height: 100%;
  overflow-y: auto;
}

.wc-sidebar__hint {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.wc-sidebar__empty {
  padding: var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
}

.wc-sidebar__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.wc-sidebar__row-label {
  color: var(--color-text);
  font-size: 0.9375rem;
}

.wc-sidebar__toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  padding: 0.25rem 0.75rem;
  font-family: var(--font-family-base);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.wc-sidebar__toggle--on {
  color: var(--color-text);
}

.wc-sidebar__toggle:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wc-sidebar__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
}

.wc-sidebar__dot--on {
  background: var(--color-primary);
}

.wc-sidebar__dot--off {
  background: var(--color-text-muted);
}
</style>
