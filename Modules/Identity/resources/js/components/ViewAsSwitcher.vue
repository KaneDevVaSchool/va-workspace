<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth.js';
import { roleLabel, SYSTEM_ROLES } from '../constants/roles.js';
import { showClientToast } from '@/lib/clientToast';

const auth = useAuthStore();
const isSwitching = ref(false);
const selectedRole = ref(auth.activeRole ?? '');

watch(
  () => auth.activeRole,
  (role) => {
    selectedRole.value = role ?? '';
  },
);

async function onSelect() {
  const roleCode = selectedRole.value;
  if (!roleCode || roleCode === auth.activeRole || isSwitching.value) {
    selectedRole.value = auth.activeRole ?? '';
    return;
  }

  isSwitching.value = true;
  try {
    if (roleCode === 'super_admin' && auth.isImpersonating) {
      await auth.exitViewAs();
    } else {
      await auth.viewAs(roleCode);
    }
    showClientToast('success', `Đang xem thử vai trò: ${roleLabel(auth.activeRole)}`);
  } catch (error) {
    selectedRole.value = auth.activeRole ?? '';
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không đổi được vai trò. Vui lòng thử lại.');
  } finally {
    isSwitching.value = false;
  }
}

async function onExit() {
  if (isSwitching.value) {
    return;
  }

  isSwitching.value = true;
  try {
    await auth.exitViewAs();
    selectedRole.value = auth.activeRole ?? '';
    showClientToast('success', 'Đã thoát xem thử vai trò.');
  } catch (error) {
    const message = error?.response?.data?.message;
    showClientToast('error', message || 'Không thoát xem thử được. Vui lòng thử lại.');
  } finally {
    isSwitching.value = false;
  }
}
</script>

<template>
  <div v-if="auth.canViewAs" class="view-as">
    <label class="view-as__label" for="view-as-role">Xem thử vai trò</label>

    <select
      id="view-as-role"
      v-model="selectedRole"
      class="view-as__select"
      :disabled="isSwitching"
      @mousedown.stop
      @click.stop
      @change="onSelect"
    >
      <option v-for="role in SYSTEM_ROLES" :key="role.code" :value="role.code">
        {{ role.label }}
      </option>
    </select>

    <button
      v-if="auth.isImpersonating"
      type="button"
      class="view-as__exit"
      :disabled="isSwitching"
      @click="onExit"
    >
      Thoát xem thử
    </button>
  </div>
</template>

<style scoped>
.view-as {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  box-shadow: 0 1px 0 var(--color-sidebar-divider);
}

.view-as__label {
  color: var(--color-sidebar-text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.view-as__select {
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: none;
  border-radius: var(--radius-sm);
  background: var(--color-sidebar);
  color: var(--color-on-primary);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: inset 0 0 0 1px var(--color-sidebar-divider);
  color-scheme: dark;
  cursor: pointer;
}

.view-as__select option {
  background: var(--color-sidebar);
  color: var(--color-on-primary);
}

.view-as__select:disabled,
.view-as__exit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.view-as__exit {
  width: 100%;
  padding: 0.375rem 0.5rem;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--color-sidebar-text);
  font-family: var(--font-family-base);
  font-size: 0.8125rem;
  font-weight: 500;
  text-align: left;
  cursor: pointer;
}

.view-as__exit:hover:not(:disabled) {
  background: var(--color-sidebar-hover);
  color: var(--color-on-primary);
}
</style>
