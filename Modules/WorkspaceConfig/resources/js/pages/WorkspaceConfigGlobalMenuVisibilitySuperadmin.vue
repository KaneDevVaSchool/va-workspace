<script setup>
//
// superadmin/workspace-config/global-menu — ẩn/hiện menu sidebar Ở MỨC
// TOÀN HỆ THỐNG. Khác trang "Cấu hình Workspace theo phòng ban" (chỉ xem
// per-department) — đây là cơ chế MỚI, superadmin bật/tắt trực tiếp, áp
// dụng cho mọi tài khoản không phải super_admin, thắng tuyệt đối
// per-department override. Bật/tắt = ghi ngay, không có nút Lưu riêng.
//
import { computed, onMounted, ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import { showClientToast } from '@/lib/clientToast';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';

const SECTION_ORDER = ['general', 'admin', 'manager', 'superadmin-workspace-config'];

const auth = useAuthStore();

const menus = ref([]);
const isLoading = ref(false);
const savingKey = ref(null);

const groupedSections = computed(() => {
  const groups = new Map();
  for (const menu of menus.value) {
    const sectionId = menu.section || 'other';
    if (!groups.has(sectionId)) {
      groups.set(sectionId, { id: sectionId, label: menu.section_label || sectionId, items: [] });
    }
    groups.get(sectionId).items.push(menu);
  }

  const ordered = SECTION_ORDER.filter((id) => groups.has(id)).map((id) => groups.get(id));
  const rest = [...groups.values()].filter((group) => !SECTION_ORDER.includes(group.id));
  return [...ordered, ...rest];
});

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
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không tải được danh sách menu.');
  } finally {
    isLoading.value = false;
  }
}

async function toggle(menu) {
  if (savingKey.value || menu.is_protected) return;

  const nextHidden = !menu.is_hidden;
  const previous = menu.is_hidden;
  savingKey.value = menu.menu_key;
  applyMenu(menu.menu_key, { is_hidden: nextHidden });

  try {
    const { data } = await window.axios.put('/api/workspace-config/global-menu', {
      menu_key: menu.menu_key,
      is_hidden: nextHidden,
    });
    const updated = (data.menus ?? []).find((item) => item.menu_key === menu.menu_key);
    if (updated) applyMenu(menu.menu_key, updated);
    auth.setGlobalMenuKeyHidden(menu.menu_key, nextHidden);
    showClientToast('success', `Đã ${nextHidden ? 'ẩn' : 'hiện'} "${menu.label}" cho toàn hệ thống.`);
  } catch (error) {
    applyMenu(menu.menu_key, { is_hidden: previous });
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không lưu được thay đổi.');
  } finally {
    savingKey.value = null;
  }
}

onMounted(() => {
  loadMenus();
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
    />

    <div class="gmv-page__body">
      <p class="gmv-page__explain">
        Menu bị ẩn ở đây sẽ không hiển thị và không dùng được với bất kỳ ai,
        kể cả khi trưởng phòng đã bật riêng cho phòng ban mình. Chỉ tài
        khoản Super Admin luôn thấy đầy đủ mọi menu.
      </p>

      <p v-if="isLoading" class="gmv-page__empty">Đang tải…</p>

      <div v-else class="gmv-page__sections">
        <section v-for="section in groupedSections" :key="section.id" class="gmv-page__section">
          <h2 class="gmv-page__section-label">{{ section.label }}</h2>

          <div class="gmv-page__list">
            <div
              v-for="menu in section.items"
              :key="menu.menu_key"
              class="gmv-page__row"
              :class="{ 'gmv-page__row--saving': savingKey === menu.menu_key }"
            >
              <div class="gmv-page__row-info">
                <span class="gmv-page__row-name">
                  <span
                    class="gmv-page__dot"
                    :class="menu.is_hidden ? 'gmv-page__dot--hidden' : 'gmv-page__dot--visible'"
                  />
                  {{ menu.label }}
                </span>
                <span v-if="menu.is_protected" class="gmv-page__row-hint">Không thể tự ẩn mục này.</span>
                <span v-else class="gmv-page__row-hint">{{ menu.is_hidden ? 'Đang ẩn' : 'Đang hiện' }}</span>
              </div>

              <button
                type="button"
                class="gmv-page__switch"
                :class="{ 'gmv-page__switch--on': !menu.is_hidden }"
                role="switch"
                :aria-checked="!menu.is_hidden"
                :disabled="menu.is_protected || savingKey === menu.menu_key"
                :aria-label="menu.is_hidden ? `Hiện mục ${menu.label}` : `Ẩn mục ${menu.label}`"
                @click="toggle(menu)"
              >
                <span class="gmv-page__switch-thumb" />
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>

<style scoped>
.gmv-page {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  height: 100%;
  overflow: hidden;
}

.gmv-page__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
  padding: var(--space-5);
}

.gmv-page__explain {
  margin: 0;
  max-width: 48rem;
  color: var(--color-text-muted);
  font-size: 0.875rem;
  line-height: 1.6;
}

.gmv-page__empty {
  margin: 0;
  padding: var(--space-8) var(--space-5);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.875rem;
}

.gmv-page__sections {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: var(--space-5);
}

.gmv-page__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.gmv-page__section-label {
  margin: 0 0 var(--space-1);
  padding-bottom: var(--space-2);
  color: var(--color-text);
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  box-shadow: 0 1px 0 var(--color-border);
}

.gmv-page__list {
  display: flex;
  flex-direction: column;
}

.gmv-page__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) 0;
  box-shadow: 0 1px 0 var(--color-border);
}

.gmv-page__row:last-child {
  box-shadow: none;
}

.gmv-page__row--saving {
  opacity: 0.7;
}

.gmv-page__row-info {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.gmv-page__row-name {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--color-text);
  font-size: 0.9375rem;
  font-weight: 600;
}

.gmv-page__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-full);
  flex-shrink: 0;
}

.gmv-page__dot--visible {
  background: var(--color-success);
}

.gmv-page__dot--hidden {
  background: color-mix(in srgb, var(--color-text-muted) 55%, transparent);
}

.gmv-page__row-hint {
  color: var(--color-text-muted);
  font-size: 0.75rem;
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

@media (max-width: 768px) {
  .gmv-page__sections {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .gmv-page__body {
    padding: var(--space-4) var(--space-3);
  }

  .gmv-page__row {
    align-items: flex-start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .gmv-page__switch-thumb {
    transition: none;
  }
}
</style>
