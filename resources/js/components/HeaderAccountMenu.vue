<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@modules/Identity/resources/js/stores/auth.js';
import { roleLabel as formatRoleLabel } from '@modules/Identity/resources/js/constants/roles.js';
import ViewAsSwitcher from '@modules/Identity/resources/js/components/ViewAsSwitcher.vue';
import { showClientToast } from '../lib/clientToast';
import { useHeaderPopover } from '../composables/useHeaderPopover';
import AppIcon from './AppIcon.vue';
import ConfirmDialog from './ConfirmDialog.vue';

const router = useRouter();
const auth = useAuthStore();
const rootRef = ref(null);
const { isOpen, toggle, close } = useHeaderPopover('account');
const logoutConfirmOpen = ref(false);
const loggingOut = ref(false);

const userLabel = computed(() => auth.user?.name ?? auth.user?.email ?? 'Người dùng');
const userInitial = computed(() => userLabel.value.trim().charAt(0).toUpperCase() || '?');
const roleLabel = computed(() => formatRoleLabel(auth.activeRole));

function isNativeSelectEvent(event) {
  const target = event.target;
  if (target instanceof HTMLSelectElement || target instanceof HTMLOptionElement) {
    return true;
  }
  if (typeof target?.closest === 'function' && target.closest('select')) {
    return true;
  }
  const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
  return path.some((node) => node instanceof HTMLSelectElement || node instanceof HTMLOptionElement);
}

function handleDocumentClick(event) {
  if (!isOpen.value || !rootRef.value || logoutConfirmOpen.value) {
    return;
  }
  if (rootRef.value.contains(event.target) || isNativeSelectEvent(event)) {
    return;
  }
  window.setTimeout(() => {
    const active = document.activeElement;
    if (rootRef.value?.contains(active) && active?.tagName === 'SELECT') {
      return;
    }
    close();
  }, 0);
}

function handleDocumentKeydown(event) {
  if (event.key === 'Escape' && isOpen.value) {
    close();
  }
}

async function confirmLogout() {
  loggingOut.value = true;
  try {
    await auth.logout();
    logoutConfirmOpen.value = false;
    close();
    await router.push({ name: 'login' });
    showClientToast('success', 'Đã đăng xuất.');
  } catch {
    showClientToast('error', 'Đăng xuất thất bại. Vui lòng thử lại.');
  } finally {
    loggingOut.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleDocumentKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleDocumentKeydown);
});
</script>

<template>
  <div ref="rootRef" class="header-account">
    <button
      type="button"
      class="header-account__btn"
      :class="{ 'header-account__btn--open': isOpen }"
      aria-haspopup="menu"
      :aria-expanded="isOpen"
      :aria-label="`Tài khoản ${userLabel}`"
      @click="toggle"
    >
      <span class="header-account__avatar">
        <img
          v-if="auth.user?.avatar_url"
          :src="auth.user.avatar_url"
          :alt="userLabel"
          class="header-account__avatar-img"
          referrerpolicy="no-referrer"
        />
        <template v-else>{{ userInitial }}</template>
      </span>
    </button>

    <div
      v-show="isOpen"
      class="header-account__menu"
      role="menu"
      :aria-hidden="!isOpen"
      @click.stop
      @mousedown.stop
    >
      <div class="header-account__profile">
        <span class="header-account__name">{{ userLabel }}</span>
        <span v-if="auth.user?.email" class="header-account__email">{{ auth.user.email }}</span>
        <span v-if="roleLabel" class="header-account__role">Vai trò: {{ roleLabel }}</span>
      </div>
      <ViewAsSwitcher />
      <button
        type="button"
        class="header-account__logout"
        role="menuitem"
        @click="logoutConfirmOpen = true"
      >
        <AppIcon name="logout" :size="18" />
        <span>Đăng xuất</span>
      </button>
    </div>

    <ConfirmDialog
      v-model:open="logoutConfirmOpen"
      title="Xác nhận đăng xuất"
      description="Bạn có chắc muốn đăng xuất khỏi VA Workspace? Phiên làm việc hiện tại sẽ kết thúc."
      confirm-label="Đăng xuất"
      danger
      :loading="loggingOut"
      @confirm="confirmLogout"
    />
  </div>
</template>

<style scoped>
.header-account {
  position: relative;
}

.header-account__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: none;
  border-radius: var(--radius-full);
  background: transparent;
  cursor: pointer;
}

.header-account__btn:hover,
.header-account__btn--open {
  outline: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
  outline-offset: 2px;
}

.header-account__btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

.header-account__avatar {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--color-primary);
  color: var(--color-on-primary);
  font-size: 0.8125rem;
  font-weight: 700;
}

.header-account__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.header-account__menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 50;
  width: 16.5rem;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 12px;
  background: var(--color-surface);
  box-shadow:
    inset 0 0 0 1px var(--color-border),
    var(--shadow-lg);
}

.header-account__profile {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-border);
}

.header-account__name {
  color: var(--color-text);
  font-size: 0.875rem;
  font-weight: 700;
  overflow-wrap: break-word;
}

.header-account__email,
.header-account__role {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  overflow-wrap: break-word;
}

.header-account__logout {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3);
  border: none;
  background: transparent;
  color: var(--color-text);
  font-family: var(--font-family-base);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.header-account__logout:hover {
  background: var(--color-surface-muted);
  color: var(--color-primary);
}

@media (max-width: 480px) {
  .header-account__menu {
    right: 0;
    width: min(16.5rem, calc(100vw - 1.5rem));
  }
}
</style>
